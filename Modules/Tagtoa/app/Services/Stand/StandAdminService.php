<?php

namespace Modules\Tagtoa\App\Services\Stand;

use Illuminate\Support\Facades\DB;
use Modules\Tagtoa\App\Models\Stand\Stand;
use Modules\Tagtoa\App\Models\Stand\StandEvent;
use Modules\Tagtoa\App\Support\Stand\StandState;

/**
 * TAGTOA SMART STAND — le parc, vu et tenu par le fondateur.
 *
 * ── LE CHIFFRE QUI N'EXISTAIT NULLE PART : LES STANDS MUETS ─────────────
 *
 * Un stand MUET est un objet VENDU dont personne n'a jamais gratté le panneau.
 * Le revendeur a encaissé, le marchand a payé, l'objet est sur une table — et
 * aucun compte n'existe derrière. Du point de vue de TAGTOA, cette vente n'a
 * produit aucun client : ni menu, ni commandes, ni abonnement.
 *
 * C'est la seule fuite que rien ne signale. Elle ne produit aucune erreur,
 * aucun ticket de support, aucune réclamation — le marchand croit simplement
 * que « le truc ne marche pas » et le range dans un tiroir. Sans ce compteur,
 * on ne l'apprend jamais ; avec lui, on sait qui rappeler.
 *
 * ── CE QUE CE SERVICE NE FAIT PAS ───────────────────────────────────────
 *
 * Il ne montre AUCUN code d'activation, et n'en fabrique aucun. Les secrets
 * vivent sous un panneau à gratter et, pour le fondateur, dans le fichier de
 * frappe — produit une fois, détruit après tirage. Les remettre dans une page
 * web les rendrait consultables depuis n'importe quel navigateur resté ouvert
 * sur un comptoir.
 */
class StandAdminService
{
    /* Résultats. Une chaîne libre divergerait entre le service et l'écran. */
    public const OK           = 'ok';
    public const INTROUVABLE  = 'introuvable';
    public const IMPOSSIBLE   = 'impossible';   // l'état de départ ne le permet pas
    public const SANS_MOTIF   = 'sans_motif';
    public const MEME_COMMERCE = 'meme_commerce';

    /** Un motif plus court n'explique rien et ne vaut rien dans un litige. */
    public const MOTIF_MIN = 10;

    public function __construct(protected StandResolver $resolver)
    {
    }

    /* ==================================================================
       1. LE PARC
       ================================================================== */

    /**
     * L'état du parc en quelques nombres.
     *
     * Deux requêtes groupées, pas une par état : à dix mille stands, compter
     * état par état ferait quatorze allers-retours pour une page d'accueil.
     *
     * @return array{physique:array<string,int>, numerique:array<string,int>,
     *               total:int, muets:int, actifs:int, jamais_scannes:int}
     */
    public function parc(?int $batchId = null): array
    {
        $physique  = $this->compter('physical_state', $batchId);
        $numerique = $this->compter('digital_state', $batchId);

        return [
            'physique'  => $physique,
            'numerique' => $numerique,
            'total'     => array_sum($physique),
            'actifs'    => $numerique[StandState::ACTIVE] ?? 0,

            // LE chiffre du module : vendu, et jamais réclamé.
            'muets'     => $this->muets($batchId)->count(),

            // Réclamé, mais jamais scanné par un client. L'objet est dans un
            // tiroir plutôt que sur une table : le compte existe et ne sert à
            // rien, ce qui est un autre échec, plus tardif.
            'jamais_scannes' => $this->requete($batchId)
                ->where('digital_state', StandState::ACTIVE)
                ->whereNull('last_scanned_at')->count(),
        ];
    }

