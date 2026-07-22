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
            $this->applyPaid($txn);

            return true;
        }
        if ($status === 'failed') {
            $txn->update(['status' => PayTransaction::STATUS_FAILED]);
        }

        return false;
    }

    /**
     * Démarre le paiement d'un ABONNEMENT (forfait). Retourne l'URL de
     * redirection, ou null si le forfait est gratuit / passerelle indisponible.
     */
    public function startSubscription(?string $tenantId, string $plan, string $gateway = 'moncash'): ?string
    {
        $price = (float) (config('tagtoa.plans.'.$plan.'.price') ?? 0);
        if ($price <= 0 || ! GatewayManager::enabled($gateway)) {
            return null;
        }
        $driver = $this->driver($gateway);
        if (! $driver) {
            return null;
        }

        $txn = PayTransaction::create([
            'tenant_id'  => $tenantId,
            'gateway'    => $gateway,
            'reference'  => PayTransaction::generateReference(),
            'order_type' => 'subscription',
            'order_id'   => 0,
            'amount'     => $price,
            'currency'   => 'HTG',
            'status'     => PayTransaction::STATUS_PENDING,
            'meta'       => ['plan' => $plan, 'tenant_id' => $tenantId],
        ]);

        return $driver->createPayment($txn);
    }

    /** Charge la commande (amount/currency/tenant/isPaid) selon le type. */
    protected function loadOrder(string $type, int $id): ?object
    {
        $model = self::ORDERS[$type] ?? null;

        return $model ? $model::find($id) : null;
    }

    /** Applique le paiement (commande OU abonnement) — idempotent. */
    protected function applyPaid(PayTransaction $txn): void
    {
        if ($txn->order_type === 'subscription') {
            $plan = $txn->meta['plan'] ?? null;
            $tenantId = $txn->meta['tenant_id'] ?? $txn->tenant_id;
            if ($plan && array_key_exists($plan, (array) config('tagtoa.plans', []))) {
                \Modules\Tagtoa\App\Models\Billing\Subscription::updateOrCreate(
                    ['tenant_id' => $tenantId],
                    ['plan' => $plan, 'status' => 'active', 'started_at' => now(), 'expires_at' => now()->addMonth()]
                );
            }

            return;
        }

        $order = $this->loadOrder($txn->order_type, (int) $txn->order_id);
        if (! $order || $order->isPaid()) {
            return;
        }

        switch ($txn->order_type) {
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
