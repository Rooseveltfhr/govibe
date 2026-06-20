<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Services\Loyalty\LoyaltyCardService;
use PHPUnit\Framework\TestCase;

/**
 * Logique pure des numéros de carte de fidélité (algorithme de Luhn).
 */
class LuhnTest extends TestCase
{
    private LoyaltyCardService $svc;

    protected function setUp(): void
    {
        $this->svc = new LoyaltyCardService();
    }

    public function test_check_digit_makes_number_luhn_valid(): void
    {
        $body = '429700000001234';                 // 15 chiffres (préfixe TAGTOA 4297)
        $full = $body.$this->svc->luhnCheckDigit($body);

        $this->assertSame(16, strlen($full));
        $this->assertTrue($this->svc->isValidLuhn($full));
    }

    public function test_known_luhn_values(): void
    {
        // Exemples canoniques.
        $this->assertTrue($this->svc->isValidLuhn('4242424242424242'));
        $this->assertFalse($this->svc->isValidLuhn('4242424242424241'));
    }

    public function test_check_digit_is_single_digit(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $body = (string) random_int(100000000000000, 999999999999999); // 15 chiffres
            $d = $this->svc->luhnCheckDigit($body);
            $this->assertGreaterThanOrEqual(0, $d);
            $this->assertLessThanOrEqual(9, $d);
            $this->assertTrue($this->svc->isValidLuhn($body.$d), "Luhn invalide pour $body");
        }
    }

    public function test_non_numeric_is_invalid(): void
    {
        $this->assertFalse($this->svc->isValidLuhn('4242-4242'));
    }
}
