<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Support\Nfc\AesCmac;
use Modules\Tagtoa\App\Support\Nfc\Ntag424;
use PHPUnit\Framework\TestCase;

/**
 * Vérifie la LOGIQUE de sécurité NTAG424 (troncature, anti-clonage, anti-rejeu,
 * extraction UID/compteur). Le CMAC sous-jacent est déjà prouvé (RFC 4493).
 *
 * Les vecteurs sont auto-cohérents : on signe avec la même dérivation puis on
 * vérifie — ce qui prouve que la machine à états (accepter le bon, rejeter le
 * falsifié/rejoué) est correcte. Le préfixe SV2 exact reste à valider contre un
 * tag réel avant activation prod (cf. docblock Ntag424).
 */
class Ntag424Test extends TestCase
{
    private const KEY = '00112233445566778899aabbccddeeff';
    private const UID = '04a2b3c4d5e680';   // 7 octets
    private const CTR = '050000';           // 3 octets, LE => 5

    public function test_truncate_keeps_odd_bytes(): void
    {
        // Octets d'index impair de 00 01 02 … 0f => 01 03 05 07 09 0b 0d 0f.
        $full = hex2bin('000102030405060708090a0b0c0d0e0f');
        $this->assertSame('01030507090b0d0f', bin2hex(Ntag424::truncate($full)));
    }

    public function test_valid_tag_read_is_accepted(): void
    {
        [$key, $uid, $ctr] = $this->raw();
        $cmac = Ntag424::expectedMac($key, $uid, $ctr);

        $this->assertTrue(Ntag424::verify($key, $uid, $ctr, $cmac));
        $this->assertSame(8, strlen($cmac));
    }

    public function test_clone_with_wrong_key_is_rejected(): void
    {
        [$key, $uid, $ctr] = $this->raw();
        $cmac = Ntag424::expectedMac($key, $uid, $ctr);
        $wrongKey = hex2bin('ffffffffffffffffffffffffffffffff');

        $this->assertFalse(Ntag424::verify($wrongKey, $uid, $ctr, $cmac));
    }

    public function test_tampered_uid_is_rejected(): void
    {
        [$key, $uid, $ctr] = $this->raw();
        $cmac = Ntag424::expectedMac($key, $uid, $ctr);
        $otherUid = hex2bin('04ffffffffffff');

        $this->assertFalse(Ntag424::verify($key, $otherUid, $ctr, $cmac));
    }

    public function test_replayed_counter_is_rejected_by_freshness(): void
    {
        // Compteur 5 déjà accepté → une relecture au compteur 5 (ou moins) est rejouée.
        $this->assertFalse(Ntag424::isFresh(5, 5));
        $this->assertFalse(Ntag424::isFresh(5, 4));
        $this->assertTrue(Ntag424::isFresh(5, 6));
        $this->assertTrue(Ntag424::isFresh(null, 0)); // première lecture
    }

    public function test_counter_value_is_little_endian(): void
    {
        $this->assertSame(5, Ntag424::counterValue(hex2bin('050000')));
        $this->assertSame(0x010203, Ntag424::counterValue(hex2bin('030201')));
    }

    public function test_wrong_length_inputs_are_rejected(): void
    {
        $this->assertFalse(Ntag424::verify(hex2bin(self::KEY), hex2bin(self::UID), hex2bin(self::CTR), 'short'));

        $this->expectException(\InvalidArgumentException::class);
        Ntag424::sessionMacKey(hex2bin(self::KEY), 'bad', hex2bin(self::CTR));
    }

    /** @return array{0:string,1:string,2:string} clé,uid,ctr bruts */
    private function raw(): array
    {
        return [hex2bin(self::KEY), hex2bin(self::UID), hex2bin(self::CTR)];
    }
}
