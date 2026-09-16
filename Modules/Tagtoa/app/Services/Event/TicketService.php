<?php

namespace Modules\Tagtoa\App\Services\Event;

use Illuminate\Support\Facades\DB;
use Modules\Tagtoa\App\Models\Event\Event;
use Modules\Tagtoa\App\Models\Event\Order;
use Modules\Tagtoa\App\Models\Event\Ticket;
use Modules\Tagtoa\App\Models\Event\TicketType;
use Modules\Tagtoa\App\Services\Order\OrderSpine;
use Modules\Tagtoa\App\Support\Order\Channel;
use Modules\Tagtoa\App\Support\Order\OrderStatus;

/**
 * TAGTOA Event — création de commandes + émission des billets.
 */
class TicketService
{
    public function createOrder(Event $event, array $lines, array $buyer): Order
    {
        return DB::transaction(function () use ($event, $lines, $buyer) {
            $total = 0; $toIssue = [];

            foreach ($lines as $line) {
                $qty = max(0, (int) ($line['qty'] ?? 0));
                if ($qty === 0) {
                    continue;
                }
                $type = TicketType::where('event_id', $event->id)->lockForUpdate()->findOrFail($line['ticket_type_id']);
                if (! $type->isOnSale()) {
                    throw new \RuntimeException(__('Billet « :n » non disponible.', ['n' => $type->name]));
                }
                if ($type->remaining !== null && $qty > $type->remaining) {
                    throw new \RuntimeException(__('Stock insuffisant pour « :n ».', ['n' => $type->name]));
                }
                $total += (float) $type->price * $qty;
                $type->sold += $qty;
                $type->save();
                $toIssue[] = [$type, $qty];
            }

            if (empty($toIssue)) {
                throw new \RuntimeException(__('Aucun billet sélectionné.'));
            }

            $order = $event->orders()->create([
                'reference'   => Order::generateReference(),
                'buyer_name'  => $buyer['name'],
                'buyer_phone' => $buyer['phone'] ?? null,
                'buyer_email' => $buyer['email'] ?? null,
                'total'       => $total,
                'currency'    => $event->currency,
                'status'      => $event->is_free ? Order::STATUS_PAID : Order::STATUS_PENDING,
                'paid_at'     => $event->is_free ? now() : null,
            ]);

            foreach ($toIssue as [$type, $qty]) {
                for ($i = 0; $i < $qty; $i++) {
                    Ticket::create([
                        'event_id' => $event->id, 'order_id' => $order->id, 'ticket_type_id' => $type->id,
                        'code' => Ticket::generateCode(), 'holder_name' => $buyer['name'],
                        'holder_phone' => $buyer['phone'] ?? null, 'status' => Ticket::STATUS_VALID,
                    ]);
                }
            }

            // Colonne vertébrale, dans la MÊME transaction : une billetterie
            // qui compte à part obligerait le marchand à additionner deux
            // écrans pour connaître sa journée.
            $this->inscrire($event, $order);

            return $order;
        });
    }

    /**
     * Billet OFFERT par l'organisateur à un invité (VIP, presse, staff…) —
     * jamais passé par le panier public, jamais compté dans le chiffre
     * d'affaires. Respecte le même stock que la vente normale : un carton de
     * 20 places VIP offertes en trop resterait un carton trop plein le soir
     * de l'événement, quel que soit le prix affiché sur le billet.
     */
    public function inviteGuest(Event $event, TicketType $type, array $guest): Order
    {
        return DB::transaction(function () use ($event, $type, $guest) {
            $type = TicketType::where('event_id', $event->id)->lockForUpdate()->findOrFail($type->id);
            if ($type->remaining !== null && $type->remaining < 1) {
                throw new \RuntimeException(__('Plus de place disponible pour « :n ».', ['n' => $type->name]));
            }
            $type->sold += 1;
            $type->save();

            $order = $event->orders()->create([
                'reference'   => Order::generateReference(),
                'buyer_name'  => $guest['name'],
                'buyer_phone' => $guest['phone'] ?? null,
                'buyer_email' => $guest['email'] ?? null,
                'total'       => 0,
                'currency'    => $event->currency,
                'status'      => Order::STATUS_PAID,
                'paid_at'     => now(),
                'source'      => Order::SOURCE_INVITE,
            ]);

            Ticket::create([
                'event_id' => $event->id, 'order_id' => $order->id, 'ticket_type_id' => $type->id,
                'code' => Ticket::generateCode(), 'holder_name' => $guest['name'],
                'holder_phone' => $guest['phone'] ?? null, 'status' => Ticket::STATUS_VALID,
            ]);

            $this->inscrire($event, $order);

            return $order;
        });
    }

    public function markPaid(Order $order, ?string $method = null): Order
    {
        if (! $order->isPaid()) {
            $order->update(['status' => Order::STATUS_PAID, 'payment_method' => $method ?? $order->payment_method, 'paid_at' => now()]);

            app(OrderSpine::class)->touch('event_order', $order->id,
                OrderStatus::fromEvent(Order::STATUS_PAID), OrderStatus::PAID);
        }
        return $order;
    }

    /**
     * Inscrit une commande de billetterie sur la colonne vertébrale.
     *
     * La billetterie compte ses statuts en entiers, parce qu'elle est
     * antérieure à cette table. On les TRADUIT ici plutôt que de réécrire un
     * module qui fonctionne — un test vérifie qu'aucun entier ne se perd en
     * chemin.
     */
    private function inscrire($event, Order $order): void
    {
        $paye = $order->status === Order::STATUS_PAID;

        app(OrderSpine::class)->record([
            'tenant_id'      => $event->tenant_id,
            'channel'        => Channel::EVENT,
            'source_type'    => 'event_order',
            'source_id'      => $order->id,
            'reference'      => $order->reference,
            'subtotal'       => (float) $order->total,
            'total'          => (float) $order->total,
            'currency'       => $order->currency,
            'status'         => OrderStatus::fromEvent((int) $order->status),
            'payment_status' => $paye ? OrderStatus::PAID : OrderStatus::UNPAID,
            'customer_id'    => app(OrderSpine::class)
                ->customerFor($event->tenant_id, $order->buyer_name, $order->buyer_phone)?->id,
            'customer_name'  => $order->buyer_name,
            'customer_phone' => $order->buyer_phone,
            'placed_at'      => $order->created_at ?? now(),
        ]);
    }
}
