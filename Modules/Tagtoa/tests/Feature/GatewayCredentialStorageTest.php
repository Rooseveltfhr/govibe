<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA Pay — identifiants de passerelle stockés en base : chiffrement au
| repos, prise en compte par les drivers, et bascule d'une passerelle vers
| l'état « prête à encaisser ».
|--------------------------------------------------------------------------
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Tagtoa\App\Models\Pay\GatewayCredential;
use Modules\Tagtoa\App\Support\GatewayManager;
use Modules\Tagtoa\Tests\TestCase;

class GatewayCredentialStorageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        GatewayManager::flush();
    }

    protected function tearDown(): void
    {
        GatewayManager::flush();
        parent::tearDown();
    }

    public function test_credentials_are_encrypted_at_rest(): void
    {
        GatewayCredential::create([
            'driver' => 'moncash',
            'values' => ['credentials' => ['client_id' => 'ID_SECRET_123', 'secret' => 'TRES_SECRET']],
        ]);

        // Ce que voit quelqu'un qui vole un dump SQL : rien d'exploitable.
        $raw = DB::table('tagtoa_gateway_credentials')->where('driver', 'moncash')->value('values');

        $this->assertNotNull($raw);
        $this->assertStringNotContainsString('TRES_SECRET', $raw);
        $this->assertStringNotContainsString('ID_SECRET_123', $raw);

        // Mais l'application, elle, les relit correctement.
        $this->assertSame('TRES_SECRET', GatewayCredential::first()->values['credentials']['secret']);
    }

    public function test_saved_credentials_make_the_gateway_ready(): void
    {
        // Sans identifiants, MonCash ne peut pas encaisser…
        $this->assertFalse(GatewayManager::enabled('moncash'));

        GatewayCredential::create([
            'driver' => 'moncash',
            'values' => ['credentials' => ['client_id' => 'abc', 'secret' => 'xyz']],
        ]);
        GatewayManager::flush();

        // …et une fois saisis dans le super-admin, elle bascule toute seule.
        $this->assertTrue(GatewayManager::enabled('moncash'));
    }

    public function test_partial_credentials_do_not_activate_a_gateway(): void
    {
        GatewayCredential::create([
            'driver' => 'moncash',
            'values' => ['credentials' => ['client_id' => 'abc']], // secret manquant
        ]);
        GatewayManager::flush();

        $this->assertFalse(GatewayManager::enabled('moncash'));
    }

    public function test_drivers_receive_the_stored_credentials_through_the_config(): void
    {
        // Point d'intégration clé : les drivers lisent GatewayManager::config(),
        // donc brancher la base ici suffit à les alimenter tous.
        GatewayCredential::create([
            'driver' => 'stripe',
            'values' => ['credentials' => ['key' => 'pk_base', 'secret' => 'sk_base'], 'webhook_secret' => 'whsec_base'],
        ]);
        GatewayManager::flush();

        $cfg = GatewayManager::config('stripe');

        $this->assertSame('sk_base', $cfg['credentials']['secret']);
        $this->assertSame('whsec_base', $cfg['webhook_secret']);
    }

    public function test_an_unconfigured_driver_stays_on_its_config_defaults(): void
    {
        $cfg = GatewayManager::config('paypal');

        $this->assertSame('PayPal', $cfg['label']);
        $this->assertArrayHasKey('credentials', $cfg);
    }
}
