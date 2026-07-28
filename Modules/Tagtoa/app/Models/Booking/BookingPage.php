<?php

namespace Modules\Tagtoa\App\Models\Booking;

use App\Models\Vcard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Tagtoa\App\Models\Pay\PaymentPage;

/**
 * TAGTOA BOOKING — page de réservation (salon, clinique, consultant, coach…).
 * Le client réserve un créneau pour une prestation via NFC/QR : tagtoa.com/book/{alias}.
 */
class BookingPage extends Model
{
    protected $table = 'tagtoa_booking_pages';

    public const THEMES = ['light', 'dark'];

    protected $fillable = [
        'vcard_id', 'tenant_id', 'name', 'alias', 'tagline', 'about',
        'logo_path', 'cover_path', 'theme', 'accent_color', 'phone', 'whatsapp',
        'email', 'address', 'currency', 'pay_page_id', 'is_active', 'views',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'views'     => 'integer',
    ];

    public static function generateAlias(string $base): string
    {
        $slug = Str::slug($base) ?: 'booking';
        $alias = $slug;
        $i = 1;
        while (static::query()->where('alias', $alias)->exists()) {
            $alias = $slug.'-'.(++$i);
        }

        return $alias;
    }

    public function vcard(): BelongsTo
    {
        return $this->belongsTo(Vcard::class, 'vcard_id');
    }

    public function payPage(): BelongsTo
    {
        return $this->belongsTo(PaymentPage::class, 'pay_page_id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'booking_page_id')->orderBy('sort');
    }

    public function activeServices(): HasMany
    {
        return $this->services()->where('is_active', true);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'booking_page_id')->latest('starts_at');
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? Storage::url($this->logo_path) : null;
    }

    public function getCoverUrlAttribute(): ?string
    {
        return $this->cover_path ? Storage::url($this->cover_path) : null;
    }

    public function getPublicUrlAttribute(): string
    {
        return url('/book/'.$this->alias);
    }

    /** Numéro WhatsApp normalisé (chiffres uniquement) pour wa.me. */
    public function getWhatsappDigitsAttribute(): ?string
    {
        return $this->whatsapp ? preg_replace('/\D+/', '', $this->whatsapp) : null;
    }
}
