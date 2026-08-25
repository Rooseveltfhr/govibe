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

    /**
     * Instancie le driver AVEC le tenant concerné : si la passerelle est réglée
     * en mode « le marchand encaisse » et que ce marchand a branché ses propres
     * identifiants, le paiement part sur SON compte et non sur celui de TAGTOA.
     */
    protected function driver(string $gateway, ?string $tenantId = null): ?GatewayDriver
    {
        return match ($gateway) {
            'moncash'      => new MonCashDriver($tenantId),
            'paypal'       => new \Modules\Tagtoa\App\Support\Gateways\PayPalDriver($tenantId),
            'coinpayments' => new \Modules\Tagtoa\App\Support\Gateways\CoinPaymentsDriver($tenantId),
            'stripe'       => new \Modules\Tagtoa\App\Support\Gateways\StripeDriver($tenantId),
            default        => null,
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
        if (! $order || $order->isPaid() || ! GatewayManager::enabled($gateway, $order->tenant_id)) {
            return null;
        }
        $driver = $this->driver($gateway, $order->tenant_id);
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

        $driver = $this->driver($txn->gateway, $txn->tenant_id);
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
     * Démarre le paiement en ligne d'une PAGE DE PAIEMENT (lien de paiement
     * ouvert — le payeur saisit le montant). Retourne l'URL de redirection, ou
     * null si indisponible.
     */
    public function startPayPage(\Modules\Tagtoa\App\Models\Pay\PaymentPage $page, \Modules\Tagtoa\App\Models\Pay\PaymentMethod $method, string $gateway, float $amount, array $payer = []): ?string
    {
        if ($amount <= 0 || ! GatewayManager::enabled($gateway, $page->tenant_id)) {
            return null;
        }
        $driver = $this->driver($gateway, $page->tenant_id);
        if (! $driver) {
            return null;
        }

        $txn = PayTransaction::create([
            'tenant_id'  => $page->tenant_id,
            'gateway'    => $gateway,
            'reference'  => PayTransaction::generateReference(),
            'order_type' => 'pay_page',
            'order_id'   => $page->id,
            'amount'     => $amount,
            'currency'   => $page->default_currency ?: 'USD',
            'status'     => PayTransaction::STATUS_PENDING,
            'meta'       => [
                'method_id'   => $method->id,
                'method_type' => $method->type,
                'payer_name'  => $payer['name'] ?? null,
                'payer_phone' => $payer['phone'] ?? null,
            ],
        ]);

        return $driver->createPayment($txn);
    }

    /**
     * Démarre la RECHARGE EN LIGNE d'une carte TAGTOA (le titulaire recharge son
     * propre solde via une passerelle). Retourne l'URL de redirection, ou null.
     */
    public function startCardTopup(\Modules\Tagtoa\App\Models\Card\CardAccount $card, float $amount, string $gateway): ?string
    {
        if ($amount <= 0 || ! GatewayManager::enabled($gateway, $card->tenant_id) || ! $card->isSpendable()) {
            return null;
        }
        $driver = $this->driver($gateway, $card->tenant_id);
        if (! $driver) {
            return null;
        }

        $txn = PayTransaction::create([
            'tenant_id'  => $card->tenant_id,
            'gateway'    => $gateway,
            'reference'  => PayTransaction::generateReference(),
            'order_type' => 'card_topup',
            'order_id'   => $card->id,
            'amount'     => $amount,
            'currency'   => $card->currency,
            'status'     => PayTransaction::STATUS_PENDING,
            'meta'       => ['card_id' => $card->id, 'card_code' => $card->code],
        ]);

        return $driver->createPayment($txn);
    }

    /**
     * Démarre le paiement d'un ABONNEMENT (forfait). Retourne l'URL de
     * redirection, ou null si le forfait est gratuit / passerelle indisponible.
     */
    public function startSubscription(?string $tenantId, string $plan, string $gateway = 'moncash'): ?string
    {
        $price = (float) (\Modules\Tagtoa\App\Services\Billing\PlanService::effectivePlans()[$plan]['price'] ?? 0);
        // Un abonnement est payé À TAGTOA : on reste volontairement sur les
        // identifiants plateforme (aucun tenant passé au driver).
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
            if ($plan && array_key_exists($plan, \Modules\Tagtoa\App\Services\Billing\PlanService::effectivePlans())) {
                \Modules\Tagtoa\App\Models\Billing\Subscription::updateOrCreate(
                    ['tenant_id' => $tenantId],
                    ['plan' => $plan, 'status' => 'active', 'started_at' => now(), 'expires_at' => now()->addMonth()]
                );
            }

            return;
        }

        if ($txn->order_type === 'pay_page') {
            $this->applyPayPagePaid($txn);

            return;
        }

        if ($txn->order_type === 'card_topup') {
            $card = \Modules\Tagtoa\App\Models\Card\CardAccount::find((int) $txn->order_id);
            if ($card) {
                // Idempotent : la référence de la transaction verrouille la recharge.
                app(\Modules\Tagtoa\App\Services\Card\CardWalletService::class)->topUp(
                    $card,
                    (float) $txn->amount,
                    ['reference' => 'topup-'.$txn->reference, 'context_type' => 'online_topup', 'meta' => ['gateway' => $txn->gateway]]
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

    /**
     * Paiement en ligne d'une page de paiement confirmé : enregistre une preuve
     * APPROUVÉE (visible au tableau de bord) + la commission. Idempotent.
     */
    protected function applyPayPagePaid(PayTransaction $txn): void
    {
        $meta = (array) $txn->meta;

        \Modules\Tagtoa\App\Models\Pay\PaymentProof::firstOrCreate(
            ['reference' => $txn->reference, 'payment_page_id' => (int) $txn->order_id],
            [
                'payment_method_id' => $meta['method_id'] ?? null,
                'payer_name'        => ($meta['payer_name'] ?? '') ?: 'Paiement en ligne',
                'payer_phone'       => $meta['payer_phone'] ?? null,
                'amount'            => (float) $txn->amount,
                'currency'          => $txn->currency,
                'status'            => \Modules\Tagtoa\App\Models\Pay\PaymentProof::STATUS_APPROVED,
                'note'              => 'Payé en ligne ('.$txn->gateway.')',
                'reviewed_at'       => now(),
            ]
        );

        app(RevenueService::class)->record('pay_page', (int) $txn->id, 'pay', (float) $txn->amount, $txn->tenant_id, $txn->currency);
    }
}
