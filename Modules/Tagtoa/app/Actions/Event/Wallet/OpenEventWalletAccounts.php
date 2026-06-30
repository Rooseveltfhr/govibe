<?php

namespace Modules\Tagtoa\App\Actions\Event\Wallet;

use Modules\Tagtoa\App\Models\Event\Event;
use Modules\Tagtoa\App\Models\Event\WalletAccount;

/**
 * TAGTOA EVENT — crée (idempotent) les comptes système d'un event nécessaires
 * au double-entry : gateway_clearing, house, organizer.
 */
class OpenEventWalletAccounts
{
    public function handle(Event $event): array
    {
        return [
            WalletAccount::TYPE_CLEARING  => self::system($event, WalletAccount::TYPE_CLEARING),
            WalletAccount::TYPE_HOUSE     => self::system($event, WalletAccount::TYPE_HOUSE),
            WalletAccount::TYPE_ORGANIZER => self::system($event, WalletAccount::TYPE_ORGANIZER),
        ];
    }

    /** Compte système d'un event (créé si absent). */
    public static function system(Event $event, string $type): WalletAccount
    {
        return WalletAccount::firstOrCreate(
            ['event_id' => $event->id, 'type' => $type, 'nfc_tag_id' => null],
            [
                'tenant_id'     => $event->tenant_id,
                'currency'      => $event->currency ?: 'HTG',
                'owner_label'   => ucfirst(str_replace('_', ' ', $type)),
                'balance_minor' => 0,
                'status'        => 'active',
            ]
        );
    }

    /** Compte vendeur/stand d'un event (créé si absent, identifié par libellé). */
    public static function vendor(Event $event, string $label): WalletAccount
    {
        return WalletAccount::firstOrCreate(
            ['event_id' => $event->id, 'type' => WalletAccount::TYPE_VENDOR, 'owner_label' => $label],
            [
                'tenant_id'     => $event->tenant_id,
                'currency'      => $event->currency ?: 'HTG',
                'balance_minor' => 0,
                'status'        => 'active',
            ]
        );
    }
}
