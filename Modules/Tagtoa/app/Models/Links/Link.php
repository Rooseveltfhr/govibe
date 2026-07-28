<?php

namespace Modules\Tagtoa\App\Models\Links;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * TAGTOA Links — un lien avec détection automatique de plateforme.
 */
class Link extends Model
{
    public const PLATFORM_ICONS = [
        'facebook' => 'fa-brands fa-facebook', 'instagram' => 'fa-brands fa-instagram',
        'tiktok' => 'fa-brands fa-tiktok', 'youtube' => 'fa-brands fa-youtube',
        'twitter' => 'fa-brands fa-x-twitter', 'linkedin' => 'fa-brands fa-linkedin',
        'telegram' => 'fa-brands fa-telegram', 'whatsapp' => 'fa-brands fa-whatsapp',
        'snapchat' => 'fa-brands fa-snapchat', 'twitch' => 'fa-brands fa-twitch',
        'pinterest' => 'fa-brands fa-pinterest', 'discord' => 'fa-brands fa-discord',
        'spotify' => 'fa-brands fa-spotify', 'github' => 'fa-brands fa-github',
        'email' => 'fa-solid fa-envelope', 'phone' => 'fa-solid fa-phone',
        'website' => 'fa-solid fa-globe', 'custom' => 'fa-solid fa-link',
    ];

    private const HOST_MAP = [
        'facebook.com' => 'facebook', 'fb.com' => 'facebook', 'fb.me' => 'facebook',
        'instagram.com' => 'instagram', 'tiktok.com' => 'tiktok',
        'youtube.com' => 'youtube', 'youtu.be' => 'youtube',
        'twitter.com' => 'twitter', 'x.com' => 'twitter', 'linkedin.com' => 'linkedin',
        't.me' => 'telegram', 'telegram.me' => 'telegram', 'wa.me' => 'whatsapp', 'whatsapp.com' => 'whatsapp',
        'snapchat.com' => 'snapchat', 'twitch.tv' => 'twitch', 'pinterest.com' => 'pinterest',
        'discord.gg' => 'discord', 'discord.com' => 'discord', 'spotify.com' => 'spotify', 'github.com' => 'github',
    ];

    protected $table = 'tagtoa_links';

    protected $fillable = ['link_page_id', 'label', 'url', 'platform', 'is_featured', 'is_active', 'sort', 'clicks'];

    protected $casts = ['is_featured' => 'boolean', 'is_active' => 'boolean', 'sort' => 'integer', 'clicks' => 'integer'];

    public function page(): BelongsTo
    {
        return $this->belongsTo(LinkPage::class, 'link_page_id');
    }

    public static function detectPlatform(string $url): string
    {
        $url = trim($url);
        if (Str::startsWith($url, 'mailto:')) {
            return 'email';
        }
        if (Str::startsWith($url, 'tel:')) {
            return 'phone';
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (! $host) {
            return 'custom';
        }
        $host = strtolower(preg_replace('/^www\./', '', $host));
        foreach (self::HOST_MAP as $needle => $platform) {
            if ($host === $needle || Str::endsWith($host, '.'.$needle)) {
                return $platform;
            }
        }
        return 'website';
    }

    public function getIconAttribute(): string
    {
        return self::PLATFORM_ICONS[$this->platform] ?? self::PLATFORM_ICONS['custom'];
    }
}
