<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Services\Inventory\StockService;
use PHPUnit\Framework\TestCase;

/**
 * Logique pure de stock (null = illimité).
 */
class StockTest extends TestCase
{
    public function test_unlimited_when_null(): void
    {
        $this->assertTrue(StockService::canFulfill(null, 999));
        $this->assertNull(StockService::remaining(null, 5));
        $this->assertFalse(StockService::isLow(null));
        $this->assertFalse(StockService::isOut(null));
    }

    public function test_can_fulfill(): void
    {
        $this->assertTrue(StockService::canFulfill(5, 5));
        $this->assertTrue(StockService::canFulfill(5, 3));
        $this->assertFalse(StockService::canFulfill(2, 3));
        $this->assertFalse(StockService::canFulfill(0, 1));
    }

    public function test_remaining_never_negative(): void
    {
        $this->assertSame(2, StockService::remaining(5, 3));
        $this->assertSame(0, StockService::remaining(3, 3));
        $this->assertSame(0, StockService::remaining(3, 10));
    }

    public function test_is_low_and_out(): void
    {
        $this->assertTrue(StockService::isLow(5));
        $this->assertTrue(StockService::isLow(0));
        $this->assertFalse(StockService::isLow(6));
        $this->assertTrue(StockService::isLow(3, 3));

        $this->assertTrue(StockService::isOut(0));
        $this->assertFalse(StockService::isOut(1));
    }
}
