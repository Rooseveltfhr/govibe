<?php

namespace Modules\Tagtoa\App\Services\Event;

use Illuminate\Support\Facades\DB;
use Modules\Tagtoa\App\Models\Event\Event;
use Modules\Tagtoa\App\Models\Event\Order;
use Modules\Tagtoa\App\Models\Event\Ticket;
use Modules\Tagtoa\App\Models\Event\TicketType;

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

            return $order;
        });
    }

    public function markPaid(Order $order, ?string $method = null): Order
    {
        if (! $order->isPaid()) {
            $order->update(['status' => Order::STATUS_PAID, 'payment_method' => $method ?? $order->payment_method, 'paid_at' => now()]);
        }
        return $order;
    }
}
