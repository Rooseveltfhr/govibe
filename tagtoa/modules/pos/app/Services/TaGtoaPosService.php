<?php

namespace App\Services;

use App\Models\TaGtoaPosCashMovement;
use App\Models\TaGtoaPosProduct;
use App\Models\TaGtoaPosSale;
use App\Models\TaGtoaPosTerminal;
use Illuminate\Support\Facades\DB;

/**
 * TAGTOA POS — enregistrement des ventes (atomique, idempotent, offline-sync)
 * + commission plateforme via TaGtoaRevenueService.
 */
class TaGtoaPosService
{
    public function __construct(protected TaGtoaRevenueService $revenue)
    {
    }

    /**
     * Enregistre une vente.
     *
     * @param array $payload [
     *   'items'    => [ ['product_id'=>?, 'name'=>, 'price'=>, 'qty'=>], ... ],
     *   'discount' => float, 'payments' => [ ['method'=>,'amount'=>], ... ],
     *   'customer_phone' => ?string, 'client_uuid' => ?string
     * ]
     */
    public function recordSale(TaGtoaPosTerminal $terminal, array $payload): TaGtoaPosSale
    {
        $clientUuid = $payload['client_uuid'] ?? null;

        // Idempotence offline : même client_uuid => renvoyer la vente existante.
        if ($clientUuid) {
            $existing = TaGtoaPosSale::where('client_uuid', $clientUuid)->first();
            if ($existing) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($terminal, $payload, $clientUuid) {
            $items    = $payload['items'] ?? [];
            $discount = (float) ($payload['discount'] ?? 0);
            $subtotal = 0;

            foreach ($items as $it) {
                $subtotal += (float) $it['price'] * (int) $it['qty'];
            }
            $total = max(0, $subtotal - $discount);

            $sale = $terminal->sales()->create([
                'reference'      => TaGtoaPosSale::generateReference(),
                'subtotal'       => $subtotal,
                'discount'       => $discount,
                'total'          => $total,
                'currency'       => $terminal->currency,
                'payments'       => $payload['payments'] ?? [['method' => 'cash', 'amount' => $total]],
                'customer_phone' => $payload['customer_phone'] ?? null,
                'client_uuid'    => $clientUuid,
                'status'         => TaGtoaPosSale::STATUS_COMPLETED,
                'sold_at'        => now(),
            ]);

            foreach ($items as $it) {
                $qty = (int) $it['qty'];
                $sale->items()->create([
                    'product_id' => $it['product_id'] ?? null,
                    'name'       => $it['name'],
                    'price'      => $it['price'],
                    'qty'        => $qty,
                    'line_total' => (float) $it['price'] * $qty,
                ]);

                // Décrément de stock si suivi.
                if (! empty($it['product_id'])) {
                    $p = TaGtoaPosProduct::find($it['product_id']);
                    if ($p && $p->stock !== null) {
                        $p->decrement('stock', $qty);
                    }
                }
            }

            // Encaissement cash -> mouvement de caisse + solde terminal.
            $cashIn = collect($sale->payments)->where('method', 'cash')->sum('amount');
            if ($cashIn > 0) {
                $terminal->increment('cash_balance', $cashIn);
                $terminal->cashMovements()->create([
                    'type'          => 'sale',
                    'amount'        => $cashIn,
                    'balance_after' => $terminal->cash_balance,
                    'reason'        => $sale->reference,
                ]);
            }

            // Commission plateforme (modèle 'commission' ou 'both').
            $this->revenue->record(
                'pos_sale',
                $sale->id,
                'pos',
                (float) $total,
                $terminal->tenant_id,
                $terminal->currency,
            );

            return $sale;
        });
    }

    public function cashMovement(TaGtoaPosTerminal $terminal, string $type, float $amount, ?string $reason = null): TaGtoaPosCashMovement
    {
        return DB::transaction(function () use ($terminal, $type, $amount, $reason) {
            $delta = in_array($type, ['in', 'open'], true) ? $amount : -$amount;
            $terminal->increment('cash_balance', $delta);

            return $terminal->cashMovements()->create([
                'type'          => $type,
                'amount'        => $amount,
                'balance_after' => $terminal->fresh()->cash_balance,
                'reason'        => $reason,
            ]);
        });
    }
}
