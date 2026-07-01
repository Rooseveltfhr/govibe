<?php

namespace Modules\Tagtoa\App\Actions\Event\Wallet;

use Modules\Tagtoa\App\Models\Event\Event;
use Modules\Tagtoa\App\Models\Event\NfcTag;
use Modules\Tagtoa\App\Models\Event\WalletAccount;

/**
 * TAGTOA EVENT — résout un UID NFC (tap) vers le compte participant.
 * Retourne null si le tag est inconnu ou inactif (perdu/désactivé).
 */
class ResolveNfcTag
{
    public function handle(Event $event, string $uid): ?WalletAccount
    {
        $tag = NfcTag::where('event_id', $event->id)
            ->where('uid_hash', NfcTag::hashUid($uid))
            ->where('status', 'active')
            ->first();

        return $tag?->walletAccount;
    }
}
