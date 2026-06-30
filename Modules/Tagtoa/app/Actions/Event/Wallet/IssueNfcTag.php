<?php

namespace Modules\Tagtoa\App\Actions\Event\Wallet;

use Illuminate\Support\Facades\Crypt;
use Modules\Tagtoa\App\Models\Event\Event;
use Modules\Tagtoa\App\Models\Event\NfcTag;
use Modules\Tagtoa\App\Models\Event\WalletAccount;

/**
 * TAGTOA EVENT — enregistre un tag NFC (UID hashé + chiffré) et lui rattache un
 * compte participant (1 tag = 1 compte). Idempotent sur (event_id, uid_hash).
 */
class IssueNfcTag
{
    public function handle(Event $event, string $uid, array $opts = []): NfcTag
    {
        $kind = $opts['kind'] ?? 'card';

        $tag = NfcTag::firstOrCreate(
            ['event_id' => $event->id, 'uid_hash' => NfcTag::hashUid($uid)],
            [
                'tenant_id'   => $event->tenant_id,
                'uid_enc'     => Crypt::encryptString(trim($uid)),
                'label'       => $opts['label'] ?? null,
                'kind'        => in_array($kind, NfcTag::KINDS, true) ? $kind : 'card',
                'ticket_id'   => $opts['ticket_id'] ?? null,
                'status'      => 'active',
                'assigned_at' => now(),
            ]
        );

        WalletAccount::firstOrCreate(
            ['nfc_tag_id' => $tag->id],
            [
                'tenant_id'     => $event->tenant_id,
                'event_id'      => $event->id,
                'ticket_id'     => $tag->ticket_id,
                'type'          => WalletAccount::TYPE_PARTICIPANT,
                'owner_label'   => $opts['label'] ?? null,
                'currency'      => $event->currency ?: 'HTG',
                'balance_minor' => 0,
                'status'        => 'active',
            ]
        );

        return $tag->fresh('walletAccount');
    }
}
