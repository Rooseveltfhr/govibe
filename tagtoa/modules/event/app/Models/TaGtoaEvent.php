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
 * TAGTOA EVENT — événement (concert, expo, mariage, sport…).
 */
class TaGtoaEvent extends Model implements HasMedia
{
    use HasFactory;
    use BelongsToTenant;
    use InteractsWithMedia;

    protected $table = 'tagtoa_ev_events';

    protected $fillable = [
        'vcard_id', 'tenant_id', 'title', 'alias', 'type', 'description',
        'venue', 'address', 'starts_at', 'ends_at', 'currency',
        'is_free', 'is_published', 'pay_page_id', 'views',
    ];

    protected $casts = [
        'starts_at'    => 'datetime',
        'ends_at'      => 'datetime',
        'is_free'      => 'boolean',
        'is_published' => 'boolean',
        'views'        => 'integer',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('event-cover')->singleFile();
    }

    public static function generateAlias(string $base): string
    {
        $slug = Str::slug($base) ?: 'event';
        $alias = $slug; $i = 1;
        while (static::query()->where('alias', $alias)->exists()) {
            $alias = $slug . '-' . (++$i);
        }
        return $alias;
    }

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TaGtoaEvTicketType::class, 'event_id')->orderBy('sort');
    }

    public function activeTicketTypes(): HasMany
    {
        return $this->ticketTypes()->where('is_active', true);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(TaGtoaEvOrder::class, 'event_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(TaGtoaEvTicket::class, 'event_id');
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(TaGtoaEvSaleItem::class, 'event_id')->orderBy('sort');
    }

    public function payPage(): BelongsTo
    {
        return $this->belongsTo(TaGtoaPaymentPage::class, 'pay_page_id');
    }

    public function getCoverUrlAttribute(): ?string
    {
        $m = $this->getFirstMedia('event-cover');
        return $m ? $m->getUrl() : null;
    }

    public function getPublicUrlAttribute(): string
    {
        return url('/event/' . $this->alias);
    }
}
