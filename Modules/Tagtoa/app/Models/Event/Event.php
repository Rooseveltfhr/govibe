<?php

namespace Modules\Tagtoa\App\Models\Event;

use App\Models\Vcard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Tagtoa\App\Models\Pay\PaymentPage;

/**
 * TAGTOA Event — événement (billetterie + check-in NFC/QR).
 */
class Event extends Model
{
    protected $table = 'tagtoa_ev_events';

    protected $fillable = [
        'vcard_id', 'tenant_id', 'title', 'alias', 'type', 'description', 'venue', 'address',
        'starts_at', 'ends_at', 'currency', 'is_free', 'is_published', 'pay_page_id', 'cover_path', 'views',
    ];

    protected $casts = [
        'starts_at' => 'datetime', 'ends_at' => 'datetime',
        'is_free' => 'boolean', 'is_published' => 'boolean', 'views' => 'integer',
    ];

    public static function generateAlias(string $base): string
    {
        $slug = Str::slug($base) ?: 'event';
        $alias = $slug; $i = 1;
        while (static::query()->where('alias', $alias)->exists()) {
            $alias = $slug.'-'.(++$i);
        }
        return $alias;
    }

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class, 'event_id')->orderBy('sort');
    }

    public function activeTicketTypes(): HasMany
    {
        return $this->ticketTypes()->where('is_active', true);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'event_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'event_id');
    }

    public function payPage(): BelongsTo
    {
        return $this->belongsTo(PaymentPage::class, 'pay_page_id');
    }

    public function getCoverUrlAttribute(): ?string
    {
        return $this->cover_path ? Storage::url($this->cover_path) : null;
    }

    public function getPublicUrlAttribute(): string
    {
        return url('/event/'.$this->alias);
    }
}
