<?php

namespace Modules\Tagtoa\App\Services\Event;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Tagtoa\App\Models\Event\Checkin;
use Modules\Tagtoa\App\Models\Event\Delivery;
use Modules\Tagtoa\App\Models\Event\Event;
use Modules\Tagtoa\App\Models\Event\NfcTag;
use Modules\Tagtoa\App\Models\Event\Ticket;
use Modules\Tagtoa\App\Services\Notifications\NotificationService;
use Modules\Tagtoa\App\Support\Event\EventDays;
use Modules\Tagtoa\App\Support\Nfc\NfcTapVerifier;

/**
 * TAGTOA Event — moteur de check-in (scanner PWA, offline-first).
 *
 * processScan() -> ['valid','color','sound','message','ticket'].
 */
class CheckinService
{
    public function __construct(protected NotificationService $notifications, protected EventNotifier $eventNotifier)
    {
    }

    /** Résout un UID de carte NFC -> code du billet (pour check-in par tap). */
    public function resolveNfcCode(Event $event, string $uid, ?string $ctr = null, ?string $cmac = null): ?string
    {
        $tag = NfcTag::where('event_id', $event->id)
            ->where('uid_hash', NfcTag::hashUid($uid))
            ->where('status', 'active')->first();

        if (! $tag || ! $tag->ticket_id || ! NfcTapVerifier::passes($tag, $uid, $ctr, $cmac)) {
            return null;
        }

        return optional(Ticket::find($tag->ticket_id))->code;
    }

    /** Fenêtre acceptée pour l'heure d'un scan rejoué hors ligne (même règle que la caisse POS). */
    public const OFFLINE_MAX_AGE_HOURS = 72;
    public const FUTURE_SKEW_MINUTES   = 5;
    /** Au-delà, un scan rejoué est « tardif » : consigné, mais plus notifié. */
    public const LATE_REPLAY_MINUTES   = 15;

    /**
     * L'heure d'un scan : celle de l'appareil (hors ligne) si elle est plausible.
     *
     * Un scan fait samedi 22 h hors ligne et reçu dimanche doit rester un scan de
     * samedi — pour le « déjà entré aujourd'hui » d'un pass multi-jours comme
     * pour le journal. Mais l'horloge d'un téléphone se règle à la main : hors
     * fenêtre (plus vieille que 72 h, plus de 5 min dans le futur, ou illisible),
     * le scan prend l'heure du SERVEUR et l'heure annoncée n'est pas perdue — elle
     * est retournée dans `$flag` pour être journalisée avec la raison.
     *
     * @return array{0: Carbon, 1: array{reason:string, claimed:string}|null}
     */
    public function resolveScanTime(mixed $claimed, ?Event $event = null): array
    {
        $now = now();
        if ($claimed === null || $claimed === '') {
            return [$now, null];
        }
        $text = is_scalar($claimed) ? mb_substr((string) $claimed, 0, 40) : '(illisible)';

        try {
            // Ramené au fuseau de l'application : Carbon garde le fuseau du texte
            // (« …-04:00 »), et Eloquent écrit l'heure MURALE sans le convertir —
            // l'heure serait enregistrée avec des heures de décalage, et le JOUR
            // du scan pourrait changer.
            $at = Carbon::parse((string) $claimed)->setTimezone($now->getTimezone());
        } catch (\Throwable $e) {
            return [$now, ['reason' => 'invalid', 'claimed' => $text]];
        }

        if ($at->lt($now->copy()->subHours(self::OFFLINE_MAX_AGE_HOURS))) {
            return [$now, ['reason' => 'too_old', 'claimed' => $text]];
        }
        if ($at->gt($now->copy()->addMinutes(self::FUTURE_SKEW_MINUTES))) {
            return [$now, ['reason' => 'future', 'claimed' => $text]];
        }

        // Un scan ne peut avoir eu lieu qu'un jour où l'événement se tient.
        // Sans cela, l'appareil choisirait le JOUR du scan — donc le jour
        // contre lequel on teste « déjà entré » — et un billet déjà entré
        // aujourd'hui pourrait repasser avec une heure d'hier. Un événement
        // sans dates n'impose rien : on ne peut pas juger.
        if ($event !== null) {
            $days = EventDays::list(optional($event->starts_at)->toDateString(), optional($event->ends_at)->toDateString());
            if ($days !== [] && ! in_array($at->toDateString(), $days, true)) {
                return [$now, ['reason' => 'outside_event', 'claimed' => $text]];
            }
        }

        return [$at, null];
    }

