<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TAGTOA LOYALTY — mouvement sur une carte.
 *
 * @property int    $id
 * @property int    $card_id
 * @property int    $reward_id
 * @property string $type
 * @property float  $amount
 * @property int    $points_delta
 * @property string $payment_method
 * @property int    $status
 */
class TaGtoaLoyaltyTransaction extends Model
{
    use HasFactory;

    public const TYPE_TOP_UP     = 'top_up';
    public const TYPE_REDEEM     = 'redeem';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_REFUND     = 'refund';

    public const STATUS_PENDING   = 0;
    public const STATUS_CONFIRMED = 1;
    public const STATUS_FAILED    = 2;

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
        return $this->belongsTo(TaGtoaLoyaltyCard::class, 'card_id');
    }

    public function reward(): BelongsTo
    {
        return $this->belongsTo(TaGtoaLoyaltyReward::class, 'reward_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return [
            self::TYPE_TOP_UP     => __('Recharge'),
            self::TYPE_REDEEM     => __('Utilisation'),
            self::TYPE_ADJUSTMENT => __('Ajustement'),
            self::TYPE_REFUND     => __('Remboursement'),
        ][$this->type] ?? ucfirst($this->type);
    }

    public function isCredit(): bool
    {
        return in_array($this->type, [self::TYPE_TOP_UP, self::TYPE_REFUND], true);
    }
}
