<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Support\Nfc\AesCmac;
use PHPUnit\Framework\TestCase;

/**
 * Vecteurs de test OFFICIELS RFC 4493 (annexe D) — known-answer tests.
 *
 * Ces vecteurs sont la référence canonique d'AES-CMAC. S'ils passent,
 * l'implémentation est prouvée correcte, sans matériel NFC.
 */
class AesCmacTest extends TestCase
{
    private const KEY = '2b7e151628aed2a6abf7158809cf4f3c';

    private const M = '6bc1bee22e409f96e93d7e117393172a'
        .'ae2d8a571e03ac9c9eb76fac45af8e51'
        .'30c81c46a35ce411e5fbc1191a0a52ef'
        .'f69f2445df4f9b17ad2b417be66c3710';

    public function test_rfc4493_example1_empty(): void
    {
        $this->assertMac('', 'bb1d6929e95937287fa37d129b756746');
    }

    public function test_rfc4493_example2_16_bytes(): void
    {
        $this->assertMac(substr(self::M, 0, 32), '070a16b46b4d4144f79bdd9dd04a287c');
    }

    public function test_rfc4493_example3_40_bytes(): void
    {
        $this->assertMac(substr(self::M, 0, 80), 'dfa66747de9ae63030ca32611497c827');
    }

    public function test_rfc4493_example4_64_bytes(): void
    {
        $this->assertMac(self::M, '51f0bebf7e3b9d92fc49741779363cfe');
    }

    public function test_verify_constant_time_helper(): void
    {
        $key = hex2bin(self::KEY);
        $msg = hex2bin(substr(self::M, 0, 32));
        $good = AesCmac::mac($key, $msg);

        $this->assertTrue(AesCmac::verify($key, $msg, $good));
        $this->assertFalse(AesCmac::verify($key, $msg, str_repeat("\x00", 16)));
    }

    public function test_rejects_bad_key_length(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AesCmac::mac('short', 'x');
    }

    private function assertMac(string $msgHex, string $expectedHex): void
    {
        $this->assertSame(
            $expectedHex,
            AesCmac::macHex(hex2bin(self::KEY), hex2bin($msgHex))
        );
    }
}
