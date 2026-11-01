<?php

namespace Modules\Tagtoa\App\Services\Event;

use Illuminate\Support\Facades\DB;
use Modules\Tagtoa\App\Exceptions\TicketRuleException;
use Modules\Tagtoa\App\Models\Event\Event;
use Modules\Tagtoa\App\Models\Event\Order;
use Modules\Tagtoa\App\Models\Event\Ticket;
use Modules\Tagtoa\App\Models\Event\TicketType;
use Modules\Tagtoa\App\Services\Currency\CurrencyRateService;
use Modules\Tagtoa\App\Services\Order\OrderSpine;
use Modules\Tagtoa\App\Support\Order\Channel;
use Modules\Tagtoa\App\Support\Order\OrderStatus;

/**
 * TAGTOA Event — création de commandes + émission des billets.
 */
class TicketService
{
    /** Bornes des colonnes decimal(12,2). */
    public const PRIX_MAX = 99999999.0;
    public const TOTAL_MAX = 9999999999.0;

    /**
     * CUR-4 : prix d'un type de billet dans la devise de l'événement, calculé côté SERVEUR avec les
     * taux ACTIFS du commerce. null = pas de taux actif (ou prix converti nul/hors bornes) : on refuse,
     * jamais « 5 $ vendus 5 G ». Renvoie aussi l'origine à figer sur le billet.
     *
     * @return array{price: float, origin: array}|null
     */
    public function pricing(TicketType $type, Event $event, array $taux): ?array
    {
        $base = strtoupper($event->currency ?: 'HTG');
        $raw = (float) $type->price;
        if ($raw <= 0) {
            return ['price' => 0.0, 'origin' => []];
        }
        $prix = CurrencyRateService::convert($raw, $type->currency, $base, $taux);
        if ($prix === null || ($raw > 0 && $prix <= 0) || $prix > self::PRIX_MAX) {
            return null;
        }
        $devise = strtoupper((string) $type->currency);
        $origin = [];
        if ($devise !== '' && $devise !== $base) {
            $origin = [
                'currency_original'   => $devise,
                'unit_price_original' => round($raw, 2),
                'rate_used'           => CurrencyRateService::rateOf($devise, $base, $taux),
            ];
        }

        return ['price' => (float) $prix, 'origin' => $origin];
    }

    /** Taux actifs du commerce de l'événement vers sa devise. */
    public function ratesFor(Event $event): array
    {
        return app(CurrencyRateService::class)->activeFor($event->tenant_id, strtoupper($event->currency ?: 'HTG'));
    }

    public function createOrder(Event $event, array $lines, array $buyer): Order
    {
        return DB::transaction(function () use ($event, $lines, $buyer) {
            $total = 0; $toIssue = [];
            $taux = $this->ratesFor($event);

            foreach ($lines as $line) {
                $qty = max(0, (int) ($line['qty'] ?? 0));
                if ($qty === 0) {
                    continue;
                }
                $type = TicketType::where('event_id', $event->id)->lockForUpdate()->findOrFail($line['ticket_type_id']);
                if (! $type->isOnSale()) {
                    throw new TicketRuleException(__('Billet « :n » non disponible.', ['n' => $type->name]));
                }
                if ($type->remaining !== null && $qty > $type->remaining) {
                    throw new TicketRuleException(__('Stock insuffisant pour « :n ».', ['n' => $type->name]));
                }
                $prix = $this->pricing($type, $event, $taux);
                if ($prix === null && $event->is_free) {
                    $prix = ['price' => 0.0, 'origin' => []];   // événement gratuit : aucun taux nécessaire
                }
                if ($prix === null) {
                    throw new TicketRuleException(__('Le prix de « :n » n\'est plus disponible dans la devise de l\'événement. Rechargez la page.', ['n' => $type->name]));
                }
                $total += round($prix['price'] * $qty, 2);
                $type->sold += $qty;
                $type->save();
                $toIssue[] = [$type, $qty, $prix['origin']];
            }

            if (empty($toIssue)) {
                throw new TicketRuleException(__('Aucun billet sélectionné.'));
            }

            if ($total > self::TOTAL_MAX) {
                throw new TicketRuleException(__('Le montant de la commande est trop élevé.'));
            }
            // Prix affiché au chargement de la page : si le marchand a changé un taux entre-temps,
            // le client voit le NOUVEAU total avant de payer (la transaction est annulée).
            if (isset($buyer['expected_total']) && is_numeric($buyer['expected_total'])
                && abs((float) $buyer['expected_total'] - $total) > 0.01) {
                throw new TicketRuleException(__('Les prix ont changé. Le nouveau total est :t :c. Vérifiez puis confirmez de nouveau.', ['t' => number_format($total, 2), 'c' => $event->currency]));
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

            foreach ($toIssue as [$type, $qty, $origin]) {
                for ($i = 0; $i < $qty; $i++) {
                    Ticket::create([
                        'event_id' => $event->id, 'order_id' => $order->id, 'ticket_type_id' => $type->id,
                        'code' => Ticket::generateCode(), 'holder_name' => $buyer['name'],
                        'holder_phone' => $buyer['phone'] ?? null, 'status' => Ticket::STATUS_VALID,
                    ] + $origin);
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
                throw new TicketRuleException(__('Plus de place disponible pour « :n ».', ['n' => $type->name]));
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
