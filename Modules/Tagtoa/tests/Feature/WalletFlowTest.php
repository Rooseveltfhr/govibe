<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA EVENT — Tests Feature wallet closed-loop (money-critical)
|--------------------------------------------------------------------------
| ⚠️ Nécessite l'application Biztap (Laravel + DB). À exécuter DANS Biztap :
|
|   cp -r Modules/Tagtoa /var/www/biztap/Modules/
|   cd /var/www/biztap && php artisan test --filter=WalletFlowTest
|
| Ne tourne PAS dans la CI de ce dépôt (pas d'app hôte). La CI couvre la
| logique pure du ledger (tests/Unit/LedgerTest). Ces tests vérifient
| l'intégration DB : double-entry, soldes, idempotence, fonds insuffisants.
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Actions\Event\Wallet\ChargeWallet;
use Modules\Tagtoa\App\Actions\Event\Wallet\IssueNfcTag;
use Modules\Tagtoa\App\Actions\Event\Wallet\OpenEventWalletAccounts;
use Modules\Tagtoa\App\Actions\Event\Wallet\PayoutToOrganizer;
use Modules\Tagtoa\App\Actions\Event\Wallet\RefundWallet;
use Modules\Tagtoa\App\Actions\Event\Wallet\ResolveNfcTag;
use Modules\Tagtoa\App\Actions\Event\Wallet\TopUpWallet;
use Modules\Tagtoa\App\Exceptions\InsufficientFundsException;
use Modules\Tagtoa\App\Models\Event\Event;
use Modules\Tagtoa\App\Models\Event\WalletAccount;
use Modules\Tagtoa\App\Models\Event\WalletEntry;
use Tests\TestCase;

class WalletFlowTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private WalletAccount $participant;

    private WalletAccount $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::create([
            'title' => 'Wallet Test', 'alias' => 'wallet-test', 'currency' => 'HTG', 'is_published' => true,
        ]);
        app(OpenEventWalletAccounts::class)->handle($this->event);

        $tag = app(IssueNfcTag::class)->handle($this->event, 'UID-TEST-001', ['label' => 'Client A']);
        $this->participant = $tag->walletAccount;
        $this->vendor = OpenEventWalletAccounts::vendor($this->event, 'Bar principal');
    }

    /** Vérifie que la somme des écritures d'un compte == son solde caché. */
    private function assertLedgerConsistent(WalletAccount $acct): void
    {
        $sum = WalletEntry::where('account_id', $acct->id)->get()
            ->sum(fn ($e) => $e->direction === WalletEntry::CREDIT ? $e->amount_minor : -$e->amount_minor);
        $this->assertSame((int) $acct->fresh()->balance_minor, (int) $sum, 'solde caché == somme du ledger');
    }

    public function test_topup_credits_participant_and_balances(): void
    {
        $txn = app(TopUpWallet::class)->handle($this->participant, 1000, ['idempotency_key' => 'k-top-1']);

        $this->assertSame(1000, (int) $this->participant->fresh()->balance_minor);
        $clearing = OpenEventWalletAccounts::system($this->event, WalletAccount::TYPE_CLEARING);
        $this->assertSame(-1000, (int) $clearing->fresh()->balance_minor); // contrepartie négative
        $this->assertCount(2, $txn->entries); // double-entry
        $this->assertLedgerConsistent($this->participant);
    }

    public function test_topup_is_idempotent(): void
    {
        $a = app(TopUpWallet::class)->handle($this->participant, 1000, ['idempotency_key' => 'k-dup']);
        $b = app(TopUpWallet::class)->handle($this->participant, 1000, ['idempotency_key' => 'k-dup']);

        $this->assertSame($a->id, $b->id);                                   // même transaction
        $this->assertSame(1000, (int) $this->participant->fresh()->balance_minor); // pas de double crédit
    }

    public function test_charge_moves_funds_to_vendor(): void
    {
        app(TopUpWallet::class)->handle($this->participant, 1000, ['idempotency_key' => 'k1']);
        app(ChargeWallet::class)->handle($this->participant, $this->vendor, 300, ['idempotency_key' => 'k2']);

        $this->assertSame(700, (int) $this->participant->fresh()->balance_minor);
        $this->assertSame(300, (int) $this->vendor->fresh()->balance_minor);
        $this->assertLedgerConsistent($this->participant);
        $this->assertLedgerConsistent($this->vendor);
    }

    public function test_charge_refused_when_insufficient(): void
    {
        $this->expectException(InsufficientFundsException::class);
        app(ChargeWallet::class)->handle($this->participant, $this->vendor, 500); // solde 0
    }

    public function test_refund_returns_balance(): void
    {
        app(TopUpWallet::class)->handle($this->participant, 1000, ['idempotency_key' => 'k1']);
        app(RefundWallet::class)->handle($this->participant, 400, ['idempotency_key' => 'kr']);

        $this->assertSame(600, (int) $this->participant->fresh()->balance_minor);
    }

    public function test_payout_vendor_to_organizer(): void
    {
        app(TopUpWallet::class)->handle($this->participant, 1000, ['idempotency_key' => 'k1']);
        app(ChargeWallet::class)->handle($this->participant, $this->vendor, 800, ['idempotency_key' => 'k2']);
        app(PayoutToOrganizer::class)->handle($this->vendor, 800, ['idempotency_key' => 'kp']);

        $this->assertSame(0, (int) $this->vendor->fresh()->balance_minor);
        $organizer = OpenEventWalletAccounts::system($this->event, WalletAccount::TYPE_ORGANIZER);
        $this->assertSame(800, (int) $organizer->fresh()->balance_minor);
    }

    public function test_resolve_nfc_tag_returns_participant(): void
    {
        $resolved = app(ResolveNfcTag::class)->handle($this->event, 'UID-TEST-001');
        $this->assertNotNull($resolved);
        $this->assertSame($this->participant->id, $resolved->id);

        $this->assertNull(app(ResolveNfcTag::class)->handle($this->event, 'UID-UNKNOWN'));
    }
}
