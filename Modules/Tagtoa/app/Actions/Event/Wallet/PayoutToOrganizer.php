<?php

namespace Modules\Tagtoa\App\Actions\Event\Wallet;

use Modules\Tagtoa\App\Models\Event\WalletAccount;
use Modules\Tagtoa\App\Models\Event\WalletTxn;

/**
 * TAGTOA EVENT — règlement d'un vendeur vers l'organisateur (payout) : vendor -> organizer.
 */
class PayoutToOrganizer
{
    public function __construct(protected PostLedgerTransaction $poster)
    {
    }

    public function handle(WalletAccount $vendor, int $amount, array $opts = []): WalletTxn
    {
        $organizer = OpenEventWalletAccounts::system($vendor->event, WalletAccount::TYPE_ORGANIZER);

        return $this->poster->handle(WalletTxn::TYPE_PAYOUT, $vendor, $organizer, $amount, $opts);
    }
}
