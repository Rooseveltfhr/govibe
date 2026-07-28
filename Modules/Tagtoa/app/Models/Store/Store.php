<?php

namespace Modules\Tagtoa\App\Models\Store;

use App\Models\Vcard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Tagtoa\App\Models\Pay\PaymentPage;

/**
 * TAGTOA STORE — boutique en ligne (tagtoa.com/store/{alias}).
 * Catalogue de produits, panier, commande WhatsApp + paiement TAGTOA Pay.
 */
class Store extends Model
{
    protected $table = 'tagtoa_stores';

    protected $fillable = [
        'vcard_id', 'tenant_id', 'name', 'alias', 'tagline', 'description',
        'logo_path', 'cover_path', 'currency', 'whatsapp', 'phone', 'address',
        'delivery_note', 'pay_page_id', 'accent_color', 'is_published', 'views',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'views'        => 'integer',
    ];

    public static function generateAlias(string $base): string
    {
        $slug = Str::slug($base) ?: 'boutik';
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

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'store_id')->orderBy('sort')->orderBy('id');
    }

    public function availableProducts(): HasMany
    {
        return $this->products()->where('is_available', true);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'store_id')->latest();
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
        return url('/store/'.$this->alias);
    }

    /** Numéro WhatsApp normalisé (chiffres) pour wa.me. */
    public function getWhatsappDigitsAttribute(): ?string
    {
        return $this->whatsapp ? preg_replace('/\D+/', '', $this->whatsapp) : null;
    }
}
