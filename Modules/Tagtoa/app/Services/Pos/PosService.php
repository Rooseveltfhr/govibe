<?php

namespace Modules\Tagtoa\App\Services\Pos;

use Illuminate\Support\Facades\DB;
use Modules\Tagtoa\App\Models\Menu\Item as MenuItem;
use Modules\Tagtoa\App\Models\Pos\Sale;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Models\Staff\Staff;
use Modules\Tagtoa\App\Services\Billing\RevenueService;
use Modules\Tagtoa\App\Support\Catalog\Pricing;
use Modules\Tagtoa\App\Support\Pos\CatalogRef;

/**
 * TAGTOA POS — enregistrement des ventes (atomique, idempotent, offline-sync)
 * + commission plateforme.
 */
class PosService
{
    public function __construct(
        protected RevenueService $revenue,
        protected PosCatalog $catalog,
    ) {
    }

    /**
     * Enregistre une vente.
     *
     * `$staff` est facultatif : un commerce qui n'a pas encore créé d'employé
     * vend exactement comme avant, et la vente est alors celle du patron.
     */
    public function recordSale(Terminal $terminal, array $payload, ?Staff $staff = null): Sale
    {
        // Idempotence limitée à CETTE caisse. La recherche était globale : une
        // caisse qui numérote « 1 » ou « vente-42 » retrouvait alors la vente
        // d'un AUTRE commerce, sa propre vente n'était jamais enregistrée, et la
        // référence du voisin lui était renvoyée.
        $uuid = $payload['client_uuid'] ?? null;
        if ($uuid && $existing = $terminal->sales()->where('client_uuid', $uuid)->first()) {
            return $existing;
        }

        return DB::transaction(function () use ($terminal, $payload, $uuid, $staff) {
            $items    = $payload['items'] ?? [];
            $discount = max(0, (float) ($payload['discount'] ?? 0));

            // Sécurité financière : chaque ligne est pré-résolue dans le
            // catalogue du COMMERCE — boutons de la caisse ET articles du menu.
            // Le prix et le nom viennent TOUJOURS du serveur, jamais de ce que
            // la caisse a envoyé. Les articles au pied levé (sans référence)
            // gardent le prix saisi par le caissier : c'est le cas du « divers ».
            $lines = [];
            $subtotal = 0;
            foreach ($items as $it) {
                // « menu:7 » ou « pos:7 ». Les caisses déjà installées envoient
                // encore un identifiant nu, compris comme un bouton de caisse.
                $ref = $it['ref'] ?? $it['product_id'] ?? null;
                $article = $ref !== null && $ref !== ''
                    ? $this->catalog->resolve($terminal->tenant_id, $ref)
                    : null;

                // La quantité suit l'UNITÉ de l'article : 2,5 livres de riz
                // restent 2,5, alors qu'un article à la pièce est ramené à
                // l'entier. Arrondir à l'entier partout ferait payer au client
                // moins que ce qu'il emporte, à chaque vente au poids.
                $unit = $article?->unit;
                $qty  = Pricing::normalizeQty($unit, (float) ($it['qty'] ?? 1));
                if ($qty <= 0) {
                    continue; // une ligne à zéro n'est pas une vente
                }

                $price = $article ? (float) $article->price : (float) ($it['price'] ?? 0);
                $name  = $article ? $article->name : (string) ($it['name'] ?? 'Article');

                // Coût du JOUR de la vente, figé sur la ligne : sans lui, le
                // profit d'un mois passé se recalculerait avec le prix d'achat
                // d'aujourd'hui et bougerait tout seul après coup.
                $cost = $article && $article->cost_price !== null
                    ? (float) $article->cost_price
                    : null;

                $ligneTotal = Pricing::lineTotal($unit, $price, $qty);
                $subtotal += $ligneTotal;
                $lines[] = [$article, $name, $price, $cost, $qty, $ligneTotal];
            }
            $total = max(0, $subtotal - $discount);

            $sale = $terminal->sales()->create([
                'reference'      => Sale::generateReference(),
                'subtotal'       => $subtotal,
                'discount'       => $discount,
                'total'          => $total,
                'currency'       => $terminal->currency,
                'payments'       => $payload['payments'] ?? [['method' => 'cash', 'amount' => $total]],
                'customer_phone' => $payload['customer_phone'] ?? null,
                'staff_id'       => $staff?->id,
                'client_uuid'    => $uuid,
                'status'         => 1,
                'sold_at'        => now(),
            ]);

            foreach ($lines as [$article, $name, $price, $cost, $qty, $ligneTotal]) {
                // La ligne dit de QUEL catalogue vient l'article : le plat n°7
                // et le bouton n°7 sont deux choses différentes.
                $sale->items()->create([
                    'product_id' => $article?->id,
                    'source'     => $article instanceof MenuItem
                        ? CatalogRef::SOURCE_MENU
                        : CatalogRef::SOURCE_POS,
                    'name'       => $name,
                    'price'      => $price,
                    'cost_price' => $cost,
                    'qty'        => $qty,
                    'line_total' => $ligneTotal,
                ]);

                // UN SEUL STOCK : vendre un plat au comptoir retire du même
                // stock qu'une commande passée par QR.
                if ($article && $article->stock !== null) {
                    $article->decrement('stock', $qty);
                }
            }

            $this->revenue->record('pos_sale', $sale->id, 'pos', (float) $total, $terminal->tenant_id, $terminal->currency);

            return $sale;
        });
    }
}
