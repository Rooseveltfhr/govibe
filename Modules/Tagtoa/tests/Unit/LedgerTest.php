<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Support\Event\Ledger;
use PHPUnit\Framework\TestCase;

/**
 * Cœur comptable du wallet (logique pure double-entry).
 */
class LedgerTest extends TestCase
{
    public function test_flows_known_and_unknown(): void
    {
        $this->assertSame(['gateway_clearing', 'participant', false], Ledger::flow('top_up'));
        $this->assertSame(['participant', 'vendor', true], Ledger::flow('purchase'));
        $this->assertNull(Ledger::flow('nope'));
    }

    public function test_requires_funds(): void
    {
        $this->assertFalse(Ledger::requiresFunds('top_up'));
        $this->assertTrue(Ledger::requiresFunds('purchase'));
        $this->assertTrue(Ledger::requiresFunds('refund'));
        $this->assertTrue(Ledger::requiresFunds('payout'));
    }

    public function test_balance_after_sign_convention(): void
    {
        $this->assertSame(150, Ledger::balanceAfter(100, Ledger::CREDIT, 50));
        $this->assertSame(50, Ledger::balanceAfter(100, Ledger::DEBIT, 50));
        $this->assertSame(-50, Ledger::balanceAfter(0, Ledger::DEBIT, 50)); // compte système
    }

    public function test_sufficient_funds(): void
    {
        // top_up : pas de contrainte de fonds sur la source (clearing)
        $this->assertTrue(Ledger::sufficientFunds('top_up', 0, 1000));
        // purchase : le participant doit avoir le solde
        $this->assertTrue(Ledger::sufficientFunds('purchase', 1000, 1000));
        $this->assertFalse(Ledger::sufficientFunds('purchase', 999, 1000));
        $this->assertFalse(Ledger::sufficientFunds('purchase', 1000, 0));
    }

    public function test_build_pair_topup_clearing_goes_negative(): void
    {
        // top_up de 500 : clearing 0 -> -500 (débit), participant 0 -> +500 (crédit)
        $pair = Ledger::buildPair('top_up', 0, 0, 500);
        $this->assertSame('gateway_clearing', $pair['debit']['role']);
        $this->assertSame(-500, $pair['debit']['balance_after']);
        $this->assertSame('participant', $pair['credit']['role']);
        $this->assertSame(500, $pair['credit']['balance_after']);
        $this->assertTrue(Ledger::isBalanced([$pair['debit'], $pair['credit']]));
    }

    public function test_build_pair_purchase_decrements_participant(): void
    {
        // achat de 300 : participant 1000 -> 700, vendor 200 -> 500
        $pair = Ledger::buildPair('purchase', 1000, 200, 300);
        $this->assertSame(700, $pair['debit']['balance_after']);
        $this->assertSame(500, $pair['credit']['balance_after']);
        $this->assertTrue(Ledger::isBalanced([$pair['debit'], $pair['credit']]));
    }

    public function test_build_pair_insufficient_funds_throws(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('insufficient_funds');
        Ledger::buildPair('purchase', 100, 0, 300);
    }

    public function test_build_pair_unknown_flow_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Ledger::buildPair('nope', 0, 0, 100);
    }

    public function test_build_pair_non_positive_amount_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Ledger::buildPair('top_up', 0, 0, 0);
    }

    public function test_is_balanced(): void
    {
        $this->assertTrue(Ledger::isBalanced([
            ['direction' => 'debit', 'amount_minor' => 500],
            ['direction' => 'credit', 'amount_minor' => 500],
        ]));
        // déséquilibré
        $this->assertFalse(Ledger::isBalanced([
            ['direction' => 'debit', 'amount_minor' => 500],
            ['direction' => 'credit', 'amount_minor' => 400],
        ]));
        // vide
        $this->assertFalse(Ledger::isBalanced([]));
    }
}
