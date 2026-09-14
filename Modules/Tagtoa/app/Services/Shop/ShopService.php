<?php

namespace Modules\Tagtoa\App\Services\Shop;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Tagtoa\App\Models\Shop\ShopItem;
use Modules\Tagtoa\App\Models\Shop\ShopOrder;
use Modules\Tagtoa\App\Models\Shop\ShopOrderItem;
use Modules\Tagtoa\App\Support\Order\OrderStatus;

/**
 * BOUTIQUE TAGTOA — passer commande du matériel.
 *
 * ── Les règles qui tiennent ce service ──────────────────────────────────
 *
 * 1. LE PRIX NE VIENT JAMAIS DU NAVIGATEUR. Le panier dit QUELS articles et
 *    COMBIEN ; le prix est relu au catalogue. C'est la même règle que sur les
 *    ventes et les retours, et elle vaut ici autant : accepter un total envoyé
 *    par la page, ce serait laisser n'importe qui commander à son propre tarif.
 *
 * 2. LES QUANTITÉS SONT RAMENÉES À CE QUI EST LIVRABLE. Un stand se fabrique
 *    et s'expédie par lots. Accepter « 13 » quand on vend par cartons de dix
 *    ferait promettre un envoi qu'on ne sait pas préparer — et le marchand
 *    l'apprendrait à la livraison.
 *
 * 3. UN DOUBLE ENVOI NE COMMANDE QU'UNE FOIS. Une connexion haïtienne qui
 *    repart, un doigt qui insiste : sans clé, le marchand reçoit deux cartons
 *    et une facture double.
 *
 * 4. LE TRANSPORT N'EST PAS DEVINÉ. Il dépend d'où est le marchand et de ce
 *    que coûte l'envoi ce mois-là. Inventer un chiffre à la commande, c'est
 *    annoncer un total qu'il faudra démentir. Il reste à zéro jusqu'à ce que
 *    TAGTOA confirme.
 */
class ShopService
{
    /* Résultats. Une chaîne libre divergerait entre le service et l'écran. */
    public const OK        = 'ok';
    public const DEJA_FAIT = 'deja_fait';
    public const VIDE      = 'vide';
    public const INCONNU   = 'inconnu';   // aucun article commandable retenu

    /**
     * Ce que coûte un panier, calculé au catalogue. Aucune écriture.
     *
     * Sert à l'écran AVANT la commande : le marchand doit voir son total avant
     * de valider, et ce total doit être celui que le serveur retiendra — pas
     * une addition faite par la page, qui pourrait en dire un autre.
     *
     * @param  array<int|string,int>  $panier  id d'article → quantité
     * @return array{lignes:array<int,array>, subtotal:float, devise:string}
     */
    public function chiffrer(array $panier): array
    {
        $ids = array_keys(array_filter($panier, fn ($q) => (int) $q > 0));

        $articles = $ids === []
            ? collect()
            : ShopItem::shown()->whereIn('id', $ids)->get()->keyBy('id');

        $lignes = [];
        $total  = 0.0;

        foreach ($articles as $a) {
            $qty = $a->normalizeQty((int) $panier[$a->id]);
            $mt  = round((float) $a->unit_price * $qty, 2);

            $lignes[] = [
                'item'       => $a,
                'qty'        => $qty,
                // Repris pour que l'écran puisse le DIRE : une quantité corrigée
                // en silence est une quantité qu'on croit avoir commandée.
                'demande'    => (int) $panier[$a->id],
                'corrigee'   => $qty !== (int) $panier[$a->id],
                'line_total' => $mt,
            ];
            $total += $mt;
        }

        return ['lignes' => $lignes, 'subtotal' => round($total, 2), 'devise' => $this->devise()];
    }

    /**
     * Enregistre la commande.
     *
     * @param  array<int|string,int>  $panier  id d'article → quantité
     * @return array{result:string, order:?ShopOrder}
     */
    public function commander(array $panier, ?string $tenantId, array $contexte = []): array
    {
        if (! $tenantId) {
            return ['result' => self::INCONNU, 'order' => null];
        }

        $cle = trim((string) ($contexte['idempotency_key'] ?? '')) ?: (string) Str::uuid();

        // Rejouée : on renvoie la commande DÉJÀ passée, sans rien réécrire.
        // Répondre « erreur » ferait recommencer le marchand — et c'est ainsi
        // qu'on livre deux cartons.
        $existante = ShopOrder::where('reference', $cle)->first();
        if ($existante) {
            return ['result' => self::DEJA_FAIT, 'order' => $existante];
        }

        $chiffre = $this->chiffrer($panier);

        if ($chiffre['lignes'] === []) {
            // Panier vide, ou ne contenant que des articles retirés du
            // catalogue entre-temps. Dans les deux cas, rien à commander.
            return ['result' => $panier === [] ? self::VIDE : self::INCONNU, 'order' => null];
        }

        $order = DB::transaction(function () use ($chiffre, $tenantId, $contexte, $cle) {
            $order = ShopOrder::create([
                'tenant_id'     => $tenantId,
                'reference'     => $cle,
                'status'        => OrderStatus::PENDING,
                'currency'      => $chiffre['devise'],
                'subtotal'      => $chiffre['subtotal'],
                // Le transport reste à zéro : il dépend d'où est le marchand.
                // Inventer un chiffre ici, c'est annoncer un total à démentir.
                'shipping'      => 0,
                'total'         => $chiffre['subtotal'],
                'contact_name'  => $contexte['contact_name'] ?? null,
                'contact_phone' => $contexte['contact_phone'] ?? null,
                'address'       => $contexte['address'] ?? null,
                'city'          => $contexte['city'] ?? null,
                'note'          => $contexte['note'] ?? null,
                'placed_at'     => now(),
            ]);

            foreach ($chiffre['lignes'] as $l) {
                ShopOrderItem::create([
                    'order_id'   => $order->id,
                    'item_id'    => $l['item']->id,
                    // RECOPIÉS, pas référencés : un article renommé ou
                    // réévalué ne réécrit pas un bon déjà envoyé.
                    'sku'        => $l['item']->sku,
                    'name'       => $l['item']->name,
                    'unit_price' => $l['item']->unit_price,
                    'qty'        => $l['qty'],
                    'line_total' => $l['line_total'],
                ]);
            }

            return $order;
        });

        return ['result' => self::OK, 'order' => $order->load('items')];
    }

    /**
     * La devise de la boutique.
     *
     * UNE seule, pour toute la boutique : c'est TAGTOA qui vend, avec un seul
     * tarif. Mélanger les devises dans un panier obligerait à un taux de change
     * — et un taux figé dans une commande est une promesse qu'on ne tient pas.
     */
    public function devise(): string
    {
        try {
            $d = function_exists('config') ? config('tagtoa.shop_currency') : null;
        } catch (\Throwable $e) {
            $d = null;
        }

        return is_string($d) && $d !== '' ? $d : 'USD';
    }
}
