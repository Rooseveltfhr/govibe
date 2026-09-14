<?php

namespace Modules\Tagtoa\App\Support\Stand;

/**
 * TAGTOA SMART STAND — deux axes d'état, jamais une seule chaîne.
 *
 * Où se trouve l'OBJET et ce que fait son URL sont deux choses de nature
 * différente. Les fondre produit des états composés — « vendu-mais-non-réclamé »,
 * « actif-mais-perdu » — qui se multiplient sans fin et qu'il faut réécrire à
 * chaque cas nouveau.
 *
 * Un stand peut parfaitement être VENDU (axe physique) et NON RÉCLAMÉ (axe
 * numérique) : c'est même l'état normal d'un objet qui dort dans un carton chez
 * un revendeur. Et un stand ACTIF peut être déclaré PERDU sans que le menu du
 * commerce cesse de fonctionner.
 *
 * Même leçon que les commandes : statut et paiement sont deux axes, pas un.
 *
 * Classe PURE : aucune dépendance Laravel, testable sans base de données.
 */
class StandState
{
    /* ---------------- Axe 1 : l'objet physique ---------------- */

    public const MANUFACTURED = 'manufactured'; // imprimé, pas encore reçu
    public const IN_STOCK     = 'in_stock';     // en entrepôt TAGTOA
    public const ALLOCATED    = 'allocated';    // affecté à un revendeur
    public const SOLD         = 'sold';         // vendu, déclaré par le revendeur
    public const LOST         = 'lost';         // perte déclarée — secret révoqué
    public const DAMAGED      = 'damaged';      // illisible, ouvre droit au remplacement
    public const RETIRED      = 'retired';      // fin de vie, définitif

    public const PHYSICAL = [
        self::MANUFACTURED, self::IN_STOCK, self::ALLOCATED,
        self::SOLD, self::LOST, self::DAMAGED, self::RETIRED,
    ];

    public const PHYSICAL_LABELS = [
        self::MANUFACTURED => 'Fabriqué',
        self::IN_STOCK     => 'En stock',
        self::ALLOCATED    => 'Affecté',
        self::SOLD         => 'Vendu',
        self::LOST         => 'Perdu',
        self::DAMAGED      => 'Endommagé',
        self::RETIRED      => 'Retiré',
    ];

    /** L'objet n'est plus en circulation. */
    public const PHYSICAL_CLOSED = [self::LOST, self::DAMAGED, self::RETIRED];

    /* ---------------- Axe 2 : l'identité numérique ---------------- */

    public const UNCLAIMED        = 'unclaimed';        // aucun propriétaire
    public const CLAIM_PENDING    = 'claim_pending';    // secret accepté, réservé
    public const ACTIVE           = 'active';           // rattaché à un commerce
    public const TRANSFER_PENDING = 'transfer_pending'; // cession en attente
    public const SUSPENDED        = 'suspended';        // abonnement expiré, litige
    public const REVOKED          = 'revoked';          // fraude établie

    public const DIGITAL = [
        self::UNCLAIMED, self::CLAIM_PENDING, self::ACTIVE,
        self::TRANSFER_PENDING, self::SUSPENDED, self::REVOKED,
    ];

    public const DIGITAL_LABELS = [
        self::UNCLAIMED        => 'Non activé',
        self::CLAIM_PENDING    => 'Activation en cours',
        self::ACTIVE           => 'Actif',
        self::TRANSFER_PENDING => 'Cession en attente',
        self::SUSPENDED        => 'Suspendu',
        self::REVOKED          => 'Révoqué',
    ];

    /* ---------------- Questions que se posent les écrans ---------------- */

    public static function isValidPhysical(?string $state): bool
    {
        return $state !== null && in_array($state, self::PHYSICAL, true);
    }

    public static function isValidDigital(?string $state): bool
    {
        return $state !== null && in_array($state, self::DIGITAL, true);
    }

    public static function physicalLabel(?string $state): string
    {
        return self::PHYSICAL_LABELS[$state] ?? 'Inconnu';
    }

    public static function digitalLabel(?string $state): string
    {
        return self::DIGITAL_LABELS[$state] ?? 'Inconnu';
    }

    /**
     * Ce stand peut-il encore être réclamé ? PUR.
     *
     * Un stand perdu ou endommagé ne l'est plus : son secret est révoqué. C'est
     * la SEULE protection réelle contre le vol d'un objet non gratté — on ne
     * peut pas empêcher qu'on le prenne, on peut faire qu'il ne serve à rien.
     */
    public static function isClaimable(?string $physical, ?string $digital): bool
    {
        if (! self::isValidPhysical($physical) || ! self::isValidDigital($digital)) {
            return false;
        }

        if (in_array($physical, self::PHYSICAL_CLOSED, true)) {
            return false;
        }

        // « En cours » reste réclamable : une réservation expirée ne doit pas
        // condamner le stand, sinon un client qui abandonne à mi-parcours ne
        // pourrait plus jamais l'activer.
        return in_array($digital, [self::UNCLAIMED, self::CLAIM_PENDING], true);
    }

    /**
     * Le scan doit-il mener au menu du commerce ? PUR.
     *
     * Une cession en attente n'interrompt RIEN : le client attablé n'a pas à
     * subir une négociation entre deux propriétaires.
     */
    public static function resolvesToBusiness(?string $digital): bool
    {
        return in_array($digital, [self::ACTIVE, self::TRANSFER_PENDING], true);
    }

    /**
     * Le secret est-il encore valable ? PUR.
     *
     * Révoqué dès la déclaration de perte : c'est ce qui rend un stand volé
     * inutile avant même qu'il ne soit gratté.
     */
    public static function secretIsLive(?string $physical, ?string $digital): bool
    {
        return self::isClaimable($physical, $digital);
    }
}
