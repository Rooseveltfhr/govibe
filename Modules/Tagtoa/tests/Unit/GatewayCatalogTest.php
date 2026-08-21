<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Support\Pay\GatewayCatalog;
use PHPUnit\Framework\TestCase;

/**
 * Catalogue des passerelles : fusion registre codé + surcharges plateforme,
 * calcul des frais, séparation auto/manuel. Logique pure (sans Laravel).
 */
class GatewayCatalogTest extends TestCase
{
    private function registry(): array
    {
        return [
            'moncash' => ['label' => 'MonCash', 'mode' => 'auto',   'driver' => 'moncash', 'color' => '#E2001A'],
            'zelle'   => ['label' => 'Zelle',   'mode' => 'manual', 'driver' => null,      'color' => '#6D1ED4'],
        ];
    }

    public function test_without_overrides_every_gateway_gets_the_defaults(): void
    {
        $out = GatewayCatalog::merge($this->registry(), []);

        $this->assertTrue($out['moncash']['is_enabled']);
        $this->assertSame(GatewayCatalog::MODE_PLATFORM, $out['moncash']['credential_mode']);
        $this->assertSame(0.0, $out['moncash']['fee_percent']);
        $this->assertSame(0.0, $out['moncash']['fee_fixed']);
        // Les métadonnées du registre sont préservées.
        $this->assertSame('MonCash', $out['moncash']['label']);
        $this->assertSame('moncash', $out['moncash']['driver']);
    }

    public function test_overrides_replace_defaults(): void
    {
        $out = GatewayCatalog::merge($this->registry(), [
            'moncash' => ['is_enabled' => false, 'credential_mode' => 'merchant', 'fee_percent' => 2.5, 'fee_fixed' => 10],
        ]);

        $this->assertFalse($out['moncash']['is_enabled']);
        $this->assertSame(GatewayCatalog::MODE_MERCHANT, $out['moncash']['credential_mode']);
        $this->assertSame(2.5, $out['moncash']['fee_percent']);
        $this->assertSame(10.0, $out['moncash']['fee_fixed']);
        // Une passerelle non surchargée garde ses défauts.
        $this->assertTrue($out['zelle']['is_enabled']);
    }

    public function test_unknown_credential_mode_falls_back_to_platform(): void
    {
        $out = GatewayCatalog::merge($this->registry(), [
            'moncash' => ['credential_mode' => 'n_importe_quoi'],
        ]);

        $this->assertSame(GatewayCatalog::MODE_PLATFORM, $out['moncash']['credential_mode']);
    }

    public function test_overrides_for_unknown_gateways_are_ignored(): void
    {
        // Une surcharge orpheline (passerelle retirée du registre) ne doit pas
        // réintroduire la passerelle dans le catalogue.
        $out = GatewayCatalog::merge($this->registry(), ['passerelle_fantome' => ['is_enabled' => true]]);

        $this->assertArrayNotHasKey('passerelle_fantome', $out);
        $this->assertCount(2, $out);
    }

    public function test_negative_fees_are_clamped_to_zero(): void
    {
        $out = GatewayCatalog::merge($this->registry(), [
            'moncash' => ['fee_percent' => -5, 'fee_fixed' => -20],
        ]);

        $this->assertSame(0.0, $out['moncash']['fee_percent']);
        $this->assertSame(0.0, $out['moncash']['fee_fixed']);
    }

    public function test_valid_modes(): void
    {
        $this->assertTrue(GatewayCatalog::isValidMode('platform'));
        $this->assertTrue(GatewayCatalog::isValidMode('merchant'));
        $this->assertFalse(GatewayCatalog::isValidMode('autre'));
        $this->assertFalse(GatewayCatalog::isValidMode(null));
    }

    public function test_fee_combines_percent_and_fixed(): void
    {
        // 1000 * 2.5% = 25, + 10 fixe = 35
        $this->assertSame(35.0, GatewayCatalog::fee(1000, 2.5, 10));
    }

    public function test_fee_is_zero_without_configuration(): void
    {
        $this->assertSame(0.0, GatewayCatalog::fee(1000, 0, 0));
    }

    public function test_fee_never_exceeds_the_amount_paid(): void
    {
        // Garde-fou : un frais fixe démesuré ne peut pas dépasser le brut.
        $this->assertSame(100.0, GatewayCatalog::fee(100, 50, 9999));
    }

    public function test_fee_on_zero_or_negative_amount_is_zero(): void
    {
        $this->assertSame(0.0, GatewayCatalog::fee(0, 5, 10));
        $this->assertSame(0.0, GatewayCatalog::fee(-50, 5, 10));
    }

    public function test_split_separates_auto_from_manual(): void
    {
        $split = GatewayCatalog::split(GatewayCatalog::merge($this->registry(), []));

        $this->assertArrayHasKey('moncash', $split['auto']);
        $this->assertArrayHasKey('zelle', $split['manual']);
        $this->assertArrayNotHasKey('zelle', $split['auto']);
    }

    public function test_split_treats_a_gateway_without_mode_as_manual(): void
    {
        $split = GatewayCatalog::split(['bizarre' => ['label' => 'Bizarre']]);

        $this->assertArrayHasKey('bizarre', $split['manual']);
        $this->assertSame([], $split['auto']);
    }
}
