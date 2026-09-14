<?php

namespace Modules\Tagtoa\App\Services\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Tagtoa\App\Models\Inventory\StockMovement;
use Modules\Tagtoa\App\Models\Menu\Item as MenuItem;
use Modules\Tagtoa\App\Models\Staff\Staff;
use Modules\Tagtoa\App\Models\Store\Product as StoreProduct;
use Modules\Tagtoa\App\Support\Inventory\MovementType;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA INVENTORY — le SEUL chemin par lequel un stock change.
 *
 * Le stock était un nombre qu'on écrasait depuis quatre endroits différents.
 * Quand il ne correspondait plus à l'étagère, personne ne pouvait remonter le
 * fil : ni qui, ni quand, ni pourquoi.
 *
 * Même principe que le ledger MAGOCASH : on n'écrit pas un solde, on
 * enregistre un mouvement, et le solde en découle. Toute écriture directe sur
 * la colonne `stock` est désormais un bug — le journal mentirait à partir de
 * ce point, et un journal auquel on ne peut pas se fier ne sert à rien.
 *
 * Deux garanties tenues ici :
 *   • la ligne du journal et la nouvelle valeur du stock sont écrites dans la
 *     MÊME transaction, avec la ligne article verrouillée : deux caisses qui
 *     vendent le dernier article en même temps ne peuvent pas lire le même
 *     « avant » ;
 *   • un article dont le stock n'est pas suivi (null) reste non suivi : on ne
 *     lui invente pas un compteur au premier mouvement.
 */
class StockLedger
{
    /**
     * Applique un mouvement et le journalise. Renvoie la ligne écrite, ou null
     * si l'article ne suit pas son stock (rien à compter, rien à raconter).
     *
     * @param  Model  $article  un Pos\Product ou un Menu\Item
     * @param  float  $delta    signé : négatif = sortie, positif = entrée
     */
    public function apply(Model $article, float $delta, string $type, array $contexte = []): ?StockMovement
    {
        if (! MovementType::isValid($type)) {
            throw new \InvalidArgumentException("Motif de mouvement inconnu : {$type}");
        }

        return DB::transaction(function () use ($article, $delta, $type, $contexte) {
            // Verrou sur LA ligne article : sans lui, deux ventes simultanées
            // lisent le même stock avant et en écrivent un faux après.
            $frais = $article->newQuery()->whereKey($article->getKey())->lockForUpdate()->first();
            if (! $frais) {
                return null; // supprimé entre-temps
            }

            $avant = $frais->stock === null ? null : (float) $frais->stock;
            if ($avant === null) {
                // Stock non suivi : on ne lui fabrique pas un compteur par
                // surprise, sinon un article « illimité » deviendrait épuisé.
                return null;
            }

            $apres = round($avant + $delta, StockService::SCALE);

            $frais->forceFill(['stock' => $apres])->save();
            $this->refleter($article, $apres);

            return StockMovement::create([
                'tenant_id'    => $this->tenantOf($frais),
                'source'       => $this->sourceOf($frais),
                'product_id'   => $frais->getKey(),
                'product_name' => $frais->name,
                'type'         => $type,
                'delta'        => round($delta, StockService::SCALE),
                'stock_before' => $avant,
                'stock_after'  => $apres,
                'unit_cost'    => $contexte['unit_cost'] ?? null,
                'supplier_id'  => $contexte['supplier_id'] ?? null,
                'staff_id'     => $contexte['staff']?->id ?? null,
                'actor_name'   => $this->actorName($contexte['staff'] ?? null),
                'origin_type'  => $contexte['origin_type'] ?? null,
                'origin_id'    => $contexte['origin_id'] ?? null,
                'reason'       => $this->reason($contexte['reason'] ?? null),
            ]);
        });
    }

    /** Sortie de stock : la quantité est donnée POSITIVE, elle sort. */
    public function remove(Model $article, float $qty, string $type, array $contexte = []): ?StockMovement
    {
        return $this->apply($article, -abs($qty), $type, $contexte);
    }

    /** Entrée de stock. */
    public function add(Model $article, float $qty, string $type, array $contexte = []): ?StockMovement
    {
        return $this->apply($article, abs($qty), $type, $contexte);
    }

