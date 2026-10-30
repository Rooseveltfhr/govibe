<?php

namespace Modules\Tagtoa\App\Support\Pay;

use Modules\Tagtoa\App\Support\GatewayManager;
use Modules\Tagtoa\App\Support\Gateways\MonCash;
use Modules\Tagtoa\App\Support\Gateways\PayPal;
use Modules\Tagtoa\App\Support\Gateways\Stripe;

/**
 * Les moyens de paiement EN LIGNE qu'une commande peut vraiment utiliser, ici
 * et maintenant.
 *
 * Un moyen est offert seulement si, ensemble :
 *   - le fondateur ne l'a pas désactivé (réglage plateforme, GatewayCatalog) ;
 *   - ses identifiants sont tous remplis (GatewayManager::enabled — sandbox ou
 *     production, selon le mode saisi côté admin) ;
 *   - la passerelle traite la DEVISE de la commande. Offrir MonCash pour une
 *     commande en dollars, ou Stripe pour des gourdes, ferait échouer le
 *     paiement après que le client a cliqué : il vaut mieux ne pas le proposer.
 *
 * CoinPayments n'y figure pas : il demande de choisir une crypto-monnaie, un
 * écran à part (étape ultérieure).
 */
class OnlineOptions
{
    /** driver => [libellé, icône, couleur, test de devise]. */
    private const DRIVERS = [
        'moncash' => ['label' => 'MonCash',                      'icon' => 'fa-solid fa-mobile-screen', 'color' => '#E2001A'],
        'stripe'  => ['label' => 'Carte bancaire',               'icon' => 'fa-solid fa-credit-card',   'color' => '#635BFF'],
        'paypal'  => ['label' => 'PayPal',                       'icon' => 'fa-brands fa-paypal',       'color' => '#003087'],
    ];

    /** @return array<int, array{driver:string,label:string,icon:string,color:string}> */
    public static function for(?string $currency): array
    {
        $currency = strtoupper((string) $currency);
        $out = [];

        foreach (self::driversOffered() as $driver) {
            if (! isset(self::DRIVERS[$driver]) || isset($out[$driver])) {
                continue;
            }
            if (! GatewayManager::enabled($driver) || ! self::supports($driver, $currency)) {
                continue;
            }
            $out[$driver] = ['driver' => $driver] + self::DRIVERS[$driver];
        }

        return array_values($out);
    }

    /** PUR — la passerelle traite-t-elle cette devise ? */
    public static function supports(string $driver, string $currency): bool
    {
        return match ($driver) {
            'moncash' => MonCash::supportsCurrency($currency),
            'stripe'  => Stripe::supportsCurrency($currency),
            'paypal'  => PayPal::supportsCurrency($currency),
            default   => false,
        };
    }

    /**
     * Les drivers effectifs des moyens AUTOMATIQUES que le fondateur n'a pas
     * désactivés — « carte » peut être servie par PayPal ou Stripe selon son
     * choix, d'où le détour par le catalogue plutôt que la liste codée.
     */
    private static function driversOffered(): array
    {
        $drivers = [];
        foreach (GatewayCatalog::effective() as $meta) {
            if (($meta['mode'] ?? null) !== \Modules\Tagtoa\App\Support\PaymentGateway::MODE_AUTO) {
                continue;
            }
            if (empty($meta['is_enabled']) || empty($meta['driver'])) {
                continue;
            }
            $drivers[] = $meta['driver'];
        }

        return array_values(array_unique($drivers));
    }
}