    public function processScan(Event $event, string $code, string $direction = 'in', string $method = 'qr', ?string $gate = null, ?string $clientUuid = null, ?int $staffId = null, ?Carbon $scannedAt = null): array
    {
        // L'instant du scan : celui de l'appareil si on nous le donne (reprise hors
        // ligne), sinon maintenant. Il décide du JOUR pour l'anti double-entrée.
        $at = $scannedAt ?? now();

        $ticket = Ticket::where('event_id', $event->id)->where('code', $code)->with('ticketType')->first();

        if (! $ticket) {
            return $this->r(false, 'red', 'error', __('Billet introuvable.'), null, 'not_found');
        }
        if (! $ticket->isValid()) {
            return $this->r(false, 'red', 'error', __('Billet annulé.'), $ticket, 'void');
        }
        // Contrôle d'accès par porte : un type de billet peut être restreint à
        // certaines portes (ex. un Standard refusé à la porte « VIP »). Une
        // porte inconnue ($gate=null, scanner sans sélecteur de porte) ne
        // restreint jamais rien — c'est le comportement d'avant cette
        // fonctionnalité, pour tout événement qui ne configure aucune porte.
        if ($direction === 'in' && ! ($ticket->ticketType?->allowsGate($gate) ?? true)) {
            return $this->r(false, 'red', 'error', __('Ce billet n\'est pas valable à cette porte.'), $ticket, 'gate');
        }
        if ($direction === 'out' && ! $ticket->checked_in) {
            return $this->r(false, 'orange', 'warning', __('Pas encore entré.'), $ticket, 'not_yet_in');
        }

        // Tout ce qui DÉCIDE d'admettre se fait ICI, sous verrou de ligne sur le
        // billet. Avant, « déjà entré aujourd'hui ? » était lu AVANT la
        // transaction : deux portes scannant le même billet à la même seconde
        // lisaient toutes deux « non », puis écrivaient chacune une entrée —
        // deux personnes pour un billet. Le verrou fait attendre la seconde
        // requête ; relue après, elle voit l'entrée de la première.
        $entered = false;
        $result = DB::transaction(function () use ($event, $ticket, $direction, $method, $gate, $clientUuid, $staffId, $at, $scannedAt, &$entered) {
            /** @var Ticket $locked */
            $this->beforeTicketLock($ticket);
            $locked = Ticket::whereKey($ticket->id)->lockForUpdate()->with('ticketType')->first();

            // Billet supprimé entre la recherche et le verrou : introuvable, pas une erreur 500.
            if (! $locked) {
                return $this->r(false, 'red', 'error', __('Billet introuvable.'), null, 'not_found');
            }

            // Un billet annulé pendant l'attente du verrou ne passe pas.
            if (! $locked->isValid()) {
                return $this->r(false, 'red', 'error', __('Billet annulé.'), $locked, 'void');
            }

            // La reprise d'un scan déjà enregistré passe AVANT le contrôle de
            // doublon : sinon le rejeu d'un scan réussi répondrait « déjà entré »
            // à l'appareil, qui le prendrait pour un refus.
            if ($clientUuid && Checkin::where('ticket_id', $locked->id)->where('client_uuid', $clientUuid)->exists()) {
                return $this->r(true, 'green', 'success', __('Déjà synchronisé.'), $locked);
            }

            if ($direction === 'out' && ! $locked->checked_in) {
                return $this->r(false, 'orange', 'warning', __('Pas encore entré.'), $locked, 'not_yet_in');
            }

            // Commande NON payée : le billet existe (créé à la commande, QR affiché)
            // mais n'a pas été réglé. Relu ici, sous verrou, car le paiement peut
            // arriver pendant l'attente. Les billets sans commande (guichet, import,
            // carte encodée) ne sont pas concernés.
            if ($direction === 'in' && $locked->awaitingPayment()) {
                return $this->r(false, 'red', 'error', __('Commande non payée — billet non valable.'), $locked, 'unpaid');
            }

            // Anti double-entrée PAR JOUR (multi-jour) : on bloque une 2ᵉ entrée le
            // même jour, mais on autorise l'entrée un autre jour (ex. Pass 2 jours).
            if ($direction === 'in') {
                $today = $at->toDateString();
                $enteredToday = Checkin::where('ticket_id', $locked->id)
                    ->where('direction', 'in')
                    ->whereDate('scanned_at', $today)
                    ->exists();
                if ($enteredToday) {
                    return $this->r(false, 'orange', 'warning', __('Déjà entré aujourd\'hui.'), $locked, 'duplicate_today');
                }

                // Reprise hors ligne ANTIDATÉE (jour du scan antérieur à aujourd'hui) :
                // si le billet est déjà entré aujourd'hui pour de bon, une entrée
                // « d'hier » ne peut être qu'une seconde entrée déguisée — horloge
                // d'appareil reculée, ou requête fabriquée. Elle est refusée et
                // consignée ; l'entrée d'hier, si elle est réelle, reste visible dans
                // les conflits à vérifier plutôt que d'ouvrir un contournement.
                if ($scannedAt !== null && $today < now()->toDateString()) {
                    $enteredServerToday = Checkin::where('ticket_id', $locked->id)
                        ->where('direction', 'in')
                        ->whereDate('scanned_at', now()->toDateString())
                        ->exists();
                    if ($enteredServerToday) {
                        return $this->r(false, 'orange', 'warning', __('Déjà entré aujourd\'hui.'), $locked, 'backdated_reentry');
                    }
                }
            }

            $locked->checked_in = $direction === 'in';
            $locked->checked_in_at = $direction === 'in' ? $at : $locked->checked_in_at;
            $locked->save();

            Checkin::create([
                'event_id' => $event->id, 'ticket_id' => $locked->id, 'direction' => $direction,
                'method' => $method, 'gate' => $gate, 'staff_id' => $staffId,
                'client_uuid' => $clientUuid, 'scanned_at' => $at,
            ]);

            $entered = ($direction === 'in');

            return $this->r(true, 'green', 'success', $direction === 'in' ? $this->welcomeMessage($event, $locked, $at) : __('Sortie enregistrée.'), $locked->fresh('ticketType'));
        });

        // Notifications hors transaction (tolérant) : organisateur + participant à l'entrée.
        // Pas de « entrée confirmée » pour une REPRISE tardive : le message dirait
        // l'heure du serveur, pas celle de l'entrée, et un scan antidaté ferait
        // sonner le téléphone du porteur et la boîte de l'organisateur à chaque fois.
        if ($entered && $at->gt(now()->subMinutes(self::LATE_REPLAY_MINUTES))) {
            $this->notifyEntry($event, $ticket);
        }

        return $result;
    }

