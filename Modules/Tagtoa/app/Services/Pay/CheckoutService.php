<?php

namespace Modules\Tagtoa\App\Services\Pay;

use Modules\Tagtoa\App\Models\Pay\PayTransaction;
use Modules\Tagtoa\App\Services\Billing\RevenueService;
use Modules\Tagtoa\App\Support\Gateways\GatewayDriver;
use Modules\Tagtoa\App\Support\Gateways\MonCashDriver;
use Modules\Tagtoa\App\Support\GatewayManager;

/**
 * TAGTOA PAY — orchestration du paiement en ligne d'une commande.
 *
 * Types supportés : store | menu | event. Idempotent (une transaction PAYÉE
 * marque la commande une seule fois). Dormant si la passerelle n'a pas
 * d'identifiants (retourne null → repli sur le paiement manuel).
 */
class CheckoutService
{
    /** Modèle + service markPaid par type de commande. */
    protected const ORDERS = [
        'store' => \Modules\Tagtoa\App\Models\Store\Order::class,
        'menu'  => \Modules\Tagtoa\App\Models\Menu\Order::class,
        'event' => \Modules\Tagtoa\App\Models\Event\Order::class,
    ];

    /** Drivers disponibles. */
    protected function driver(string $gateway): ?GatewayDriver
    {
        return match ($gateway) {
            'moncash' => new MonCashDriver,
            default   => null,
        };
    }

    /**
     * Démarre un paiement pour une commande. Retourne l'URL de redirection, ou
     * null si indisponible (passerelle non configurée, commande introuvable/payée,
     * devise non supportée).
     */
    public function start(string $type, int $orderId, string $gateway = 'moncash'): ?string
    {
        $order = $this->loadOrder($type, $orderId);
        if (! $order || $order->isPaid() || ! GatewayManager::enabled($gateway)) {
            return null;
        }
        $driver = $this->driver($gateway);
        if (! $driver) {
            return null;
        }

        // Réutilise une transaction en attente pour cette commande + passerelle.
        $txn = PayTransaction::where('order_type', $type)->where('order_id', $orderId)
            ->where('gateway', $gateway)->where('status', PayTransaction::STATUS_PENDING)
            ->latest()->first();

        if (! $txn) {
            $txn = PayTransaction::create([
                'tenant_id'  => $order->tenant_id,
                'gateway'    => $gateway,
                'reference'  => PayTransaction::generateReference(),
                'order_type' => $type,
                'order_id'   => $orderId,
                'amount'     => (float) $order->total,
                'currency'   => $order->currency,
                'status'     => PayTransaction::STATUS_PENDING,
            ]);
        }

        return $driver->createPayment($txn);
    }

    /** Confirme une transaction par sa référence. Retourne true si payée. */
    public function confirm(string $reference): bool
    {
        $txn = PayTransaction::where('reference', $reference)->first();
        if (! $txn) {
            return false;
        }
        if ($txn->isPaid()) {
            return true;
        }

        $driver = $this->driver($txn->gateway);
        if (! $driver) {
            return false;
        }

        $status = $driver->verify($txn);
        if ($status === 'paid') {
            $txn->update(['status' => PayTransaction::STATUS_PAID, 'paid_at' => now()]);
            $this->markOrderPaid($txn->order_type, (int) $txn->order_id);

            return true;
        }
        if ($status === 'failed') {
            $txn->update(['status' => PayTransaction::STATUS_FAILED]);
        }

        return false;
    }

    /** Charge la commande (amount/currency/tenant/isPaid) selon le type. */
    protected function loadOrder(string $type, int $id): ?object
    {
        $model = self::ORDERS[$type] ?? null;

        return $model ? $model::find($id) : null;
    }

    /** Marque la commande sous-jacente payée + commission (idempotent). */
    protected function markOrderPaid(string $type, int $orderId): void
    {
        $order = $this->loadOrder($type, $orderId);
        if (! $order || $order->isPaid()) {
            return;
        }

        switch ($type) {
            case 'store':
                app(\Modules\Tagtoa\App\Services\Store\StoreOrderService::class)->markPaid($order);
                break;
            case 'menu':
                app(\Modules\Tagtoa\App\Services\Menu\MenuOrderService::class)->markPaid($order);
                break;
            case 'event':
                app(\Modules\Tagtoa\App\Services\Event\TicketService::class)->markPaid($order, 'moncash');
                app(RevenueService::class)->record('event_order', $order->id, 'event', (float) $order->total, $order->tenant_id, $order->currency);
                break;
        }
    }
}
