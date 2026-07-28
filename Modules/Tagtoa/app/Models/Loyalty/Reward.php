<?php

namespace Modules\Tagtoa\App\Models\Loyalty;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TAGTOA Loyalty — récompense échangeable contre des points.
 */
class Reward extends Model
{
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
        return $this->belongsTo(Program::class, 'program_id');
    }

    public function getDiscountLabelAttribute(): string
    {
        if ($this->discount_value === null) {
            return $this->name;
        }
        return $this->discount_type === 'percent'
            ? rtrim(rtrim((string) $this->discount_value, '0'), '.').'%'
            : number_format((float) $this->discount_value, 2);
    }
}
