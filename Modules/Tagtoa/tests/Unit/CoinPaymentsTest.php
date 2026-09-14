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

    /* ------------------------------------------------------------------
       Vérification du MONTANT — le trou trouvé à l'audit.
       Le statut CoinPayments seul disait « payé » même si le payeur avait
       envoyé moins que le montant dû.
       ------------------------------------------------------------------ */

    public function test_a_full_payment_is_accepted(): void
    {
        $this->assertSame('paid', CoinPayments::resolve([
            'status' => 100, 'amountf' => '25.00000000', 'receivedf' => '25.00000000',
        ]));
    }

    public function test_an_overpayment_is_accepted(): void
    {
        // Le payeur a envoyé plus que demandé : la commande est due, pas bloquée.
        $this->assertSame('paid', CoinPayments::resolve([
            'status' => 100, 'amountf' => '25.00000000', 'receivedf' => '25.40000000',
        ]));
    }

    public function test_an_underpayment_is_never_marked_paid(): void
    {
        // 1 gourde envoyée sur une facture de 10 000 : le statut disait « payé ».
        $this->assertSame('pending', CoinPayments::resolve([
            'status' => 100, 'amountf' => '250.00000000', 'receivedf' => '0.00002500',
        ]));

        // Même un centime manquant ne suffit pas.
        $this->assertSame('pending', CoinPayments::resolve([
            'status' => 100, 'amountf' => '25.00000000', 'receivedf' => '24.99000000',
        ]));
    }

    public function test_an_underpayment_stays_open_rather_than_failing(): void
    {
        // « failed » figerait une transaction où de la crypto RÉELLE a été reçue
        // et que le payeur peut encore compléter. CoinPayments finit par expirer
        // la transaction lui-même, et le statut négatif donne alors « failed ».
        $this->assertNotSame('failed', CoinPayments::resolve([
            'status' => 100, 'amountf' => '25.00000000', 'receivedf' => '10.00000000',
        ]));
    }

    public function test_a_rounding_of_one_satoshi_does_not_block_a_full_payment(): void
    {
        $this->assertSame('paid', CoinPayments::resolve([
            'status' => 100, 'amountf' => '0.00250000', 'receivedf' => '0.00249999',
        ]));
    }

    public function test_without_amount_fields_nothing_is_declared_paid(): void
    {
        // Réponse tronquée ou API modifiée : on refuse de conclure au paiement
        // plutôt que de faire confiance au seul statut.
        $this->assertSame('pending', CoinPayments::resolve(['status' => 100]));
        $this->assertSame('pending', CoinPayments::resolve([]));
    }

    public function test_a_failed_or_pending_status_is_passed_through_unchanged(): void
    {
        // Le montant n'entre en jeu que pour CONFIRMER un paiement, jamais pour
        // en inventer un.
        $this->assertSame('failed', CoinPayments::resolve([
            'status' => -1, 'amountf' => '25.00000000', 'receivedf' => '25.00000000',
        ]));
        $this->assertSame('pending', CoinPayments::resolve([
            'status' => 0, 'amountf' => '25.00000000', 'receivedf' => '0.00000000',
        ]));
    }
}