    /**
     * Point d'accroche : appelé juste AVANT la lecture verrouillante du billet.
     * Vide en production ; les tests y simulent ce qu'une autre porte valide
     * pendant l'attente du verrou (la lecture verrouillante, elle, renvoie l'état
     * d'APRÈS cette attente).
     */
    protected function beforeTicketLock(Ticket $ticket): void
    {
    }

    /**
     * Message de bienvenue à l'entrée : pour un événement multi-jours (ex.
     * festival 2 jours), précise explicitement « jour X/N » — le staff au
     * portail voit clairement que le billet reste valable les jours suivants.
     * Rétro-compatible : événement d'un seul jour ⇒ message simple inchangé.
     */
    private function welcomeMessage(Event $event, Ticket $ticket, ?Carbon $at = null): string
    {
        $days = EventDays::list(optional($event->starts_at)->toDateString(), optional($event->ends_at)->toDateString());

        $message = count($days) <= 1
            ? __('Bienvenue!')
            : (function () use ($days, $at) {
                $today = EventDays::resolveDay($days, ($at ?? now())->toDateString());
                $index = array_search($today, $days, true);
                $dayNumber = $index === false ? 1 : $index + 1;

                return __('Bienvenue! Billet valable :n jours — jour :d/:n', ['n' => count($days), 'd' => $dayNumber]);
            })();

        // Le staff au portail doit voir IMMÉDIATEMENT qu'il fait entrer un
        // invité VIP — c'est le moment où une file d'attente prioritaire ou un
        // accueil particulier se décide, pas après coup en consultant le badge.
        return $ticket->isVip() ? __('⭐ VIP').' — '.$message : $message;
    }

