<?php

namespace Modules\Tagtoa\App\Services\Pos;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Order\Order;
use Modules\Tagtoa\App\Models\Pos\Product;
use Modules\Tagtoa\App\Models\Pos\Sale;
use Modules\Tagtoa\App\Models\Pos\SaleItem;
use Modules\Tagtoa\App\Models\Pos\SaleReturn;
use Modules\Tagtoa\App\Models\Pos\SaleReturnItem;
use Modules\Tagtoa\App\Services\Inventory\StockLedger;
use Modules\Tagtoa\App\Services\Inventory\StockService;
use Modules\Tagtoa\App\Support\Inventory\MovementType;
use Modules\Tagtoa\App\Support\Order\OrderStatus;
use Modules\Tagtoa\App\Support\Pos\CatalogRef;

/**
 * TAGTOA POS — rendre un article, rendre l'argent.
 *
 * ── Les quatre règles qui tiennent ce service ───────────────────────────
 *
 * 1. LE MONTANT NE VIENT JAMAIS DU NAVIGATEUR. Le formulaire dit QUELLES lignes
 *    et COMBIEN d'unités ; le prix, la taxe et le total sont relus sur la
 *    vente, où ils sont figés depuis l'encaissement. Accepter un montant envoyé
 *    par la page, ce serait laisser n'importe qui se rembourser ce qu'il veut.
 *
 * 2. ON NE REND JAMAIS PLUS QU'ON N'A VENDU. Le plafond est cumulatif : il tient
 *    compte de TOUS les retours déjà faits sur la même ligne. Sans ce cumul,
 *    trois retours d'une unité sur un article vendu une fois passeraient tous
 *    les trois — chacun étant valable pris isolément.
 *
 * 3. UN DOUBLE ENVOI NE REMBOURSE QU'UNE FOIS. Même protection que sur les
 *    ventes, et elle compte davantage ici : l'argent SORT. Un téléphone lent,
 *    un doigt qui insiste, une connexion qui repart — et sans clé
 *    d'idempotence, la caisse paie deux fois.
 *
 * 5. ON REND CE QUI A ÉTÉ PAYÉ, PAS « PRIX × QUANTITÉ + TAXE ». Voir payeLigne() : prix TTC
 *    (taxe déjà dedans), remise de la vente, retours partiels cumulés au centime près.
 *
 * 4. LA VENTE NE BOUGE PAS. Le retour est un document séparé. Réécrire la vente
 *    changerait le rapport Z d'une journée close et rendrait faux le ticket
 *    déjà remis au client.
 *
 * Le stock repasse par le JOURNAL (StockLedger), comme tout le reste : aucune
 * écriture directe sur une colonne de stock, jamais.
 */
class ReturnService
{
    /* Résultats. Une chaîne libre divergerait entre le service et l'écran. */
    public const OK          = 'ok';
    public const DEJA_FAIT   = 'deja_fait';   // même clé : on renvoie le retour existant
    public const RIEN        = 'rien';        // aucune ligne, aucune quantité
    public const TROP        = 'trop';        // au-delà de ce qui reste à rendre
    public const INTROUVABLE = 'introuvable';

    public function __construct(
        protected StockLedger $ledger,
    ) {
    }

    /**
     * Ce qu'il reste à rendre sur chaque ligne d'une vente.
     *
     * @return array<int,float>  id de ligne de vente → quantité encore rendable
     */
    public function rendable(Sale $sale): array
    {
        $vendu = $sale->items->pluck('qty', 'id')->map(fn ($q) => (float) $q);

        $deja = SaleReturnItem::whereIn('sale_item_id', $vendu->keys())
            ->selectRaw('sale_item_id, SUM(qty) as total')
            ->groupBy('sale_item_id')
            ->pluck('total', 'sale_item_id');

        $out = [];
        foreach ($vendu as $id => $q) {
            $out[$id] = max(0.0, round($q - (float) ($deja[$id] ?? 0), StockService::SCALE));
        }

        return $out;
    }

