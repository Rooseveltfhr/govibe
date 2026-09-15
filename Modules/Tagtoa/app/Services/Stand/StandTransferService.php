<?php

namespace Modules\Tagtoa\App\Services\Stand;

use Illuminate\Support\Facades\DB;
use Modules\Tagtoa\App\Models\Stand\Stand;
use Modules\Tagtoa\App\Models\Stand\StandEvent;
use Modules\Tagtoa\App\Models\Stand\StandTransfer;
use Modules\Tagtoa\App\Models\Stand\StandTransferItem;
use Modules\Tagtoa\App\Support\Stand\StandState;
use Modules\Tagtoa\App\Support\Stand\TransferCode;

/**
 * TAGTOA SMART STAND — céder ses stands à un autre commerce.
 *
 * ── LA RÈGLE QUI GOUVERNE TOUT CE SERVICE ───────────────────────────────
 *
 * UN STAND ACTIVÉ NE REDEVIENT JAMAIS RÉCLAMABLE.
 *
 * Le panneau à gratter a déjà été gratté : le secret est écrit en clair sur
 * l'objet, et l'ancien propriétaire a pu le photographier. Rendre ce secret
 * vivant donnerait à celui qui vend son bar le pouvoir de reprendre les
 * quarante stands le lendemain, et de rediriger tous les QR posés sur les
 * tables vers son propre menu. Le client attablé chez l'acheteur commanderait
 * chez le vendeur.
 *
 * La SEULE sortie d'un commerce est donc une cession dirigée : le propriétaire
 * actuel émet une offre, et un repreneur la présente. Ce service n'accepte à
 * aucun moment le secret gratté — deux autorités différentes, deux chemins
 * différents.
 *
 * ── Ce qui ne s'éteint pas ──────────────────────────────────────────────
 *
 * Pendant toute la négociation, les QR CONTINUENT DE MARCHER. `transfer_pending`
 * est déjà traité comme une destination valable par le résolveur (voir
 * `StandState::resolvesToBusiness`). Un client attablé n'a pas à subir une
 * vente de fonds de commerce : couper les menus pendant trois jours ferait
 * perdre trois jours de commandes aux deux parties.
 */
class StandTransferService
{
    /* Résultats. Une chaîne libre divergerait entre le service et l'écran. */
    public const OK           = 'ok';
    public const RIEN         = 'rien';          // aucun stand cessible dans la sélection
    public const TROP         = 'trop';          // sélection au-delà du plafond
    public const INTROUVABLE  = 'introuvable';   // ce code ne correspond à rien
    public const EXPIREE      = 'expiree';
    public const DEJA_UTILISE = 'deja_utilise';
    public const ANNULEE      = 'annulee';
    public const SOI_MEME     = 'soi_meme';      // céder à soi-même n'a pas de sens
    public const PAS_A_LUI    = 'pas_a_lui';     // cette offre n'est pas la sienne

    /**
     * Durée de vie d'une offre.
     *
     * Trois jours : une vente de fonds de commerce ne se conclut pas dans
     * l'heure, et le repreneur doit parfois créer son compte et son commerce
     * avant d'accepter. Au-delà, un code au porteur qui traîne sur WhatsApp
     * devient un risque sans contrepartie — et le cédant peut toujours en
     * réémettre un en une seconde.
     */
    public const HEURES = 72;

    /**
     * Au-delà, ce n'est plus une cession : c'est une erreur de saisie.
     *
     * Le plus gros établissement imaginable pose quelques centaines de stands.
     * Un nombre sans plafond permettrait de bloquer d'un coup tout le parc d'un
     * commerce sur une seule requête.
     */
    public const MAX_STANDS = 500;

    public function __construct(protected StandResolver $resolver)
    {
    }

    /* ==================================================================
       1. PROPOSER
       ================================================================== */

