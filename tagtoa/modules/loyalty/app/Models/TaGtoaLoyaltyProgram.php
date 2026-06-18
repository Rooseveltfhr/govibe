<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * TAGTOA LOYALTY — programme de fidélité.
 *
 * @property int    $id
 * @property int    $vcard_id
 * @property string $tenant_id
 * @property string $name
 * @property string $alias
 * @property string $description
 * @property float  $points_per_dollar
 * @property float  $dollar_per_point
 * @property string $currency
 * @property bool   $is_active
 */
class TaGtoaLoyaltyProgram extends Model implements HasMedia
{
    use HasFactory;
    use BelongsToTenant;
    use InteractsWithMedia;

    protected $table = 'tagtoa_loyalty_programs';

    protected $fillable = [
        'vcard_id', 'tenant_id', 'name', 'alias', 'description',
        'points_per_dollar', 'dollar_per_point', 'currency', 'is_active',
    ];

    protected $casts = [
        'points_per_dollar' => 'decimal:2',
        'dollar_per_point'  => 'decimal:4',
        'is_active'         => 'boolean',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('program-logo')->singleFile();
    }

    public static function generateAlias(string $base): string
    {
        $slug  = Str::slug($base) ?: 'loyalty';
        $alias = $slug;
        $i     = 1;
        while (static::query()->where('alias', $alias)->exists()) {
            $alias = $slug . '-' . (++$i);
        }
        return $alias;
    }

    public function vcard(): BelongsTo
    {
        return $this->belongsTo(Vcard::class);
    }

    public function cards(): HasMany
    {
        return $this->hasMany(TaGtoaLoyaltyCard::class, 'program_id');
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(TaGtoaLoyaltyReward::class, 'program_id');
    }

    public function activeRewards(): HasMany
    {
        return $this->rewards()->where('is_active', true)->orderBy('points_required');
    }

    /** Convertit un montant en points selon le barème du programme. */
    public function pointsForAmount(float $amount): int
    {
        return (int) floor($amount * (float) $this->points_per_dollar);
    }

    /** Valeur monétaire de N points. */
    public function valueForPoints(int $points): float
    {
        return round($points * (float) $this->dollar_per_point, 2);
    }

    public function getLogoUrlAttribute(): ?string
    {
        $m = $this->getFirstMedia('program-logo');
        return $m ? $m->getUrl() : null;
    }
}
