<?php

namespace Modules\Tagtoa\App\Services\Hotel;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Modules\Tagtoa\App\Models\Hotel\HotelRoom;
use Modules\Tagtoa\App\Models\Stand\Stand;
use Modules\Tagtoa\App\Models\Stand\StandEvent;
use Modules\Tagtoa\App\Services\Stand\StandActivator;
use Modules\Tagtoa\App\Services\Stand\StandResolver;
use Modules\Tagtoa\App\Support\Stand\StandState;

/**
 * TAGTOA HÔTEL (H4) — rattacher, révoquer et remplacer le support QR/NFC d'une chambre.
 *
 * Réutilise le Smart Stand : aucune table de supports parallèle. Le secret gratté est exigé en
 * entier (StandActivator) ; ce service ajoute le lien « une chambre ↔ un support » sous verrou.
 *
 * Reçoit TOUJOURS le commerce en paramètre et filtre explicitement (`allTenants()`) : il ne
 * dépend d'aucun état ambiant. Tout est atomique : si le lien échoue, l'activation du support
 * est annulée avec lui.
 *
 * Cycle de vie d'un support de chambre : (non activé) → actif sur UNE chambre → révoqué.
 * « Révoqué » est définitif : l'objet est mort, le secret ne rouvre rien. Changer de support =
 * en activer un nouveau (remplacement) ; l'ancien est révoqué dans la même transaction.
 */
class HotelRoomSupportService
{
    public const OK            = 'ok';
    public const NOT_FOUND     = 'not_found';    // chambre inconnue (ou d'un autre hôtel)
    public const ROOM_INACTIVE = 'room_inactive';
    public const HAS_SUPPORT   = 'has_support';  // déjà un support : passer par le remplacement
    public const NO_SUPPORT    = 'no_support';
    public const STAND_IN_USE  = 'stand_in_use'; // support déjà affecté (autre chambre / autre usage)
    public const SAME_STAND    = 'same_stand';   // remplacement par le support déjà en place

    public function __construct(
        protected StandActivator $activator,
        protected StandResolver $resolver,
    ) {
    }

    /**
     * Active un support et le rattache à la chambre.
     *
     * @return array{result:string, stand:?Stand}
     */
    public function attach(string $tenantId, int $roomId, ?string $lecture, array $context = []): array
    {
        return $this->run($tenantId, $roomId, function (HotelRoom $room) use ($tenantId, $lecture, $context) {
            if ($room->stand_id !== null) {
                return $this->non(self::HAS_SUPPORT);
            }
            if (! $room->is_active) {
                return $this->non(self::ROOM_INACTIVE);
            }

            return $this->link($room, $tenantId, $lecture, $context);
        });
    }

    /** Révoque le support de la chambre. Définitif : le secret de cet objet ne rouvre plus rien. */
    public function revoke(string $tenantId, int $roomId, array $context = []): array
    {
        return $this->run($tenantId, $roomId, function (HotelRoom $room) use ($tenantId, $context) {
            if ($room->stand_id === null) {
                return $this->non(self::NO_SUPPORT);
            }
            $stand = $this->kill($room, $tenantId, $context, StandEvent::REVOKED);
            $room->forceFill(['stand_id' => null])->save();

            return ['result' => self::OK, 'stand' => $stand];
        });
    }

    /**
     * Remplace le support (perdu, abîmé) par un nouveau. Si le nouveau est refusé, l'ancien reste
     * en place et continue de servir : rien n'est révoqué avant que le remplaçant soit activé.
     */
    public function replace(string $tenantId, int $roomId, ?string $lecture, array $context = []): array
    {
        return $this->run($tenantId, $roomId, function (HotelRoom $room) use ($tenantId, $lecture, $context) {
            if ($room->stand_id === null) {
                return $this->non(self::NO_SUPPORT);
            }
            if (! $room->is_active) {
                return $this->non(self::ROOM_INACTIVE);
            }

            $ancien = (int) $room->stand_id;
            // Libère le lien (dans la transaction) pour que le remplaçant puisse prendre la place.
            $room->forceFill(['stand_id' => null])->save();

            $r = $this->link($room, $tenantId, $lecture, $context);
            if ($r['result'] !== self::OK) {
                return $r;   // la transaction est annulée : l'ancien lien et l'ancien support restent
            }
            if ((int) $r['stand']->id === $ancien) {
                return $this->non(self::SAME_STAND);
            }

            $this->kill($room, $tenantId, $context, StandEvent::REPLACED, $ancien, (int) $r['stand']->id);

            return $r;
        });
    }

