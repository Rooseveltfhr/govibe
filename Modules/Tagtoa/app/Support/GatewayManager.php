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
    /** Identifiants saisis en base, chargés une seule fois par requête. */
    private static ?array $storedCache = null;

    /**
     * Config effective d'un driver : config('tagtoa.gateways.{driver}')
     * SURCHARGÉE par les identifiants saisis dans le super-admin.
     *
     * C'est le seul point d'entrée des drivers vers leurs identifiants, donc
     * les brancher ici suffit : MonCashDriver, PayPalDriver, StripeDriver et
     * CoinPaymentsDriver reçoivent automatiquement les valeurs de la base sans
     * être modifiés.
     */
    public static function config(string $driver): array
    {
        $base = (array) config('tagtoa.gateways.'.$driver, []);

        return Pay\GatewayCredentialFields::merge($base, self::stored()[$driver] ?? null);
    }

    /**
     * Identifiants stockés, tous drivers confondus. Tolérant : si la table
     * n'existe pas encore (migration non passée) ou si le déchiffrement échoue
     * (APP_KEY changée), on retombe silencieusement sur le .env plutôt que de
     * casser les pages de paiement.
     */
    public static function stored(): array
    {
        if (self::$storedCache !== null) {
            return self::$storedCache;
        }

        try {
            self::$storedCache = \Modules\Tagtoa\App\Models\Pay\GatewayCredential::query()
                ->get()->mapWithKeys(fn ($r) => [$r->driver => (array) $r->values])->all();
        } catch (\Throwable $e) {
            self::$storedCache = [];
        }

        return self::$storedCache;
    }

    /** À appeler après enregistrement dans le super-admin. */
    public static function flush(): void
    {
        self::$storedCache = null;
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
