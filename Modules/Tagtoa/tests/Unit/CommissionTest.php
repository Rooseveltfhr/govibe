<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Services\Billing\RevenueService;
use PHPUnit\Framework\TestCase;

/**
 * Logique pure du calcul de commission plateforme.
 */
class CommissionTest extends TestCase
{
    private RevenueService $svc;

    protected function setUp(): void
    {
        $this->svc = new RevenueService();
    }

    public function test_percent_only(): void
    {
        // 5% de 1000 = 50
        $this->assertSame(50.0, $this->svc->computeCommission(1000, 5, 0));
    }

    public function test_percent_plus_fixed(): void
    {
        // 2% de 500 = 10, + frais fixe 5 = 15
        $this->assertSame(15.0, $this->svc->computeCommission(500, 2, 5));
    }

    public function test_zero_when_no_rate(): void
    {
        $this->assertSame(0.0, $this->svc->computeCommission(1000, 0, 0));
    }

    public function test_never_exceeds_gross(): void
    {
        // Commission bornée au montant brut.
        $this->assertSame(100.0, $this->svc->computeCommission(100, 0, 999));
    }

    public function test_rounding_two_decimals(): void
    {
        // 3.33% de 100 = 3.33
        $this->assertSame(3.33, $this->svc->computeCommission(100, 3.33, 0));
    }
}
