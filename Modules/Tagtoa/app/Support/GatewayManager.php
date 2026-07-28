<?php

namespace Modules\Tagtoa\App\Support;

/**
 * TAGTOA Pay — état des passerelles API (activées seulement si les
 * identifiants sont configurés). Les drivers réels (PayPal, CoinPayments,
 * Stripe, MonCash, Authorize.Net) se branchent ici, une fois testés.
 *
 * Tant qu'un driver n'a pas d'identifiants, la méthode reste en mode manuel
 * (preuve) — aucune dépendance, aucun échec.
 */
class GatewayManager
{
    /** Config d'un driver : config('tagtoa.gateways.{driver}'). */
    public static function config(string $driver): array
    {
        return (array) config('tagtoa.gateways.'.$driver, []);
    }

    /** Un driver est « activé » si TOUTES ses clés d'identifiants sont remplies. */
    public static function enabled(string $driver): bool
    {
        $creds = self::config($driver)['credentials'] ?? null;
        if (! is_array($creds) || empty($creds)) {
            return false;
        }
        foreach ($creds as $value) {
            if ($value === null || $value === '') {
                return false;
            }
        }

        return true;
    }

    /** Le type de méthode peut-il être réglé en ligne MAINTENANT ? */
    public static function onlineAvailable(string $type): bool
    {
        $driver = PaymentGateway::driver($type);

        return $driver !== null && self::enabled($driver);
    }

    /** Liste des drivers actuellement activés (pour diagnostic/dashboard). */
    public static function enabledDrivers(): array
    {
        return array_values(array_filter(
            array_keys((array) config('tagtoa.gateways', [])),
            fn ($d) => self::enabled($d)
        ));
    }
}
