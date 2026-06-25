<?php

namespace Modules\Tagtoa\App\Models\Menu;

use App\Models\Vcard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Tagtoa\App\Models\Pay\PaymentPage;

/**
 * TAGTOA MENU — menu digital d'un établissement (tagtoa.com/menu/{alias}).
 * Restaurants, clubs, lounges, hôtels, bars, cafés : vente de produits & services
 * via NFC/QR, avec commande WhatsApp et lien de paiement TAGTOA Pay optionnel.
 */
class Menu extends Model
{
    protected $table = 'tagtoa_menus';

    /** Types d'établissement : label + icône FA. */
    public const TYPES = [
        'restaurant' => ['label' => 'Restaurant', 'icon' => 'fa-solid fa-utensils'],
        'cafe'       => ['label' => 'Café',        'icon' => 'fa-solid fa-mug-hot'],
        'bar'        => ['label' => 'Bar',         'icon' => 'fa-solid fa-martini-glass'],
        'club'       => ['label' => 'Club',        'icon' => 'fa-solid fa-record-vinyl'],
        'lounge'     => ['label' => 'Lounge',      'icon' => 'fa-solid fa-couch'],
        'hotel'      => ['label' => 'Hôtel',       'icon' => 'fa-solid fa-hotel'],
        'other'      => ['label' => 'Autre',       'icon' => 'fa-solid fa-store'],
    ];

    public const THEMES = ['light', 'dark'];

    protected $fillable = [
        'vcard_id', 'tenant_id', 'name', 'alias', 'type', 'tagline', 'description',
        'logo_path', 'cover_path', 'currency', 'whatsapp', 'phone', 'address',
        'pay_page_id', 'accent_color', 'theme', 'show_prices', 'ordering_enabled',
        'is_active', 'views',
    ];

    protected $casts = [
        'show_prices'      => 'boolean',
        'ordering_enabled' => 'boolean',
        'is_active'        => 'boolean',
        'views'            => 'integer',
    ];

    public static function generateAlias(string $base): string
    {
        $slug = Str::slug($base) ?: 'menu';
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

    public function payPage(): BelongsTo
    {
        return $this->belongsTo(PaymentPage::class, 'pay_page_id');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class, 'menu_id')->orderBy('sort');
    }

    public function activeCategories(): HasMany
    {
        return $this->categories()->where('is_active', true);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'menu_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'menu_id')->latest();
    }

    public function getTypeMetaAttribute(): array
    {
        return self::TYPES[$this->type] ?? self::TYPES['other'];
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
        return url('/menu/'.$this->alias);
    }

    /** Numéro WhatsApp normalisé (chiffres uniquement) pour wa.me. */
    public function getWhatsappDigitsAttribute(): ?string
    {
        return $this->whatsapp ? preg_replace('/\D+/', '', $this->whatsapp) : null;
    }
}
