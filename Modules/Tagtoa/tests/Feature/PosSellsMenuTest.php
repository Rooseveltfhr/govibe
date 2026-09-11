<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Services\Pos\PosCatalog;
use Modules\Tagtoa\App\Services\Pos\PosService;
use Modules\Tagtoa\App\Support\Pos\CatalogRef;
use Modules\Tagtoa\Tests\TestCase;

/**
 * La caisse vend ce qui est au menu : une saisie, un stock.
 *
 * Le marchand entre un plat une fois dans son menu digital. Il le vend au
 * comptoir ET par QR, et le stock descend au même endroit.
 */
class PosSellsMenuTest extends TestCase
{
    use RefreshDatabase;

    private Terminal $caisse;
    private Menu $menu;
    private Category $categorie;

    protected function setUp(): void
    {
        parent::setUp();

        $this->caisse = Terminal::create(['tenant_id' => 't-1', 'name' => 'Caisse', 'currency' => 'HTG', 'is_active' => true]);
        $this->menu = Menu::create(['tenant_id' => 't-1', 'name' => 'Carte', 'alias' => 'carte', 'currency' => 'HTG', 'is_active' => true]);
        $this->categorie = Category::create(['menu_id' => $this->menu->id, 'name' => 'Plats', 'is_active' => true]);
    }

    private function plat(array $attrs = []): Item
    {
        return Item::create(array_merge([
            'menu_id' => $this->menu->id, 'category_id' => $this->categorie->id,
            'name' => 'Griot', 'price' => 350, 'is_available' => true,
        ], $attrs));
    }

    public function test_the_till_offers_the_menu_alongside_its_own_buttons(): void
    {
        app(PosCatalog::class)->save($this->caisse, ['name' => 'Sachet dlo', 'price' => 25, 'is_active' => true]);
        $this->plat();

        $vendables = app(PosCatalog::class)->sellable('t-1');

        $this->assertSame(['Sachet dlo', 'Griot'], array_column($vendables, 'name'));
        $this->assertSame(['pos', 'menu'], array_column($vendables, 'source'));
        // L'article du menu est rangé sous sa catégorie.
        $this->assertSame('Plats', $vendables[1]['group']);
    }

    public function test_selling_a_dish_at_the_counter_uses_the_menu_price(): void
    {
        $griot = $this->plat(['price' => 350]);

        // La caisse envoie un prix fantaisiste : le serveur impose le sien.
        $vente = app(PosService::class)->recordSale($this->caisse, [
            'items' => [['ref' => CatalogRef::make('menu', $griot->id), 'qty' => 2, 'price' => 5, 'name' => 'Bidon']],
        ]);

        $this->assertEquals(700.0, (float) $vente->total);
        $this->assertSame('Griot', $vente->items->first()->name);
    }

    public function test_the_counter_and_the_qr_code_share_one_stock(): void
    {
        // Le cœur de la demande : une seule saisie, un seul stock.
        $griot = $this->plat(['stock' => 10]);

        app(PosService::class)->recordSale($this->caisse, [
            'items' => [['ref' => CatalogRef::make('menu', $griot->id), 'qty' => 3]],
        ]);

        $this->assertSame(7, $griot->fresh()->stock, 'Vendre au comptoir doit retirer du stock du menu.');
    }

    public function test_dish_seven_and_button_seven_are_never_confused(): void
    {
        // Les deux listes ont leurs propres identifiants, qui commencent tous
        // les deux à 1. Sans discriminant, on viderait le mauvais stock.
        $bouton = app(PosCatalog::class)->save($this->caisse, ['name' => 'Sachet dlo', 'price' => 25, 'stock' => 100, 'is_active' => true]);
        $plat   = $this->plat(['name' => 'Griot', 'price' => 350, 'stock' => 10]);

        app(PosService::class)->recordSale($this->caisse, [
            'items' => [['ref' => CatalogRef::make('menu', $plat->id), 'qty' => 1]],
        ]);

        $this->assertSame(9, $plat->fresh()->stock, 'Le plat doit baisser…');
        $this->assertSame(100, $bouton->fresh()->stock, '…et le bouton ne doit pas bouger.');
    }