    /** Alerte organisateur (email) + confirmation participant (WhatsApp) à l'entrée. */
    protected function notifyEntry(Event $event, Ticket $ticket): void
    {
        try {
            $name = $ticket->holder_name ?: __('Participant');

            if ($event->notify_email) {
                $this->notifications->push([
                    'channels' => ['email'],
                    'email'    => $event->notify_email,
                    'subject'  => __('Entrée check-in').' — '.$event->title,
                    'body'     => __('Participant entré').' : '.$name.' — '.now()->format('H:i'),
                ]);
            }

            if ($ticket->holder_phone) {
                $this->eventNotifier->notifyCustomer(
                    $event,
                    Delivery::CONTEXT_CHECKIN_CONFIRMATION,
                    $event->title,
                    __('Bienvenue!').' '.__('Votre entrée est confirmée.').' — '.$name,
                    null,
                    $ticket->holder_phone,
                    $ticket->id
                );
            }
        } catch (\Throwable $e) {
            if (function_exists('report')) {
                report($e);
            }
        }
    }

    /** Scans traités par appel : au-delà, ils restent sur l'appareil (`deferred`). */
    public const SYNC_BATCH_MAX = 100;

    /**
     * Reprise des scans faits HORS LIGNE par l'écran de scan du tableau de bord.
     *
     * Contrat avec l'écran : une entrée par scan reçu, avec `ack`. L'écran ne
     * retire de sa file QUE les scans `ack:true` ; tout le reste (échec inattendu,
     * lot trop long, scan sans clé) reste sur l'appareil et sera rejoué. Avant,
     * l'écran vidait TOUTE sa file dès qu'une réponse JSON arrivait : un scan en
     * échec disparaissait sans trace, et une entrée jamais enregistrée n'était
     * jamais rejouée.
     *
     * `ack:true` = le serveur a statué (admis OU refusé pour une raison ferme —
     * doublon, billet inconnu, autre évènement) : rejouer ne changerait rien.
     * L'idempotence par `client_uuid` (processScan) rend un rejeu inoffensif.
     * Aucun texte d'exception ne sort : numéro de suivi `ref`, journalisé.
     *
     * @return array<int, array{client_uuid:?string, ack:bool, status:string, result?:array, ref?:string}>
     */
    public function sync(Event $event, array $scans): array
    {
        $out = [];
        $n = 0;
        foreach ($scans as $s) {
            if (! is_array($s)) {
                $out[] = ['client_uuid' => null, 'ack' => false, 'status' => 'invalid'];

                continue;
            }
            $uuid = isset($s['client_uuid']) && is_string($s['client_uuid']) && $s['client_uuid'] !== '' ? $s['client_uuid'] : null;

            if (++$n > self::SYNC_BATCH_MAX) {
                $out[] = ['client_uuid' => $uuid, 'ack' => false, 'status' => 'deferred'];

                continue;
            }
            // Sans clé, l'écran ne saurait jamais que le scan est passé.
            if ($uuid === null) {
                $out[] = ['client_uuid' => null, 'ack' => false, 'status' => 'invalid'];

                continue;
            }

            try {
                $res = $this->processScan($event, (string) ($s['code'] ?? ''), $s['direction'] ?? 'in', $s['method'] ?? 'qr', $s['gate'] ?? null, $uuid);
                $out[] = ['client_uuid' => $uuid, 'ack' => true, 'status' => ($res['valid'] ?? false) ? 'admitted' : 'refused', 'result' => $res];
            } catch (\Throwable $e) {
                $ref = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(8));
                report($e);
                \Illuminate\Support\Facades\Log::error('event.scan.sync.error', ['ref' => $ref, 'event' => $event->id, 'client_uuid' => $uuid, 'exception' => $e::class]);
                $out[] = ['client_uuid' => $uuid, 'ack' => false, 'status' => 'error', 'ref' => $ref];
            }
        }

        return $out;
    }

    private function r(bool $valid, string $color, string $sound, string $message, ?Ticket $ticket = null, ?string $reason = null): array
    {
        return [
            'valid' => $valid, 'color' => $color, 'sound' => $sound, 'message' => $message,
            // Pourquoi un scan est refusé, sous forme de code stable : l'appelant
            // (la reprise hors ligne) en a besoin pour classer le refus sans
            // deviner à partir d'un message traduit.
            'reason' => $valid ? null : $reason,
            'ticket_id' => $ticket?->id,
            'ticket' => $ticket ? [
                'code' => $ticket->code, 'holder' => $ticket->holder_name,
                'type' => optional($ticket->ticketType)->name, 'checked_in' => $ticket->checked_in,
            ] : null,
        ];
    }
}
