<?php

namespace Modules\Tagtoa\App\Models\Event;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * TAGTOA EVENT — en-tête de transaction wallet. timestamps désactivés (created_at seul).
 */
class WalletTxn extends Model
{
    protected $table = 'tagtoa_ev_wallet_txns';

    public $timestamps = false;

    public const TYPE_TOPUP = 'top_up';
    public const TYPE_PURCHASE = 'purchase';
    public const TYPE_REFUND = 'refund';
    public const TYPE_PAYOUT = 'payout';
    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPES = [
        self::TYPE_TOPUP, self::TYPE_PURCHASE, self::TYPE_REFUND,
        self::TYPE_PAYOUT, self::TYPE_ADJUSTMENT,
    ];

    protected $fillable = [
        'tenant_id', 'event_id', 'type', 'reference', 'idempotency_key',
        'amount_minor', 'currency', 'status', 'source_account_id', 'dest_account_id',
        'payment_ref', 'meta', 'created_by', 'created_at',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'meta'         => 'array',
        'created_at'   => 'datetime',
    ];

    public static function generateReference(): string
    {
        return 'WTX-'.strtoupper(Str::random(12));
    }

    public function entries(): HasMany
    {
        return $this->hasMany(WalletEntry::class, 'txn_id');
    }

    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(WalletAccount::class, 'source_account_id');
    }

    public function destAccount(): BelongsTo
    {
        return $this->belongsTo(WalletAccount::class, 'dest_account_id');
    }
}
