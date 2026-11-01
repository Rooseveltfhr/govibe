<?php

namespace Modules\Tagtoa\App\Support\Pay;

use Illuminate\Support\Collection;
use Modules\Tagtoa\App\Models\Pay\PaymentMethod;

/**
 * TAGTOA Pay (PAY-2) — la liste UNIQUE des moyens que le client peut choisir pour UNE commande.
 *
 * Elle ne décide de rien : elle met en forme ce que le serveur a déjà jugé utilisable
 * (OnlineOptions::for = passerelle prête, devise acceptée, non décochée par le marchand ;
 * MerchantMethods::declarable = moyens du marchand actifs et déclarables). Un moyen absent de ces
 * deux listes n'est jamais affiché, et la case cochée dans le navigateur ne prouve rien : le
 * lancement du paiement (CheckoutService::start) et la déclaration (claim) revérifient côté serveur.
 *
 * Une carte = un moyen. Le client en choisit UN (boutons radio), jamais plusieurs.
 */
class PaymentChoices
{
    public const KIND_ONLINE = 'online';

    public const KIND_MANUAL = 'manual';

    /**
     * @param  array<int, array{driver:string,label:string,icon:string,color:string}>  $online
     * @param  Collection<int, PaymentMethod>  $manual
     * @return array<int, array<string,mixed>>
     */
    public static function build(array $online, Collection $manual, string $type, int $orderId): array
    {
        $out = [];

        foreach ($online as $o) {
            $out[] = [
                'key'      => 'online-'.$o['driver'],
                'kind'     => self::KIND_ONLINE,
                'label'    => (string) $o['label'],
                'icon'     => (string) $o['icon'],
                'color'    => self::safeColor((string) $o['color']),
                'logo_url' => null,
                'badge'    => 'Confirmation automatique',
                'url'      => route('tagtoa.pay.online.start', ['gateway' => $o['driver'], 'type' => $type, 'orderId' => $orderId]),
                'method'   => null,
            ];
        }

        foreach ($manual as $m) {
            $out[] = [
                'key'      => 'manual-'.$m->id,
                'kind'     => self::KIND_MANUAL,
                'label'    => $m->display_label,
                'icon'     => $m->icon,
                'color'    => self::safeColor((string) $m->brand_color),
                // Logo téléversé par le marchand, sinon pastille de la couleur de marque : jamais de
                // faux logo de marque déposée.
                'logo_url' => $m->logo_url,
                'badge'    => 'Vérifié par le commerce',
                'url'      => null,
                'method'   => $m,
            ];
        }

        return $out;
    }

    /** Une couleur de marque est injectée dans un attribut style : on n'accepte que #rgb / #rrggbb. */
    public static function safeColor(string $c): string
    {
        return preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $c) ? $c : '#2cb809';
    }
}
