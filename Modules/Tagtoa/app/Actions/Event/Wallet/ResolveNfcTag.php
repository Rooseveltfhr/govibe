<?php

namespace Modules\Tagtoa\App\Actions\Event\Wallet;

use Modules\Tagtoa\App\Models\Event\Event;
use Modules\Tagtoa\App\Models\Event\NfcTag;
use Modules\Tagtoa\App\Models\Event\WalletAccount;
use Modules\Tagtoa\App\Support\Nfc\NfcTapVerifier;

/**
 * TAGTOA EVENT — résout un UID NFC (tap) vers le compte participant.
 * Retourne null si le tag est inconnu, inactif (perdu/désactivé), ou si le
 * tag exige une vérification NTAG424 (SUN) qui échoue ou n'est pas fournie
 * ($ctr/$cmac, en hexadécimal — ignorés pour un tag qui n'exige pas SUN).
 */
class ResolveNfcTag
{
    public function handle(Event $event, string $uid, ?string $ctr = null, ?string $cmac = null): ?WalletAccount
    {
        $tag = NfcTag::where('event_id', $event->id)
            ->where('uid_hash', NfcTag::hashUid($uid))
            ->where('status', 'active')
            ->first();

        if (! $tag || ! NfcTapVerifier::passes($tag, $uid, $ctr, $cmac)) {
            return null;
        }

        return $tag->walletAccount;
    }
}
