<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Support\Nfc\Ntag424;
use Modules\Tagtoa\App\Support\Nfc\SunVerifier;
use PHPUnit\Framework\TestCase;

/** Vérificateur de tap NTAG424 (CMAC + anti-rejeu), logique pure. */
class SunVerifierTest extends TestCase
{
    private const KEY = '00112233445566778899aabbccddeeff';
    private const UID = '04a2b3c4d5e680';
    private const CTR = '050000'; // LE => 5

    private function parts(): array
    {
        $key = hex2bin(self::KEY);
        $uid = hex2bin(self::UID);
        $ctr = hex2bin(self::CTR);

        return [$key, $uid, $ctr, Ntag424::expectedMac($key, $uid, $ctr)];
    }

    public function test_valid_fresh_tap_passes(): void
    {
        [$key, $uid, $ctr, $cmac] = $this->parts();
        $r = SunVerifier::verifyTap($key, $uid, $ctr, $cmac, null);
        $this->assertTrue($r['ok']);
        $this->assertSame(5, $r['counter']);
    }

    public function test_replay_is_rejected(): void
    {
        [$key, $uid, $ctr, $cmac] = $this->parts();
        // Compteur déjà vu (5) → rejeu refusé même si le CMAC est correct.
        $r = SunVerifier::verifyTap($key, $uid, $ctr, $cmac, 5);
        $this->assertFalse($r['ok']);
    }

    public function test_bad_cmac_is_rejected(): void
    {
        [$key, $uid, $ctr] = $this->parts();
        $r = SunVerifier::verifyTap($key, $uid, $ctr, 'badmac00', null);
        $this->assertFalse($r['ok']);
    }
}
