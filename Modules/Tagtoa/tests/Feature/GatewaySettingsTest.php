<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA Pay — réglages plateforme des passerelles (super_admin) appliqués
| au catalogue effectif, contre une vraie base.
|--------------------------------------------------------------------------
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Pay\GatewaySetting;
use Modules\Tagtoa\App\Support\Pay\GatewayCatalog;
use Modules\Tagtoa\App\Support\PaymentGateway;
use Modules\Tagtoa\Tests\TestCase;

class GatewaySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_exposes_every_coded_gateway_with_its_metadata(): void
    {
        $registry = GatewayCatalog::registry();

        $this->assertSame(array_keys(PaymentGateway::GATEWAYS), array_keys($registry));
        // Les métadonnées d'affichage (label/icône) viennent bien de PaymentPage::METHODS.
        $this->assertSame('MonCash', $registry['moncash']['label']);
        $this->assertSame('moncash', $registry['moncash']['driver']);
    }

    public function test_effective_catalog_uses_code_defaults_when_nothing_is_overridden(): void
    {
        $catalog = GatewayCatalog::effective();

        $this->assertTrue($catalog['moncash']['is_enabled']);
        $this->assertSame(0.0, $catalog['moncash']['fee_percent']);
    }

    public function test_a_super_admin_override_wins_over_the_code_default(): void
    {
        GatewaySetting::create([
            'gateway'         => 'moncash',
            'is_enabled'      => false,
            'credential_mode' => GatewayCatalog::MODE_MERCHANT,
            'fee_percent'     => 3.5,
            'fee_fixed'       => 25,
        ]);

        $catalog = GatewayCatalog::effective();

        $this->assertFalse($catalog['moncash']['is_enabled']);
        $this->assertSame(GatewayCatalog::MODE_MERCHANT, $catalog['moncash']['credential_mode']);
        $this->assertSame(3.5, $catalog['moncash']['fee_percent']);
        $this->assertSame(25.0, $catalog['moncash']['fee_fixed']);

        // Les autres passerelles restent aux valeurs par défaut.
        $this->assertTrue($catalog['zelle']['is_enabled']);
    }

    public function test_a_disabled_gateway_is_never_online_ready_for_a_merchant(): void
    {
        // Même si un driver existait avec des identifiants, une passerelle coupée
        // par le fondateur ne doit jamais encaisser en ligne.
        GatewaySetting::create(['gateway' => 'moncash', 'is_enabled' => false]);

        $this->assertFalse(GatewayCatalog::forMerchant()['moncash']['online_ready']);
    }

    public function test_manual_gateways_are_never_online_ready(): void
    {
        // Zelle n'a pas de driver : elle ne peut pas encaisser automatiquement.
        $this->assertFalse(GatewayCatalog::forMerchant()['zelle']['online_ready']);
    }

    public function test_auto_gateway_without_credentials_is_not_online_ready(): void
    {
        // Aucun identifiant n'est configuré dans l'environnement de test :
        // MonCash est bien « auto » au registre mais ne peut pas encaisser.
        $merchant = GatewayCatalog::forMerchant();

        $this->assertSame(PaymentGateway::MODE_AUTO, $merchant['moncash']['mode']);
        $this->assertFalse($merchant['moncash']['online_ready']);
    }
}
