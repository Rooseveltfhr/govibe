<?php

namespace Modules\Tagtoa\App\Models\Event;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TAGTOA EVENT — compte de valeur (wallet closed-loop).
 * balance_minor est un CACHE (unités mineures). La vérité = somme des écritures.
 */
class WalletAccount extends Model
{
    protected $table = 'tagtoa_ev_wallet_accounts';

    /** Comptes "valeur" (porteur) vs comptes "système" (contreparties). */
    public const TYPE_PARTICIPANT = 'participant';
    public const TYPE_VENDOR = 'vendor';
    public const TYPE_ORGANIZER = 'organizer';
    public const TYPE_CLEARING = 'gateway_clearing';
    public const TYPE_HOUSE = 'house';

    public const TYPES = [
        self::TYPE_PARTICIPANT, self::TYPE_VENDOR, self::TYPE_ORGANIZER,
        self::TYPE_CLEARING, self::TYPE_HOUSE,
    ];

    /** Comptes système : peuvent passer en négatif (contreparties). */
    public const SYSTEM_TYPES = [self::TYPE_CLEARING, self::TYPE_HOUSE];

    protected $fillable = [
        'tenant_id', 'event_id', 'nfc_tag_id', 'ticket_id', 'type',
        'owner_label', 'owner_phone', 'currency', 'balance_minor', 'status',
    ];

    protected $casts = [
        'balance_minor' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function nfcTag(): BelongsTo
    {
        return $this->belongsTo(NfcTag::class, 'nfc_tag_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(WalletEntry::class, 'account_id');
    }

    /** Compte porteur de valeur (ne peut pas passer négatif). */
    public function isValueAccount(): bool
    {
        return ! in_array($this->type, self::SYSTEM_TYPES, true);
    }
}
