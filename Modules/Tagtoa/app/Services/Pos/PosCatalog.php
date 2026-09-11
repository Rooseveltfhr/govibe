<?php

namespace Modules\Tagtoa\App\Services\Pos;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Pos\Product;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Support\Pos\CatalogRef;

/**
 * TAGTOA POS — le catalogue du COMMERCE, partagé par toutes ses caisses.
 *
 * Le catalogue appartenait à la caisse : un commerce à deux caisses saisissait
 * ses produits deux fois, et un article créé sur la caisse 1 restait
 * introuvable depuis la caisse 2.
 *
 * Point de lecture UNIQUE : toute recherche de produit passe par ici, toujours
 * limitée au commerce. C'est ce qui rendra le scan de code-barres correct — un
 * code identifie un produit du commerce, jamais celui du voisin.
 */
class PosCatalog
{
    /** Requête de base : le catalogue du commerce, dans l'ordre d'affichage. */
    public function query(?string $tenantId): Builder
    {
        return Product::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('sort')->orderBy('id');
    }

    /** Tout le catalogue, y compris les articles retirés de la vente. */
    public function all(?string $tenantId): Collection
    {
        return $this->query($tenantId)->get();
    }

    /** Articles proposés à la vente. */
    public function active(?string $tenantId): Collection
    {
        return $this->query($tenantId)->where('is_active', true)->get();
    }

    /**
     * Un article du catalogue de CE commerce, ou null.
     *
     * Jamais `Product::find()` : un identifiant deviné donnerait l'article d'un
     * autre commerce. C'est ce chemin que le scanner utilisera demain.
     */
    public function find(?string $tenantId, int $productId): ?Product
    {
        return $this->query($tenantId)->whereKey($productId)->first();
    }

    /* ----------------------------------------------------------------
       Ce que la caisse peut vendre : SES boutons ET le menu du commerce.

       Le marchand saisit un plat une fois dans son menu digital et le vend
       aussi au comptoir. Une seule saisie, un seul stock : vendre au comptoir
       retire du même stock que la commande passée par QR.
       ---------------------------------------------------------------- */

    /**
     * Articles du MENU du commerce, vendables au comptoir.
     *
     * Seuls les articles disponibles et non épuisés remontent : proposer au
     * caissier un plat que la cuisine n'a plus, c'est le faire encaisser pour
     * rien.
     */
    public function menuItems(?string $tenantId): Collection
    {
        return Item::query()
            ->whereHas('menu', fn ($m) => $m->where('tenant_id', $tenantId)->where('is_active', true))
            ->where('is_available', true)
            ->with('category:id,name')
            ->orderBy('sort')->orderBy('id')
            ->get()
            ->filter->in_stock
            ->values();
    }

    /**
     * Tout ce que la caisse peut vendre, chaque article portant sa référence
     * d'origine (« menu:7 », « pos:7 ») pour qu'aucune confusion ne soit
     * possible entre les deux listes.
     *
     * @return array<int, array{ref:string, source:string, id:int, name:string,
     *                          price:float, emoji:?string, color:string,
     *                          group:?string, stock:?int}>
     */
    public function sellable(?string $tenantId): array
    {
        $lignes = [];

        foreach ($this->active($tenantId) as $p) {
            $lignes[] = [
                'ref'    => CatalogRef::make(CatalogRef::SOURCE_POS, $p->id),
                'source' => CatalogRef::SOURCE_POS,
                'id'     => $p->id,
                'name'   => $p->name,
                'price'  => (float) $p->price,
                'emoji'  => $p->emoji,
                'color'  => $p->color ?: '#2cb809',
                'group'  => null,
                'stock'  => $p->stock,
            ];
        }

        foreach ($this->menuItems($tenantId) as $i) {
            $lignes[] = [
                'ref'    => CatalogRef::make(CatalogRef::SOURCE_MENU, $i->id),
                'source' => CatalogRef::SOURCE_MENU,
                'id'     => $i->id,
                'name'   => $i->name,
                'price'  => (float) $i->price,
                'emoji'  => $i->emoji,
                'color'  => '#1F4E79',           // le menu se distingue d'un coup d'œil
                'group'  => $i->category?->name, // rangé par catégorie du menu
                'stock'  => $i->stock,
            ];
        }

        return $lignes;
    }

    /**
     * Retrouve l'article désigné par une référence, dans le catalogue du
     * commerce. Renvoie null si la référence n'a pas de sens ou désigne
     * l'article d'un autre commerce.
     *
     * C'est le SEUL chemin par lequel une vente résout un article : le prix et
     * le nom viennent toujours d'ici, jamais de ce que la caisse a envoyé.
     */
    public function resolve(?string $tenantId, mixed $ref): Product|Item|null
    {
        $parsed = CatalogRef::parse($ref);
        if ($parsed === null) {
            return null;
        }

        [$source, $id] = $parsed;

        if ($source === CatalogRef::SOURCE_MENU) {
            return Item::query()
                ->whereHas('menu', fn ($m) => $m->where('tenant_id', $tenantId))
                ->whereKey($id)->first();
        }

        return $this->find($tenantId, $id);
    }

    /**
     * Enregistre un article. `terminal_id` reste renseigné : la colonne est
     * obligatoire en base et garde la trace de la caisse de saisie.
     */
    public function save(Terminal $terminal, array $attributes, ?int $productId = null): Product
    {
        $existing = $productId ? $this->find($terminal->tenant_id, $productId) : null;

        if ($existing) {
            $existing->update($attributes);

            return $existing;
        }

        return Product::create($attributes + [
            'tenant_id'   => $terminal->tenant_id,
            'terminal_id' => $terminal->id,
        ]);
    }
}
