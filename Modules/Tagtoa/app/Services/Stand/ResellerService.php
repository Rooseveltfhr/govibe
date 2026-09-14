<?php

namespace Modules\Tagtoa\App\Services\Stand;

use Illuminate\Support\Facades\DB;
use Modules\Tagtoa\App\Models\Stand\Reseller;
use Modules\Tagtoa\App\Models\Stand\Stand;
use Modules\Tagtoa\App\Models\Stand\StandEvent;
use Modules\Tagtoa\App\Support\Stand\StandState;

/**
 * TAGTOA SMART STAND — affecter des cartons, déclarer des ventes.
 *
 * ── LA RÈGLE QUI GOUVERNE TOUT CE SERVICE ───────────────────────────────
 *
 * Le revendeur déplace l'OBJET. Il ne touche JAMAIS à l'identité numérique.
 *
 * Déclarer une vente met le stand en SOLD sur l'axe physique, et RIEN de plus.
 * Le stand reste NON RÉCLAMÉ : c'est le marchand qui grattera le panneau et le
 * réclamera lui-même, avec un code que le revendeur n'a jamais vu.
 *
 * Si le revendeur pouvait réclamer à la place du marchand, il possèderait le
 * compte : il déciderait de ce que le QR affiche, verrait les commandes, et
 * pourrait rendre le stand inutilisable en partant. Toute la valeur du produit
 * tiendrait entre ses mains plutôt qu'entre celles du commerçant qui l'a payé.
 *
 * ── Pourquoi l'affectation se fait par PLAGE ────────────────────────────
 *
 * Parce qu'un carton est une plage. On n'affecte pas trente stands choisis un
 * par un : on envoie le carton 41–80. Faire autrement obligerait le fondateur
 * à cocher trente cases pour décrire un geste qui en est un seul.
 */
class ResellerService
{
    /* Résultats. Une chaîne libre divergerait entre le service et l'écran. */
    public const OK           = 'ok';
    public const RIEN         = 'rien';          // la plage ne contient rien d'affectable
    public const PAS_A_LUI    = 'pas_a_lui';     // le stand n'est pas détenu par ce revendeur
    public const DEJA_VENDU   = 'deja_vendu';
    public const INTROUVABLE  = 'introuvable';

    /** Au-delà, ce n'est plus un carton : c'est une erreur de saisie. */
    public const MAX_PLAGE = 5000;

    /**
     * Affecte une plage de stands à un revendeur. Acte du FONDATEUR.
     *
     * @return array{result:string, count:int}
     */
    public function allocate(Reseller $reseller, int $batchId, int $du, int $au, array $contexte = []): array
    {
        [$du, $au] = [min($du, $au), max($du, $au)];

        if ($au - $du + 1 > self::MAX_PLAGE) {
            return ['result' => self::RIEN, 'count' => 0];
        }

        $n = DB::transaction(function () use ($reseller, $batchId, $du, $au, $contexte) {
            // On n'affecte QUE ce qui est encore chez TAGTOA et non réclamé.
            //
            // Un stand déjà vendu, perdu ou réclamé par un commerce ne se
            // réaffecte pas : ce serait dire à un revendeur qu'il détient un
            // objet qui est sur la table de quelqu'un d'autre.
            $stands = Stand::where('batch_id', $batchId)
                ->whereBetween('serial', [$du, $au])
                ->whereIn('physical_state', [StandState::MANUFACTURED, StandState::IN_STOCK])
                ->where('digital_state', StandState::UNCLAIMED)
                ->lockForUpdate()->get();

            foreach ($stands as $stand) {
                $avant = $stand->physical_state;

                $stand->forceFill([
                    'physical_state' => StandState::ALLOCATED,
                    'holder_type'    => Stand::HOLDER_RESELLER,
                    'holder_id'      => $reseller->id,
                ])->save();

                StandEvent::create([
                    'stand_id'   => $stand->id,
                    'event'      => StandEvent::ALLOCATED,
                    'from_state' => $avant,
                    'to_state'   => StandState::ALLOCATED,
                    'actor_type' => 'platform',
                    'actor_name' => $contexte['actor_name'] ?? null,
                    'ip'         => $contexte['ip'] ?? null,
                    'meta'       => ['reseller_id' => $reseller->id, 'reseller' => $reseller->name],
                ]);
            }

            return $stands->count();
        });

        return ['result' => $n > 0 ? self::OK : self::RIEN, 'count' => $n];
    }

