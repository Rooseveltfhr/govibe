<?php

namespace Modules\Tagtoa\App\Support\Store;

/**
 * TAGTOA STORE — logique PURE du panier (testable sans Laravel).
 *
 * Sécurité : le prix vient TOUJOURS du catalogue serveur ($catalog), jamais
 * du client. On ignore silencieusement tout produit inconnu/indisponible.
 */
class Cart
{
    /**
     * Construit les lignes validées + le sous-total.
     *
     * @param  array  $catalog   [id => ['price'=>float,'name'=>string]] (produits autorisés)
     * @param  array  $requested [['id'=>int,'qty'=>int], ...] (panier client)
     * @return array{lines: array, subtotal: float}  PUR
     */
    public static function build(array $catalog, array $requested): array
    {
        $lines = [];
        $subtotal = 0.0;

        foreach ($requested as $r) {
            $id = (int) ($r['id'] ?? 0);
            if (! isset($catalog[$id])) {
                continue; // produit inconnu / indisponible → ignoré
            }
            $qty = self::clampQty($r['qty'] ?? 1);
            if ($qty <= 0) {
                continue;
            }
            $price = round((float) ($catalog[$id]['price'] ?? 0), 2);
            $lineTotal = round($price * $qty, 2);
            $subtotal += $lineTotal;
            $lines[] = [
                'id'         => $id,
                'name'       => $catalog[$id]['name'] ?? '',
                'price'      => $price,
                'qty'        => $qty,
                'line_total' => $lineTotal,
            ];
        }

        return ['lines' => $lines, 'subtotal' => round($subtotal, 2)];
    }

    /** Quantité bornée 1..99 (entier). PUR. */
    public static function clampQty($qty): int
    {
        $q = (int) $qty;

        return max(0, min(99, $q));
    }
}