    public function test_each_sale_line_says_which_catalogue_it_came_from(): void
    {
        $bouton = app(PosCatalog::class)->save($this->caisse, ['name' => 'Sachet dlo', 'price' => 25, 'is_active' => true]);
        $plat   = $this->plat();

        $vente = app(PosService::class)->recordSale($this->caisse, [
            'items' => [
                ['ref' => CatalogRef::make('pos', $bouton->id), 'qty' => 1],
                ['ref' => CatalogRef::make('menu', $plat->id), 'qty' => 1],
            ],
        ]);

        $this->assertSame(['pos', 'menu'], $vente->items->pluck('source')->all());
    }

    public function test_a_dish_from_a_neighbouring_shop_is_never_sellable_here(): void
    {
        $autreMenu = Menu::create(['tenant_id' => 't-2', 'name' => 'Bar', 'alias' => 'bar', 'currency' => 'HTG', 'is_active' => true]);
        $autreCat  = Category::create(['menu_id' => $autreMenu->id, 'name' => 'Boissons', 'is_active' => true]);
        $biere = Item::create(['menu_id' => $autreMenu->id, 'category_id' => $autreCat->id,
            'name' => 'Prestige', 'price' => 200, 'stock' => 50, 'is_available' => true]);

        $this->assertNull(app(PosCatalog::class)->resolve('t-1', CatalogRef::make('menu', $biere->id)));

        // Et une vente qui la réclame retombe sur un article libre.
        $vente = app(PosService::class)->recordSale($this->caisse, [
            'items' => [['ref' => CatalogRef::make('menu', $biere->id), 'qty' => 1, 'price' => 10, 'name' => 'Divers']],
        ]);

        $this->assertEquals(10.0, (float) $vente->total);
        $this->assertSame('Divers', $vente->items->first()->name);
        $this->assertSame(50, $biere->fresh()->stock, 'Le stock du voisin ne doit pas bouger.');
    }

    public function test_a_dish_the_kitchen_no_longer_has_is_not_offered(): void
    {
        // Proposer au caissier un plat épuisé, c'est le faire encaisser pour rien.
        $this->plat(['name' => 'Épuisé', 'stock' => 0]);
        $this->plat(['name' => 'Retiré', 'is_available' => false]);
        $this->plat(['name' => 'Disponible', 'stock' => 4]);

        $noms = array_column(app(PosCatalog::class)->sellable('t-1'), 'name');

        $this->assertSame(['Disponible'], $noms);
    }

    public function test_a_closed_menu_stops_feeding_the_till(): void
    {
        $this->plat();
        $this->menu->update(['is_active' => false]);

        $this->assertSame([], app(PosCatalog::class)->sellable('t-1'));
    }

    public function test_a_till_already_installed_keeps_selling_after_the_update(): void
    {
        // Les caisses en service envoient encore `product_id` nu. Une mise à
        // jour de l'application ne doit pas interrompre une vente en cours.
        $bouton = app(PosCatalog::class)->save($this->caisse, ['name' => 'Sachet dlo', 'price' => 25, 'stock' => 100, 'is_active' => true]);

        $vente = app(PosService::class)->recordSale($this->caisse, [
            'items' => [['product_id' => $bouton->id, 'qty' => 2]],
        ]);

        $this->assertEquals(50.0, (float) $vente->total);
        $this->assertSame(98, $bouton->fresh()->stock);
        $this->assertSame('pos', $vente->items->first()->source);
    }

    public function test_an_item_sold_off_the_cuff_keeps_the_cashiers_price(): void
    {
        // Le « divers » : pas de référence, donc le prix du caissier fait foi.
        $vente = app(PosService::class)->recordSale($this->caisse, [
            'items' => [['name' => 'Bouteille consignée', 'price' => 15, 'qty' => 2]],
        ]);

        $this->assertEquals(30.0, (float) $vente->total);
        $this->assertNull($vente->items->first()->product_id);
    }

    public function test_an_invented_reference_never_picks_an_article(): void
    {
        $bouton = app(PosCatalog::class)->save($this->caisse, ['name' => 'Sachet dlo', 'price' => 25, 'stock' => 100, 'is_active' => true]);

        foreach (['stock:'.$bouton->id, 'attaquant:1', 'menu:abc'] as $bidon) {
            $vente = app(PosService::class)->recordSale($this->caisse, [
                'items' => [['ref' => $bidon, 'qty' => 1, 'price' => 1, 'name' => 'X']],
            ]);
            $this->assertEquals(1.0, (float) $vente->total, "« $bidon » ne doit désigner aucun article.");
        }

        $this->assertSame(100, $bouton->fresh()->stock);
    }
}