    /**
     * Enregistre un retour.
     *
     * @param  array<int,float>  $lignes  id de ligne de vente → quantité rendue
     * @return array{result:string, return:?SaleReturn, message:?string}
     */
    public function record(Sale $sale, array $lignes, array $contexte = []): array
    {
        $cle = trim((string) ($contexte['idempotency_key'] ?? '')) ?: (string) Str::uuid();
        $tenantId = $contexte['tenant_id'] ?? null;

        if (! $tenantId) {
            return $this->non(self::INTROUVABLE);
        }

        // Rejoué : on renvoie le retour DÉJÀ enregistré, sans rien réécrire.
        // Répondre « erreur » ferait recommencer le caissier, et c'est ainsi
        // qu'on rembourse deux fois.
        $existant = SaleReturn::where('tenant_id', $tenantId)
            ->where('idempotency_key', $cle)->first();
        if ($existant) {
            return ['result' => self::DEJA_FAIT, 'return' => $existant, 'message' => null];
        }

        $restant = $this->rendable($sale);
        $demande = [];

        foreach ($lignes as $saleItemId => $qty) {
            $qty = round((float) $qty, 3);
            if ($qty <= 0) {
                continue; // une ligne à zéro n'est pas une erreur, c'est un refus
            }
            if (! array_key_exists((int) $saleItemId, $restant)) {
                // Une ligne qui n'appartient pas à CETTE vente : un identifiant
                // venu du navigateur ne doit pas atteindre une autre vente.
                return $this->non(self::INTROUVABLE);
            }
            if ($qty > $restant[(int) $saleItemId] + 0.0001) {
                return $this->non(self::TROP);
            }
            $demande[(int) $saleItemId] = $qty;
        }

        if ($demande === []) {
            return $this->non(self::RIEN);
        }

        $retour = DB::transaction(function () use ($sale, $demande, $contexte, $cle, $tenantId) {
            // Verrou sur les lignes de vente concernées : deux caissiers qui
            // rendent le même article au même instant liraient tous deux
            // « il reste 1 » et rendraient chacun 1.
            $items = SaleItem::whereIn('id', array_keys($demande))
                ->where('sale_id', $sale->id)
                ->lockForUpdate()->get()->keyBy('id');

            // Re-contrôle DANS la transaction : entre le calcul plus haut et
            // maintenant, un autre retour a pu passer.
            $restant = $this->rendable($sale->load('items'));
            foreach ($demande as $id => $qty) {
                if (! isset($items[$id]) || $qty > ($restant[$id] ?? 0) + 0.0001) {
                    return null;
                }
            }

            $sousTotal = 0.0;
            $taxe      = 0.0;

            $retour = SaleReturn::create([
                'tenant_id'       => $tenantId,
                'sale_id'         => $sale->id,
                'terminal_id'     => $sale->terminal_id,
                'staff_id'        => $contexte['staff_id'] ?? null,
                'reference'       => 'R-'.strtoupper(Str::random(8)),
                'subtotal'        => 0, 'tax_total' => 0, 'total' => 0,
                'currency'        => $sale->currency,
                'reason'          => $contexte['reason'] ?? null,
                'kind'            => array_key_exists($contexte['kind'] ?? '', SaleReturn::KINDS)
                                        ? $contexte['kind'] : 'customer',
                'restocked'       => (bool) ($contexte['restock'] ?? true),
                'idempotency_key' => $cle,
                'returned_at'     => now(),
            ]);

            // Ce que la vente a RÉELLEMENT encaissé, ligne par ligne (voir payeLigne).
            $dejaRembourse = (float) SaleReturn::where('sale_id', $sale->id)->sum('total');

            foreach ($demande as $id => $qty) {
                $ligne = $items[$id];

                // Le remboursement d'une ligne = ce qui a été payé pour elle × la fraction rendue —
                // jamais « prix × quantité + taxe ». Sur une vente TTC (usage haïtien) la taxe est
                // DÉJÀ dans le prix : l'ajouter rendait trop ; sur une vente remisée, le prix est
                // celui d'avant remise : on rendait la remise en plus.
                //
                // Calcul CUMULATIF (part rendue après − part déjà rendue) : trois retours d'un tiers
                // retombent exactement sur le total de la ligne, sans centime perdu ni créé.
                $dejaRendu = (float) SaleReturnItem::where('sale_item_id', $ligne->id)->sum('qty');
                $q = (float) $ligne->qty;
                [$paye, $taxeLigneTotale] = $this->payeLigne($sale, $ligne);

                $montant   = round(round($paye * ($dejaRendu + $qty) / $q, 2) - round($paye * $dejaRendu / $q, 2), 2);
                $taxeLigne = round(round($taxeLigneTotale * ($dejaRendu + $qty) / $q, 2) - round($taxeLigneTotale * $dejaRendu / $q, 2), 2);
                $base      = round($montant - $taxeLigne, 2);

                SaleReturnItem::create([
                    'return_id'    => $retour->id,
                    'sale_item_id' => $ligne->id,
                    'product_id'   => $ligne->product_id,
                    'source'       => $ligne->source,
                    'name'         => $ligne->name,
                    'price'        => $ligne->price,
                    'qty'          => $qty,
                    'line_total'   => $base,        // hors taxe ; base + taxe = montant rendu
                    'tax_amount'   => $taxeLigne,
                ]);

                $sousTotal += $base;
                $taxe      += $taxeLigne;

                if ($retour->restocked) {
                    $this->remettreEnStock($ligne, $qty, $retour, $contexte);
                }
            }

            $total = round($sousTotal + $taxe, 2);

            // Plafond de sécurité : jamais plus que ce que la vente a encaissé, tous retours confondus
            // (des arrondis ou une vente aux totaux incohérents ne doivent pas faire sortir un centime de trop).
            $reste = max(0.0, round((float) $sale->total - $dejaRembourse, 2));
            if ($total > $reste) {
                $sousTotal = max(0.0, round($sousTotal - ($total - $reste), 2));
                $total     = $reste;
            }

            $retour->forceFill([
                'subtotal'  => round($sousTotal, 2),
                'tax_total' => round($taxe, 2),
                'total'     => $total,
            ])->save();

            $this->refleterSurLaColonneVertebrale($sale, $retour);

            return $retour;
        });

        if (! $retour) {
            return $this->non(self::TROP);
        }

        return ['result' => self::OK, 'return' => $retour->load('items'), 'message' => null];
    }

