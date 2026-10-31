<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA PAY — CS-10 : un « payé » sans montant ni devise n'est pas une preuve
|--------------------------------------------------------------------------
| Avant : Stripe et PayPal acceptaient une réponse « payée » dont le montant était
| ABSENT (« pas d'info → on ne bloque pas ») ; aucune devise n'était comparée (100 USD
| encaissés pour 100 HTG attendus passaient) ; MonCash condamnait un paiement dont le
| coût manquait au lieu de le laisser en attente. Défense en profondeur (la réponse
| vient de l'API du fournisseur, pas de l'attaquant) — mais une porte ouverte « au cas où »
| est une porte ouverte.
|
| Décision D8 : un « payé » exige un montant ET une devise DITS par la passerelle ; absents =
| en attente (la prochaine vérification tranchera), insuffisants ou autre devise = échec.
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Pay\GatewayCredential;
use Modules\Tagtoa\App\Models\Pay\PayTransaction;
use Modules\Tagtoa\App\Support\GatewayManager;
use Modules\Tagtoa\App\Support\Gateways\MonCashDriver;
use Modules\Tagtoa\App\Support\Gateways\PayPalDriver;
use Modules\Tagtoa\App\Support\Gateways\StripeDriver;
use Modules\Tagtoa\Tests\TestCase;

/** Une réponse HTTP réduite à ce que les drivers lisent : json('a.b.c'). */
class FakeJsonResponse
{
    public function __construct(private array $data)
    {
    }

    public function json(?string $key = null, $default = null)
    {
        return data_get($this->data, $key, $default);
    }
}

class GatewayFailClosedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        GatewayManager::flush();
    }

    private function txn(float $amount = 25, string $currency = 'USD'): PayTransaction
    {
        return PayTransaction::create(['tenant_id' => 't-1', 'gateway' => 'stripe', 'reference' => PayTransaction::generateReference(),
            'order_type' => 'pay_page', 'order_id' => 1, 'amount' => $amount, 'currency' => $currency, 'status' => PayTransaction::STATUS_PENDING]);
    }

    private function verdict(object $driver, array $response, PayTransaction $txn): string
    {
        $m = new \ReflectionMethod($driver, 'amountVerdict');
        $m->setAccessible(true);

        return $m->invoke($driver, new FakeJsonResponse($response), $txn);
    }

    private function stripe(): StripeDriver
    {
        config(['tagtoa.gateways.stripe.credentials' => ['key' => 'pk_test_x', 'secret' => 'sk_test_x'], 'tagtoa.gateways.stripe.mode' => 'sandbox']);

        return new StripeDriver();
    }

    private function paypal(): PayPalDriver
    {
        config(['tagtoa.gateways.paypal.credentials' => ['client_id' => 'c', 'secret' => 's']]);

        return new PayPalDriver();
    }

    /* ---------- Stripe ---------- */

    public function test_stripe_amount_and_currency_that_cover_the_transaction_are_accepted(): void
    {
        $this->assertSame('ok', $this->verdict($this->stripe(), ['amount_total' => 2500, 'currency' => 'usd'], $this->txn(25, 'USD')));
        $this->assertSame('ok', $this->verdict($this->stripe(), ['amount_total' => 3000, 'currency' => 'usd'], $this->txn(25, 'USD')), 'Plus que prévu : accepté.');
    }

    public function test_stripe_response_without_an_amount_is_pending_not_paid(): void
    {
        $this->assertSame('pending', $this->verdict($this->stripe(), ['currency' => 'usd'], $this->txn()));
        $this->assertSame('pending', $this->verdict($this->stripe(), ['amount_total' => null, 'currency' => 'usd'], $this->txn()));
    }

    public function test_stripe_response_without_a_currency_is_pending(): void
    {
        $this->assertSame('pending', $this->verdict($this->stripe(), ['amount_total' => 2500], $this->txn()));
    }

    public function test_stripe_wrong_currency_fails_even_when_the_number_is_large_enough(): void
    {
        // 25 EUR encaissés pour 25 USD attendus : même nombre, autre monnaie.
        $this->assertSame('failed', $this->verdict($this->stripe(), ['amount_total' => 2500, 'currency' => 'eur'], $this->txn(25, 'USD')));
        // 100 USD cents encaissés pour 100 HTG attendus.
        $this->assertSame('failed', $this->verdict($this->stripe(), ['amount_total' => 10000, 'currency' => 'usd'], $this->txn(100, 'HTG')));
    }

    public function test_stripe_underpayment_fails(): void
    {
        $this->assertSame('failed', $this->verdict($this->stripe(), ['amount_total' => 1000, 'currency' => 'usd'], $this->txn(25, 'USD')));
    }

    /* ---------- PayPal ---------- */

    public function test_paypal_matching_amount_and_currency_are_accepted(): void
    {
        $r = ['purchase_units' => [['amount' => ['value' => '25.00', 'currency_code' => 'USD']]]];
        $this->assertSame('ok', $this->verdict($this->paypal(), $r, $this->txn(25, 'USD')));
    }

    public function test_paypal_response_without_amount_or_currency_is_pending(): void
    {
        $this->assertSame('pending', $this->verdict($this->paypal(), ['purchase_units' => [['amount' => ['currency_code' => 'USD']]]], $this->txn()));
        $this->assertSame('pending', $this->verdict($this->paypal(), ['purchase_units' => [['amount' => ['value' => '25.00']]]], $this->txn()));
        $this->assertSame('pending', $this->verdict($this->paypal(), [], $this->txn()));
    }

    public function test_paypal_wrong_currency_or_underpayment_fails(): void
    {
        $this->assertSame('failed', $this->verdict($this->paypal(), ['purchase_units' => [['amount' => ['value' => '25.00', 'currency_code' => 'EUR']]]], $this->txn(25, 'USD')));
        $this->assertSame('failed', $this->verdict($this->paypal(), ['purchase_units' => [['amount' => ['value' => '10.00', 'currency_code' => 'USD']]]], $this->txn(25, 'USD')));
    }

    /* ---------- MonCash ---------- */

    public function test_moncash_paid_with_a_sufficient_cost_is_paid(): void
    {
        $t = $this->txn(500, 'HTG');
        $this->assertSame('paid', MonCashDriver::judge(['message' => 'successful', 'cost' => 500], $t));
        $this->assertSame('paid', MonCashDriver::judge(['message' => 'successful', 'cost' => '750.00'], $t));
    }

    public function test_moncash_paid_without_a_cost_is_pending_not_failed_and_not_paid(): void
    {
        $t = $this->txn(500, 'HTG');
        $this->assertSame('pending', MonCashDriver::judge(['message' => 'successful'], $t));
        $this->assertSame('pending', MonCashDriver::judge(['message' => 'successful', 'cost' => null], $t));
        $this->assertSame('pending', MonCashDriver::judge(['message' => 'successful', 'cost' => 'abc'], $t));
    }

    public function test_moncash_underpayment_fails_and_unfinished_payments_are_never_judged_on_amount(): void
    {
        $t = $this->txn(500, 'HTG');
        $this->assertSame('failed', MonCashDriver::judge(['message' => 'successful', 'cost' => 100], $t));
        // Un paiement pas encore abouti (message « pending ») sans coût n'est PLUS condamné par erreur.
        $this->assertSame('pending', MonCashDriver::judge(['message' => 'pending'], $t));
        $this->assertSame('failed', MonCashDriver::judge(['message' => 'failed', 'cost' => 0], $t));
    }

    /* ---------- SEC-6 : le secret de webhook Stripe absent est signalé ---------- */

    public function test_a_stripe_ready_without_a_webhook_secret_is_flagged_to_the_super_admin_without_being_disabled(): void
    {
        config(['tagtoa.gateways.stripe.credentials' => ['key' => 'pk_test_x', 'secret' => 'sk_test_x'], 'tagtoa.gateways.stripe.mode' => 'sandbox',
            'tagtoa.gateways.stripe.webhook_secret' => null]);
        GatewayManager::flush();

        $this->assertNotNull(GatewayManager::advisory('stripe'));
        $this->assertNull(GatewayManager::warning('stripe'), 'Un simple rappel ne désactive pas Stripe.');
        $this->assertTrue(GatewayManager::enabled('stripe'));
    }

    public function test_no_advisory_when_the_webhook_secret_is_set_or_stripe_is_not_configured(): void
    {
        config(['tagtoa.gateways.stripe.credentials' => ['key' => 'pk_test_x', 'secret' => 'sk_test_x'], 'tagtoa.gateways.stripe.mode' => 'sandbox',
            'tagtoa.gateways.stripe.webhook_secret' => 'whsec_x']);
        GatewayManager::flush();
        $this->assertNull(GatewayManager::advisory('stripe'));

        config(['tagtoa.gateways.stripe.credentials' => ['key' => null, 'secret' => null], 'tagtoa.gateways.stripe.webhook_secret' => null]);
        GatewayManager::flush();
        $this->assertNull(GatewayManager::advisory('stripe'), 'Stripe non configuré : rien à signaler.');
        $this->assertNull(GatewayManager::advisory('paypal'));
    }
}
