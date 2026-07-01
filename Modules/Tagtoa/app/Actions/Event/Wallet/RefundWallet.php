<?php

namespace Modules\Tagtoa\App\Actions\Event\Wallet;

use Modules\Tagtoa\App\Models\Event\WalletAccount;
use Modules\Tagtoa\App\Models\Event\WalletTxn;

/**
 * TAGTOA EVENT — remboursement du solde participant : participant -> gateway_clearing.
 */
class RefundWallet
{
    public function __construct(protected PostLedgerTransaction $poster)
    {
    }

    public function handle(WalletAccount $participant, int $amount, array $opts = []): WalletTxn
    {
        $clearing = OpenEventWalletAccounts::system($participant->event, WalletAccount::TYPE_CLEARING);

        return $this->poster->handle(WalletTxn::TYPE_REFUND, $participant, $clearing, $amount, $opts);
    }
}
