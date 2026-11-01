<?php

namespace Modules\Tagtoa\App\Services\Pos;

use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Pos\Category as PosCategory;
use Modules\Tagtoa\App\Models\Pos\Product;

/**
 * TAGTOA — « Synchroniser avec la caisse (POS) », un geste du propriétaire.
 *
 * Un article de Menu se vendait DÉJÀ au comptoir (PosCatalog::sellable()
 * les fusionne à la vente) — mais seulement là. Il n'existait nulle part
 * comme un VRAI produit POS, donc impossible de lui suivre un stock, un
 * code-barres, un prix d'achat ou un seuil d'alerte : ces écrans ne
 * connaissent que tagtoa_pos_products.
 *
 * SENS UNIQUE, Menu → POS, et seulement sur demande (jamais automatique) :
 * le propriétaire clique, chaque article de son menu reçoit ou met à jour
 * son produit POS lié (nom, prix, description, photo, disponibilité).
 * Les champs propres à la caisse (stock, prix d'achat, code-barres, seuil
 * d'alerte, taxe, unité) ne sont JAMAIS écrasés par un re-sync : une fois
 * créés, ils appartiennent à la caisse, pas au menu.
 *
 * Idempotent par menu_item_id (voir Product::menuItem()) : relancer la
 * synchronisation met à jour les produits déjà liés au lieu d'en créer un
 * second — cliquer deux fois ne duplique jamais le catalogue.
 */
class MenuProductSync
{
    public function sync(string $tenantId, int $terminalId): array
    {
        $menu = Menu::where('tenant_id', $tenantId)->first();
        if (! $menu) {
            return ['created' => 0, 'updated' => 0];
        }

        $created = 0;
        $updated = 0;
        $categoriesPos = [];

        $items = Item::query()->whereHas('menu', fn ($m) => $m->whereKey($menu->id))
            ->with('category:id,name')->get();

        // CUR-1 : un article en USD / $HT est converti en devise du menu avant d'aller en caisse.
        // Sans taux actif, le produit POS est désactivé — jamais vendu 5 G pour 5 $.
        $deviseMenu = strtoupper($menu->currency ?: 'HTG');
        $taux = app(\Modules\Tagtoa\App\Services\Currency\CurrencyRateService::class)->activeFor($menu->tenant_id, $deviseMenu);

        foreach ($items as $item) {
            $prixCaisse = \Modules\Tagtoa\App\Services\Currency\CurrencyRateService::convert((float) $item->price, $item->currency, $deviseMenu, $taux);
            $categoryName = $item->category?->name;
            $categoryId = null;
            if ($categoryName) {
                $key = mb_strtolower(trim($categoryName));
                $categoriesPos[$key] ??= $this->resolveCategory($tenantId, $categoryName);
                $categoryId = $categoriesPos[$key]->id;
            }

            $champsMenu = [
                'name'        => $item->name,
                'description' => $item->description,
                'price'       => $prixCaisse ?? $item->price,
                'image_path'  => $item->image_path,
                'emoji'       => $item->emoji,
                'category_id' => $categoryId,
                // L'article masqué du menu public ne doit plus se vendre
                // au comptoir non plus — c'est le même « disponible ou non ».
                'is_active'   => $item->is_available && $prixCaisse !== null,
            ];

            $produit = Product::where('tenant_id', $tenantId)->where('menu_item_id', $item->id)->first();

            if ($produit) {
                $produit->update($champsMenu);
                $updated++;
            } else {
                Product::create(array_merge($champsMenu, [
                    'tenant_id'    => $tenantId,
                    'terminal_id'  => $terminalId,
                    'menu_item_id' => $item->id,
                ]));
                $created++;
            }
        }

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * Le rayon POS portant ce nom, ou nouvellement créé. Aucune contrainte
     * d'unicité en base sur (tenant_id, name) — le rapprochement se fait ici,
     * insensible à la casse/aux espaces, pour ne pas dédoubler « Boissons »
     * et « boissons » à chaque synchronisation.
     */
    private function resolveCategory(string $tenantId, string $name): PosCategory
    {
        $name = trim($name);

        $existante = PosCategory::where('tenant_id', $tenantId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        return $existante ?: PosCategory::create(['tenant_id' => $tenantId, 'name' => $name, 'is_active' => true]);
    }
}
