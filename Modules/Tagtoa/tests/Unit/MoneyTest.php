<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Support\Money;
use PHPUnit\Framework\TestCase;

/**
 * Formatage monétaire (logique pure, config-tolérante via DEFAULTS).
 */
class MoneyTest extends TestCase
{
    public function test_usd_symbol_before_two_decimals(): void
    {
        $this->assertSame('$1,500.00', Money::format(1500, 'USD'));
    }

    public function test_htg_symbol_after_no_decimals(): void
    {
        $this->assertSame('1,500 G', Money::format(1500, 'HTG'));
    }

    public function test_eur_symbol_after(): void
    {
        $this->assertSame('1,500.00 €', Money::format(1500, 'EUR'));
    }

    public function test_unknown_currency_falls_back_to_code_after(): void
    {
        $this->assertSame('10.00 XYZ', Money::format(10, 'XYZ'));
    }

    public function test_case_insensitive_currency(): void
    {
        $this->assertSame('$5.00', Money::format(5, 'usd'));
    }

    public function test_options_contains_known_currencies(): void
    {
        $opts = Money::options();
        $this->assertArrayHasKey('HTG', $opts);
        $this->assertArrayHasKey('USD', $opts);
    }

    public function test_to_minor_units(): void
    {
        $this->assertSame(200, Money::toMinor(2, 'USD'));   // 2 décimales
        $this->assertSame(50, Money::toMinor(50, 'HTG'));   // 0 décimale
        $this->assertSame(1550, Money::toMinor(15.5, 'USD'));
    }

    public function test_from_minor_units(): void
    {
        $this->assertSame(2.0, Money::fromMinor(200, 'USD'));
        $this->assertSame(50.0, Money::fromMinor(50, 'HTG'));
    }

    public function test_format_minor(): void
    {
        $this->assertSame('$2.00', Money::formatMinor(200, 'USD'));
        $this->assertSame('50 G', Money::formatMinor(50, 'HTG'));
    }
}
