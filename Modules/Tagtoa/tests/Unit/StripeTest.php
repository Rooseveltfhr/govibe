<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Support\Gateways\Stripe;
use PHPUnit\Framework\TestCase;

/** Logique pure du driver Stripe (montant en unité mineure, devise, statut, signature). */
class StripeTest extends TestCase
{
    public function test_amount_in_minor_units(): void
    {
        $this->assertSame(1000, Stripe::amount(10, 'USD'));
        $this->assertSame(1050, Stripe::amount(10.5, 'USD'));
        $this->assertSame(123457, Stripe::amount(1234.567, 'EUR'));
        $this->assertSame(1, Stripe::amount(0, 'USD'));   // min 1
    }

    public function test_zero_decimal_currency_amount(): void
    {
        $this->assertTrue(Stripe::isZeroDecimal('JPY'));
        $this->assertFalse(Stripe::isZeroDecimal('USD'));
        // JPY : le montant est déjà en plus petite unité (pas de ×100).
        $this->assertSame(500, Stripe::amount(500, 'JPY'));
    }

    public function test_currency_support_excludes_htg(): void
    {
        $this->assertTrue(Stripe::supportsCurrency('USD'));
        $this->assertTrue(Stripe::supportsCurrency('eur'));
        $this->assertTrue(Stripe::supportsCurrency('DOP'));
        $this->assertFalse(Stripe::supportsCurrency('HTG'));
        $this->assertFalse(Stripe::supportsCurrency(null));
    }

    public function test_status_mapping(): void
    {
        $this->assertSame('paid', Stripe::mapStatus('paid'));
        $this->assertSame('paid', Stripe::mapStatus('no_payment_required'));
        $this->assertSame('pending', Stripe::mapStatus('unpaid'));
        $this->assertSame('pending', Stripe::mapStatus(null));
        $this->assertSame('failed', Stripe::mapStatus('unpaid', 'expired'));
        // payé prime sur un statut de session « open ».
        $this->assertSame('paid', Stripe::mapStatus('paid', 'complete'));
    }

    public function test_webhook_signature_valid_and_tampered(): void
    {
        $payload = '{"id":"evt_1","type":"checkout.session.completed"}';
        $secret = 'whsec_test';
        $t = 1700000000;
        $good = hash_hmac('sha256', $t.'.'.$payload, $secret);
        $header = 't='.$t.',v1='.$good;

        // Signature correcte, dans la fenêtre de tolérance.
        $this->assertTrue(Stripe::verifySignature($payload, $header, $secret, 300, $t + 10));
        // Corps falsifié → signature invalide.
        $this->assertFalse(Stripe::verifySignature($payload.'x', $header, $secret, 300, $t + 10));
        // Mauvais secret → invalide.
        $this->assertFalse(Stripe::verifySignature($payload, $header, 'whsec_other', 300, $t + 10));
        // Hors fenêtre temporelle → rejeté.
        $this->assertFalse(Stripe::verifySignature($payload, $header, $secret, 300, $t + 10000));
        // Header/secret vide → rejeté.
        $this->assertFalse(Stripe::verifySignature($payload, '', $secret));
        $this->assertFalse(Stripe::verifySignature($payload, $header, ''));
    }
}
