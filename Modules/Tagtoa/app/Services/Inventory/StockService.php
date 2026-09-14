<?php

namespace Modules\Tagtoa\App\Services\Inventory;

/**
 * TAGTOA INVENTORY — logique de stock partagée (MENU, POS, BOUTIQUE).
 *
 * Deux conventions, valables partout :
 *
 *   • `null` = stock NON suivi (illimité). Ce n'est pas « zéro ».
 *   • les quantités sont DÉCIMALES. Le riz se vend à la mamit et à la livre,
 *     l'huile au gode : arrondir 2,5 à 2 ferait payer au client moins que ce
 *     qu'il emporte, et ferait mentir le stock à chaque vente.
 *
 * C'est ici — et nulle part ailleurs — qu'on décide si un stock est faible :
 * une deuxième règle ailleurs finirait tôt ou tard par dire le contraire.
 *
 * Toute la logique est PURE (testable sans Laravel ni base de données).
 */
class StockService
{
    /** Plancher commun quand le commerce n'a réglé aucun seuil. */
    public const LOW_THRESHOLD = 5.0;

    /** Précision des quantités, alignée sur les colonnes decimal(12,3). */
    public const SCALE = 3;

    /** Quantité propre : jamais négative, arrondie à la précision retenue. PUR. */
    public static function qty(float $qty): float
    {
        return round(max(0.0, $qty), self::SCALE);
    }

    /** Peut-on servir $qty ? (null = illimité). PUR. */
    public static function canFulfill(?float $stock, float $qty): bool
    {
        if ($stock === null) {
            return true;
        }

        return round($stock, self::SCALE) >= self::qty($qty);
    }

    /** Stock restant après avoir retiré $qty, jamais sous 0 (null reste null). PUR. */
    public static function remaining(?float $stock, float $qty): ?float
    {
        if ($stock === null) {
            return null;
        }

        return round(max(0.0, $stock - self::qty($qty)), self::SCALE);
    }

    /**
     * Stock faible ? (null = jamais faible). PUR.
     *
     * Le seuil vient de l'article quand le commerce l'a réglé — une boulangerie
     * qui vend 200 pains par jour n'alerte pas au même niveau qu'un bijoutier.
     * Sans réglage on applique le plancher commun plutôt que de ne jamais
     * prévenir : un commerce qui n'a rien touché mérite quand même l'alerte.
     */
    public static function isLow(?float $stock, ?float $threshold = null): bool
    {
        if ($stock === null) {
            return false;
        }

        return round($stock, self::SCALE) <= max(0.0, $threshold ?? self::LOW_THRESHOLD);
    }

    /** En rupture ? (null = jamais en rupture). PUR. */
    public static function isOut(?float $stock): bool
    {
        return $stock !== null && round($stock, self::SCALE) <= 0;
    }
}
