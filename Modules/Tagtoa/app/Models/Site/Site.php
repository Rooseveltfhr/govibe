<?php

namespace Modules\Tagtoa\App\Models\Site;

use App\Models\Vcard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Tagtoa\App\Models\Links\LinkPage;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Pay\PaymentPage;

/**
 * TAGTOA SITE — site web professionnel (vitrine) servi sur tagtoa.com/site/{alias}.
 */
class Site extends Model
{
    public const THEMES = ['light', 'dark'];

    protected $table = 'tagtoa_sites';

    protected $fillable = [
        'vcard_id', 'tenant_id', 'name', 'alias', 'tagline', 'about',
        'logo_path', 'cover_path', 'theme', 'accent_color',
        'phone', 'whatsapp', 'email', 'address', 'map_url',
        'services', 'hours', 'socials', 'gallery',
        'menu_id', 'pay_page_id', 'link_page_id',
        'show_services', 'show_hours', 'show_gallery', 'show_contact',
        'is_published', 'views',
    ];

    protected $casts = [
        'services'      => 'array',
        'hours'         => 'array',
        'socials'       => 'array',
        'gallery'       => 'array',
        'show_services' => 'boolean',
        'show_hours'    => 'boolean',
        'show_gallery'  => 'boolean',
        'show_contact'  => 'boolean',
        'is_published'  => 'boolean',
        'views'         => 'integer',
    ];

    public static function generateAlias(string $base): string
    {
        $slug = Str::slug($base) ?: 'site';
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

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'menu_id');
    }

    public function payPage(): BelongsTo
    {
        return $this->belongsTo(PaymentPage::class, 'pay_page_id');
    }

    public function linkPage(): BelongsTo
    {
        return $this->belongsTo(LinkPage::class, 'link_page_id');
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? Storage::url($this->logo_path) : null;
    }

    public function getCoverUrlAttribute(): ?string
    {
        return $this->cover_path ? Storage::url($this->cover_path) : null;
    }

    public function getWhatsappDigitsAttribute(): ?string
    {
        return $this->whatsapp ? preg_replace('/\D+/', '', $this->whatsapp) : null;
    }

    public function getPublicUrlAttribute(): string
    {
        return url('/site/'.$this->alias);
    }

    /** Icône FA pour un réseau social (réutilise la détection de Links). */
    public function socialIcon(string $platform): string
    {
        return \Modules\Tagtoa\App\Models\Links\Link::PLATFORM_ICONS[$platform]
            ?? 'fa-solid fa-globe';
    }
}
