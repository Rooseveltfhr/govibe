<?php

namespace Modules\Tagtoa\App\Services\Inventory;

/**
 * TAGTOA INVENTORY — logique de stock partagée (MENU, POS).
 *
 * Convention : un stock `null` = NON suivi (illimité). Un entier = quantité
 * disponible. Toute la logique ici est PURE (testable sans Laravel).
 */
class StockService
{
    /** Seuil "stock faible" par défaut. */
    public const LOW_THRESHOLD = 5;

    /** Peut-on servir $qty unités ? (null = illimité). PUR. */
    public static function canFulfill(?int $stock, int $qty): bool
    {
        if ($stock === null) {
            return true;
        }

        return $stock >= max(0, $qty);
    }

    /** Stock restant après avoir retiré $qty, jamais sous 0 (null reste null). PUR. */
    public static function remaining(?int $stock, int $qty): ?int
    {
        if ($stock === null) {
            return null;
        }

        return max(0, $stock - max(0, $qty));
    }

    /** Stock faible ? (null = jamais faible). PUR. */
    public static function isLow(?int $stock, int $threshold = self::LOW_THRESHOLD): bool
    {
        if ($stock === null) {
            return false;
        }

        return $stock <= max(0, $threshold);
    }

    /** En rupture ? (null = jamais en rupture). PUR. */
    public static function isOut(?int $stock): bool
    {
        return $stock !== null && $stock <= 0;
    }
}
