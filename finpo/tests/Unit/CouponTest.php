<?php

namespace Tests\Unit;

use App\Models\Coupon;
use PHPUnit\Framework\TestCase;

class CouponTest extends TestCase
{
    public function test_percent_coupon_applies_discount(): void
    {
        $coupon = new Coupon(['type' => 'percent', 'value' => 25]);

        $this->assertSame(750, $coupon->apply(1000));
    }

    public function test_fixed_coupon_never_goes_below_zero(): void
    {
        $coupon = new Coupon(['type' => 'fixed', 'value' => 5000]);

        $this->assertSame(0, $coupon->apply(1000));
    }

    public function test_percent_is_capped_at_100(): void
    {
        $coupon = new Coupon(['type' => 'percent', 'value' => 250]);

        $this->assertSame(0, $coupon->apply(1000));
    }
}
