<?php

namespace Modules\Tagtoa\App\Support\Pay;

use Modules\Tagtoa\App\Models\Pay\GatewaySetting;
use Modules\Tagtoa\App\Support\GatewayManager;
use Modules\Tagtoa\App\Support\PaymentGateway;

/**
 * TAGTOA Pay — catalogue effectif des passerelles.
 *
 * Registre codé (Support/PaymentGateway + PaymentPage::METHODS) SURCHARGÉ par
 * les réglages plateforme (`tagtoa_gateway_settings`, éditables par le
 * super_admin) — même principe que PlanService::effectivePlans().
 *
 * Le cœur (merge / fee / isValidMode) est de la LOGIQUE PURE : il prend des
 * tableaux en paramètre et ne touche ni Eloquent ni la config, donc il est
 * testable sans Laravel. Les méthodes qui lisent la base sont tolérantes :
 * si la table n'existe pas encore (migration non passée), on retombe
 * proprement sur le registre codé plutôt que de casser la page.
 */
class GatewayCatalog
{
    /** Les identifiants API de TAGTOA encaissent (rôle d'agrégateur). */
    public const MODE_PLATFORM = 'platform';

    /** Le marchand branche ses propres identifiants : l'argent va direct chez lui. */
    public const MODE_MERCHANT = 'merchant';

    public const CREDENTIAL_MODES = [self::MODE_PLATFORM, self::MODE_MERCHANT];

    /** Réglages appliqués quand le super_admin n'a rien surchargé. */
    public const DEFAULTS = [
        'is_enabled'      => true,
        'credential_mode' => self::MODE_PLATFORM,
        'fee_percent'     => 0.0,
        'fee_fixed'       => 0.0,
    ];

    /**
     * PUR — fusionne le registre codé avec les surcharges plateforme.
     *
     * @param  array  $registry   type => métadonnées codées (mode, kind, color, driver, label…)
     * @param  array  $overrides  type => surcharges partielles (is_enabled, credential_mode, fee_*)
     * @return array  type => métadonnées + réglages effectifs
     */
    public static function merge(array $registry, array $overrides): array
    {
        $out = [];
        foreach ($registry as $type => $meta) {
            $o = is_array($overrides[$type] ?? null) ? $overrides[$type] : [];

            $out[$type] = array_merge($meta, [
                'is_enabled'      => array_key_exists('is_enabled', $o)
                    ? (bool) $o['is_enabled']
                    : self::DEFAULTS['is_enabled'],
                'credential_mode' => self::isValidMode($o['credential_mode'] ?? null)
                    ? $o['credential_mode']
                    : self::DEFAULTS['credential_mode'],
                'fee_percent'     => max(0.0, (float) ($o['fee_percent'] ?? self::DEFAULTS['fee_percent'])),
                'fee_fixed'       => max(0.0, (float) ($o['fee_fixed'] ?? self::DEFAULTS['fee_fixed'])),
            ]);
        }

        return $out;
    }

    /** PUR — un mode de credentials est-il reconnu ? */
    public static function isValidMode(?string $mode): bool
    {
        return $mode !== null && in_array($mode, self::CREDENTIAL_MODES, true);
    }

    /**
     * PUR — frais plateforme prélevés sur un encaissement par cette passerelle.
     * Bornés au montant brut (comme RevenueService::computeCommission) : les
     * frais ne peuvent jamais dépasser ce que le client a payé.
     */
    public static function fee(float $gross, float $percent, float $fixed): float
    {
        if ($gross <= 0) {
            return 0.0;
        }

        return min(round($gross * $percent / 100, 2) + $fixed, $gross);
    }

    /** PUR — sépare un catalogue en deux groupes pour l'affichage. */
    public static function split(array $catalog): array
    {
        $auto = [];
        $manual = [];
        foreach ($catalog as $type => $meta) {
            if (($meta['mode'] ?? PaymentGateway::MODE_MANUAL) === PaymentGateway::MODE_AUTO) {
                $auto[$type] = $meta;
            } else {
                $manual[$type] = $meta;
            }
        }

        return ['auto' => $auto, 'manual' => $manual];
    }

    /* ---------- lecture (tolérante) ---------- */

    /** Registre codé complet : type => métadonnées (label, icon, mode, kind, color, driver). */
    public static function registry(): array
    {
        $out = [];
        foreach (array_keys(PaymentGateway::GATEWAYS) as $type) {
            $out[$type] = PaymentGateway::meta($type);
        }

        return $out;
    }

    /** Surcharges plateforme, chargées une seule fois par requête. */
    private static ?array $overridesCache = null;

    /** Surcharges plateforme depuis la base. Tolérant : [] si la table manque. */
    public static function overrides(): array
    {
        if (self::$overridesCache !== null) {
            return self::$overridesCache;
        }

        try {
            self::$overridesCache = GatewaySetting::query()->get()->keyBy('gateway')
                ->map(fn ($s) => [
                    'is_enabled'      => (bool) $s->is_enabled,
                    'credential_mode' => $s->credential_mode,
                    'fee_percent'     => (float) $s->fee_percent,
                    'fee_fixed'       => (float) $s->fee_fixed,
                ])->all();
        } catch (\Throwable $e) {
            self::$overridesCache = [];
        }

        return self::$overridesCache;
    }

    /** À appeler après modification des réglages plateforme. */
    public static function flush(): void
    {
        self::$overridesCache = null;
    }

    /**
     * Mode d'encaissement d'un DRIVER (les identifiants sont par driver, alors
     * que le mode est réglé par type de méthode). Un driver bascule en mode
     * marchand dès qu'au moins un des types qu'il sert y est réglé — sinon un
     * marchand ayant branché son compte Stripe verrait ses cartes encaissées
     * par la plateforme selon le type utilisé, ce qui serait incompréhensible.
     */
    public static function driverMode(string $driver): string
    {
        foreach (self::effective() as $meta) {
            if (($meta['driver'] ?? null) === $driver
                && ($meta['credential_mode'] ?? null) === self::MODE_MERCHANT) {
                return self::MODE_MERCHANT;
            }
        }

        return self::MODE_PLATFORM;
    }

    /** Catalogue effectif : registre + surcharges plateforme. */
    public static function effective(): array
    {
        return self::merge(self::registry(), self::overrides());
    }

    /**
     * Catalogue tel que le marchand doit le voir. Chaque passerelle porte :
     *   online_ready    — encaissement automatique réellement possible ;
     *   needs_own_keys  — la passerelle attend que CE marchand branche ses
     *                     propres identifiants (mode « le marchand encaisse »).
     *
     * Une passerelle auto non prête reste proposable en mode manuel (preuve).
     */
    public static function forMerchant(?string $tenantId = null): array
    {
        $out = [];
        foreach (self::effective() as $type => $meta) {
            $driver = $meta['driver'] ?? null;

            $meta['online_ready'] = (bool) $meta['is_enabled']
                && $driver !== null
                && GatewayManager::enabled($driver, $tenantId);

            $meta['needs_own_keys'] = $driver !== null
                && ($meta['credential_mode'] ?? null) === self::MODE_MERCHANT;

            $out[$type] = $meta;
        }

        return $out;
    }
}