    /* ---------------- interne ---------------- */

    /**
     * La marchandise revient sur l'étagère — par le JOURNAL, jamais en écrivant
     * une colonne.
     *
     * Un article défectueux ou périmé ne revient PAS en vente : le remettre au
     * stock ferait croire au marchand qu'il possède une marchandise qu'il va
     * jeter, et il ne recommanderait pas à temps.
     */
    private function remettreEnStock(SaleItem $ligne, float $qty, SaleReturn $retour, array $contexte): void
    {
        if (in_array($retour->kind, ['defective', 'expired'], true)) {
            return;
        }

        $article = $this->article($ligne);
        if (! $article) {
            return; // article supprimé du catalogue depuis la vente
        }

        $this->ledger->apply($article, $qty, MovementType::RETURN_IN, [
            'origin_type' => 'pos_return',
            'origin_id'   => $retour->id,
            'staff'       => $contexte['staff'] ?? null,
            'reason'      => $retour->kind_label,
        ]);
    }

    /**
     * Ce que la vente a encaissé pour UNE ligne entière, et la part de taxe qu'il contient.
     *
     * La remise de la vente s'impute au prorata sur toutes les lignes (comme PosService :
     * `line_total × (sous-total − remise) / sous-total`). Puis :
     *   - prix TTC  : ce montant EST le payé, la taxe en est extraite (déjà dedans) ;
     *   - prix HT   : la taxe s'ajoute, le client a payé montant + taxe.
     *
     * @return array{0:float,1:float}  [payé pour la ligne entière, dont taxe]
     */
    private function payeLigne(Sale $sale, SaleItem $ligne): array
    {
        $sousTotal = (float) $sale->subtotal;
        $facteur   = $sousTotal > 0 ? max(0.0, $sousTotal - (float) $sale->discount) / $sousTotal : 0.0;
        $net       = round((float) $ligne->line_total * $facteur, 2);
        $taxe      = (float) ($ligne->tax_amount ?? 0);
        $inclusive = (bool) ($sale->tax_inclusive ?? true);

        return [$inclusive ? $net : round($net + $taxe, 2), $taxe];
    }

    /** L'article du catalogue derrière une ligne de vente, ou null. */
    private function article(SaleItem $ligne): Product|Item|null
    {
        if (! $ligne->product_id) {
            return null;
        }

        return $ligne->source === CatalogRef::SOURCE_MENU
            ? Item::find($ligne->product_id)
            : Product::find($ligne->product_id);
    }

    /**
     * La commande unifiée suit le remboursement.
     *
     * Sans cela, l'écran Commandes continuerait d'afficher « payée » une vente
     * dont l'argent est ressorti — et le chiffre d'affaires du jour compterait
     * une recette que le commerce n'a plus.
     */
    private function refleterSurLaColonneVertebrale(Sale $sale, SaleReturn $retour): void
    {
        $order = Order::where('source_type', 'pos_sale')->where('source_id', $sale->id)->first();
        if (! $order) {
            return;
        }

        // Rendu en entier ou en partie : ce n'est pas la même chose pour un
        // marchand qui relit sa journée.
        $rendu = (float) SaleReturn::where('sale_id', $sale->id)->sum('total');
        $total = (float) $sale->total;

        $order->forceFill([
            'payment_status' => $rendu + 0.005 >= $total ? OrderStatus::REFUND : OrderStatus::PARTIAL,
            'status'         => $rendu + 0.005 >= $total ? OrderStatus::REFUNDED : $order->status,
        ])->save();
    }

    /** @return array{result:string, return:null, message:null} */
    private function non(string $result): array
    {
        return ['result' => $result, 'return' => null, 'message' => null];
    }
}
