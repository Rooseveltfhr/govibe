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
            $items = $payload['items'] ?? [];
            $discount = (float) ($payload['discount'] ?? 0);
            $subtotal = 0;
            foreach ($items as $it) {
                $subtotal += (float) $it['price'] * (int) $it['qty'];
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

            foreach ($items as $it) {
                $qty = (int) $it['qty'];

                // Sécurité : ne résoudre le produit QUE via le terminal courant.
                // Un product_id appartenant à un autre tenant est ignoré (pas de
                // référence stockée, pas de décrément de stock cross-tenant).
                $product = ! empty($it['product_id'])
                    ? $terminal->products()->whereKey($it['product_id'])->first()
                    : null;

                $sale->items()->create([
                    'product_id' => $product?->id,
                    'name'       => $it['name'],
                    'price'      => $it['price'],
                    'qty'        => $qty,
                    'line_total' => (float) $it['price'] * $qty,
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
