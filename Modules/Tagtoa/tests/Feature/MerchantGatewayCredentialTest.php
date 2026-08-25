<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA Pay — identifiants de passerelle PROPRES AU MARCHAND.
|--------------------------------------------------------------------------
| Complète le mode « le marchand encaisse » : sans ces identifiants, le mode
| était décoratif. Le point critique testé ici est l'isolation — les clés d'un
| marchand ne doivent JAMAIS servir à encaisser pour un autre.
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Tagtoa\App\Models\Pay\GatewayCredential;
use Modules\Tagtoa\App\Models\Pay\GatewaySetting;
use Modules\Tagtoa\App\Models\Pay\MerchantGatewayCredential;
use Modules\Tagtoa\App\Support\GatewayManager;
use Modules\Tagtoa\App\Support\Pay\GatewayCatalog;
use Modules\Tagtoa\Tests\TestCase;

class MerchantGatewayCredentialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->flushAll();
    }

    protected function tearDown(): void
    {
        $this->flushAll();
        parent::tearDown();
    }

    private function flushAll(): void
    {
        GatewayManager::flush();
        GatewayCatalog::flush();
    }

    /** Règle MonCash en mode « le marchand encaisse ». */
    private function merchantMode(string $gateway = 'moncash'): void
    {
        GatewaySetting::updateOrCreate(
            ['gateway' => $gateway],
            ['is_enabled' => true, 'credential_mode' => GatewayCatalog::MODE_MERCHANT]
        );
        $this->flushAll();
    }

    private function keysFor(string $tenant, array $creds = ['client_id' => 'id', 'secret' => 'sk']): void
    {
        MerchantGatewayCredential::updateOrCreate(
            ['tenant_id' => $tenant, 'driver' => 'moncash'],
            ['values' => ['credentials' => $creds]]
        );
        $this->flushAll();
    }

    public function test_merchant_credentials_are_encrypted_at_rest(): void
    {
        $this->keysFor('t1', ['client_id' => 'ID_MARCHAND', 'secret' => 'SECRET_MARCHAND']);

        $raw = DB::table('tagtoa_merchant_gateway_credentials')->where('tenant_id', 't1')->value('values');

        $this->assertNotNull($raw);
        $this->assertStringNotContainsString('SECRET_MARCHAND', $raw);
        $this->assertStringNotContainsString('ID_MARCHAND', $raw);
    }

    public function test_merchant_keys_activate_the_gateway_for_that_merchant_only(): void
    {
        $this->merchantMode();
        $this->keysFor('t1');

        $this->assertTrue(GatewayManager::enabled('moncash', 't1'));
        // Un autre marchand n'hérite de rien.
        $this->assertFalse(GatewayManager::enabled('moncash', 't2'));
        // Et la plateforme n'est pas activée non plus.
        $this->assertFalse(GatewayManager::enabled('moncash'));
    }

    public function test_a_merchants_keys_never_leak_into_another_merchants_config(): void
    {
        $this->merchantMode();
        $this->keysFor('t1', ['client_id' => 'id_t1', 'secret' => 'secret_t1']);
        $this->keysFor('t2', ['client_id' => 'id_t2', 'secret' => 'secret_t2']);

        $this->assertSame('secret_t1', GatewayManager::config('moncash', 't1')['credentials']['secret']);
        $this->assertSame('secret_t2', GatewayManager::config('moncash', 't2')['credentials']['secret']);
    }

    public function test_merchant_keys_are_ignored_when_the_gateway_is_in_platform_mode(): void
    {
        // Le fondateur garde la main : repasser en mode plateforme doit
        // immédiatement cesser d'utiliser les clés du marchand.
        GatewaySetting::updateOrCreate(
            ['gateway' => 'moncash'],
            ['is_enabled' => true, 'credential_mode' => GatewayCatalog::MODE_PLATFORM]
        );
        $this->keysFor('t1');

        $this->assertFalse(GatewayManager::enabled('moncash', 't1'));
        $this->assertArrayNotHasKey('secret', array_filter(
            GatewayManager::config('moncash', 't1')['credentials'] ?? []
        ));
    }

    public function test_merchant_keys_take_precedence_over_platform_keys(): void
    {
        // Le marchand encaisse : ses clés doivent gagner, sinon l'argent
        // partirait sur le compte de TAGTOA à son insu.
        $this->merchantMode();
        GatewayCredential::create([
            'driver' => 'moncash',
            'values' => ['credentials' => ['client_id' => 'plateforme', 'secret' => 'secret_plateforme']],
        ]);
        $this->keysFor('t1', ['client_id' => 'marchand', 'secret' => 'secret_marchand']);

        $this->assertSame('secret_marchand', GatewayManager::config('moncash', 't1')['credentials']['secret']);
        // Sans tenant (ex. paiement d'abonnement à TAGTOA), on reste plateforme.
        $this->assertSame('secret_plateforme', GatewayManager::config('moncash')['credentials']['secret']);
    }

    public function test_partial_merchant_keys_do_not_activate_the_gateway(): void
    {
        $this->merchantMode();
        $this->keysFor('t1', ['client_id' => 'seulement_id']);

        $this->assertFalse(GatewayManager::enabled('moncash', 't1'));
    }

    public function test_driver_mode_follows_the_gateway_settings(): void
    {
        $this->assertSame(GatewayCatalog::MODE_PLATFORM, GatewayCatalog::driverMode('moncash'));

        $this->merchantMode();

        $this->assertSame(GatewayCatalog::MODE_MERCHANT, GatewayCatalog::driverMode('moncash'));
    }

    public function test_catalog_flags_which_gateways_expect_the_merchants_own_keys(): void
    {
        $this->merchantMode();

        $catalog = GatewayCatalog::forMerchant('t1');

        $this->assertTrue($catalog['moncash']['needs_own_keys']);
        // Une méthode manuelle n'a pas de driver : elle n'attend aucune clé.
        $this->assertFalse($catalog['zelle']['needs_own_keys']);
    }
}
