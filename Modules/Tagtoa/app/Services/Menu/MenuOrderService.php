<?php

namespace Modules\Tagtoa\App\Services\Menu;

use Illuminate\Support\Facades\DB;
use Modules\Tagtoa\App\Models\Loyalty\Card;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Menu\Order;
use Modules\Tagtoa\App\Services\Billing\RevenueService;
use Modules\Tagtoa\App\Services\Inventory\StockService;
use Modules\Tagtoa\App\Services\Loyalty\LoyaltyCardService;
use Modules\Tagtoa\App\Services\Notifications\NotificationService;
use Modules\Tagtoa\App\Support\Menu\ItemOptionPricing;

/**
 * TAGTOA MENU — capture & gestion des commandes.
 *
 * Sécurité financière : le prix de CHAQUE ligne (article + options choisies)
 * est imposé par le SERVEUR (depuis la base, articles/options de CE menu) —
 * jamais le prix envoyé par le client (anti-tampering). Idempotent via client_uuid.
 */
class MenuOrderService
{
    public function __construct(
        protected RevenueService $revenue,
        protected NotificationService $notifications,
        protected LoyaltyCardService $loyalty,
    ) {
    }

    public function placeOrder(Menu $menu, array $payload): Order
    {
        $uuid = $payload['client_uuid'] ?? null;
        if ($uuid && $existing = Order::where('client_uuid', $uuid)->first()) {
            return $existing;
        }

        $order = DB::transaction(function () use ($menu, $payload, $uuid) {
            // Catalogue autorisé : articles disponibles de ce menu, indexés par id.
            $catalog = $menu->items()->where('is_available', true)->with('options.choices')->get()->keyBy('id');

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

                [$optionsExtra, $optionsSnapshot] = $this->resolveOptions($item, $it['options'] ?? []);

                $price = round((float) $item->price + $optionsExtra, 2);
                $subtotal += $price * $qty;
                $lines[] = ['item' => $item, 'price' => $price, 'qty' => $qty, 'options' => $optionsSnapshot];
            }

            if (! $lines) {
                throw new \RuntimeException('empty_order');
            }

            $subtotal = round($subtotal, 2);
            $tip = max(0, round((float) ($payload['tip'] ?? 0), 2));
            $total = round($subtotal + $tip, 2);
            $requestedType = $payload['order_type'] ?? 'dine_in';
            $orderType = in_array($requestedType, Order::ORDER_TYPES, true) ? $requestedType : 'dine_in';
            $requestedChannel = $payload['channel'] ?? 'menu';
            $channel = in_array($requestedChannel, ['menu', 'whatsapp'], true) ? $requestedChannel : 'menu';

            $order = $menu->orders()->create([
                'tenant_id'        => $menu->tenant_id,
                'reference'        => Order::generateReference(),
                'subtotal'         => $subtotal,
                'total'            => $total,
                'tip'              => $tip,
                'currency'         => $menu->currency ?: 'HTG',
                'status'           => 'pending',
                'payment_status'   => 'unpaid',
                'channel'          => $channel,
                'order_type'       => $orderType,
                'customer_name'    => $payload['customer_name'] ?? null,
                'customer_phone'   => $payload['customer_phone'] ?? null,
                'table_label'      => $orderType === 'dine_in' ? ($payload['table_label'] ?? null) : null,
                'delivery_address' => $orderType === 'delivery' ? ($payload['delivery_address'] ?? null) : null,
                'note'             => $payload['note'] ?? null,
                'client_uuid'      => $uuid,
                'placed_at'        => now(),
            ]);

            foreach ($lines as $l) {
                $order->items()->create([
                    'item_id'          => $l['item']->id,
                    'name'             => $l['item']->name,
                    'price'            => $l['price'],
                    'qty'              => $l['qty'],
                    'line_total'       => round($l['price'] * $l['qty'], 2),
                    'selected_options' => $l['options'] ?: null,
                ]);

                // Décrémente le stock suivi (null = illimité, ignoré).
                if ($l['item']->stock !== null) {
                    $l['item']->decrement('stock', $l['qty']);
                }
            }

            return $order;
        });

        // Notifications hors transaction (tolérant, opt-in) : nouvelle commande.
        $this->notifyMerchant($menu, $order);
        $this->notifyCustomer($menu, $order);

