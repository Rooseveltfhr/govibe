<?php

namespace Modules\Tagtoa\App\Support;

/**
 * TAGTOA i18n — langues supportées (Kreyòl, Français, English, Español)
 * et devise par défaut associée à chaque langue.
 */
class Locale
{
    /** Toutes les langues supportées : ['fr' => ['label','flag','currency'], …]. */
    public static function all(): array
    {
        return (array) config('tagtoa.locales', []);
    }

    /** Codes de langue supportés. */
    public static function codes(): array
    {
        return array_keys(self::all());
    }

    public static function default(): string
    {
        $d = config('tagtoa.default_locale', 'fr');

        return in_array($d, self::codes(), true) ? $d : (self::codes()[0] ?? 'fr');
    }

    /** Langue courante (toujours valide). */
    public static function current(): string
    {
        $loc = app()->getLocale();

        return in_array($loc, self::codes(), true) ? $loc : self::default();
    }

    public static function isSupported(?string $code): bool
    {
        return $code !== null && in_array($code, self::codes(), true);
    }

    public static function meta(?string $code = null): array
    {
        $code = $code ?: self::current();

        return self::all()[$code] ?? ['label' => strtoupper($code), 'flag' => '🏳️', 'currency' => config('tagtoa.default_currency', 'HTG')];
    }

    /** Devise par défaut associée à une langue (ex. en → USD, ht → HTG). */
    public static function currencyFor(?string $code = null): string
    {
        return self::meta($code)['currency'] ?? config('tagtoa.default_currency', 'HTG');
    }
}
