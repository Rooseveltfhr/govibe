<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA API développeur — parcours complet contre une vraie base :
| création d'un paiement par un site tiers, isolation entre marchands,
| idempotence, et passage en « payé ».
|--------------------------------------------------------------------------
*/

use Illuminate\Database\QueryException;
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

    /* ------------------------------------------------------------------
       La course critique sur external_id — un double-tap réseau lent côté
       intégrateur (voir MenuOrderTaxTest pour le même défaut sur client_uuid).
       ------------------------------------------------------------------ */

    public function test_a_racing_duplicate_external_id_is_recognized_and_handled_gracefully(): void
    {
        $key = $this->key();
        $page = $this->pageWithMethod();
        $svc = app(ApiPaymentService::class);

        $insert = new \ReflectionMethod($svc, 'insertPayment');
        $insert->setAccessible(true);

        // Le premier « thread » écrit directement, en contournant le
        // contrôle préalable — exactement ce qui se passe quand deux
        // requêtes le franchissent toutes les deux avant que l'une des deux
        // n'écrive.
        $premier = $insert->invoke($svc, $key, $page, ['amount' => 100], 'course-1');

        // Le second « thread » tente le MÊME external_id : la contrainte
        // unique en base doit refuser une deuxième ligne, jamais dupliquer
        // silencieusement le paiement.
        try {
            $insert->invoke($svc, $key, $page, ['amount' => 100], 'course-1');
            $this->fail('Une deuxième écriture avec le même external_id aurait dû être refusée par la base.');
        } catch (QueryException $e) {
            $reconnue = new \ReflectionMethod($svc, 'isDuplicateExternalId');
            $reconnue->setAccessible(true);
            $this->assertTrue($reconnue->invoke($svc, $e));
        }

        // Le chemin PUBLIC, lui, ne doit jamais laisser passer une erreur
        // 500 : il rend le paiement déjà écrit.
        $encore = $svc->create($key, ['amount' => 100, 'external_id' => 'course-1']);
        $this->assertSame($premier->id, $encore->id);
        $this->assertSame(1, ApiPayment::count());
    }

    /**
     * La vraie fenêtre de course : AUCUN paiement n'existe au moment où
     * create() vérifie — puis une autre requête commit AVANT que celle-ci
     * n'écrive. insertPayment() est substitué pour simuler précisément cet
     * ordre d'événements (impossible à provoquer de façon fiable avec de
     * vrais threads dans un test).
     */
    public function test_the_public_path_recovers_from_a_genuine_concurrent_commit_without_a_500(): void
    {
        $key = $this->key();
        $page = $this->pageWithMethod();

        $svc = \Mockery::mock(ApiPaymentService::class, [])
            ->makePartial()->shouldAllowMockingProtectedMethods();

        $svc->shouldReceive('insertPayment')->once()->andReturnUsing(function () use ($key, $page) {
            // L'AUTRE requête vient de committer, exactement entre le
            // contrôle initial de create() et cette tentative d'écriture.
            $existing = ApiPayment::create([
                'tenant_id' => $key->tenant_id, 'api_key_id' => $key->id,
                'payment_page_id' => $page->id, 'reference' => ApiPayment::generateReference(),
                'external_id' => 'course-2', 'amount' => 100, 'currency' => 'HTG',
                'status' => ApiPayment::STATUS_PENDING,
            ]);

            throw new QueryException(
                'sqlite', 'insert into "tagtoa_api_payments" ("external_id") values (?)', ['course-2'],
                new \Exception('UNIQUE constraint failed: tagtoa_api_payments.tenant_id, tagtoa_api_payments.external_id')
            );
        });

        $payment = $svc->create($key, ['amount' => 100, 'external_id' => 'course-2']);

        $this->assertSame('course-2', $payment->external_id);
        // Une seule ligne en base malgré la collision : le résultat de
        // l'AUTRE requête, jamais un deuxième paiement.
        $this->assertSame(1, ApiPayment::count());
    }

    /* ------------------------------------------------------------------
       Validation de la devise à la frontière HTTP. routes/api.php n'est pas
       chargé par le harnais (voir tests/TestCase.php) : on appelle donc le
       contrôleur directement, comme ApiAuthTest le fait pour le middleware.
       ------------------------------------------------------------------ */

    private function storeRequest(ApiKey $key, array $payload): \Illuminate\Http\JsonResponse
    {
        $request = \Illuminate\Http\Request::create('/api/v1/tagtoa/payments', 'POST', $payload);
        $request->attributes->set('tagtoa_api_key', $key);

        return app(\Modules\Tagtoa\App\Http\Controllers\Api\PaymentApiController::class)->store($request);
    }

    public function test_an_unknown_currency_is_refused_instead_of_creating_a_ghost_payment(): void
    {
        // Sans cette garde, POST {"currency":"ZZZ"} créait un paiement réel,
        // visible par l'intégrateur et inscrit au journal — mais qu'AUCUNE
        // passerelle ne peut encaisser (chaque driver vérifie supportsCurrency).
        $key = $this->key();
        $this->pageWithMethod();

        $response = $this->storeRequest($key, ['amount' => 100, 'currency' => 'ZZZ']);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('validation_failed', $response->getData(true)['error']);
        $this->assertSame(0, ApiPayment::count());
    }

    public function test_a_lowercase_currency_is_still_accepted(): void
    {
        // Le service faisait déjà strtoupper() : contraindre la devise au
        // catalogue ne doit pas rejeter « usd », qu'un intégrateur envoie
        // légitimement aujourd'hui.
        $key = $this->key();
        $this->pageWithMethod();

        $response = $this->storeRequest($key, ['amount' => 100, 'currency' => 'usd']);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('USD', ApiPayment::first()->currency);
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
