<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Support\Gateways\CoinPayments;
use PHPUnit\Framework\TestCase;

/** Logique pure du driver CoinPayments (signature HMAC, statut, coin, montant). */
class CoinPaymentsTest extends TestCase
{
    public function test_signature_is_deterministic_hmac_sha512(): void
    {
        $payload = ['cmd' => 'create_transaction', 'amount' => '10.00', 'currency1' => 'USD'];
        $sig = CoinPayments::sign($payload, 'private-key');

        // HMAC-SHA512 => 128 caractères hexadécimaux, déterministe.
        $this->assertSame(128, strlen($sig));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{128}$/', $sig);
        $this->assertSame($sig, CoinPayments::sign($payload, 'private-key'));
        // Known-answer : identique à HMAC du querystring exact.
        $this->assertSame(hash_hmac('sha512', http_build_query($payload), 'private-key'), $sig);
    }

    public function test_signature_is_key_and_payload_sensitive(): void
    {
        $p = ['cmd' => 'x'];
        $this->assertNotSame(CoinPayments::sign($p, 'k1'), CoinPayments::sign($p, 'k2'));
        $this->assertNotSame(CoinPayments::sign(['cmd' => 'x'], 'k'), CoinPayments::sign(['cmd' => 'y'], 'k'));
    }

    public function test_status_mapping(): void
    {
        $this->assertSame('paid', CoinPayments::mapStatus(100));
        $this->assertSame('paid', CoinPayments::mapStatus(2));
        $this->assertSame('pending', CoinPayments::mapStatus(0));
        $this->assertSame('pending', CoinPayments::mapStatus(1));
        $this->assertSame('failed', CoinPayments::mapStatus(-1));
    }

    public function test_default_coin(): void
    {
        $this->assertSame('BTC', CoinPayments::defaultCoin('btc'));
        $this->assertSame('ETH', CoinPayments::defaultCoin('eth'));
        $this->assertSame('USDT.TRC20', CoinPayments::defaultCoin(null));
        $this->assertSame('USDT.TRC20', CoinPayments::defaultCoin('unknown'));
    }

    public function test_amount_format(): void
    {
        $this->assertSame('10.00', CoinPayments::amount(10));
        $this->assertSame('0.01', CoinPayments::amount(0));
    }
}
