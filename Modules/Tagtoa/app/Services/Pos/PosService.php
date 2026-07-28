<?php

namespace Modules\Tagtoa\App\Services\Pos;

use Illuminate\Support\Facades\DB;
use Modules\Tagtoa\App\Models\Pos\Sale;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Services\Billing\RevenueService;

/**
 * TAGTOA POS — enregistrement des ventes (atomique, idempotent, offline-sync)
 * + commission plateforme.
 */
class PosService
{
    public function __construct(protected RevenueService $revenue)
    {
    }

    public function recordSale(Terminal $terminal, array $payload): Sale
    {
        $uuid = $payload['client_uuid'] ?? null;
        if ($uuid && $existing = Sale::where('client_uuid', $uuid)->first()) {
            return $existing;
        }

        return DB::transaction(function () use ($terminal, $payload, $uuid) {
            $items    = $payload['items'] ?? [];
            $discount = max(0, (float) ($payload['discount'] ?? 0));

            // Sécurité financière : pré-résoudre chaque ligne. Pour un produit du
            // catalogue (appartenant à CE terminal), on impose le prix/nom du SERVEUR
            // — jamais le prix envoyé par le client (anti-tampering). Les articles
            // ad-hoc (sans product_id) gardent le prix saisi par le caissier.
            $lines = [];
            $subtotal = 0;
            foreach ($items as $it) {
                $qty = max(1, (int) ($it['qty'] ?? 1));
                $product = ! empty($it['product_id'])
                    ? $terminal->products()->whereKey($it['product_id'])->first()
                    : null;
                $price = $product ? (float) $product->price : (float) ($it['price'] ?? 0);
                $name  = $product ? $product->name : (string) ($it['name'] ?? 'Article');
                $subtotal += $price * $qty;
                $lines[] = [$product, $name, $price, $qty];
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
                'client_uuid'    => $uuid,
                'status'         => 1,
                'sold_at'        => now(),
            ]);

            foreach ($lines as [$product, $name, $price, $qty]) {
                $sale->items()->create([
                    'product_id' => $product?->id,
                    'name'       => $name,
                    'price'      => $price,
                    'qty'        => $qty,
                    'line_total' => $price * $qty,
                ]);

                if ($product && $product->stock !== null) {
                    $product->decrement('stock', $qty);
                }
            }

            $this->revenue->record('pos_sale', $sale->id, 'pos', (float) $total, $terminal->tenant_id, $terminal->currency);

            return $sale;
        });
    }
}
