<?php

namespace Modules\Tagtoa\App\Services\Menu;

use Illuminate\Support\Facades\DB;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Menu\Order;
use Modules\Tagtoa\App\Services\Billing\RevenueService;
use Modules\Tagtoa\App\Services\Inventory\StockService;

/**
 * TAGTOA MENU — capture & gestion des commandes.
 *
 * Sécurité financière : le prix de CHAQUE ligne est imposé par le SERVEUR
 * (depuis la base, articles disponibles de CE menu) — jamais le prix envoyé
 * par le client (anti-tampering). Idempotent via client_uuid.
 */
class MenuOrderService
{
    public function __construct(protected RevenueService $revenue)
    {
    }

    public function placeOrder(Menu $menu, array $payload): Order
    {
        $uuid = $payload['client_uuid'] ?? null;
        if ($uuid && $existing = Order::where('client_uuid', $uuid)->first()) {
            return $existing;
        }

        $order = DB::transaction(function () use ($menu, $payload, $uuid) {
            // Catalogue autorisé : articles disponibles de ce menu, indexés par id.
            $catalog = $menu->items()->where('is_available', true)->get()->keyBy('id');

            $lines = [];
            $subtotal = 0.0;
            foreach (($payload['items'] ?? []) as $it) {
                $item = $catalog->get((int) ($it['id'] ?? 0));
                if (! $item) {
                    continue; // ignore tout article inconnu / indisponible
                }
                $qty = max(1, (int) ($it['qty'] ?? 1));

                // Stock : refuse la commande si un article suivi n'a pas assez de stock.
                if (! StockService::canFulfill($item->stock, $qty)) {
                    throw new \RuntimeException('out_of_stock');
                }

                $price = (float) $item->price;
                $subtotal += $price * $qty;
                $lines[] = ['item' => $item, 'price' => $price, 'qty' => $qty];
            }

            if (! $lines) {
                throw new \RuntimeException('empty_order');
            }

            $total = round($subtotal, 2);

            $order = $menu->orders()->create([
                'tenant_id'      => $menu->tenant_id,
                'reference'      => Order::generateReference(),
                'subtotal'       => $total,
                'total'          => $total,
                'currency'       => $menu->currency ?: 'HTG',
                'status'         => 'pending',
                'payment_status' => 'unpaid',
                'channel'        => in_array(($payload['channel'] ?? 'menu'), ['menu', 'whatsapp'], true) ? $payload['channel'] : 'menu',
                'customer_name'  => $payload['customer_name'] ?? null,
                'customer_phone' => $payload['customer_phone'] ?? null,
                'table_label'    => $payload['table_label'] ?? null,
                'note'           => $payload['note'] ?? null,
                'client_uuid'    => $uuid,
                'placed_at'      => now(),
            ]);

            foreach ($lines as $l) {
                $order->items()->create([
                    'item_id'    => $l['item']->id,
                    'name'       => $l['item']->name,
                    'price'      => $l['price'],
                    'qty'        => $l['qty'],
                    'line_total' => round($l['price'] * $l['qty'], 2),
                ]);

                // Décrémente le stock suivi (null = illimité, ignoré).
                if ($l['item']->stock !== null) {
                    $l['item']->decrement('stock', $l['qty']);
                }
            }

            return $order;
        });

        // Alerte marchand hors transaction (tolérant, opt-in) : nouvelle commande.
        $this->notifyMerchant($menu, $order);

        return $order;
    }

    /** WhatsApp au marchand à chaque nouvelle commande (no-op sans credentials). */
    protected function notifyMerchant(Menu $menu, Order $order): void
    {
        try {
            if (! $menu->whatsapp) {
                return;
            }
            app(\Modules\Tagtoa\App\Services\Notifications\NotificationService::class)->push([
                'channels' => ['whatsapp'],
                'phone'    => $menu->whatsapp,
                'subject'  => $menu->name,
                'body'     => __('Nouvelle commande').' '.$order->reference
                    .' — '.number_format((float) $order->total, 2).' '.$order->currency
                    .($order->table_label ? ' · '.__('Table').' '.$order->table_label : '')
                    .($order->customer_name ? ' · '.$order->customer_name : ''),
            ]);
        } catch (\Throwable $e) {
            if (function_exists('report')) {
                report($e);
            }
        }
    }

    /** Marque payée + enregistre la commission plateforme (idempotent). */
    public function markPaid(Order $order): Order
    {
        if (! $order->isPaid()) {
            $order->update(['payment_status' => 'paid']);
            $this->revenue->record('menu_order', $order->id, 'menu', (float) $order->total, $order->tenant_id, $order->currency);
        }

        return $order;
    }
}
