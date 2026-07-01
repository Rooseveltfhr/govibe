<?php

namespace Modules\Tagtoa\App\Models\Event;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * TAGTOA EVENT — tag NFC (carte/bracelet/virtuel) relié à un billet et/ou un wallet.
 * L'UID n'est jamais stocké en clair : on hashe (uid_hash) et on chiffre (uid_enc).
 */
class NfcTag extends Model
{
    protected $table = 'tagtoa_ev_nfc_tags';

    public const KINDS = ['card', 'wristband', 'virtual'];
    public const STATUSES = ['active', 'lost', 'disabled'];

    protected $fillable = [
        'tenant_id', 'event_id', 'uid_hash', 'uid_enc', 'label', 'kind',
        'ticket_id', 'status', 'assigned_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    /** Hash stable d'un UID NFC (jamais l'UID en clair en base/lookup). */
    public static function hashUid(string $uid): string
    {
        return hash('sha256', trim($uid));
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function walletAccount(): HasOne
    {
        return $this->hasOne(WalletAccount::class, 'nfc_tag_id');
    }

    public function isUsable(): bool
    {
        return $this->status === 'active';
    }
}
