<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TAGTOA LOYALTY — récompense échangeable contre des points.
 *
 * @property int    $id
 * @property int    $program_id
 * @property string $name
 * @property int    $points_required
 * @property float  $discount_value
 * @property string $discount_type
 * @property bool   $is_active
 */
class TaGtoaLoyaltyReward extends Model
{
    use HasFactory;

    public const TYPE_FIXED   = 'fixed';
    public const TYPE_PERCENT = 'percent';

    protected $table = 'tagtoa_loyalty_rewards';

    protected $fillable = [
        'program_id', 'name', 'description', 'points_required',
        'discount_value', 'discount_type', 'is_active',
    ];

    protected $casts = [
        'points_required' => 'integer',
        'discount_value'  => 'decimal:2',
        'is_active'       => 'boolean',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(TaGtoaLoyaltyProgram::class, 'program_id');
    }

    public function getDiscountLabelAttribute(): string
    {
        if ($this->discount_value === null) {
            return $this->name;
        }
        return $this->discount_type === self::TYPE_PERCENT
            ? rtrim(rtrim((string) $this->discount_value, '0'), '.') . '%'
            : number_format((float) $this->discount_value, 2);
    }
}