    /**
     * Le revendeur déclare avoir vendu un stand.
     *
     * L'identité numérique n'est PAS touchée : `digital_state` reste
     * `unclaimed`, `tenant_id` reste nul. Le marchand grattera et réclamera.
     *
     * @return array{result:string, stand:?Stand}
     */
    public function declareSale(Reseller $reseller, ?string $publicId, array $contexte = []): array
    {
        $resultat = DB::transaction(function () use ($reseller, $publicId, $contexte) {
            $stand = Stand::byPublicId($publicId)->lockForUpdate()->first();

            if (! $stand) {
                return ['result' => self::INTROUVABLE, 'stand' => null];
            }

            // Détenu par CE revendeur, et par personne d'autre. Sans ce
            // contrôle, un revendeur déclarerait les ventes d'un autre — et
            // toucherait sa commission.
            if ($stand->holder_type !== Stand::HOLDER_RESELLER || (int) $stand->holder_id !== $reseller->id) {
                return ['result' => self::PAS_A_LUI, 'stand' => null];
            }

            if ($stand->physical_state === StandState::SOLD) {
                // Déjà déclaré : on le dit calmement plutôt que d'écrire deux
                // fois. Un revendeur qui hésite ne doit pas créer deux ventes.
                return ['result' => self::DEJA_VENDU, 'stand' => $stand];
            }

            if ($stand->physical_state !== StandState::ALLOCATED) {
                // Perdu, endommagé, retiré : l'objet n'est plus en circulation.
                return ['result' => self::PAS_A_LUI, 'stand' => null];
            }

            $stand->forceFill([
                'physical_state' => StandState::SOLD,
                // ON S'ARRÊTE LÀ. Ni digital_state, ni tenant_id : le stand
                // reste NON RÉCLAMÉ, et c'est le marchand qui le réclamera
                // avec un code que le revendeur n'a jamais vu.
            ])->save();

            StandEvent::create([
                'stand_id'   => $stand->id,
                'event'      => StandEvent::SOLD,
                'from_state' => StandState::ALLOCATED,
                'to_state'   => StandState::SOLD,
                'actor_type' => 'reseller',
                'actor_name' => $reseller->name,
                'ip'         => $contexte['ip'] ?? null,
                // Le téléphone de l'acheteur, s'il l'a donné : c'est ce qui
                // permet de retrouver à qui un stand a été vendu quand il
                // revient cassé six mois plus tard.
                'meta'       => array_filter([
                    'reseller_id' => $reseller->id,
                    'buyer_phone' => $contexte['buyer_phone'] ?? null,
                    'buyer_name'  => $contexte['buyer_name'] ?? null,
                ]),
            ]);

            return ['result' => self::OK, 'stand' => $stand];
        });

        return $resultat;
    }

    /** Ce que le revendeur détient, par état. */
    public function inventory(Reseller $reseller): array
    {
        $parEtat = Stand::heldBy($reseller->id)
            ->selectRaw('physical_state, COUNT(*) as n')
            ->groupBy('physical_state')
            ->pluck('n', 'physical_state');

        return [
            'en_stock' => (int) ($parEtat[StandState::ALLOCATED] ?? 0),
            'vendus'   => (int) ($parEtat[StandState::SOLD] ?? 0),
            'perdus'   => (int) ($parEtat[StandState::LOST] ?? 0)
                          + (int) ($parEtat[StandState::DAMAGED] ?? 0),
            'total'    => (int) $parEtat->sum(),
        ];
    }
}
