<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA API développeur — parcours complet contre une vraie base :
| création d'un paiement par un site tiers, isolation entre marchands,
| idempotence, et passage en « payé ».
|--------------------------------------------------------------------------
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Api\ApiKey;
use Modules\Tagtoa\App\Models\Api\ApiPayment;
use Modules\Tagtoa\App\Models\Pay\PaymentMethod;
use Modules\Tagtoa\App\Models\Pay\PaymentPage;
use Modules\Tagtoa\App\Services\Api\ApiPaymentService;
use Modules\Tagtoa\App\Support\Api\ApiToken;
use Modules\Tagtoa\Tests\TestCase;

class ApiPaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    private function key(string $tenant = 't1'): ApiKey
    {
        $g = ApiToken::generate();

        return ApiKey::create([
            'tenant_id'  => $tenant,
            'name'       => 'Test',
            'key_prefix' => $g['prefix'],
            'key_hash'   => $g['hash'],
        ]);
    }

    private function pageWithMethod(string $tenant = 't1', string $alias = 'boutique'): PaymentPage
    {
        $page = PaymentPage::create([
            'tenant_id' => $tenant, 'alias' => $alias, 'title' => 'Boutique',
            'default_currency' => 'HTG', 'is_active' => true,
        ]);
        // Les moyens appartiennent au MARCHAND (configurés une fois), pas au lien.
        PaymentMethod::create([
            'payment_page_id' => app(\Modules\Tagtoa\App\Services\Pay\MerchantMethods::class)->library($tenant)->id,
            'tenant_id'       => $tenant,
            'type'            => 'moncash',
            'account_number'  => '+509 3000 0000', 'requires_proof' => true, 'is_active' => true,
        ]);

        return $page;
    }

    public function test_creating_a_payment_returns_a_pending_payment_with_a_checkout_url(): void
    {
        $key = $this->key();
        $this->pageWithMethod();

        $p = app(ApiPaymentService::class)->create($key, [
            'amount' => 2500, 'currency' => 'HTG', 'external_id' => 'commande-1042',
            'description' => 'Commande #1042',
        ]);

        $this->assertSame(ApiPayment::STATUS_PENDING, $p->status);
        $this->assertSame('2500.00', $p->amount);
        $this->assertSame('t1', $p->tenant_id);
        $this->assertStringContainsString($p->reference, $p->checkout_url);
        $this->assertStringContainsString('/pay/i/', $p->checkout_url);
    }

    public function test_same_external_id_never_creates_a_second_payment(): void
    {
        // Un site tiers qui réessaie après un timeout ne doit pas facturer deux fois.
        $key = $this->key();
        $this->pageWithMethod();
        $svc = app(ApiPaymentService::class);

        $a = $svc->create($key, ['amount' => 100, 'external_id' => 'cmd-1']);
        $b = $svc->create($key, ['amount' => 100, 'external_id' => 'cmd-1']);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, ApiPayment::count());
    }

    public function test_a_merchant_without_an_active_page_gets_an_actionable_error(): void
    {
        $key = $this->key();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(ApiPaymentService::ERR_NO_PAGE);

        app(ApiPaymentService::class)->create($key, ['amount' => 100]);
    }

    public function test_a_page_without_any_active_method_is_refused(): void
    {
        $key = $this->key();
        PaymentPage::create([
            'tenant_id' => 't1', 'alias' => 'vide', 'default_currency' => 'HTG', 'is_active' => true,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(ApiPaymentService::ERR_NO_METHOD);

        app(ApiPaymentService::class)->create($key, ['amount' => 100]);
    }

    public function test_a_key_never_reaches_another_merchants_page(): void
    {
        // Isolation multi-tenant : la clé du tenant t2 ne doit jamais résoudre
        // la page de t1, même en demandant explicitement son alias.
        $this->pageWithMethod('t1', 'page-de-t1');
        $this->pageWithMethod('t2', 'page-de-t2');

        $page = app(ApiPaymentService::class)->resolvePage('t2', 'page-de-t1');

        $this->assertSame('page-de-t2', $page->alias);
        $this->assertSame('t2', $page->tenant_id);
    }

    public function test_marking_paid_is_idempotent(): void
    {
        $key = $this->key();
        $this->pageWithMethod();
        $svc = app(ApiPaymentService::class);

        $p = $svc->create($key, ['amount' => 500]);
        $svc->markPaid($p);
        $first = $p->fresh()->paid_at;

        $svc->markPaid($p->fresh());

        $this->assertSame(ApiPayment::STATUS_PAID, $p->fresh()->status);
        $this->assertEquals($first, $p->fresh()->paid_at); // pas réécrit
    }

    public function test_callback_is_skipped_when_no_valid_url_is_configured(): void
    {
        $key = $this->key();
        $this->pageWithMethod();
        $svc = app(ApiPaymentService::class);

        $p = $svc->create($key, ['amount' => 500]);

        $this->assertFalse($svc->notifyCallback($p)); // aucune URL
    }

    public function test_exposed_methods_never_leak_merchant_account_details(): void
    {
        $this->pageWithMethod();
        $page = PaymentPage::where('alias', 'boutique')->first();

        $methods = app(ApiPaymentService::class)->methodsFor($page);

        $this->assertCount(1, $methods);
        $this->assertSame('moncash', $methods[0]['type']);
        // Le numéro de compte du marchand ne doit pas sortir par l'API.
        $this->assertArrayNotHasKey('account_number', $methods[0]);
        $this->assertStringNotContainsString('3000 0000', json_encode($methods));
    }

    public function test_api_array_never_exposes_internal_ids(): void
    {
        $key = $this->key();
        $this->pageWithMethod();

        $payload = app(ApiPaymentService::class)->create($key, ['amount' => 100])->toApiArray();

        $this->assertArrayNotHasKey('id', $payload);
        $this->assertArrayNotHasKey('tenant_id', $payload);
        $this->assertArrayNotHasKey('api_key_id', $payload);
        $this->assertArrayHasKey('reference', $payload);
        $this->assertArrayHasKey('status', $payload);
    }
}