    /**
     * Les stands MUETS : vendus, jamais réclamés.
     *
     * Renvoie une REQUÊTE, pas une collection : l'écran la pagine, et charger
     * dix mille lignes pour en montrer cinquante ferait tomber la page du
     * fondateur le jour où le parc grandit — c'est-à-dire le jour où ce
     * chiffre commence enfin à compter.
     */
    public function muets(?int $batchId = null)
    {
        return $this->requete($batchId)
            ->where('physical_state', StandState::SOLD)
            ->where('digital_state', StandState::UNCLAIMED);
    }

    /* ==================================================================
       2. UN STAND, ET TOUTE SON HISTOIRE
       ================================================================== */

    /**
     * Le journal complet d'un stand, du plus récent au plus ancien.
     *
     * C'est ce qui tranche « ce stand est à moi » : qui l'a réclamé, depuis
     * quelle adresse, à quelle heure. Le journal ne se réécrit jamais — une
     * ligne modifiée effacerait précisément la trace qu'on cherche.
     */
    public function history(Stand $stand)
    {
        return StandEvent::where('stand_id', $stand->id)
            ->orderByDesc('id')
            ->limit(200)
            ->get();
    }

    /* ==================================================================
       3. LES ACTES DU FONDATEUR
       ================================================================== */

    /**
     * Change l'état PHYSIQUE d'un stand : perdu, endommagé, retiré, en stock.
     *
     * Déclarer PERDU révoque le secret — `StandState::isClaimable` refuse tout
     * état physique clos. C'est la SEULE protection réelle contre un stand
     * volé : on ne peut pas empêcher qu'on le prenne, on peut faire qu'il ne
     * serve à rien.
     *
     * @return array{result:string, stand:?Stand}
     */
    public function markPhysical(?string $publicId, string $etat, array $contexte = []): array
    {
        if (! StandState::isValidPhysical($etat)) {
            return ['result' => self::IMPOSSIBLE, 'stand' => null];
        }

        $resultat = DB::transaction(function () use ($publicId, $etat, $contexte) {
            $stand = Stand::byPublicId($publicId)->lockForUpdate()->first();

            if (! $stand) {
                return ['result' => self::INTROUVABLE, 'stand' => null];
            }

            $avant = $stand->physical_state;

            if ($avant === $etat) {
                return ['result' => self::OK, 'stand' => $stand]; // rien à faire, on ne s'en plaint pas
            }

            // Remettre EN STOCK un stand réclamé par un commerce reviendrait à
            // dire qu'il est chez nous alors qu'il est sur la table de
            // quelqu'un. L'identité passe par une cession, jamais par l'axe
            // physique — c'est toute la raison des deux axes.
            if ($etat === StandState::IN_STOCK && $stand->tenant_id !== null) {
                return ['result' => self::IMPOSSIBLE, 'stand' => $stand];
            }

            $champs = ['physical_state' => $etat];

            // Rendu au stock : l'objet revient chez TAGTOA, donc il quitte le
            // revendeur qui le détenait.
            if ($etat === StandState::IN_STOCK) {
                $champs['holder_type'] = Stand::HOLDER_PLATFORM;
                $champs['holder_id'] = null;
            }

            // PERDU ou ENDOMMAGÉ : le détenteur RESTE inscrit, volontairement.
            // C'est lui qu'on rappelle — « le carton 41-80 est chez Wilner, et
            // le 57 n'est jamais arrivé ». L'effacer perdrait exactement
            // l'information qui sert à retrouver l'objet.

            $stand->forceFill($champs)->save();

            StandEvent::create([
                'stand_id'   => $stand->id,
                'event'      => $this->evenementDe($etat),
                'from_state' => $avant,
                'to_state'   => $etat,
                'actor_type' => 'platform',
                'actor_name' => $contexte['actor_name'] ?? null,
                'ip'         => $contexte['ip'] ?? null,
                'meta'       => array_filter(['motif' => $contexte['motif'] ?? null]),
            ]);

            return ['result' => self::OK, 'stand' => $stand];
        });

        // Hors transaction : le vider dedans le remplirait depuis une lecture
        // non validée, et un rollback laisserait l'ancienne destination servie
        // une heure durant.
        if ($resultat['result'] === self::OK && $resultat['stand']) {
            $this->resolver->forget($resultat['stand']->public_id);
        }

        return $resultat;
    }

