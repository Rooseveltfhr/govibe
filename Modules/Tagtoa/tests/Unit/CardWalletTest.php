<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Support\Card\CardWallet;
use PHPUnit\Framework\TestCase;

/** Logique pure de la carte TAGTOA closed-loop (UID, solde entier, statut, PIN). */
class CardWalletTest extends TestCase
{
    public function test_uid_normalization_is_separator_and_case_insensitive(): void
    {
        $this->assertSame('04A21B', CardWallet::normalizeUid('04:A2:1B'));
        $this->assertSame('04A21B', CardWallet::normalizeUid('04-a2-1b'));
        $this->assertSame('04A21B', CardWallet::normalizeUid('  04 a2 1b '));
        // Même carte, deux écritures → même hash.
        $this->assertSame(CardWallet::hashUid('04:A2:1B'), CardWallet::hashUid('04a21b'));
    }

    public function test_hash_never_returns_raw_uid_and_is_salt_sensitive(): void
    {
        $h = CardWallet::hashUid('04A21B');
        $this->assertSame(64, strlen($h));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $h);
        $this->assertNotSame(CardWallet::hashUid('04A21B'), CardWallet::hashUid('04A21B', 'salt'));
    }

    public function test_mask_uid_keeps_last_four(): void
    {
        $this->assertSame('••••1234', CardWallet::maskUid('AABB1234'));
        $this->assertSame('AB12', CardWallet::maskUid('AB12'));   // trop court → tel quel
    }

    public function test_spendable_only_when_active(): void
    {
        $this->assertTrue(CardWallet::isSpendable(CardWallet::STATUS_ACTIVE));
        $this->assertFalse(CardWallet::isSpendable(CardWallet::STATUS_BLOCKED));
        $this->assertFalse(CardWallet::isSpendable(CardWallet::STATUS_LOST));
        $this->assertFalse(CardWallet::isSpendable(null));
    }

    public function test_can_charge_guards_amount_and_balance(): void
    {
        $this->assertTrue(CardWallet::canCharge(1000, 1000));   // solde exact
        $this->assertTrue(CardWallet::canCharge(1000, 500));
        $this->assertFalse(CardWallet::canCharge(1000, 1001));  // insuffisant
        $this->assertFalse(CardWallet::canCharge(1000, 0));     // montant nul
        $this->assertFalse(CardWallet::canCharge(1000, -50));   // montant négatif
    }

    public function test_apply_charge_and_topup_integer_math(): void
    {
        $this->assertSame(500, CardWallet::applyCharge(1000, 500));
        $this->assertSame(0, CardWallet::applyCharge(1000, 1000));
        $this->assertSame(0, CardWallet::applyCharge(1000, 5000));   // jamais négatif
        $this->assertSame(1500, CardWallet::applyTopup(1000, 500));
        $this->assertSame(1000, CardWallet::applyTopup(1000, -50));  // recharge négative ignorée
    }

    public function test_effective_quota_and_activation(): void
    {
        // Forfait illimité (null) → illimité, aucun crédit consommé.
        $this->assertNull(CardWallet::effectiveQuota(null, 50));
        $this->assertTrue(CardWallet::canActivate(null, 0, 999999));
        $this->assertFalse(CardWallet::consumesCredit(null, 100));

        // Forfait = 0 (bloqué), + 5 crédits achetés → quota effectif 5.
        $this->assertSame(5, CardWallet::effectiveQuota(0, 5));
        $this->assertTrue(CardWallet::canActivate(0, 5, 4));
        $this->assertFalse(CardWallet::canActivate(0, 5, 5)); // quota atteint
        $this->assertTrue(CardWallet::consumesCredit(0, 0));  // dès la 1re, on puise dans les crédits

        // Forfait = 10, 3 crédits → 13. Les crédits ne se consomment qu'après 10.
        $this->assertSame(13, CardWallet::effectiveQuota(10, 3));
        $this->assertFalse(CardWallet::consumesCredit(10, 9));  // encore dans le quota forfait
        $this->assertTrue(CardWallet::consumesCredit(10, 10));  // au-delà → crédit
    }

    public function test_pin_format(): void
    {
        $this->assertTrue(CardWallet::isValidPinFormat('1234'));
        $this->assertTrue(CardWallet::isValidPinFormat('123456'));
        $this->assertTrue(CardWallet::isValidPinFormat(null));   // sans PIN autorisé
        $this->assertTrue(CardWallet::isValidPinFormat(''));
        $this->assertFalse(CardWallet::isValidPinFormat('12'));
        $this->assertFalse(CardWallet::isValidPinFormat('1234567'));
        $this->assertFalse(CardWallet::isValidPinFormat('12ab'));
    }
}