    /**
     * Émet une offre de cession sur les stands choisis.
     *
     * Le code en clair est renvoyé UNE fois, à l'appelant qui l'affichera. Il
     * n'entre jamais en base : seul son empreinte y est écrite. Perdu, on
     * annule et on réémet — c'est la seule façon honnête de tenir la promesse
     * « personne d'autre ne peut le lire », fuite de la base comprise.
     *
     * @param  array<int>  $standIds
     * @return array{result:string, transfer:?StandTransfer, code:?string, count:int}
     */
    public function offer(?string $fromBusinessId, array $standIds, array $contexte = []): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $standIds))));

        if ($fromBusinessId === null || $ids === []) {
            return $this->vide(self::RIEN);
        }

        if (count($ids) > self::MAX_STANDS) {
            return $this->vide(self::TROP);
        }

        $code = TransferCode::make();

        $resultat = DB::transaction(function () use ($fromBusinessId, $ids, $code, $contexte) {
            // On ne cède QUE ce qui est actif et à soi.
            //
            // `ofBusiness` est explicite parce que le modèle Stand n'a pas de
            // portée automatique : sans ce filtre, on céderait les stands des
            // autres en envoyant leurs identifiants à la main.
            //
            // `ACTIVE` seulement : un stand déjà dans une offre est en
            // `transfer_pending` et sort donc de cette requête — c'est ce qui
            // empêche deux codes de circuler pour le même objet.
            $stands = Stand::ofBusiness($fromBusinessId)
                ->whereIn('id', $ids)
                ->where('digital_state', StandState::ACTIVE)
                ->whereNotIn('physical_state', StandState::PHYSICAL_CLOSED)
                ->lockForUpdate()->get();

            if ($stands->isEmpty()) {
                return $this->vide(self::RIEN);
            }

            $transfer = StandTransfer::create([
                'from_business_id' => $fromBusinessId,
                'code_hash'        => TransferCode::fingerprint($code),
                'note'             => $contexte['note'] ?? null,
                'expires_at'       => now()->addHours(self::HEURES),
                'actor_name'       => $contexte['actor_name'] ?? null,
                'ip'               => $contexte['ip'] ?? null,
            ]);

            foreach ($stands as $stand) {
                StandTransferItem::create([
                    'transfer_id' => $transfer->id,
                    'stand_id'    => $stand->id,
                ]);

                $stand->forceFill(['digital_state' => StandState::TRANSFER_PENDING])->save();

                StandEvent::create([
                    'stand_id'   => $stand->id,
                    'event'      => StandEvent::TRANSFER_OFFERED,
                    'from_state' => StandState::ACTIVE,
                    'to_state'   => StandState::TRANSFER_PENDING,
                    'actor_type' => 'business',
                    'actor_name' => $contexte['actor_name'] ?? null,
                    'tenant_id'  => $fromBusinessId,
                    'ip'         => $contexte['ip'] ?? null,
                    'meta'       => ['transfer_id' => $transfer->id],
                ]);

                // PAS de `forget()` ici, volontairement : la destination du
                // scan NE CHANGE PAS. Le stand appartient toujours au même
                // commerce, et `transfer_pending` résout déjà vers lui. Vider
                // le cache ferait retomber sur la base des milliers de scans
                // sans rien corriger.
            }

            return [
                'result'   => self::OK,
                'transfer' => $transfer,
                'code'     => null, // rempli hors transaction, jamais journalisé
                'count'    => $stands->count(),
            ];
        });

        if ($resultat['result'] === self::OK) {
            $resultat['code'] = $code;
        }

        return $resultat;
    }

    /* ==================================================================
       2. ACCEPTER
       ================================================================== */

    /**
     * Le repreneur présente le code : les stands changent de commerce.
     *
     * Verrou de ligne et re-contrôle DANS la transaction : deux personnes à qui
     * le même code aurait été transmis ne doivent pas reprendre les stands
     * toutes les deux. Sans le verrou, les deux lisent « en attente » puis
     * écrivent tour à tour, et la seconde efface la première en silence.
     *
     * @return array{result:string, transfer:?StandTransfer, count:int}
     */
    public function accept(?string $code, ?string $toBusinessId, array $contexte = []): array
    {
        if (! TransferCode::isValid($code) || $toBusinessId === null) {
            return ['result' => self::INTROUVABLE, 'transfer' => null, 'count' => 0];
        }

        $empreinte = TransferCode::fingerprint($code);

        $resultat = DB::transaction(function () use ($empreinte, $toBusinessId, $contexte) {
            $transfer = StandTransfer::where('code_hash', $empreinte)->lockForUpdate()->first();

            if (! $transfer) {
                return ['result' => self::INTROUVABLE, 'transfer' => null, 'count' => 0];
            }

            if ($transfer->accepted_at !== null) {
                // Usage unique. Un code déjà consommé ne reprend rien : sinon
                // le même code, transmis deux fois, reprendrait deux fois les
                // mêmes stands — au dernier arrivé.
                return ['result' => self::DEJA_UTILISE, 'transfer' => $transfer, 'count' => 0];
            }

            if ($transfer->cancelled_at !== null) {
                return ['result' => self::ANNULEE, 'transfer' => $transfer, 'count' => 0];
            }

            if ($transfer->isExpired()) {
                // On rend les stands en passant : une offre périmée ne doit pas
                // laisser un parc bloqué en « cession en attente » jusqu'à ce
                // que quelqu'un pense à ouvrir l'écran.
                //
                // Contexte VIDE, volontairement : celui qui présente un code
                // périmé n'est pas l'auteur de ce balayage, et signer les lignes
                // du journal de son nom et de son adresse les rendrait trompeuses
                // le jour où elles servent à trancher un litige.
                $this->rendre($transfer, []);

                return ['result' => self::EXPIREE, 'transfer' => $transfer, 'count' => 0];
            }

            if ($transfer->from_business_id === $toBusinessId) {
                // Se céder à soi-même consommerait le code sans rien déplacer,
                // et laisserait le marchand croire que la cession est faite.
                return ['result' => self::SOI_MEME, 'transfer' => $transfer, 'count' => 0];
            }

            $standIds = StandTransferItem::where('transfer_id', $transfer->id)->pluck('stand_id');

            $stands = Stand::whereIn('id', $standIds)
                ->where('digital_state', StandState::TRANSFER_PENDING)
                ->where('tenant_id', $transfer->from_business_id)
                ->lockForUpdate()->get();

            foreach ($stands as $stand) {
                $stand->forceFill([
                    'tenant_id'     => $toBusinessId,
                    'digital_state' => StandState::ACTIVE,
                    'holder_type'   => Stand::HOLDER_BUSINESS,
                    'holder_id'     => null,
                    // `claimed_at` n'est PAS réécrit : c'est la date de la
                    // toute première activation, et c'est elle qui prouve
                    // l'ancienneté de l'objet dans un litige. La date de la
                    // cession vit dans le journal, où elle ne peut pas écraser
                    // autre chose.
                ])->save();

                StandEvent::create([
                    'stand_id'   => $stand->id,
                    'event'      => StandEvent::TRANSFER,
                    'from_state' => StandState::TRANSFER_PENDING,
                    'to_state'   => StandState::ACTIVE,
                    'actor_type' => 'business',
                    'actor_name' => $contexte['actor_name'] ?? null,
                    'tenant_id'  => $toBusinessId,
                    'ip'         => $contexte['ip'] ?? null,
                    'meta'       => [
                        'transfer_id' => $transfer->id,
                        'from'        => $transfer->from_business_id,
                        'to'          => $toBusinessId,
                    ],
                ]);
            }

            $transfer->forceFill([
                'to_business_id' => $toBusinessId,
                'accepted_at'    => now(),
            ])->save();

            return ['result' => self::OK, 'transfer' => $transfer, 'count' => $stands->count(), 'stands' => $stands];
        });

        // Le cache du résolveur est vidé APRÈS la transaction : le vider dedans
        // le remplirait à nouveau depuis une lecture non encore validée, et un
        // rollback laisserait alors l'ancienne destination en mémoire pour une
        // heure — c'est-à-dire le menu de l'ancien propriétaire au client du
        // nouveau, sur des tables déjà vendues.
        if ($resultat['result'] === self::OK) {
            foreach ($resultat['stands'] as $stand) {
                $this->resolver->forget($stand->public_id);
            }
            unset($resultat['stands']);
        }

        return $resultat;
    }

    /* ==================================================================
       3. ANNULER  ·  4. EXPIRER
       ================================================================== */

    /**
     * Le cédant retire son offre : les stands lui reviennent.
     *
     * @return array{result:string, count:int}
     */
    public function cancel(int $transferId, ?string $fromBusinessId, array $contexte = []): array
    {
        return DB::transaction(function () use ($transferId, $fromBusinessId, $contexte) {
            $transfer = StandTransfer::whereKey($transferId)->lockForUpdate()->first();

            if (! $transfer) {
                return ['result' => self::INTROUVABLE, 'count' => 0];
            }

            // La sienne, et celle de personne d'autre. Sans ce contrôle, un
            // marchand annulerait la cession d'un concurrent en devinant un
            // numéro de ligne.
            if ($fromBusinessId === null || $transfer->from_business_id !== $fromBusinessId) {
                return ['result' => self::PAS_A_LUI, 'count' => 0];
            }

            if ($transfer->accepted_at !== null) {
                // Trop tard : les stands sont chez le repreneur. Les reprendre
                // ici serait exactement le vol que ce module doit empêcher.
                return ['result' => self::DEJA_UTILISE, 'count' => 0];
            }

            if ($transfer->cancelled_at !== null) {
                return ['result' => self::ANNULEE, 'count' => 0];
            }

            $n = $this->rendre($transfer, $contexte);

            return ['result' => self::OK, 'count' => $n];
        });
    }

    /**
     * Rend à leur propriétaire les stands des offres périmées d'un commerce.
     *
     * Appelé à l'ouverture de l'écran plutôt que par une tâche planifiée : une
     * offre expirée ne casse rien tant que personne ne regarde — le QR marche,
     * le stand sert — et une tâche de plus à installer sur le serveur est une
     * tâche de plus qui peut ne pas tourner.
     */
    public function expireStale(?string $fromBusinessId): int
    {
        if ($fromBusinessId === null) {
            return 0;
        }

        $perimees = StandTransfer::issuedBy($fromBusinessId)
            ->whereNull('accepted_at')->whereNull('cancelled_at')
            ->where('expires_at', '<=', now())
            ->get();

        $n = 0;
        foreach ($perimees as $transfer) {
            $n += DB::transaction(fn () => $this->rendre($transfer, []));
        }

        return $n;
    }

    /* ==================================================================
       Ce que voient les écrans
       ================================================================== */

    /** Les offres émises par ce commerce, la plus récente d'abord. */
    public function outgoing(?string $fromBusinessId)
    {
        return StandTransfer::issuedBy($fromBusinessId)
            ->withCount('items')
            ->orderByDesc('id')
            ->limit(50)
            ->get();
    }

    /* ---------------- interne ---------------- */

    /**
     * Rend les stands d'une offre à leur propriétaire et clôt l'offre.
     *
     * Ne touche QUE les stands encore en attente sur CETTE offre : un stand
     * qu'un autre chemin aurait suspendu ou révoqué entre-temps ne doit pas
     * être remis en service par une annulation.
     */
    private function rendre(StandTransfer $transfer, array $contexte): int
    {
        $standIds = StandTransferItem::where('transfer_id', $transfer->id)->pluck('stand_id');

        $stands = Stand::whereIn('id', $standIds)
            ->where('digital_state', StandState::TRANSFER_PENDING)
            ->where('tenant_id', $transfer->from_business_id)
            ->lockForUpdate()->get();

        foreach ($stands as $stand) {
            $stand->forceFill(['digital_state' => StandState::ACTIVE])->save();

            StandEvent::create([
                'stand_id'   => $stand->id,
                'event'      => StandEvent::TRANSFER_CANCELLED,
                'from_state' => StandState::TRANSFER_PENDING,
                'to_state'   => StandState::ACTIVE,
                'actor_type' => 'business',
                'actor_name' => $contexte['actor_name'] ?? null,
                'tenant_id'  => $transfer->from_business_id,
                'ip'         => $contexte['ip'] ?? null,
                'meta'       => ['transfer_id' => $transfer->id],
            ]);
        }

        if ($transfer->cancelled_at === null) {
            $transfer->forceFill(['cancelled_at' => now()])->save();
        }

        return $stands->count();
    }

    /** @return array{result:string, transfer:null, code:null, count:int} */
    private function vide(string $result): array
    {
        return ['result' => $result, 'transfer' => null, 'code' => null, 'count' => 0];
    }
}