        return $order;
    }

    /**
     * Valide + calcule le supplément de prix des options choisies pour un item,
     * à partir du catalogue serveur (jamais du prix envoyé par le client).
     * Adapte les relations Eloquent en tableaux simples et délègue le calcul
     * (pur, testable sans Laravel) à ItemOptionPricing.
     */
    protected function resolveOptions($item, array $selected): array
    {
        $groups = $item->options->map(fn ($g) => [
            'id'       => $g->id,
            'name'     => $g->name,
            'required' => $g->required,
            'multiple' => $g->multiple,
            'choices'  => $g->choices->map(fn ($c) => [
                'id' => $c->id, 'label' => $c->label, 'price_delta' => (float) $c->price_delta,
            ])->all(),
        ])->all();

        return ItemOptionPricing::resolve($groups, $selected);
    }

    /** WhatsApp au marchand à chaque nouvelle commande (no-op sans credentials). */
    protected function notifyMerchant(Menu $menu, Order $order): void
    {
        try {
            if (! $menu->whatsapp) {
                return;
            }
            $this->notifications->push([
                'channels' => ['whatsapp'],
                'phone'    => $menu->whatsapp,
                'subject'  => $menu->name,
                'body'     => __('Nouvelle commande').' '.$order->reference
                    .' — '.number_format((float) $order->total, 2).' '.$order->currency
                    .' · '.$order->order_type_label
                    .($order->table_label ? ' · '.__('Table').' '.$order->table_label : '')
                    .($order->customer_name ? ' · '.$order->customer_name : ''),
            ]);
        } catch (\Throwable $e) {
            if (function_exists('report')) {
                report($e);
            }
        }
    }

    /** Confirmation WhatsApp automatique AU CLIENT (tolérant, opt-in, no-op sans numéro). */
    protected function notifyCustomer(Menu $menu, Order $order): void
    {
        try {
            if (! $order->customer_phone) {
                return;
            }
            $lines = [
                __('Bonjour').' '.($order->customer_name ?: '').',',
                '',
                __('Votre commande chez :n est bien reçue !', ['n' => $menu->name]),
                __('Référence').' : '.$order->reference,
                __('Total').' : '.number_format((float) $order->total, 2).' '.$order->currency,
                __('Statut').' : '.__($order->status_meta['label']),
                '',
                __('Suivre ma commande').' : '.route('tagtoa.menu.track', $order->reference),
                '',
                __('Propulsé par').' TAGTOA',
            ];
            $this->notifications->push([
                'channels' => ['whatsapp'],
                'phone'    => $order->customer_phone,
                'subject'  => $menu->name,
                'body'     => implode("\n", array_filter($lines, fn ($l) => $l !== null)),
            ]);
        } catch (\Throwable $e) {
            if (function_exists('report')) {
                report($e);
            }
        }
    }

    /** Marque payée + commission plateforme (hors pourboire) + points fidélité (idempotent). */
    public function markPaid(Order $order): Order
    {
        if (! $order->isPaid()) {
            $order->update(['payment_status' => 'paid']);
            $this->revenue->record('menu_order', $order->id, 'menu', (float) $order->subtotal, $order->tenant_id, $order->currency);
            $this->awardLoyaltyPoints($order);
        }

        return $order;
    }

    /**
     * Crédite des points fidélité si le téléphone client correspond à une carte
     * TAGTOA existante du même établissement — n'enrôle JAMAIS automatiquement
     * une nouvelle carte (portée volontairement conservatrice).
     */
    protected function awardLoyaltyPoints(Order $order): void
    {
        try {
            if (! $order->customer_phone) {
                return;
            }
            $menu = $order->menu;
            if (! $menu || ! $menu->vcard_id) {
                return;
            }
            $digits = preg_replace('/\D+/', '', (string) $order->customer_phone) ?? '';
            if (strlen($digits) < 4) {
                return;
            }
            $suffix = substr($digits, -8);

            $card = Card::whereHas('program', fn ($q) => $q->where('vcard_id', $menu->vcard_id)->where('is_active', true))
                ->with('program')
                ->get()
                ->first(fn ($c) => $c->cardholder_phone
                    && str_ends_with(preg_replace('/\D+/', '', $c->cardholder_phone) ?? '', $suffix));

            if (! $card || ! $card->isActive()) {
                return;
            }

            $points = $card->program->pointsForAmount((float) $order->subtotal);
            $this->loyalty->earnPoints($card, $points, [
                'reference' => $order->reference,
                'note'      => __('Commande MENU').' '.$order->reference,
            ]);
        } catch (\Throwable $e) {
            if (function_exists('report')) {
                report($e);
            }
        }
    }
}
