<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA MENU (HM-1) — un seul menu pour le restaurant ET le bar
|--------------------------------------------------------------------------
| Un hôtel (ou un restaurant) qui a un bar ne crée pas deux menus : les catégories portent un rayon
| (restaurant / bar), une station (cuisine / bar) et un drapeau « alcool » (18 ans et plus).
|
| - la page publique montre des onglets SEULEMENT s'il y a deux rayons ;
| - un rayon d'alcool exige la confirmation d'âge CÔTÉ SERVEUR ;
| - la station est gelée sur la ligne de commande ;
| - l'écran cuisine / bar filtre par station (statut global, une seule commande) ;
| - le formulaire n'écrit rien sans son marqueur (assistant, vieux gabarits).
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Menu\Order;
use Modules\Tagtoa\Tests\TestCase;

class MenuSectionsStationsTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenant = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenant, 'name' => 'Rose']));
    }

    /** @return array{0:Menu,1:Item,2:Item,3:Item} menu, plat, cocktail (alcool), eau */
    private function menuAvecBar(string $type = 'hotel'): array
    {
        $menu = Menu::create(['tenant_id' => 't-1', 'name' => 'Hôtel Alpha', 'alias' => 'alpha-'.uniqid(), 'type' => $type, 'currency' => 'HTG', 'is_active' => true, 'ordering_enabled' => true, 'show_prices' => true]);
        $plats = Category::create(['menu_id' => $menu->id, 'name' => 'Plats', 'is_active' => true, 'section' => 'restaurant', 'station' => 'kitchen']);
        $cock = Category::create(['menu_id' => $menu->id, 'name' => 'Cocktails', 'is_active' => true, 'section' => 'bar', 'station' => 'bar', 'is_alcohol' => true]);
        $soft = Category::create(['menu_id' => $menu->id, 'name' => 'Eaux', 'is_active' => true, 'section' => 'bar', 'station' => 'bar']);
        $plat = Item::create(['menu_id' => $menu->id, 'category_id' => $plats->id, 'name' => 'Griot', 'price' => 400, 'is_available' => true, 'on_request' => true]);
        $mojito = Item::create(['menu_id' => $menu->id, 'category_id' => $cock->id, 'name' => 'Mojito', 'price' => 500, 'is_available' => true]);
        $eau = Item::create(['menu_id' => $menu->id, 'category_id' => $soft->id, 'name' => 'Eau 1L', 'price' => 100, 'is_available' => true]);

        return [$menu, $plat, $mojito, $eau];
    }

    private function commander(Menu $m, array $items, array $extra = [])
    {
        return $this->postJson(route('tagtoa.menu.order', $m->alias), array_merge([
            'items' => array_map(fn ($i) => ['id' => $i->id, 'qty' => 1], $items), 'order_type' => 'dine_in',
        ], $extra));
    }

    /* ---------- schéma ---------- */

    public function test_the_columns_exist_with_safe_defaults(): void
    {
        $this->assertTrue(Schema::hasColumns('tagtoa_menu_categories', ['section', 'station', 'is_alcohol']));
        $this->assertTrue(Schema::hasColumn('tagtoa_menu_items', 'on_request'));
        $this->assertTrue(Schema::hasColumn('tagtoa_menu_order_items', 'station'));

        $menu = Menu::create(['tenant_id' => 't-1', 'name' => 'Ancien', 'alias' => 'ancien-'.uniqid(), 'currency' => 'HTG']);
        $c = Category::create(['menu_id' => $menu->id, 'name' => 'Boissons'])->fresh();
        $this->assertSame('restaurant', $c->section);
        $this->assertSame('kitchen', $c->station);
        $this->assertFalse($c->is_alcohol);
    }

    public function test_unknown_section_or_station_values_fall_back_to_the_safe_default(): void
    {
        $this->assertSame('restaurant', Category::cleanSection('<script>'));
        $this->assertSame('bar', Category::cleanSection('bar'));
        $this->assertSame('kitchen', Category::cleanStation('piscine'));
        $this->assertSame('bar', Category::cleanStation('bar'));
        $this->assertSame('kitchen', Category::cleanStation(null));
    }

    /* ---------- page publique ---------- */

    public function test_the_public_page_shows_section_tabs_only_with_two_sections(): void
    {
        [$menu] = $this->menuAvecBar();
        $html = $this->get(route('tagtoa.menu.show', $menu->alias))->assertOk()->getContent();

        $this->assertStringContainsString('id="sectabs"', $html);
        $this->assertStringContainsString('data-sec="bar"', $html);
        $this->assertStringContainsString('data-sec="restaurant"', $html);

        // Un restaurant ordinaire garde la page d'avant : pas d'onglets.
        $simple = Menu::create(['tenant_id' => 't-1', 'name' => 'Simple', 'alias' => 'simple-'.uniqid(), 'type' => 'restaurant', 'currency' => 'HTG', 'is_active' => true, 'ordering_enabled' => true]);
        $cat = Category::create(['menu_id' => $simple->id, 'name' => 'Plats', 'is_active' => true]);
        Item::create(['menu_id' => $simple->id, 'category_id' => $cat->id, 'name' => 'Pâtes', 'price' => 10, 'is_available' => true]);
        $this->assertStringNotContainsString('id="sectabs"', $this->get(route('tagtoa.menu.show', $simple->alias))->getContent());
    }

    public function test_on_request_items_and_alcohol_categories_are_flagged_for_the_guest(): void
    {
        [$menu] = $this->menuAvecBar();
        $html = $this->get(route('tagtoa.menu.show', $menu->alias))->getContent();

        $this->assertStringContainsString('Sur commande', $html);
        $this->assertStringContainsString('18+', $html);
        $this->assertStringContainsString('id="ageBox"', $html);
    }

    /* ---------- âge : le serveur décide ---------- */

    public function test_an_alcohol_item_is_refused_without_the_age_confirmation(): void
    {
        [$menu, , $mojito] = $this->menuAvecBar();

        $this->commander($menu, [$mojito])->assertStatus(422);
        $this->assertSame(0, Order::allTenants()->count());
    }

    public function test_an_alcohol_item_is_accepted_with_the_age_confirmation(): void
    {
        [$menu, , $mojito] = $this->menuAvecBar();

        $this->commander($menu, [$mojito], ['age_ok' => true])->assertSuccessful();
        $this->assertSame(1, Order::allTenants()->count());
    }

    public function test_a_mixed_cart_needs_the_confirmation_but_a_soft_drink_alone_does_not(): void
    {
        [$menu, $plat, $mojito, $eau] = $this->menuAvecBar();

        $this->commander($menu, [$eau, $plat])->assertSuccessful();                      // sans alcool : aucune confirmation
        $this->commander($menu, [$plat, $mojito])->assertStatus(422);                    // un seul article d'alcool suffit
        $this->commander($menu, [$plat, $mojito], ['age_ok' => false])->assertStatus(422);
        $this->commander($menu, [$plat, $mojito], ['age_ok' => true])->assertSuccessful();
    }

    /* ---------- stations ---------- */

    public function test_each_order_line_freezes_its_station_at_order_time(): void
    {
        [$menu, $plat, $mojito, $eau] = $this->menuAvecBar();
        $this->commander($menu, [$plat, $mojito, $eau], ['age_ok' => true])->assertSuccessful();

        $order = Order::allTenants()->with('items')->first();
        $parNom = $order->items->pluck('station', 'name');
        $this->assertSame('kitchen', $parNom['Griot']);
        $this->assertSame('bar', $parNom['Mojito']);
        $this->assertSame('bar', $parNom['Eau 1L']);

        // Déplacer la catégorie plus tard ne réécrit pas l'histoire.
        Category::where('name', 'Cocktails')->update(['station' => 'kitchen']);
        $this->assertSame('bar', $order->fresh()->items->firstWhere('name', 'Mojito')->station);
    }

    public function test_the_kitchen_feed_splits_one_order_across_kitchen_and_bar_screens(): void
    {
        [$menu, $plat, $mojito, $eau] = $this->menuAvecBar();
        $this->commander($menu, [$plat, $mojito, $eau], ['age_ok' => true])->assertSuccessful();
        $this->commander($menu, [$eau])->assertSuccessful();                              // commande 100 % bar
        $this->patron();

        $tout = $this->getJson(route('tagtoa.menu.dashboard.kitchen.feed', $menu->id))->assertOk()->json();
        $this->assertCount(2, $tout['orders']);
        $this->assertTrue($tout['has_bar']);

        $cuisine = $this->getJson(route('tagtoa.menu.dashboard.kitchen.feed', $menu->id).'?station=kitchen')->json();
        $this->assertCount(1, $cuisine['orders'], 'La commande 100 % bar n\'apparaît pas en cuisine.');
        $this->assertSame(['Griot'], array_column($cuisine['orders'][0]['items'], 'name'));

        $bar = $this->getJson(route('tagtoa.menu.dashboard.kitchen.feed', $menu->id).'?station=bar')->json();
        $this->assertCount(2, $bar['orders']);
        $noms = collect($bar['orders'][0]['items'])->pluck('name')->sort()->values()->all();
        $this->assertSame(['Eau 1L', 'Mojito'], $noms, 'Le bar ne voit que ses lignes.');

        // Station inconnue → ignorée (pas d'erreur, tout est montré).
        $this->assertCount(2, $this->getJson(route('tagtoa.menu.dashboard.kitchen.feed', $menu->id).'?station=piscine')->json()['orders']);
    }

    public function test_the_kitchen_feed_of_a_menu_without_bar_hides_the_bar_tab(): void
    {
        $this->patron();
        $menu = Menu::create(['tenant_id' => 't-1', 'name' => 'Simple', 'alias' => 'simple-'.uniqid(), 'type' => 'restaurant', 'currency' => 'HTG']);
        $this->assertFalse($this->getJson(route('tagtoa.menu.dashboard.kitchen.feed', $menu->id))->json()['has_bar']);
    }

    public function test_a_line_without_station_from_before_counts_as_kitchen(): void
    {
        [$menu, $plat] = $this->menuAvecBar();
        $this->commander($menu, [$plat])->assertSuccessful();
        \Modules\Tagtoa\App\Models\Menu\OrderItem::query()->update(['station' => null]);
        $this->patron();

        $this->assertCount(1, $this->getJson(route('tagtoa.menu.dashboard.kitchen.feed', $menu->id).'?station=kitchen')->json()['orders']);
        $this->assertCount(0, $this->getJson(route('tagtoa.menu.dashboard.kitchen.feed', $menu->id).'?station=bar')->json()['orders']);
    }

    public function test_another_tenant_cannot_read_this_kitchen_feed(): void
    {
        [$menu] = $this->menuAvecBar();
        $this->patron('t-2');

        $this->getJson(route('tagtoa.menu.dashboard.kitchen.feed', $menu->id))->assertNotFound();
    }

    /* ---------- formulaire (éditeur) ---------- */

    public function test_the_editor_saves_section_station_and_alcohol_only_with_its_marker(): void
    {
        $this->patron();
        [$menu] = $this->menuAvecBar();
        $cat = Category::where('name', 'Plats')->first();
        $payload = fn (array $c) => ['name' => $menu->name, 'type' => 'hotel', 'form_end' => 1, 'cats' => [array_merge(['id' => $cat->id, 'name' => 'Plats'], $c)]];

        // Sans marqueur (assistant, vieux gabarit) : RIEN n'est écrit.
        $this->put(route('tagtoa.menu.dashboard.update', $menu->id), $payload(['section' => 'bar', 'station' => 'bar', 'is_alcohol' => '1']));
        $c = $cat->fresh();
        $this->assertSame(['restaurant', 'kitchen', false], [$c->section, $c->station, $c->is_alcohol]);

        // Avec marqueur : écrit.
        $this->put(route('tagtoa.menu.dashboard.update', $menu->id), $payload(['rayon_sent' => 1, 'section' => 'bar', 'station' => 'bar', 'is_alcohol' => '1']))->assertSessionHasNoErrors();
        $c = $cat->fresh();
        $this->assertSame(['bar', 'bar', true], [$c->section, $c->station, $c->is_alcohol]);

        // Marqueur + valeurs invalides : retombent sur le défaut sûr, case décochée = non alcool.
        $this->put(route('tagtoa.menu.dashboard.update', $menu->id), $payload(['rayon_sent' => 1, 'section' => 'piscine', 'station' => 'x']));
        $c = $cat->fresh();
        $this->assertSame(['restaurant', 'kitchen', false], [$c->section, $c->station, $c->is_alcohol]);
    }

    public function test_the_editor_saves_on_request_on_items_only_with_its_marker(): void
    {
        $this->patron();
        [$menu, $plat] = $this->menuAvecBar();      // « Griot » est créé sur commande
        $cat = Category::where('name', 'Plats')->first();
        $envoi = fn (array $it) => ['name' => $menu->name, 'type' => 'hotel', 'form_end' => 1, 'cats' => [['id' => $cat->id, 'name' => 'Plats', 'items' => [array_merge(['id' => $plat->id, 'name' => 'Griot', 'price' => 400], $it)]]]];

        $this->put(route('tagtoa.menu.dashboard.update', $menu->id), $envoi([]))->assertSessionHasNoErrors();
        $this->assertTrue($plat->fresh()->on_request, 'Sans marqueur : jamais écrasé.');

        $this->put(route('tagtoa.menu.dashboard.update', $menu->id), $envoi(['rayon_sent' => 1]))->assertSessionHasNoErrors();
        $this->assertFalse($plat->fresh()->on_request, 'Marqueur + case décochée = plus sur commande.');

        $this->put(route('tagtoa.menu.dashboard.update', $menu->id), $envoi(['rayon_sent' => 1, 'on_request' => 1]))->assertSessionHasNoErrors();
        $this->assertTrue($plat->fresh()->on_request);
    }

    public function test_the_editor_form_offers_sections_for_a_hotel_but_not_for_a_hardware_store(): void
    {
        $this->patron();
        [$menu] = $this->menuAvecBar('hotel');
        $this->get(route('tagtoa.menu.dashboard.edit', $menu->id))->assertOk()->assertSee('rayon_sent', false)->assertSee('Alcool (18+)');

        $q = Menu::create(['tenant_id' => 't-1', 'name' => 'Quinc', 'alias' => 'quinc-'.uniqid(), 'type' => 'quincaillerie', 'currency' => 'HTG']);
        $this->get(route('tagtoa.menu.dashboard.edit', $q->id))->assertOk()->assertDontSee('Alcool (18+)');
    }

    public function test_the_migration_is_replayable_and_reversible(): void
    {
        $m = require __DIR__.'/../../Database/migrations/2026_11_02_000195_add_sections_and_stations_to_menu.php';
        $m->up();
        $m->up();                                                  // rejouable
        $m->down();
        $this->assertFalse(Schema::hasColumn('tagtoa_menu_categories', 'section'));
        $this->assertFalse(Schema::hasColumn('tagtoa_menu_items', 'on_request'));
        $this->assertFalse(Schema::hasColumn('tagtoa_menu_order_items', 'station'));
        $m->up();
        $this->assertTrue(Schema::hasColumn('tagtoa_menu_categories', 'is_alcohol'));
    }
}
