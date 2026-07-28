<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Support\Gateways\MonCash;
use PHPUnit\Framework\TestCase;

/**
 * MonCash — logique pure (endpoints, montant, statut). Sans réseau ni Laravel.
 */
class MonCashTest extends TestCase
{
    public function test_api_base_by_mode(): void
    {
        $this->assertSame('https://sandbox.moncashbutton.digicel.com/Api', MonCash::apiBase('sandbox'));
        $this->assertSame('https://moncashbutton.digicel.com/Api', MonCash::apiBase('live'));
        $this->assertSame('https://sandbox.moncashbutton.digicel.com/Api', MonCash::apiBase('anything')); // défaut sandbox
    }

    public function test_redirect_url_contains_token(): void
    {
        $url = MonCash::redirectUrl('live', 'abc123');
        $this->assertStringContainsString('moncashbutton.digicel.com/Moncash-middleware/Payment/Redirect', $url);
        $this->assertStringContainsString('token=abc123', $url);
    }

    public function test_only_htg_supported(): void
    {
        $this->assertTrue(MonCash::supportsCurrency('HTG'));
        $this->assertTrue(MonCash::supportsCurrency('htg'));
        $this->assertFalse(MonCash::supportsCurrency('USD'));
        $this->assertFalse(MonCash::supportsCurrency(null));
    }

    public function test_amount_is_positive_integer(): void
    {
        $this->assertSame(500, MonCash::amount(500));
        $this->assertSame(500, MonCash::amount(499.6));
        $this->assertSame(1, MonCash::amount(0));
        $this->assertSame(1, MonCash::amount(-50));
    }

    public function test_status_mapping(): void
    {
        $this->assertSame('paid', MonCash::mapStatus('successful'));
        $this->assertSame('paid', MonCash::mapStatus('SUCCESSFUL'));
        $this->assertSame('failed', MonCash::mapStatus('declined'));
        $this->assertSame('failed', MonCash::mapStatus('cancelled'));
        $this->assertSame('pending', MonCash::mapStatus(null));
        $this->assertSame('pending', MonCash::mapStatus('processing'));
    }
}
