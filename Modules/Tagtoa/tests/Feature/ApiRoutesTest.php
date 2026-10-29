<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA API — la surface HTTP publique, par ses VRAIES routes
|--------------------------------------------------------------------------
| routes/api.php n'était pas chargé par le harnais : ApiPaymentFlowTest
| appelle le service, ApiAuthTest appelle le middleware, et personne ne
| vérifiait leur ASSEMBLAGE. Une route mal nommée, un middleware oublié
| dans le groupe, un verbe HTTP inversé : rien de tout cela n'échouait en
| test. C'est pourtant la frontière que des sites tiers appellent.
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Api\ApiKey;
use Modules\Tagtoa\App\Models\Api\ApiPayment;
use Modules\Tagtoa\App\Models\Pay\PaymentMethod;
use Modules\Tagtoa\App\Models\Pay\PaymentPage;
use Modules\Tagtoa\App\Support\Api\ApiToken;
use Modules\Tagtoa\Tests\TestCase;

class ApiRoutesTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $g = ApiToken::generate();
        ApiKey::create([
            'tenant_id' => null, 'name' => 'Intégration',
            'key_prefix' => $g['prefix'], 'key_hash' => $g['hash'],
        ]);
        $this->token = $g['token'];
    }

    private function merchantIsReady(): void
    {
        PaymentPage::create([
            'tenant_id' => null, 'alias' => 'boutique', 'title' => 'Boutique',
            'default_currency' => 'HTG', 'is_active' => true,
        ]);
        PaymentMethod::create([
            'payment_page_id' => app(\Modules\Tagtoa\App\Services\Pay\MerchantMethods::class)->library(null)->id,
            'tenant_id' => null, 'type' => 'moncash',
            'account_number' => '+509 3000 0000', 'requires_proof' => true, 'is_active' => true,
        ]);
    }

    private function auth(): array
    {
        return ['Authorization' => 'Bearer '.$this->token];
    }

    public function test_the_api_refuses_a_request_without_a_key(): void
    {
        // La frontière de sécurité, par la vraie route : si le middleware
        // tombait du groupe, l'API s'ouvrirait en grand sans rien casser
        // d'autre.
        $this->postJson('/api/v1/tagtoa/payments', ['amount' => 100])->assertStatus(401);
        $this->getJson('/api/v1/tagtoa/ping')->assertStatus(401);
    }

    public function test_ping_answers_with_a_valid_key(): void
    {
        $this->getJson('/api/v1/tagtoa/ping', $this->auth())
            ->assertOk()
            ->assertJson(['ok' => true, 'service' => 'tagtoa-pay']);
    }

    public function test_creating_a_payment_end_to_end_returns_201(): void
    {
        $this->merchantIsReady();

        $this->postJson('/api/v1/tagtoa/payments', [
            'amount' => 2500, 'currency' => 'HTG', 'external_id' => 'cmd-1',
        ], $this->auth())
            ->assertStatus(201)
            ->assertJson(['ok' => true]);

        $this->assertSame(1, ApiPayment::count());
    }

    public function test_an_unknown_currency_is_refused_through_the_real_route(): void
    {
        // Le correctif « devise contrainte au catalogue » vérifié là où il
        // compte vraiment : à la frontière HTTP. Avant, ceci renvoyait 201.
        $this->merchantIsReady();

        $this->postJson('/api/v1/tagtoa/payments', [
            'amount' => 100, 'currency' => 'ZZZ',
        ], $this->auth())
            ->assertStatus(422)
            ->assertJson(['error' => 'validation_failed']);

        $this->assertSame(0, ApiPayment::count());
    }

    public function test_the_same_external_id_is_idempotent_through_the_real_route(): void
    {
        $this->merchantIsReady();

        $a = $this->postJson('/api/v1/tagtoa/payments', ['amount' => 100, 'external_id' => 'cmd-2'], $this->auth());
        $b = $this->postJson('/api/v1/tagtoa/payments', ['amount' => 100, 'external_id' => 'cmd-2'], $this->auth());

        $this->assertSame(
            $a->json('payment.reference'),
            $b->json('payment.reference'),
            'Un retry du site tiers doit retrouver son paiement, jamais en créer un second.'
        );
        $this->assertSame(1, ApiPayment::count());
    }

    public function test_a_payment_is_readable_by_its_reference_and_hides_internal_ids(): void
    {
        $this->merchantIsReady();
        $reference = $this->postJson('/api/v1/tagtoa/payments', ['amount' => 100], $this->auth())
            ->json('payment.reference');

        $payload = $this->getJson('/api/v1/tagtoa/payments/'.$reference, $this->auth())
            ->assertOk()->json('payment');

        $this->assertArrayNotHasKey('id', $payload);
        $this->assertArrayNotHasKey('tenant_id', $payload);
        $this->assertArrayNotHasKey('api_key_id', $payload);
    }

    public function test_an_unknown_reference_is_a_404_not_a_500(): void
    {
        $this->getJson('/api/v1/tagtoa/payments/TAG-INEXISTANT', $this->auth())
            ->assertStatus(404)
            ->assertJson(['ok' => false, 'error' => 'not_found']);
    }

    public function test_a_merchant_without_a_configured_page_gets_an_actionable_409(): void
    {
        // Pas de page, pas de méthode : l'intégrateur doit comprendre que le
        // marchand n'a rien configuré, pas recevoir une erreur serveur.
        $this->postJson('/api/v1/tagtoa/payments', ['amount' => 100], $this->auth())
            ->assertStatus(409)
            ->assertJson(['ok' => false, 'error' => 'no_payment_page']);
    }
}
