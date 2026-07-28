<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Support\Gateways\PayPal;
use PHPUnit\Framework\TestCase;

/** Logique pure du driver PayPal (montant, devise, lien, statut). */
class PayPalTest extends TestCase
{
    public function test_amount_is_two_decimals_string(): void
    {
        $this->assertSame('10.00', PayPal::amount(10));
        $this->assertSame('10.50', PayPal::amount(10.5));
        $this->assertSame('0.01', PayPal::amount(0));       // min 0.01
        $this->assertSame('1234.57', PayPal::amount(1234.567));
    }

    public function test_currency_support_excludes_htg(): void
    {
        $this->assertTrue(PayPal::supportsCurrency('USD'));
        $this->assertTrue(PayPal::supportsCurrency('eur'));
        $this->assertFalse(PayPal::supportsCurrency('HTG'));
        $this->assertFalse(PayPal::supportsCurrency(null));
    }

    public function test_approve_link_extraction(): void
    {
        $links = [
            ['rel' => 'self', 'href' => 'https://x/self'],
            ['rel' => 'approve', 'href' => 'https://x/approve'],
            ['rel' => 'capture', 'href' => 'https://x/capture'],
        ];
        $this->assertSame('https://x/approve', PayPal::approveLink($links));
        $this->assertNull(PayPal::approveLink([['rel' => 'self', 'href' => 'x']]));
        $this->assertNull(PayPal::approveLink([]));
    }

    public function test_status_mapping(): void
    {
        $this->assertSame('paid', PayPal::mapStatus('COMPLETED'));
        $this->assertSame('failed', PayPal::mapStatus('VOIDED'));
        $this->assertSame('failed', PayPal::mapStatus('DECLINED'));
        $this->assertSame('pending', PayPal::mapStatus('APPROVED'));
        $this->assertSame('pending', PayPal::mapStatus('CREATED'));
        $this->assertSame('pending', PayPal::mapStatus(null));
    }

    public function test_api_base_by_mode(): void
    {
        $this->assertStringContainsString('sandbox', PayPal::apiBase('sandbox'));
        $this->assertSame('https://api-m.paypal.com', PayPal::apiBase('live'));
    }
}