    /**
     * Comptage physique : le stock devient ce qui a été compté sur l'étagère.
     *
     * L'écart est calculé par le service, jamais saisi : c'est justement le
     * chiffre que le patron ne connaît pas encore, et le lui demander
     * reviendrait à lui faire deviner ce qu'on cherche à lui apprendre.
     */
    public function count(Model $article, float $compte, array $contexte = []): ?StockMovement
    {
        $frais = $article->newQuery()->whereKey($article->getKey())->first();
        if (! $frais) {
            return null;
        }

        // Premier comptage d'un article non suivi : on ouvre son compteur, ce
        // qui est exactement ce que le patron demande en comptant.
        if ($frais->stock === null) {
            $mouvement = $this->ouvrir($frais, $compte, $contexte);
            $this->refleter($article, $frais->stock);

            return $mouvement;
        }

        $ecart = round(max(0.0, $compte) - (float) $frais->stock, StockService::SCALE);
        if ($ecart == 0.0) {
            $this->refleter($article, $frais->stock);

            return null; // rien à raconter : l'étagère et l'écran sont d'accord
        }

        $mouvement = $this->apply($frais, $ecart, MovementType::COUNT, $contexte);
        $this->refleter($article, $frais->stock);

        return $mouvement;
    }

    /**
     * Reporte le nouveau stock sur l'instance que l'appelant tient en main.
     *
     * Le service travaille sur une copie verrouillée. Sans ce report, du code
     * parfaitement raisonnable — enregistrer un article puis afficher son
     * stock — lirait la valeur d'avant le mouvement et l'afficherait au
     * marchand comme si c'était la vérité.
     */
    private function refleter(Model $article, mixed $stock): void
    {
        $article->setAttribute('stock', $stock);
        $article->syncOriginalAttribute('stock');
    }

    /** Ouvre le suivi d'un article qui n'en avait pas. */
    private function ouvrir(Model $article, float $quantite, array $contexte): StockMovement
    {
        $quantite = round(max(0.0, $quantite), StockService::SCALE);

        $article->forceFill(['stock' => $quantite])->save();

        return StockMovement::create([
            'tenant_id'    => $this->tenantOf($article),
            'source'       => $this->sourceOf($article),
            'product_id'   => $article->getKey(),
            'product_name' => $article->name,
            'type'         => MovementType::OPENING,
            'delta'        => $quantite,
            'stock_before' => null,   // il n'y avait pas d'« avant » : ne pas mentir avec 0
            'stock_after'  => $quantite,
            'staff_id'     => $contexte['staff']?->id ?? null,
            'actor_name'   => $this->actorName($contexte['staff'] ?? null),
            'reason'       => $this->reason($contexte['reason'] ?? null),
        ]);
    }

    /* ---------------- détails ---------------- */

    private function sourceOf(Model $article): string
    {
        return match (true) {
            $article instanceof MenuItem     => StockMovement::SOURCE_MENU,
            $article instanceof StoreProduct => StockMovement::SOURCE_STORE,
            default                          => StockMovement::SOURCE_POS,
        };
    }

    /**
     * Le commerce propriétaire.
     *
     * Un article du menu ne porte pas `tenant_id` : c'est son menu qui le
     * porte. On le remonte plutôt que de retomber sur le commerce courant, qui
     * serait faux pour un traitement en file d'attente ou en console.
     */
    private function tenantOf(Model $article): ?string
    {
        if ($article instanceof MenuItem) {
            return $article->menu?->tenant_id ?? Tenant::id();
        }

        if ($article instanceof StoreProduct) {
            return $article->store?->tenant_id ?? Tenant::id();
        }

        return $article->tenant_id ?? Tenant::id();
    }

    private function actorName(?Staff $staff): ?string
    {
        if ($staff) {
            return $staff->name;
        }

        // Pas d'employé : c'est le patron depuis son écran.
        $user = Tenant::user();

        return $user->name ?? null;
    }

    private function reason(?string $reason): ?string
    {
        $reason = trim((string) $reason);

        return $reason === '' ? null : mb_substr($reason, 0, 240);
    }
}
