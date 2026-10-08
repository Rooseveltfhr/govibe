<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA — « Synchroniser avec la caisse (POS) »
|--------------------------------------------------------------------------
| Un article de Menu se vendait déjà au comptoir (PosCatalog fusionne les
| deux catalogues à la vente), mais n'existait nulle part comme un VRAI
| produit POS : impossible de lui suivre un stock, un code-barres ou un
| prix d'achat, puisque ces écrans ne connaissent que tagtoa_pos_products.
|
| Demandé explicitement : un bouton, jamais automatique, pour que le
| propriétaire choisisse quand copier/mettre à jour ses articles de Menu
| vers de vrais produits POS. Sens unique (Menu → POS) et idempotent par
| menu_item_id : cliquer deux fois ne duplique jamais le catalogue, et ne
| réécrit jamais les champs que la caisse gère de son côté (stock, prix
| d'achat, code-barres, seuil d'alerte) une fois le produit créé.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Category as MenuCategory;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Pos\Category as PosCategory;
use Modules\Tagtoa\App\Models\Pos\Product;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Services\Pos\MenuProductSync;
use Modules\Tagtoa\App\Services\Pos\PosCatalog;
use Modules\Tagtoa\App\Support\Tenant;
use Modules\Tagtoa\Tests\TestCase;

class MenuProductSyncTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));
        Tenant::flush();
    }

    private function caisse(string $tenantId = 't-1'): Terminal
    {
        return Terminal::firstOrCreate(['tenant_id' => $tenantId, 'name' => 'Caisse'],
            ['currency' => 'HTG', 'is_active' => true]);
    }

    private function article(string $tenantId = 't-1', array $attrs = []): Item
    {
        $menu = Menu::firstOrCreate(['tenant_id' => $tenantId], ['name' => 'Lounge', 'alias' => 'lounge-'.uniqid(), 'currency' => 'HTG']);
        $cat = MenuCategory::create(['menu_id' => $menu->id, 'name' => 'Boissons', 'is_active' => true]);

        return Item::create(array_merge([
            'menu_id' => $menu->id, 'category_id' => $cat->id,
            'name' => 'Rhum Sour', 'price' => 350, 'description' => 'Rhum, citron, sucre',
            'is_available' => true,
        ], $attrs));
    }

    /* ------------------------------------------------------------------
       Le service lui-même.
       ------------------------------------------------------------------ */

    public function test_syncing_creates_a_linked_pos_product_from_a_menu_item(): void
    {
        $item = $this->article();

        $res = app(MenuProductSync::class)->sync('t-1', $this->caisse()->id);

        $this->assertSame(['created' => 1, 'updated' => 0], $res);
        $produit = Product::where('menu_item_id', $item->id)->sole();
        $this->assertSame('Rhum Sour', $produit->name);
        $this->assertEquals(350.0, (float) $produit->price);
        $this->assertSame('Rhum, citron, sucre', $produit->description);
        $this->assertTrue((bool) $produit->is_active);
    }

    public function test_syncing_resolves_an_existing_pos_category_by_name_case_insensitively(): void
    {
        $tenantId = 't-1';
        $existante = PosCategory::create(['tenant_id' => $tenantId, 'name' => 'boissons', 'is_active' => true]);
        $this->article($tenantId);

        app(MenuProductSync::class)->sync($tenantId, $this->caisse($tenantId)->id);

        $this->assertSame(1, PosCategory::where('tenant_id', $tenantId)->count(),
            'Une seconde catégorie "Boissons" a été créée au lieu de réutiliser l\'existante.');
        $produit = Product::where('tenant_id', $tenantId)->sole();
        $this->assertSame($existante->id, $produit->category_id);
    }

    public function test_syncing_twice_updates_the_same_product_instead_of_duplicating(): void
    {
        $item = $this->article();
        $sync = app(MenuProductSync::class);
        $terminalId = $this->caisse()->id;

        $sync->sync('t-1', $terminalId);
        $item->update(['name' => 'Rhum Sour (double)', 'price' => 400]);
        $res = $sync->sync('t-1', $terminalId);

        $this->assertSame(['created' => 0, 'updated' => 1], $res);
        $this->assertSame(1, Product::where('menu_item_id', $item->id)->count());
        $produit = Product::where('menu_item_id', $item->id)->sole();
        $this->assertSame('Rhum Sour (double)', $produit->name);
        $this->assertEquals(400.0, (float) $produit->price);
    }

    public function test_resyncing_never_overwrites_pos_managed_fields(): void
    {
        // Stock, prix d'achat, code-barres : gérés par la caisse une fois le
        // produit créé — un re-sync ne doit jamais les remettre à zéro.
        $item = $this->article();
        $sync = app(MenuProductSync::class);
        $terminalId = $this->caisse()->id;
        $sync->sync('t-1', $terminalId);

        $produit = Product::where('menu_item_id', $item->id)->sole();
        $produit->update(['stock' => 24, 'cost_price' => 180, 'sku' => 'RHUM-001', 'low_stock_threshold' => 5]);

        $sync->sync('t-1', $terminalId);

        $produit->refresh();
        $this->assertSame(24.0, $produit->stock);
        $this->assertEquals(180.0, (float) $produit->cost_price);
        $this->assertSame('RHUM-001', $produit->sku);
        $this->assertEquals(5.0, (float) $produit->low_stock_threshold);
    }

    public function test_an_unavailable_menu_item_syncs_as_inactive_in_pos(): void
    {
        $this->article('t-1', ['is_available' => false]);

        app(MenuProductSync::class)->sync('t-1', $this->caisse()->id);

        $produit = Product::sole();
        $this->assertFalse((bool) $produit->is_active);
    }

    public function test_syncing_a_tenant_with_no_menu_does_nothing(): void
    {
        $res = app(MenuProductSync::class)->sync('t-sans-menu', $this->caisse('t-sans-menu')->id);

        $this->assertSame(['created' => 0, 'updated' => 0], $res);
        $this->assertSame(0, Product::count());
    }

    /* ------------------------------------------------------------------
       Pas de double entrée dans le catalogue de vente.
       ------------------------------------------------------------------ */

    public function test_a_synced_item_no_longer_appears_twice_in_the_sellable_catalog(): void
    {
        $item = $this->article();
        app(MenuProductSync::class)->sync('t-1', $this->caisse()->id);

        $lignes = app(PosCatalog::class)->sellable('t-1');

        $refs = array_column($lignes, 'ref');
        $this->assertContains('pos:'.Product::where('menu_item_id', $item->id)->value('id'), $refs);
        $this->assertNotContains('menu:'.$item->id, $refs, 'Le même plat apparaît encore une fois sous sa référence Menu — vendu en double.');
        $this->assertSame(1, count($lignes), 'Un seul article créé puis synchronisé doit donner UNE seule ligne vendable.');
    }

    /* ------------------------------------------------------------------
       Le bouton — contrôleur + route.
       ------------------------------------------------------------------ */

    public function test_the_sync_button_is_present_on_the_products_screen(): void
    {
        $this->patron();

        $html = $this->get(route('tagtoa.pos.products.terminal', $this->caisse()->id))->assertOk()->getContent();

        $this->assertStringContainsString(route('tagtoa.pos.products.sync'), $html);
        $this->assertStringContainsString(__('Synchroniser avec le menu'), $html);
    }

    public function test_posting_to_the_sync_route_creates_products_and_flashes_counts(): void
    {
        $this->patron();
        $this->article();

        $this->post(route('tagtoa.pos.products.sync'))
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame(1, Product::where('tenant_id', 't-1')->count());
    }
}
