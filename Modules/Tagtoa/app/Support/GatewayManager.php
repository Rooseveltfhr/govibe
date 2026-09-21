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
        // Le driver effectif, pas le déclaré : le fondateur peut avoir choisi
        // PayPal ou Stripe pour la carte bancaire.
        $driver = \Modules\Tagtoa\App\Support\Pay\GatewayCatalog::driverFor($type);

        return $driver !== null && self::enabled($driver);
    }

    /**
     * Avertissement propre à un driver, au-delà de « les identifiants sont-ils
     * remplis » (enabled()) — un cas où les identifiants sont bien là mais se
     * contredisent. Aujourd'hui, seul Stripe a une règle vérifiable : le
     * préfixe de la clé secrète (sk_test_…/sk_live_…) doit correspondre à
     * l'environnement annoncé (voir Stripe::modeMatchesKey()). Sans cet appel,
     * le super-admin verrait « Prête » alors que StripeDriver refuse en
     * silence d'opérer — un désaccord invisible jusqu'au premier client bloqué.
     */
    public static function warning(string $driver): ?string
    {
        if ($driver !== 'stripe') {
            return null;
        }

        $cfg = self::config('stripe');
        $secret = $cfg['credentials']['secret'] ?? null;
        $mode = $cfg['mode'] ?? 'sandbox';

        if ($secret && ! \Modules\Tagtoa\App\Support\Gateways\Stripe::modeMatchesKey($mode, $secret)) {
            return $mode === 'live'
                ? __('La clé enregistrée ressemble à une clé de TEST (sk_test_…) alors que l\'environnement est réglé sur Production : Stripe reste désactivé tant que ce n\'est pas corrigé.')
                : __('La clé enregistrée ressemble à une clé de PRODUCTION (sk_live_…) alors que l\'environnement est réglé sur Test : Stripe reste désactivé tant que ce n\'est pas corrigé.');
        }

        return null;
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
