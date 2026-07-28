<?php

namespace Modules\Tagtoa\App\Actions\Event\Wallet;

use Modules\Tagtoa\App\Models\Event\WalletAccount;
use Modules\Tagtoa\App\Models\Event\WalletTxn;

/**
 * TAGTOA EVENT — achat chez un vendeur (purchase) : participant -> vendor.
 * Le solde du participant est vérifié sous verrou ; débit refusé si insuffisant.
 */
class ChargeWallet
{
    public function __construct(protected PostLedgerTransaction $poster)
    {
    }

    public function handle(WalletAccount $participant, WalletAccount $vendor, int $amount, array $opts = []): WalletTxn
    {
        return $this->poster->handle(WalletTxn::TYPE_PURCHASE, $participant, $vendor, $amount, $opts);
    }
}
