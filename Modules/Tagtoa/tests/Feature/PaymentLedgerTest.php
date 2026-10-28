<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA PAY — une transition de statut laisse toujours une trace
|--------------------------------------------------------------------------
| Signalé en audit : CheckoutService::confirm() et ApiPaymentService::
| markPaid() font un ->update(['status' => …]) direct sur la transaction —
| rien n'enregistrait QUAND ni DEPUIS QUEL état un paiement passait « payé »
| ou « échoué », ce que CLAUDE.md exige pour toute logique financière
| (« aucune écriture directe sur les soldes »). tagtoa_payment_ledger_entries
| ajoute cette trace, À CÔTÉ de la colonne status existante (qui reste la
| source rapide pour l'affichage) — jamais modifiée après coup par du code
| applicatif.
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Api\ApiKey;
use Modules\Tagtoa\App\Models\Api\ApiPayment;
use Modules\Tagtoa\App\Models\Pay\PaymentLedgerEntry;
use Modules\Tagtoa\App\Models\Pay\PaymentPage;
use Modules\Tagtoa\App\Models\Pay\PayTransaction;
use Modules\Tagtoa\App\Services\Api\ApiPaymentService;
use Modules\Tagtoa\App\Services\Pay\CheckoutService;
use Modules\Tagtoa\App\Support\Gateways\GatewayDriver;
use Modules\Tagtoa\Tests\TestCase;

class PaymentLedgerTest extends TestCase
{
    use RefreshDatabase;

    /** Un driver de test : « vérifie » toujours ce qu'on lui dit de répondre,
     *  sans jamais toucher le réseau ni exiger des identifiants passerelle. */
    private function faireDriver(string $reponse): GatewayDriver
    {
        return new class($reponse) implements GatewayDriver
        {
            public function __construct(private string $reponse) {}

            public function createPayment(PayTransaction $txn): ?string
            {
                return null;
            }

            public function verify(PayTransaction $txn): string
            {
                return $this->reponse;
            }
        };
    }

    /** Sous-classe anonyme : seul driver() change, tout le reste (y compris
     *  le constructeur et son PaymentLedger réel) est le code de production. */
    private function serviceAvecDriver(string $reponse): CheckoutService
    {
        $fake = $this->faireDriver($reponse);

        return new class($fake) extends CheckoutService
        {
            public function __construct(private GatewayDriver $fake)
            {
                parent::__construct();
            }

            protected function driver(string $gateway): ?GatewayDriver
            {
                return $this->fake;
            }
        };
    }

    /** order_type « subscription » sans plan dans meta : applyPaid() ne fait
     *  alors RIEN de plus — le test reste concentré sur le grand livre, pas
     *  sur les effets de bord propres à chaque type de commande. */
    private function transaction(string $tenant = 't-1'): PayTransaction
    {
        return PayTransaction::create([
            'tenant_id' => $tenant, 'gateway' => 'moncash', 'reference' => PayTransaction::generateReference(),
            'order_type' => 'subscription', 'order_id' => 0, 'amount' => 500, 'currency' => 'HTG',
            'status' => PayTransaction::STATUS_PENDING,
        ]);
    }

    public function test_confirming_a_payment_records_an_immutable_paid_ledger_entry(): void
    {
        $txn = $this->transaction();

        $this->serviceAvecDriver('paid')->confirm($txn->reference);

        $entree = PaymentLedgerEntry::where('payable_type', PayTransaction::class)
            ->where('payable_id', $txn->id)->sole();

        $this->assertSame(PayTransaction::STATUS_PENDING, $entree->from_status);
        $this->assertSame(PayTransaction::STATUS_PAID, $entree->to_status);
        $this->assertSame(500.0, (float) $entree->amount);
        $this->assertSame('HTG', $entree->currency);
        $this->assertSame('moncash', $entree->gateway);
        $this->assertSame($txn->reference, $entree->reference);
        $this->assertSame('t-1', $entree->tenant_id);
    }

    public function test_a_failed_verification_records_a_failed_ledger_entry(): void
    {
        $txn = $this->transaction();

        $this->serviceAvecDriver('failed')->confirm($txn->reference);

        $entree = PaymentLedgerEntry::where('payable_type', PayTransaction::class)
            ->where('payable_id', $txn->id)->sole();

        $this->assertSame(PayTransaction::STATUS_PENDING, $entree->from_status);
        $this->assertSame(PayTransaction::STATUS_FAILED, $entree->to_status);
    }

    public function test_confirming_an_already_paid_transaction_does_not_duplicate_the_entry(): void
    {
        $txn = $this->transaction();
        $svc = $this->serviceAvecDriver('paid');

        // Un webhook rejoué de la passerelle : confirm() est appelé deux fois.
        $svc->confirm($txn->reference);
        $svc->confirm($txn->reference);

        $this->assertSame(1, PaymentLedgerEntry::where('payable_type', PayTransaction::class)
            ->where('payable_id', $txn->id)->count());
    }

    public function test_a_pending_verification_records_nothing(): void
    {
        $txn = $this->transaction();

        $this->serviceAvecDriver('pending')->confirm($txn->reference);

        $this->assertSame(0, PaymentLedgerEntry::count());
    }

    /* ------------------------------------------------------------------
       ApiPayment — aucun driver passerelle impliqué, markPaid() est direct.
       ------------------------------------------------------------------ */

    public function test_marking_an_api_payment_paid_records_a_paid_ledger_entry(): void
    {
        $page = PaymentPage::create([
            'tenant_id' => 't-1', 'alias' => 'boutique-'.random_int(1000, 9999), 'title' => 'Boutique',
            'default_currency' => 'HTG', 'is_active' => true,
        ]);
        $key = ApiKey::create(['tenant_id' => 't-1', 'name' => 'Test', 'key_prefix' => 'tg_test', 'key_hash' => hash('sha256', 'x')]);
        $payment = ApiPayment::create([
            'tenant_id' => 't-1', 'api_key_id' => $key->id, 'payment_page_id' => $page->id,
            'reference' => ApiPayment::generateReference(), 'amount' => 85, 'currency' => 'USD',
            'status' => ApiPayment::STATUS_PENDING,
        ]);

        app(ApiPaymentService::class)->markPaid($payment);

        $entree = PaymentLedgerEntry::where('payable_type', ApiPayment::class)
            ->where('payable_id', $payment->id)->sole();

        $this->assertSame(ApiPayment::STATUS_PENDING, $entree->from_status);
        $this->assertSame(ApiPayment::STATUS_PAID, $entree->to_status);
        $this->assertSame(85.0, (float) $entree->amount);
        $this->assertSame('USD', $entree->currency);
        $this->assertNull($entree->gateway);
    }

    public function test_marking_an_already_paid_api_payment_again_does_not_duplicate_the_entry(): void
    {
        $page = PaymentPage::create([
            'tenant_id' => 't-1', 'alias' => 'boutique-'.random_int(1000, 9999), 'title' => 'Boutique',
            'default_currency' => 'HTG', 'is_active' => true,
        ]);
        $key = ApiKey::create(['tenant_id' => 't-1', 'name' => 'Test', 'key_prefix' => 'tg_test2', 'key_hash' => hash('sha256', 'y')]);
        $payment = ApiPayment::create([
            'tenant_id' => 't-1', 'api_key_id' => $key->id, 'payment_page_id' => $page->id,
            'reference' => ApiPayment::generateReference(), 'amount' => 85, 'currency' => 'USD',
            'status' => ApiPayment::STATUS_PENDING,
        ]);

        $svc = app(ApiPaymentService::class);
        $svc->markPaid($payment);
        $svc->markPaid($payment->fresh());

        $this->assertSame(1, PaymentLedgerEntry::where('payable_type', ApiPayment::class)
            ->where('payable_id', $payment->id)->count());
    }
}
