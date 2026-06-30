<?php

namespace Modules\Tagtoa\App\Actions\Event\Wallet;

use Modules\Tagtoa\App\Models\Event\WalletAccount;
use Modules\Tagtoa\App\Models\Event\WalletTxn;

/**
 * TAGTOA EVENT — recharge (top_up) : gateway_clearing -> participant.
 * Idempotent via opts['idempotency_key'] (obligatoire côté appelant réseau).
 */
class TopUpWallet
{
    public function __construct(protected PostLedgerTransaction $poster)
    {
    }

    public function handle(WalletAccount $participant, int $amount, array $opts = []): WalletTxn
    {
        $clearing = OpenEventWalletAccounts::system($participant->event, WalletAccount::TYPE_CLEARING);

        return $this->poster->handle(WalletTxn::TYPE_TOPUP, $clearing, $participant, $amount, $opts);
    }
}
