<?php

namespace Modules\Tagtoa\App\Services\Inventory;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Tagtoa\App\Models\Inventory\StockMovement;
use Modules\Tagtoa\App\Models\Menu\Item as MenuItem;
use Modules\Tagtoa\App\Models\Pos\Product;
use Modules\Tagtoa\App\Support\Inventory\MovementType;

/**
 * TAGTOA INVENTORY — ce que le patron vient chercher sur son écran de stock.
 *
 * Trois questions, dans cet ordre :
 *   1. qu'est-ce que je dois recommander AUJOURD'HUI ?
 *   2. combien vaut ce que j'ai en réserve ?
 *   3. où sont passées les douze bouteilles qui manquent ?
 *
 * Un seul catalogue depuis B-3 : la caisse et le menu sont réunis ici, sinon
 * le patron devrait additionner deux écrans de tête.
 */
class StockReport
{
    /**
     * Les articles suivis du commerce, caisse et menu réunis.
     *
     * @return Collection<int, object>
     */
    public function articles(?string $tenantId, bool $seulementFaibles = false): Collection
    {
        $pos = Product::query()->where('tenant_id', $tenantId)->whereNotNull('stock');
        $menu = MenuItem::query()
            ->whereHas('menu', fn ($m) => $m->where('tenant_id', $tenantId))
            ->whereNotNull('stock');

        if ($seulementFaibles) {
            $pos->lowStock();
            $menu->lowStock();
        }

        $lignes = $pos->get()->map(fn ($p) => $this->ligne($p, StockMovement::SOURCE_POS))
            ->concat($menu->with('menu')->get()->map(fn ($i) => $this->ligne($i, StockMovement::SOURCE_MENU)));

        // Ce qu'il faut recommander d'abord, puis ce qui vaut le plus cher :
        // c'est l'ordre dans lequel un commerçant regarde sa réserve.
        return $lignes->sortBy([
            fn ($a, $b) => ($b->low <=> $a->low),
            fn ($a, $b) => ($b->value <=> $a->value),
        ])->values();
    }

    private function ligne($article, string $source): object
    {
        $cout = $article->cost_price === null ? null : (float) $article->cost_price;

        return (object) [
            'ref'       => $source.':'.$article->id,
            'source'    => $source,
            'id'        => $article->id,
            'name'      => $article->name,
            'unit'      => $article->unit_label,
            'stock'     => (float) $article->stock,
            'threshold' => $article->low_stock_threshold === null ? null : (float) $article->low_stock_threshold,
            'low'       => $article->isLowStock(),
            'out'       => $article->isOutOfStock(),
            'cost'      => $cout,
            // Valeur d'achat de ce qui dort en réserve. Null quand le prix
            // d'achat n'est pas renseigné : on ne l'invente pas.
            'value'     => $cout === null ? null : round($cout * (float) $article->stock, 2),
        ];
    }

    /**
     * Ce que vaut la réserve, et sur quelle part du stock on peut le dire.
     *
     * `known` compte les ARTICLES, pas l'argent : dire « 40 000 gourdes » sans
     * préciser que la moitié des articles n'a pas de prix d'achat renseigné
     * donnerait un chiffre que le marchand croirait complet.
     *
     * @return array{value:float, articles:int, low:int, out:int, known:int}
     */
    public function summary(?string $tenantId): array
    {
        $articles = $this->articles($tenantId);

        return [
            'value'    => round($articles->sum(fn ($a) => $a->value ?? 0), 2),
            'articles' => $articles->count(),
            'low'      => $articles->where('low', true)->count(),
            'out'      => $articles->where('out', true)->count(),
            'known'    => $articles->whereNotNull('value')->count(),
        ];
    }

    /**
     * Le journal, filtré. Rendu paginé par l'appelant.
     *
     * @param  array{ref?:string, type?:string, from?:string, to?:string, staff?:int}  $filtres
     */
    public function journal(?string $tenantId, array $filtres = []): Builder
    {
        $q = StockMovement::query()
            ->where('tenant_id', $tenantId)
            ->with('supplier')
            ->orderByDesc('created_at')->orderByDesc('id');

        if (! empty($filtres['ref']) && str_contains($filtres['ref'], ':')) {
            [$source, $id] = explode(':', $filtres['ref'], 2);
            if (in_array($source, StockMovement::SOURCES, true) && ctype_digit($id)) {
                $q->where('source', $source)->where('product_id', (int) $id);
            }
        }

        if (! empty($filtres['type']) && MovementType::isValid($filtres['type'])) {
            $q->where('type', $filtres['type']);
        }

        if (! empty($filtres['staff'])) {
            $q->where('staff_id', (int) $filtres['staff']);
        }

        // Bornes de date incluses des deux côtés : « du 1 au 5 » doit contenir
        // le 5, sinon le marchand cherche une journée qui a disparu.
        if (! empty($filtres['from'])) {
            $q->whereDate('created_at', '>=', $filtres['from']);
        }
        if (! empty($filtres['to'])) {
            $q->whereDate('created_at', '<=', $filtres['to']);
        }

        return $q;
    }

    /**
     * Ce qui a disparu sans être vendu, sur une période.
     *
     * C'est le chiffre qui fait réagir un patron : la casse et les manquants
     * lui coûtent de l'argent sans jamais apparaître dans ses ventes.
     *
     * @return array{qty:float, value:float, byType:array<string,float>}
     */
    public function shrinkage(?string $tenantId, ?string $from = null, ?string $to = null): array
    {
        $q = StockMovement::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('type', [MovementType::LOSS, MovementType::INTERNAL, MovementType::COUNT])
            ->where('delta', '<', 0);

        if ($from) {
            $q->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $q->whereDate('created_at', '<=', $to);
        }

        $qty = 0.0;
        $valeur = 0.0;
        $parMotif = [];

        // Le coût vient de l'article AUJOURD'HUI : un mouvement de perte n'en
        // porte pas. C'est une estimation, et l'écran le dit.
        $couts = $this->coutsCourants($tenantId);

        foreach ($q->get() as $m) {
            $manque = abs((float) $m->delta);
            $qty += $manque;
            $parMotif[$m->type] = round(($parMotif[$m->type] ?? 0) + $manque, 3);

            $cout = $couts[$m->source.':'.$m->product_id] ?? null;
            if ($cout !== null) {
                $valeur += $cout * $manque;
            }
        }

        return ['qty' => round($qty, 3), 'value' => round($valeur, 2), 'byType' => $parMotif];
    }

    /** @return array<string, float> ref => prix d'achat courant */
    private function coutsCourants(?string $tenantId): array
    {
        $couts = [];

        foreach (Product::where('tenant_id', $tenantId)->whereNotNull('cost_price')->get(['id', 'cost_price']) as $p) {
            $couts[StockMovement::SOURCE_POS.':'.$p->id] = (float) $p->cost_price;
        }

        $items = MenuItem::whereHas('menu', fn ($m) => $m->where('tenant_id', $tenantId))
            ->whereNotNull('cost_price')->get(['id', 'cost_price']);
        foreach ($items as $i) {
            $couts[StockMovement::SOURCE_MENU.':'.$i->id] = (float) $i->cost_price;
        }

        return $couts;
    }
}
