<?php

namespace Modules\Tagtoa\App\Models\Loyalty;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TAGTOA Loyalty — mouvement sur une carte.
 */
class Transaction extends Model
{
    public const TYPE_TOP_UP = 'top_up';
    public const TYPE_REDEEM = 'redeem';

    protected $table = 'tagtoa_loyalty_transactions';

    protected $fillable = [
        'card_id', 'reward_id', 'type', 'amount', 'points_delta',
        'balance_after', 'points_after', 'payment_method', 'reference', 'note', 'status',
    ];

    protected $casts = [
        'amount'        => 'decimal:2',
        'balance_after' => 'decimal:2',
        'points_delta'  => 'integer',
        'points_after'  => 'integer',
        'status'        => 'integer',
    ];

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class, 'card_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return $this->type === self::TYPE_TOP_UP ? __('Recharge') : __('Utilisation');
    }

    public function isCredit(): bool
    {
        return $this->type === self::TYPE_TOP_UP;
    }
}
