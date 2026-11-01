<?php

namespace Modules\Tagtoa\App\Services\Pay;

use Illuminate\Support\Facades\DB;
use Modules\Tagtoa\App\Models\Pay\PayTransaction;
use Modules\Tagtoa\App\Services\Billing\RevenueService;
use Modules\Tagtoa\App\Services\Notifications\NotificationService;
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
    public function __construct(
        protected NotificationService $notifications = new NotificationService(),
        protected PaymentLedger $ledger = new PaymentLedger(),
    ) {
    }

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
            'moncash'      => new MonCashDriver,
            'paypal'       => new \Modules\Tagtoa\App\Support\Gateways\PayPalDriver,
            'coinpayments' => new \Modules\Tagtoa\App\Support\Gateways\CoinPaymentsDriver,
            'stripe'       => new \Modules\Tagtoa\App\Support\Gateways\StripeDriver,
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
        if (! $order || $order->isPaid() || ! GatewayManager::enabled($gateway)) {
            return null;
        }
        // Commande de billetterie annulée : on ne démarre pas un paiement qui la réactiverait.
        if ($order instanceof \Modules\Tagtoa\App\Models\Event\Order && $order->isCancelled()) {
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

    /**
     * Confirme une transaction par sa référence. Retourne true si payée.
     *
     * Deux défauts d'avant, tous deux sur de l'argent réel :
     *
     *  1. PAS ATOMIQUE. La transaction passait « payée », puis le grand livre,
     *     puis la commande/l'abonnement/la recharge, chacun sa propre écriture.
     *     Une panne au milieu (base qui lâche, exception dans applyPaid) laissait
     *     la transaction « payée » et la commande NON payée — et comme la
     *     transaction est déjà payée, plus aucun retour ni webhook ne
     *     réessayait. Le client avait payé, le marchand ne le voyait jamais.
     *
     *  2. COURSE. `isPaid()` se lisait sur une copie périmée, hors verrou : le
     *     retour du client et le webhook de la passerelle arrivent presque
     *     ensemble, et les deux appliquaient le paiement (commission, points
     *     fidélité, notification en double).
     *
     * Maintenant : l'appel à la passerelle (réseau, lent) reste HORS de toute
     * transaction ; ensuite, sous verrou sur la ligne, on relit l'état réel et
     * on applique tout d'un bloc ou rien. Un échec annule le tout — la
     * transaction reste « en attente » et le prochain retour/webhook réessaie.
     */
    public function confirm(string $reference): bool
    {
        try {
            return $this->confirmOrFail($reference);
        } catch (\Throwable $e) {
            // Annulé en bloc : la transaction est restée « en attente ». On le
            // signale (supervision) sans faire échouer le retour du client.
            // Le WEBHOOK, lui, passe par confirmOrFail() : il doit répondre 500
            // pour que la passerelle rejoue — voir CheckoutController::webhook().
            report($e);

            return false;
        }
    }

    /**
     * Comme confirm(), mais l'échec d'application remonte. Pour le webhook : un
     * 200 dirait à la passerelle « bien reçu » alors que le paiement est
     * revenu en attente — elle ne rejouerait jamais, et si le client ne
     * revient pas sur le site, la commande resterait impayée pour toujours.
     */
    public function confirmOrFail(string $reference): bool
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
            return $this->settle($txn, PayTransaction::STATUS_PAID);
        }
        if ($status === 'failed') {
            $this->settle($txn, PayTransaction::STATUS_FAILED);
        }

        return false;
    }

    /**
     * Passage du FILET (tagtoa:pay:reconcile). Comme confirmOrFail(), avec deux
     * différences voulues :
     *
     *  - JAMAIS d'écriture « échouée ». Le filet est un rattrapage de paiements
     *    RÉUSSIS : un verdict « failed » (paiement partiel, ancienne session d'un
     *    paiement réessayé, lecture périmée) laissé définitif ferait perdre à
     *    jamais un paiement que le webhook ou le retour du client aurait encore
     *    pu régler. Le verdict est rendu à l'appelant, rien n'est écrit.
     *  - Une commande de billetterie ANNULÉE est ignorée AVANT d'interroger la
     *    passerelle : verify() peut capturer un paiement PayPal approuvé, et
     *    markPaid() réactiverait une commande dont les places ont été libérées.
     *
     * @return string 'paid' | 'pending' (rien à faire) | 'failed' (verdict seulement) | 'skipped'
     */
    public function reconcile(string $reference): string
    {
        $txn = PayTransaction::allTenants()->where('reference', $reference)->first();
        if (! $txn) {
            return 'pending';
        }
        if ($txn->isPaid()) {
            return 'paid';
        }
        if ($txn->order_type === 'event') {
            $order = $this->loadOrder('event', (int) $txn->order_id);
            if ($order && $order->isCancelled()) {
                return 'skipped';
            }
        }

        $driver = $this->driver($txn->gateway);
        if (! $driver) {
            return 'pending';
        }

        $status = $driver->verify($txn);
        if ($status === 'paid') {
            return $this->settle($txn, PayTransaction::STATUS_PAID) ? 'paid' : 'pending';
        }

        return $status === 'failed' ? 'failed' : 'pending';
    }

    /**
     * Fait passer une transaction de « en attente » à `$to`, et applique ses
     * effets, dans UNE transaction sous verrou. Retourne true seulement pour
     * « payée » (par nous ou par une requête concurrente).
     *
     * Jamais l'inverse de ce qu'une autre requête a déjà fait : une transaction
     * déjà payée n'est ni rejouée, ni repassée « échouée ».
     */
    protected function settle(PayTransaction $txn, string $to): bool
    {
        return DB::transaction(function () use ($txn, $to) {
            $locked = PayTransaction::whereKey($txn->getKey())->lockForUpdate()->first();
            if (! $locked) {
                return false;
            }
            if ($locked->isPaid()) {
                return true; // une autre requête a gagné : elle a tout appliqué
            }
            if ($to === PayTransaction::STATUS_FAILED && $locked->status !== PayTransaction::STATUS_PENDING) {
                return false; // déjà échouée : rien à refaire
            }

            $avant = $locked->status;
            if ($to === PayTransaction::STATUS_PAID) {
                $locked->update(['status' => $to, 'paid_at' => now()]);
            } else {
                $locked->update(['status' => $to]);
            }
            $this->ledger->record($locked, $avant, $to);

            if ($to === PayTransaction::STATUS_PAID) {
                $this->applyPaid($locked);
            }

            return $to === PayTransaction::STATUS_PAID;
        });
    }

    /**
     * Démarre le paiement en ligne d'une PAGE DE PAIEMENT (lien de paiement
     * ouvert — le payeur saisit le montant). Retourne l'URL de redirection, ou
     * null si indisponible.
     */
    public function startPayPage(\Modules\Tagtoa\App\Models\Pay\PaymentPage $page, \Modules\Tagtoa\App\Models\Pay\PaymentMethod $method, string $gateway, float $amount, array $payer = []): ?string
    {
        if ($amount <= 0 || ! GatewayManager::enabled($gateway)) {
            return null;
        }
        $driver = $this->driver($gateway);
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
        if ($amount <= 0 || ! GatewayManager::enabled($gateway) || ! $card->isSpendable()) {
            return null;
        }
        $driver = $this->driver($gateway);
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
                app(\Modules\Tagtoa\App\Services\Menu\MenuOrderService::class)->markPaid($order, (string) $txn->gateway);
                break;
            case 'event':
                app(\Modules\Tagtoa\App\Services\Event\TicketService::class)->markPaid($order, (string) $txn->gateway);
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

        $proof = \Modules\Tagtoa\App\Models\Pay\PaymentProof::firstOrCreate(
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

        // APRÈS l'écriture, jamais dedans — et seulement si cette preuve vient
        // de naître : un webhook rejoué (même référence) ne doit pas notifier
        // deux fois le même paiement.
        if ($proof->wasRecentlyCreated) {
            // Jamais `with('vcard')` ici : Vcard appartient à l'hôte Biztap, absent
            // de certains environnements (voir DashboardController::vcards()) —
            // notifyPaymentReceived() accède la relation en LAZY, dans son propre
            // bloc tolérant, exactement pour survivre à son absence.
            $page = \Modules\Tagtoa\App\Models\Pay\PaymentPage::find((int) $txn->order_id);
            if ($page) {
                $this->notifications->notifyPaymentReceived($page, $proof);
            }
        }
    }
}
