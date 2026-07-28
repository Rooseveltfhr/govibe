<?php

namespace Modules\Tagtoa\App\Support;

/**
 * TAGTOA — formatage monétaire cohérent dans toute la plateforme.
 * Tolérant : fonctionne même sans config publiée (DEFAULTS) et hors Laravel.
 */
class Money
{
    /** Devises connues par défaut (miroir de config/tagtoa.php). */
    public const DEFAULTS = [
        'HTG' => ['symbol' => 'G',   'name' => 'Gourde haïtienne',  'decimals' => 0, 'position' => 'after'],
        'USD' => ['symbol' => '$',   'name' => 'US Dollar',         'decimals' => 2, 'position' => 'before'],
        'EUR' => ['symbol' => '€',   'name' => 'Euro',              'decimals' => 2, 'position' => 'after'],
        'DOP' => ['symbol' => 'RD$', 'name' => 'Peso dominicain',   'decimals' => 2, 'position' => 'before'],
        'CAD' => ['symbol' => 'C$',  'name' => 'Dollar canadien',   'decimals' => 2, 'position' => 'before'],
    ];

    /** Toutes les devises (config si dispo, sinon DEFAULTS). */
    public static function currencies(): array
    {
        try {
            $cfg = function_exists('config') ? (array) config('tagtoa.currencies', []) : [];
        } catch (\Throwable $e) {
            $cfg = [];
        }

        return $cfg ?: self::DEFAULTS;
    }

    /** Métadonnées d'une devise (symbol, decimals, position, name). */
    public static function meta(string $currency): array
    {
        $currency = strtoupper($currency);

        return self::currencies()[$currency]
            ?? self::DEFAULTS[$currency]
            ?? ['symbol' => $currency, 'name' => $currency, 'decimals' => 2, 'position' => 'after'];
    }

    /** Devises disponibles : ['HTG' => 'Gourde haïtienne (G)', …] pour les <select>. */
    public static function options(): array
    {
        $out = [];
        foreach (self::currencies() as $code => $m) {
            $out[$code] = ($m['name'] ?? $code).' ('.($m['symbol'] ?? $code).')';
        }

        return $out;
    }

    /** Facteur d'unités mineures d'une devise (10^decimals). */
    public static function factor(string $currency): int
    {
        return (int) (10 ** (int) (self::meta($currency)['decimals'] ?? 2));
    }

    /** Montant majeur → unités mineures entières (2 USD → 200 ; 50 HTG → 50). */
    public static function toMinor($amount, string $currency): int
    {
        return (int) round(((float) $amount) * self::factor($currency));
    }

    /** Unités mineures → montant majeur (200 USD → 2.0 ; 50 HTG → 50.0). */
    public static function fromMinor(int $minor, string $currency): float
    {
        return $minor / self::factor($currency);
    }

    /** Formate des unités mineures : 200 + USD → "$2.00" ; 50 + HTG → "50 G". */
    public static function formatMinor(int $minor, ?string $currency = null): string
    {
        $currency = strtoupper($currency ?: 'HTG');

        return self::format(self::fromMinor($minor, $currency), $currency);
    }

    /**
     * Formate un montant : 1500 + USD → "$1,500.00" ; 1500 + HTG → "1,500 G".
     */
    public static function format($amount, ?string $currency = null): string
    {
        $currency = strtoupper($currency ?: 'HTG');
        $m = self::meta($currency);
        $num = number_format((float) $amount, (int) ($m['decimals'] ?? 2), '.', ',');
        $sym = $m['symbol'] ?? $currency;

        return ($m['position'] ?? 'after') === 'before' ? $sym.$num : $num.' '.$sym;
    }
}