    /**
     * Suspend ou réactive l'identité NUMÉRIQUE d'un stand.
     *
     * Un stand suspendu ne rend ni une erreur ni une page de paiement : le
     * résolveur sert une page sobre. Il est posé sur une table, devant un
     * client qui a faim — on prévient le commerçant, pas son client.
     *
     * @return array{result:string, stand:?Stand}
     */
    public function markDigital(?string $publicId, string $etat, array $contexte = []): array
    {
        // Seuls ces trois-là se posent à la main. `unclaimed`, `claim_pending`
        // et `transfer_pending` sont des états de PARCOURS : les écrire ici
        // court-circuiterait les gardes qui les produisent — et remettre un
        // stand en « non réclamé » rendrait vivant un secret déjà gratté, donc
        // déjà lu par son ancien propriétaire.
        if (! in_array($etat, [StandState::ACTIVE, StandState::SUSPENDED, StandState::REVOKED], true)) {
            return ['result' => self::IMPOSSIBLE, 'stand' => null];
        }

        $resultat = DB::transaction(function () use ($publicId, $etat, $contexte) {
            $stand = Stand::byPublicId($publicId)->lockForUpdate()->first();

            if (! $stand) {
                return ['result' => self::INTROUVABLE, 'stand' => null];
            }

            // Réactiver n'a de sens que pour un stand qui APPARTIENT à un
            // commerce. Poser « actif » sur un stand sans propriétaire
            // fabriquerait une identité qui ne mène nulle part.
            if ($etat === StandState::ACTIVE && $stand->tenant_id === null) {
                return ['result' => self::IMPOSSIBLE, 'stand' => $stand];
            }

            $avant = $stand->digital_state;

            if ($avant === $etat) {
                return ['result' => self::OK, 'stand' => $stand];
            }

            $stand->forceFill(['digital_state' => $etat])->save();

            StandEvent::create([
                'stand_id'   => $stand->id,
                'event'      => $etat === StandState::REVOKED ? StandEvent::REVOKED : StandEvent::SUSPENDED,
                'from_state' => $avant,
                'to_state'   => $etat,
                'actor_type' => 'platform',
                'actor_name' => $contexte['actor_name'] ?? null,
                'tenant_id'  => $stand->tenant_id,
                'ip'         => $contexte['ip'] ?? null,
                'meta'       => array_filter(['motif' => $contexte['motif'] ?? null]),
            ]);

            return ['result' => self::OK, 'stand' => $stand];
        });

        if ($resultat['result'] === self::OK && $resultat['stand']) {
            $this->resolver->forget($resultat['stand']->public_id);
        }

        return $resultat;
    }

    /* ==================================================================
       4. LA CESSION FORCÉE
       ================================================================== */