    /* ---------------- interne ---------------- */

    /**
     * Exécute dans une transaction, chambre verrouillée. Tout résultat autre que « ok » annule
     * la transaction (activation du support comprise).
     */
    private function run(string $tenantId, int $roomId, \Closure $work): array
    {
        $oublier = [];
        try {
            $resultat = DB::transaction(function () use ($tenantId, $roomId, $work, &$oublier) {
                $room = HotelRoom::allTenants()->where('tenant_id', $tenantId)->whereKey($roomId)->lockForUpdate()->first();
                if (! $room) {
                    throw new RollbackWith($this->non(self::NOT_FOUND));
                }
                $avant = $room->stand_id;
                $r = $work($room);
                if ($r['result'] !== self::OK) {
                    throw new RollbackWith($r);
                }
                foreach ([$avant, $room->stand_id] as $id) {
                    if ($id) {
                        $oublier[] = (int) $id;
                    }
                }

                return $r;
            });
        } catch (RollbackWith $e) {
            return $e->resultat;
        } catch (UniqueConstraintViolationException) {
            // Deux requêtes ont voulu le même support : l'index unique tranche.
            return $this->non(self::STAND_IN_USE);
        }

        // Après la validation de la transaction : jamais de cache vidé pour un état annulé.
        foreach (array_unique($oublier) as $id) {
            if ($stand = Stand::find($id)) {
                $this->resolver->forget($stand->public_id);
            }
        }
        if (! empty($resultat['stand'])) {
            $this->resolver->forget($resultat['stand']->public_id);
        }

        return $resultat;
    }

    /** Active le support (secret exigé) puis le lie à la chambre. */
    private function link(HotelRoom $room, string $tenantId, ?string $lecture, array $context): array
    {
        $a = $this->activator->activate($lecture, $tenantId, $context + [
            'target_module'  => 'hotel_room',
            'location_label' => mb_substr($room->label, 0, 60),
        ]);

        if ($a['result'] === StandActivator::ALREADY_MINE) {
            // Un support déjà à CE commerce n'est repris que s'il est déjà un support de chambre
            // laissé libre ; jamais on ne détourne le stand d'une table ou d'un autre usage.
            $s = $a['stand'];
            $libre = $s && $s->target_module === 'hotel_room'
                && ! HotelRoom::allTenants()->where('stand_id', $s->id)->exists();
            if (! $libre) {
                return $this->non(self::STAND_IN_USE);
            }
            $a = ['result' => StandActivator::OK, 'stand' => $s];
        }

        if ($a['result'] !== StandActivator::OK) {
            return ['result' => 'stand_'.$a['result'], 'stand' => null];   // ex. stand_bad_code
        }

        $stand = $a['stand'];
        $room->forceFill(['stand_id' => $stand->id])->save();

        return ['result' => self::OK, 'stand' => $stand];
    }

    /** Révoque un support (verrou de ligne) et journalise. Renvoie le stand. */
    private function kill(HotelRoom $room, string $tenantId, array $context, string $event, ?int $standId = null, ?int $by = null): ?Stand
    {
        $stand = Stand::whereKey($standId ?? $room->stand_id)->where('tenant_id', $tenantId)->lockForUpdate()->first();
        if (! $stand) {
            return null;
        }
        $avant = $stand->digital_state;
        $stand->forceFill(['digital_state' => StandState::REVOKED])->save();

        StandEvent::create([
            'stand_id'   => $stand->id,
            'event'      => $event,
            'from_state' => $avant,
            'to_state'   => StandState::REVOKED,
            'actor_type' => 'business',
            'actor_name' => $context['actor_name'] ?? null,
            'tenant_id'  => $tenantId,
            'ip'         => $context['ip'] ?? null,
            'meta'       => array_filter(['via' => 'hotel_room', 'room_id' => $room->id, 'replaced_by' => $by]),
        ]);

        return $stand;
    }

    private function non(string $result): array
    {
        return ['result' => $result, 'stand' => null];
    }
}

/** Annule la transaction en transportant le résultat à rendre. */
class RollbackWith extends \RuntimeException
{
    public function __construct(public array $resultat)
    {
        parent::__construct('rollback');
    }
}
