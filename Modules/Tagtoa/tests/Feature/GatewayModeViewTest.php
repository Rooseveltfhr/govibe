<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA Pay — écran super-admin des passerelles : Stripe montre enfin son
| sélecteur Sandbox/Production, CoinPayments explique honnêtement qu'il n'en
| a pas, et un désaccord clé/environnement Stripe s'affiche en clair au lieu
| de rester une panne silencieuse.
|--------------------------------------------------------------------------
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Pay\GatewayCredential;
use Modules\Tagtoa\App\Support\GatewayManager;
use Modules\Tagtoa\Tests\TestCase;

class GatewayModeViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        GatewayManager::flush();
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 'tagtoa', 'name' => 'Fondateur']));
    }

    protected function tearDown(): void
    {
        GatewayManager::flush();
        parent::tearDown();
    }

    public function test_stripe_shows_an_environment_selector(): void
    {
        $response = $this->get(route('tagtoa.superadmin.gateways'));

        $response->assertOk();
        $response->assertSee(__('Environnement'));
    }

    public function test_coinpayments_shows_its_no_sandbox_note(): void
    {
        $response = $this->get(route('tagtoa.superadmin.gateways'));

        $response->assertOk();
        $response->assertSee('CoinPayments n\'offre aucun mode test');
    }

    public function test_a_stripe_key_mode_mismatch_shows_a_warning(): void
    {
        GatewayCredential::create([
            'driver' => 'stripe',
            'values' => ['mode' => 'live', 'credentials' => ['key' => 'pk_x', 'secret' => 'sk_test_factice']],
        ]);
        GatewayManager::flush();

        $response = $this->get(route('tagtoa.superadmin.gateways'));

        $response->assertOk();
        $response->assertSee(__('Stripe reste désactivé tant que ce n\'est pas corrigé.'));
    }

    public function test_a_matching_stripe_key_shows_no_warning(): void
    {
        GatewayCredential::create([
            'driver' => 'stripe',
            'values' => ['mode' => 'live', 'credentials' => ['key' => 'pk_x', 'secret' => 'sk_live_reelle']],
        ]);
        GatewayManager::flush();

        $response = $this->get(route('tagtoa.superadmin.gateways'));

        $response->assertOk();
        $response->assertDontSee('tant que ce n\'est pas corrigé');
    }
}
