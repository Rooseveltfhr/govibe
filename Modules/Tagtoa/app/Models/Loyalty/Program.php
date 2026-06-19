<?php

namespace Modules\Tagtoa\App\Models\Loyalty;

use App\Models\Vcard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * TAGTOA Loyalty — programme de fidélité.
 */
class Program extends Model
{
    protected $table = 'tagtoa_loyalty_programs';

    protected $fillable = [
        'vcard_id', 'tenant_id', 'name', 'alias', 'description',
        'points_per_dollar', 'dollar_per_point', 'currency', 'logo_path', 'is_active',
    ];

    protected $casts = [
        'points_per_dollar' => 'decimal:2',
        'dollar_per_point'  => 'decimal:4',
        'is_active'         => 'boolean',
    ];

    public static function generateAlias(string $base): string
    {
        $slug = Str::slug($base) ?: 'loyalty';
        $alias = $slug; $i = 1;
        while (static::query()->where('alias', $alias)->exists()) {
            $alias = $slug.'-'.(++$i);
        }
        return $alias;
    }

    public function vcard(): BelongsTo
    {
        return $this->belongsTo(Vcard::class, 'vcard_id');
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class, 'program_id');
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(Reward::class, 'program_id');
    }

    public function activeRewards(): HasMany
    {
        return $this->rewards()->where('is_active', true)->orderBy('points_required');
    }

    public function pointsForAmount(float $amount): int
    {
        return (int) floor($amount * (float) $this->points_per_dollar);
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? Storage::url($this->logo_path) : null;
    }
}
