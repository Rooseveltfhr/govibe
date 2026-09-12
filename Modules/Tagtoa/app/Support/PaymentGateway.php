<?php

namespace Modules\Tagtoa\App\Support;

use Modules\Tagtoa\App\Models\Pay\PaymentPage;

/**
 * TAGTOA Pay — registre des passerelles : classification (auto/manuel),
 * famille (kind), couleur de marque et driver API pour chaque type de méthode.
 *
 * Auto (API)   : MonCash, PayPal (+ carte), CoinPayments (USDT/USDC/BTC/ETH),
 *                Stripe (carte), Authorize.Net (carte).
 * Manuel (preuve) : NatCash, Zelle, CashApp, Unibank, Sogebank, Capital Bank, BNC, …
 *
 * Le label + l'icône restent dans PaymentPage::METHODS (source unique).
 */
class PaymentGateway
{
    public const MODE_AUTO   = 'auto';
    public const MODE_MANUAL = 'manual';

    /**
     * type => [mode, kind, color, driver]
     *   mode   : auto|manual
     *   kind   : mobile_money|bank|card|crypto|wallet|cash
     *   color  : couleur de marque (badge)
     *   driver : passerelle API (si auto), sinon null
     */
    public const GATEWAYS = [
        // Mobile money Haïti
        'moncash'     => ['mode' => self::MODE_AUTO,   'kind' => 'mobile_money', 'color' => '#E2001A', 'driver' => 'moncash'],
        'natcash'     => ['mode' => self::MODE_MANUAL, 'kind' => 'mobile_money', 'color' => '#00A859', 'driver' => null],
        'lajancash'   => ['mode' => self::MODE_MANUAL, 'kind' => 'mobile_money', 'color' => '#0066B3', 'driver' => null],
        // Banques Haïti (virement manuel)
        'unibank'     => ['mode' => self::MODE_MANUAL, 'kind' => 'bank', 'color' => '#005DAA', 'driver' => null],
        'sogebank'    => ['mode' => self::MODE_MANUAL, 'kind' => 'bank', 'color' => '#E30613', 'driver' => null],
        'bnc'         => ['mode' => self::MODE_MANUAL, 'kind' => 'bank', 'color' => '#0A4595', 'driver' => null],
        'capitalbank' => ['mode' => self::MODE_MANUAL, 'kind' => 'bank', 'color' => '#1B3A6B', 'driver' => null],
        'buh'         => ['mode' => self::MODE_MANUAL, 'kind' => 'bank', 'color' => '#00529B', 'driver' => null],
        // Cash
        'cash'        => ['mode' => self::MODE_MANUAL, 'kind' => 'cash', 'color' => '#1D9E75', 'driver' => null],
        'cod'         => ['mode' => self::MODE_MANUAL, 'kind' => 'cash', 'color' => '#7a5200', 'driver' => null],
        // Diaspora / wallets
        'zelle'       => ['mode' => self::MODE_MANUAL, 'kind' => 'wallet', 'color' => '#6D1ED4', 'driver' => null],
        'cashapp'     => ['mode' => self::MODE_MANUAL, 'kind' => 'wallet', 'color' => '#00D632', 'driver' => null],
        'venmo'       => ['mode' => self::MODE_MANUAL, 'kind' => 'wallet', 'color' => '#3D95CE', 'driver' => null],
        'wise'        => ['mode' => self::MODE_MANUAL, 'kind' => 'wallet', 'color' => '#9FE870', 'driver' => null],
        // International (API)
        'paypal'      => ['mode' => self::MODE_AUTO,   'kind' => 'wallet', 'color' => '#003087', 'driver' => 'paypal'],
        'card'        => ['mode' => self::MODE_AUTO,   'kind' => 'card',   'color' => '#1A1F71', 'driver' => 'paypal'],
        'bank_intl'   => ['mode' => self::MODE_MANUAL, 'kind' => 'bank',   'color' => '#444444', 'driver' => null],
        // Crypto (API CoinPayments)
        'usdt'        => ['mode' => self::MODE_AUTO,   'kind' => 'crypto', 'color' => '#26A17B', 'driver' => 'coinpayments'],
        'usdc'        => ['mode' => self::MODE_AUTO,   'kind' => 'crypto', 'color' => '#2775CA', 'driver' => 'coinpayments'],
        'btc'         => ['mode' => self::MODE_AUTO,   'kind' => 'crypto', 'color' => '#F7931A', 'driver' => 'coinpayments'],
        'eth'         => ['mode' => self::MODE_AUTO,   'kind' => 'crypto', 'color' => '#627EEA', 'driver' => 'coinpayments'],
        'binance'     => ['mode' => self::MODE_MANUAL, 'kind' => 'crypto', 'color' => '#F0B90B', 'driver' => null],
        'crypto'      => ['mode' => self::MODE_MANUAL, 'kind' => 'crypto', 'color' => '#8a8a8a', 'driver' => null],
        // TAGTOA
        'tagtoa_card' => ['mode' => self::MODE_MANUAL, 'kind' => 'card', 'color' => '#2cb809', 'driver' => null],
    ];

    /**
     * Drivers autorisés par type, quand plusieurs peuvent traiter le même moyen.
     *
     * La carte bancaire est traitée par PayPal (défaut : le client paie par carte
     * sans avoir de compte PayPal) ; Stripe reste disponible, notamment parce
     * qu'il accepte le peso dominicain que PayPal refuse.
     *
     * Cette liste est une BARRIÈRE : le réglage super-admin ne peut choisir que
     * dedans, donc aucune chaîne arbitraire ne devient un nom de driver.
     */
    public const ALTERNATIVES = [
        'card' => ['paypal', 'stripe'],
    ];

    /** Le driver $driver est-il autorisé pour le type $type ? PUR. */
    public static function allowsDriver(string $type, ?string $driver): bool
    {
        if ($driver === null || $driver === '') {
            return false;
        }

        return in_array($driver, self::ALTERNATIVES[$type] ?? [], true);
    }

    private const DEFAULT = ['mode' => self::MODE_MANUAL, 'kind' => 'other', 'color' => '#2cb809', 'driver' => null];

    /** Métadonnées complètes d'un type : label + icon (METHODS) + mode/kind/color/driver. */
    public static function meta(string $type): array
    {
        $base = PaymentPage::METHODS[$type] ?? ['label' => ucfirst($type), 'icon' => 'fa-solid fa-money-check-dollar', 'region' => 'other'];

        return array_merge(self::DEFAULT, $base, self::GATEWAYS[$type] ?? []);
    }

    public static function isAuto(string $type): bool
    {
        return (self::GATEWAYS[$type]['mode'] ?? self::MODE_MANUAL) === self::MODE_AUTO;
    }

    /**
     * Driver DÉCLARÉ pour ce type — sans le réglage super-admin.
     *
     * Pour savoir qui traite réellement le paiement, passer par
     * GatewayCatalog::driverFor() : le super-admin peut avoir choisi une
     * alternative (voir ALTERNATIVES). Cette méthode reste pure et sans base de
     * données, donc testable sans Laravel.
     */
    public static function driver(string $type): ?string
    {
        return self::GATEWAYS[$type]['driver'] ?? null;
    }

    public static function color(string $type): string
    {
        return self::GATEWAYS[$type]['color'] ?? self::DEFAULT['color'];
    }
}
