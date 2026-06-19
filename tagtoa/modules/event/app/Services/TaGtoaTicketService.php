<?php

namespace App\Services;

use App\Models\TaGtoaEvOrder;
use App\Models\TaGtoaEvTicket;
use App\Models\TaGtoaEvTicketType;
use App\Models\TaGtoaEvent;
use Illuminate\Support\Facades\DB;

/**
 * TAGTOA EVENT — création de commandes + émission des billets.
 */
class TaGtoaTicketService
{
    /**
     * Crée une commande et émet les billets correspondants.
     *
     * @param array $lines  [ ['ticket_type_id'=>int, 'qty'=>int], ... ]
     * @param array $buyer  ['name'=>, 'phone'=>, 'email'=>]
     */
    public function createOrder(TaGtoaEvent $event, array $lines, array $buyer): TaGtoaEvOrder
    {
        return DB::transaction(function () use ($event, $lines, $buyer) {
            $total   = 0;
            $toIssue = [];

            foreach ($lines as $line) {
                $qty = max(0, (int) ($line['qty'] ?? 0));
                if ($qty === 0) {
                    continue;
                }

                /** @var TaGtoaEvTicketType $type */
                $type = TaGtoaEvTicketType::where('event_id', $event->id)
                    ->lockForUpdate()
                    ->findOrFail($line['ticket_type_id']);

                if (! $type->isOnSale()) {
                    throw new \RuntimeException(__('Billet « :name » non disponible.', ['name' => $type->name]));
                }
                if ($type->remaining !== null && $qty > $type->remaining) {
                    throw new \RuntimeException(__('Stock insuffisant pour « :name ».', ['name' => $type->name]));
                }

                $total      += (float) $type->price * $qty;
                $type->sold += $qty;
                $type->save();

                $toIssue[] = [$type, $qty];
            }

            if (empty($toIssue)) {
                throw new \RuntimeException(__('Aucun billet sélectionné.'));
            }

            $order = $event->orders()->create([
                'reference'      => TaGtoaEvOrder::generateReference(),
                'buyer_name'     => $buyer['name'],
                'buyer_phone'    => $buyer['phone'] ?? null,
                'buyer_email'    => $buyer['email'] ?? null,
                'total'          => $total,
                'currency'       => $event->currency,
                'status'         => $event->is_free ? TaGtoaEvOrder::STATUS_PAID : TaGtoaEvOrder::STATUS_PENDING,
                'paid_at'        => $event->is_free ? now() : null,
            ]);

            foreach ($toIssue as [$type, $qty]) {
                for ($i = 0; $i < $qty; $i++) {
                    TaGtoaEvTicket::create([
                        'event_id'       => $event->id,
                        'order_id'       => $order->id,
                        'ticket_type_id' => $type->id,
                        'code'           => TaGtoaEvTicket::generateCode(),
                        'holder_name'    => $buyer['name'],
                        'holder_phone'   => $buyer['phone'] ?? null,
                        'status'         => TaGtoaEvTicket::STATUS_VALID,
                    ]);
                }
            }

            return $order;
        });
    }

    /** Marque une commande payée (idempotent). */
    public function markPaid(TaGtoaEvOrder $order, ?string $method = null): TaGtoaEvOrder
    {
        if (! $order->isPaid()) {
            $order->update([
                'status'         => TaGtoaEvOrder::STATUS_PAID,
                'payment_method' => $method ?? $order->payment_method,
                'paid_at'        => now(),
            ]);
        }
        return $order;
    }
}
