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

    /**
     * Nettoie la liste des langues qu'un menu déclare offrir : ne garde que
     * des codes valides, ajoute TOUJOURS la langue par défaut — le contenu
     * de base est écrit dedans, elle ne peut jamais être désactivée — et
     * retombe sur null (« pas de restriction ») si le résultat couvre déjà
     * toutes les langues. Même convention que BusinessHours::sanitize() :
     * null, jamais une liste vide, pour « rien de réglé par le marchand ».
     * PUR : aucune dépendance Laravel au-delà de la config déjà lue par
     * all()/default(), testable sans base de données.
     */
    public static function sanitizeSelection(mixed $input): ?array
    {
        if (! is_array($input)) {
            return null;
        }

        $retenues = array_values(array_intersect(self::codes(), $input));
        $avecDefaut = array_values(array_unique(array_merge($retenues, [self::default()])));
        sort($avecDefaut);

        $toutes = self::codes();
        sort($toutes);

        return $avecDefaut === $toutes ? null : $avecDefaut;
    }

    /** Les langues qu'un menu offre réellement — sa sélection, ou toutes si aucune restriction. */
    public static function forMenu(?array $languages): array
    {
        return $languages ?: self::codes();
    }
}
