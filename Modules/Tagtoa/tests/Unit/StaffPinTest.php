<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Services\Event\StaffPinService;
use PHPUnit\Framework\TestCase;

/**
 * Auth staff par PIN (logique pure : normalisation, hachage, matrice d'accès).
 */
class StaffPinTest extends TestCase
{
    public function test_normalize_and_format(): void
    {
        $this->assertSame('1234', StaffPinService::normalizePin(' 12-34 '));
        $this->assertTrue(StaffPinService::isValidPinFormat('1234'));
        $this->assertTrue(StaffPinService::isValidPinFormat('123456'));
        $this->assertFalse(StaffPinService::isValidPinFormat('123'));      // trop court
        $this->assertFalse(StaffPinService::isValidPinFormat('1234567'));  // trop long
        $this->assertFalse(StaffPinService::isValidPinFormat('ab'));       // pas de chiffres
    }

    public function test_hash_verify_roundtrip(): void
    {
        $hash = StaffPinService::hashPin('4821');

        $this->assertNotSame('4821', $hash);
        $this->assertTrue(StaffPinService::verifyPin('4821', $hash));
        $this->assertTrue(StaffPinService::verifyPin('48-21', $hash)); // normalisé avant vérif
        $this->assertFalse(StaffPinService::verifyPin('0000', $hash));
        $this->assertFalse(StaffPinService::verifyPin('4821', ''));    // hash vide
    }

    public function test_role_screen_access_matrix(): void
    {
        // admin = accès total
        $this->assertTrue(StaffPinService::canAccess('admin', 'staff'));
        $this->assertTrue(StaffPinService::canAccess('admin', 'checkin'));
        $this->assertTrue(StaffPinService::canAccess('admin', 'sales'));

        // vente = vente de cartes + retrait NFC uniquement
        $this->assertTrue(StaffPinService::canAccess('vente', 'sales'));
        $this->assertTrue(StaffPinService::canAccess('vente', 'pickup'));
        $this->assertFalse(StaffPinService::canAccess('vente', 'checkin'));
        $this->assertFalse(StaffPinService::canAccess('vente', 'staff'));

        // checkin = porte uniquement
        $this->assertTrue(StaffPinService::canAccess('checkin', 'checkin'));
        $this->assertFalse(StaffPinService::canAccess('checkin', 'sales'));
        $this->assertFalse(StaffPinService::canAccess('checkin', 'dashboard'));
    }

    public function test_role_validation(): void
    {
        $this->assertTrue(StaffPinService::isValidRole('admin'));
        $this->assertTrue(StaffPinService::isValidRole('vente'));
        $this->assertTrue(StaffPinService::isValidRole('checkin'));
        $this->assertFalse(StaffPinService::isValidRole('pos'));   // pas de POS ici
        $this->assertFalse(StaffPinService::isValidRole(''));
    }
}
