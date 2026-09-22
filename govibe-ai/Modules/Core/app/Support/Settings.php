<?php

namespace Modules\Core\Support;

use Illuminate\Support\Facades\Schema;
use Modules\Core\Models\PlatformSetting;
use Throwable;

/**
 * Paramèt platfòm nan, ak yon defo ki soti nan config.
 *
 * **Tolerab**: si tab la poko egziste (premye migrasyon) oswa si baz la pa
 * disponib, nou retounen defo a olye nou kraze. Yon paj akèy ki bay yon 500
 * paske yon paramèt fakiltatif pa la se yon move echanj.
 */
class Settings
{
    /** Paramèt nou konnen, ak defo yo. Sa ki pa la a pa aksepte. */
    public const KNOWN = [
        'platform_name' => 'LOUVIA',
        'support_whatsapp' => '+509 3398 8754',
        'default_language' => 'fr',
        'hero_headline' => '',
        'moncash_mode' => 'sandbox',
    ];

    /** @var array<string, string|null>|null */
    private ?array $cache = null;

    public function get(string $key, ?string $default = null): ?string
    {
        $value = $this->all()[$key] ?? null;

        if ($value !== null && $value !== '') {
            return $value;
        }

        return $default ?? (self::KNOWN[$key] ?? null);
    }

    /** @return array<string, string|null> */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        try {
            if (! Schema::hasTable('platform_settings')) {
                return $this->cache = [];
            }

            $rows = [];

            foreach (PlatformSetting::all() as $setting) {
                $rows[$setting->key] = $setting->value;
            }

            return $this->cache = $rows;
        } catch (Throwable) {
            return $this->cache = [];
        }
    }

    public function set(string $key, ?string $value, bool $secret = false): void
    {
        PlatformSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'is_secret' => $secret],
        );

        $this->cache = null;
    }

    /** Èske yon sekrè mete? Nou di wi/non — nou pa janm remontre valè a. */
    public function hasSecret(string $key): bool
    {
        $value = $this->all()[$key] ?? null;

        return is_string($value) && trim($value) !== '';
    }

    public function forget(string $key): void
    {
        PlatformSetting::query()->where('key', $key)->delete();
        $this->cache = null;
    }
}
