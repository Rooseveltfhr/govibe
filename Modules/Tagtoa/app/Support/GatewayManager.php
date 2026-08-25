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
    /** Identifiants plateforme, chargés une seule fois par requête. */
    private static ?array $storedCache = null;

    /** Identifiants marchand, mis en cache par tenant. */
    private static array $merchantCache = [];

    /**
     * Config effective d'un driver, dans cet ordre de priorité :
     *   1. config/.env du serveur
     *   2. identifiants PLATEFORME saisis par le fondateur (super-admin)
     *   3. identifiants du MARCHAND — uniquement si la passerelle est réglée en
     *      mode « le marchand encaisse » et qu'un tenant est fourni.
     *
     * C'est le seul point d'entrée des drivers vers leurs identifiants : les
     * brancher ici suffit, les quatre drivers en bénéficient sans changement
     * de logique interne.
     */
    public static function config(string $driver, ?string $tenantId = null): array
    {
        $base = (array) config('tagtoa.gateways.'.$driver, []);
        $cfg = Pay\GatewayCredentialFields::merge($base, self::stored()[$driver] ?? null);

        if ($tenantId !== null && Pay\GatewayCatalog::driverMode($driver) === Pay\GatewayCatalog::MODE_MERCHANT) {
            $cfg = Pay\GatewayCredentialFields::merge($cfg, self::merchantStored($tenantId)[$driver] ?? null);
        }

        return $cfg;
    }

    /**
     * Identifiants du marchand, tous drivers confondus. Tolérant comme la
     * version plateforme : une table absente ne casse pas les paiements.
     */
    public static function merchantStored(string $tenantId): array
    {
        if (array_key_exists($tenantId, self::$merchantCache)) {
            return self::$merchantCache[$tenantId];
        }

        try {
            self::$merchantCache[$tenantId] = \Modules\Tagtoa\App\Models\Pay\MerchantGatewayCredential::query()
                ->where('tenant_id', $tenantId)->get()
                ->mapWithKeys(fn ($r) => [$r->driver => (array) $r->values])->all();
        } catch (\Throwable $e) {
            self::$merchantCache[$tenantId] = [];
        }

        return self::$merchantCache[$tenantId];
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

    /** À appeler après enregistrement d'identifiants (plateforme ou marchand). */
    public static function flush(): void
    {
        self::$storedCache = null;
        self::$merchantCache = [];
    }

    /**
     * Un driver est « activé » si TOUTES ses clés d'identifiants sont remplies.
     * Avec un tenant, on tient compte des identifiants propres au marchand.
     */
    public static function enabled(string $driver, ?string $tenantId = null): bool
    {
        $creds = self::config($driver, $tenantId)['credentials'] ?? null;
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
    public static function onlineAvailable(string $type, ?string $tenantId = null): bool
    {
        $driver = PaymentGateway::driver($type);

        return $driver !== null && self::enabled($driver, $tenantId);
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