    /**
     * Déplace un stand vers un autre commerce, sans le code du propriétaire.
     *
     * ── Pourquoi ce pouvoir existe ──────────────────────────────────────
     *
     * M6 exige le code du cédant, et c'est juste : c'est ce qui empêche un
     * ancien patron de reprendre le parc qu'il a vendu. Mais un compte mort —
     * une personne décédée, un marchand introuvable, un commerce fermé sans
     * prévenir — tient alors quarante stands en otage pour toujours. L'objet
     * existe, il est sur la table du repreneur, et rien ne peut le rattacher.
     *
     * ── Pourquoi il ne s'exerce JAMAIS en silence ───────────────────────
     *
     * C'est le pouvoir le plus dangereux de la plateforme : le fondateur peut
     * prendre les stands d'un commerce vivant et les donner à son concurrent.
     * Rien dans le code ne peut l'empêcher — c'est un choix humain. Ce qui
     * peut être fait, c'est le rendre IMPOSSIBLE À NIER : motif écrit
     * obligatoire, les deux commerces inscrits au journal, et une ligne d'audit
     * qui ne se réécrit pas.
     *
     * @return array{result:string, stand:?Stand}
     */
    public function forceTransfer(?string $publicId, ?string $versCommerce, string $motif, array $contexte = []): array
    {
        if (mb_strlen(trim($motif)) < self::MOTIF_MIN) {
            // Un motif vide ou d'un mot n'explique rien. Le refuser à la saisie
            // est la seule façon d'être sûr qu'il existe le jour du litige.
            return ['result' => self::SANS_MOTIF, 'stand' => null];
        }

        if ($versCommerce === null || trim($versCommerce) === '') {
            return ['result' => self::INTROUVABLE, 'stand' => null];
        }

        $resultat = DB::transaction(function () use ($publicId, $versCommerce, $motif, $contexte) {
            $stand = Stand::with('batch')->byPublicId($publicId)->lockForUpdate()->first();

            if (! $stand) {
                return ['result' => self::INTROUVABLE, 'stand' => null];
            }

            if ($stand->tenant_id === $versCommerce) {
                return ['result' => self::MEME_COMMERCE, 'stand' => $stand];
            }

            // Un objet hors circulation ne se déplace pas : un stand perdu ou
            // retiré rattaché à un commerce lui promettrait un service qui
            // n'arrivera jamais.
            if (in_array($stand->physical_state, StandState::PHYSICAL_CLOSED, true)) {
                return ['result' => self::IMPOSSIBLE, 'stand' => $stand];
            }

            $avant = $stand->tenant_id;
            $avantEtat = $stand->digital_state;

            $stand->forceFill([
                'tenant_id'            => $versCommerce,
                'digital_state'        => StandState::ACTIVE,
                'physical_state'       => StandState::SOLD,
                'holder_type'          => Stand::HOLDER_BUSINESS,
                'holder_id'            => null,
                // Une réservation en cours est consommée : sans cela, un jeton
                // encore valable permettrait de réclamer le stand par-dessus
                // la décision du fondateur.
                'claim_reserved_until' => null,
                'claim_reserved_token' => null,
            ])->save();

            StandEvent::create([
                'stand_id'   => $stand->id,
                'event'      => StandEvent::TRANSFER,
                'from_state' => $avantEtat,
                'to_state'   => StandState::ACTIVE,
                'actor_type' => 'platform',
                'actor_name' => $contexte['actor_name'] ?? null,
                'tenant_id'  => $versCommerce,
                'ip'         => $contexte['ip'] ?? null,
                // LES DEUX CÔTÉS, et le motif. C'est ce qui rend l'acte
                // impossible à nier six mois plus tard.
                'meta'       => [
                    'force' => true,
                    'from'  => $avant,
                    'to'    => $versCommerce,
                    'motif' => trim($motif),
                ],
            ]);

            return ['result' => self::OK, 'stand' => $stand];
        });

        if ($resultat['result'] === self::OK && $resultat['stand']) {
            $this->resolver->forget($resultat['stand']->public_id);
        }

        return $resultat;
    }

    /* ---------------- interne ---------------- */

    /** @return array<string,int> */
    private function compter(string $colonne, ?int $batchId): array
    {
        return $this->requete($batchId)
            ->selectRaw($colonne.', COUNT(*) as n')
            ->groupBy($colonne)
            ->pluck('n', $colonne)
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    private function requete(?int $batchId)
    {
        $q = Stand::query();

        return $batchId ? $q->where('batch_id', $batchId) : $q;
    }

    /** L'événement qui correspond à un état physique. */
    private function evenementDe(string $etat): string
    {
        return match ($etat) {
            StandState::LOST     => StandEvent::LOST,
            StandState::IN_STOCK => StandEvent::RECEIVED,
            default              => StandEvent::REPLACED,
        };
    }
}
