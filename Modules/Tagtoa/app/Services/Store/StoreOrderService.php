<?php

namespace Modules\Tagtoa\App\Services\Store;

use Illuminate\Support\Facades\DB;
use Modules\Tagtoa\App\Models\Store\Order;
use Modules\Tagtoa\App\Models\Store\Store;
use Modules\Tagtoa\App\Services\Billing\RevenueService;
use Modules\Tagtoa\App\Services\Inventory\StockService;
use Modules\Tagtoa\App\Services\Notifications\NotificationService;
use Modules\Tagtoa\App\Support\Store\Cart;

/**
 * TAGTOA STORE — capture & gestion des commandes boutique.
 *
 * Prix imposé SERVEUR (catalogue). Idempotent via client_uuid. Stock décrémenté
 * sous transaction. Commission enregistrée au paiement.
 */
class StoreOrderService
{
    public function __construct(protected RevenueService $revenue)
    {
    }

    public function placeOrder(Store $store, array $payload): Order
    {
        $uuid = $payload['client_uuid'] ?? null;
        if ($uuid && $existing = Order::where('client_uuid', $uuid)->first()) {
            return $existing;
        }

        $order = DB::transaction(function () use ($store, $payload, $uuid) {
            // Catalogue autorisé : produits disponibles de CETTE boutique.
            $products = $store->products()->where('is_available', true)->get()->keyBy('id');
            $catalog = [];
            foreach ($products as $p) {
                $catalog[$p->id] = ['price' => (float) $p->price, 'name' => $p->name];
            }

            $cart = Cart::build($catalog, (array) ($payload['items'] ?? []));
            if (! $cart['lines']) {
                throw new \RuntimeException('empty_order');
            }

            // Stock : refuse si un produit suivi n'a pas assez de stock.
            foreach ($cart['lines'] as $l) {
                $p = $products->get($l['id']);
                if ($p && ! StockService::canFulfill($p->stock, $l['qty'])) {
                    throw new \RuntimeException('out_of_stock');
                }
            }

            $total = $cart['subtotal'];

            $order = $store->orders()->create([
                'tenant_id'        => $store->tenant_id,
                'reference'        => Order::generateReference(),
                'subtotal'         => $total,
                'total'            => $total,
                'currency'         => $store->currency ?: 'HTG',
                'status'           => 'pending',
                'payment_status'   => 'unpaid',
                'channel'          => in_array(($payload['channel'] ?? 'store'), ['store', 'whatsapp'], true) ? $payload['channel'] : 'store',
                'customer_name'    => $payload['customer_name'] ?? null,
                'customer_phone'   => $payload['customer_phone'] ?? null,
                'customer_address' => $payload['customer_address'] ?? null,
                'note'             => $payload['note'] ?? null,
                'client_uuid'      => $uuid,
                'placed_at'        => now(),
            ]);

            foreach ($cart['lines'] as $l) {
                $order->items()->create([
                    'product_id' => $l['id'],
                    'name'       => $l['name'],
                    'price'      => $l['price'],
                    'qty'        => $l['qty'],
                    'line_total' => $l['line_total'],
                ]);
                $p = $products->get($l['id']);
                if ($p && $p->stock !== null) {
                    $p->decrement('stock', $l['qty']);
                }
            }

            return $order;
        });

        $this->notifyMerchant($store, $order);

        return $order;
    }

    /** Marque payée + enregistre la commission plateforme (idempotent). */
    public function markPaid(Order $order): Order
    {
        if (! $order->isPaid()) {
            $order->update(['payment_status' => 'paid']);
            $this->revenue->record('store_order', $order->id, 'store', (float) $order->total, $order->tenant_id, $order->currency);
        }

        return $order;
    }

    /** WhatsApp au marchand à chaque nouvelle commande (no-op sans credentials). */
    protected function notifyMerchant(Store $store, Order $order): void
    {
        try {
            if (! $store->whatsapp) {
                return;
            }
            app(NotificationService::class)->push([
                'channels' => ['whatsapp'],
                'phone'    => $store->whatsapp,
                'subject'  => $store->name,
                'body'     => __('Nouvelle commande').' '.$order->reference
                    .' — '.number_format((float) $order->total, 2).' '.$order->currency
                    .($order->customer_name ? ' · '.$order->customer_name : ''),
            ]);
        } catch (\Throwable $e) {
            if (function_exists('report')) {
                report($e);
            }
        }
    }
}
