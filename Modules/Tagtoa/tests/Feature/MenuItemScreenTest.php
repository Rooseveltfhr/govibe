<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA MENU — « Ajouter un produit », un écran à part
|--------------------------------------------------------------------------
| Signalé (vidéo) : le bouton « Ajouter un produit » ouvrait le même
| formulaire que créer/modifier l'établissement — nom, logo, adresse,
| catégories, tout mélangé. Cet écran (ItemController) ne doit montrer QUE
| ce qui concerne le produit, et enregistrer sans jamais toucher le nom de
| l'établissement, les autres catégories ou les autres produits.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Support\Tenant;
use Modules\Tagtoa\Tests\TestCase;

class MenuItemScreenTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));
        Tenant::flush();
    }

    private function menu(string $tenantId = 't-1', array $attrs = []): Menu
    {
        return Menu::create(array_merge([
            'tenant_id' => $tenantId, 'name' => 'Lakay Grill', 'alias' => 'lakay-'.uniqid(),
            'type' => 'restaurant', 'currency' => 'HTG', 'is_active' => true,
        ], $attrs));
    }

    public function test_the_add_product_screen_shows_no_establishment_field(): void
    {
        $this->patron();
        $menu = $this->menu();

        $html = $this->get(route('tagtoa.menu.dashboard.items.create', $menu->id))->assertOk()->getContent();

        // Les champs du PRODUIT sont là…
        $this->assertStringContainsString('name="item_name"', $html);
        $this->assertStringContainsString('name="item_price"', $html);
        // …mais jamais ceux de l'ÉTABLISSEMENT (nom du commerce, WhatsApp,
        // adresse, logo) : un envoi tronqué de ce formulaire ne doit même
        // pas pouvoir y toucher.
        $this->assertStringNotContainsString('name="whatsapp"', $html);
        $this->assertStringNotContainsString('name="address"', $html);
        $this->assertStringNotContainsString('name="logo"', $html);
        $this->assertStringNotContainsString('name="alias"', $html);
    }

    public function test_adding_a_product_creates_it_in_a_new_category_without_touching_the_menu_identity(): void
    {
        $this->patron();
        $menu = $this->menu(attrs: ['name' => 'Lakay Grill', 'whatsapp' => '50912345678']);

        $this->post(route('tagtoa.menu.dashboard.items.store', $menu->id), [
            'new_category_name' => 'Plats principaux',
            'item_name'          => 'Griot avec banane',
            'item_price'         => 750,
            'item_description'   => 'Griot bien assaisonné',
            'item_is_available'  => '1',
        ])->assertRedirect(route('tagtoa.menu.dashboard.items.index', $menu->id));

        $cat = Category::where('menu_id', $menu->id)->where('name', 'Plats principaux')->firstOrFail();
        $item = Item::where('category_id', $cat->id)->where('name', 'Griot avec banane')->firstOrFail();
        $this->assertEquals(750, $item->price);
        $this->assertTrue($item->is_available);

        // Le nom et le WhatsApp de l'établissement n'ont PAS bougé — c'est
        // tout le sens de cet écran.
        $this->assertSame('Lakay Grill', $menu->fresh()->name);
        $this->assertSame('50912345678', $menu->fresh()->whatsapp);
    }

    /** Signalé : un envoi tronqué/mal relié écrasait la description du
     *  MENU avec celle du produit, car les deux formulaires partagent le
     *  même nom de champ `description` côté serveur. */
    public function test_the_products_description_never_overwrites_the_establishments_own_description(): void
    {
        $this->patron();
        $menu = $this->menu(attrs: ['description' => 'Description officielle du restaurant']);

        $this->post(route('tagtoa.menu.dashboard.items.store', $menu->id), [
            'new_category_name' => 'Plats',
            'item_name'          => 'Soupe joumou',
            'item_price'         => 300,
            'item_description'   => 'Servie chaude, le dimanche',
        ]);

        $this->assertSame('Description officielle du restaurant', $menu->fresh()->description);
        $this->assertSame('Servie chaude, le dimanche', Item::where('name', 'Soupe joumou')->firstOrFail()->description);
    }

    public function test_adding_a_product_to_an_existing_category_reuses_it_instead_of_duplicating(): void
    {
        $this->patron();
        $menu = $this->menu();
        $cat = Category::create(['menu_id' => $menu->id, 'name' => 'Boissons', 'is_active' => true]);
        Item::create(['menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Cola', 'price' => 100, 'is_available' => true]);

        $this->post(route('tagtoa.menu.dashboard.items.store', $menu->id), [
            'category_id' => $cat->id,
            'item_name'    => 'Jus naturel',
            'item_price'   => 150,
        ]);

        $this->assertSame(1, Category::where('menu_id', $menu->id)->count());
        $this->assertSame(2, Item::where('category_id', $cat->id)->count());
    }

    public function test_editing_an_existing_product_updates_it_in_place(): void
    {
        $this->patron();
        $menu = $this->menu();
        $cat = Category::create(['menu_id' => $menu->id, 'name' => 'Plats', 'is_active' => true]);
        $item = Item::create(['menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Riz collé', 'price' => 200, 'is_available' => true]);

        $this->put(route('tagtoa.menu.dashboard.items.update', [$menu->id, $item->id]), [
            'category_id' => $cat->id,
            'item_name'    => 'Riz collé aux pois',
            'item_price'   => 225,
        ])->assertRedirect(route('tagtoa.menu.dashboard.items.index', $menu->id));

        $this->assertSame(1, Item::where('category_id', $cat->id)->count());
        $fresh = $item->fresh();
        $this->assertSame('Riz collé aux pois', $fresh->name);
        $this->assertEquals(225, $fresh->price);
    }

    public function test_saving_a_product_never_removes_other_categories_or_items(): void
    {
        $this->patron();
        $menu = $this->menu();
        $catA = Category::create(['menu_id' => $menu->id, 'name' => 'Entrées', 'is_active' => true]);
        $existing = Item::create(['menu_id' => $menu->id, 'category_id' => $catA->id, 'name' => 'Accras', 'price' => 100, 'is_available' => true]);

        $this->post(route('tagtoa.menu.dashboard.items.store', $menu->id), [
            'new_category_name' => 'Desserts',
            'item_name'          => 'Flan',
            'item_price'         => 125,
        ]);

        $this->assertNotNull($existing->fresh());
        $this->assertNotNull(Category::find($catA->id));
        $this->assertSame(2, Category::where('menu_id', $menu->id)->count());
    }

    public function test_a_business_type_field_is_sanitized_and_stored_on_the_new_product(): void
    {
        $this->patron();
        $menu = $this->menu(attrs: ['type' => 'restaurant']);

        $this->post(route('tagtoa.menu.dashboard.items.store', $menu->id), [
            'new_category_name' => 'Plats',
            'item_name'          => 'Tassot cabrit',
            'item_price'         => 600,
            'specs'              => ['spice' => 'Piquant', 'is_admin' => true],
        ]);

        $item = Item::where('name', 'Tassot cabrit')->firstOrFail();
        $this->assertSame(['spice' => 'Piquant'], $item->specs);
    }

    public function test_uploading_a_photo_attaches_it_to_the_new_product(): void
    {
        $this->patron();
        $menu = $this->menu();

        $this->post(route('tagtoa.menu.dashboard.items.store', $menu->id), [
            'new_category_name' => 'Plats',
            'item_name'          => 'Poulet rôti',
            'item_price'         => 500,
            'item_image'         => UploadedFile::fake()->image('poulet.jpg'),
        ]);

        $item = Item::where('name', 'Poulet rôti')->firstOrFail();
        $this->assertNotNull($item->image_path);
    }

    public function test_a_missing_name_redisplays_the_form_instead_of_a_404(): void
    {
        $this->patron();
        $menu = $this->menu();

        $this->post(route('tagtoa.menu.dashboard.items.store', $menu->id), [
            'new_category_name' => 'Plats',
            'item_price'          => 300,
        ])->assertSessionHasErrors('item_name');
    }

    public function test_a_tenant_cannot_add_a_product_to_another_tenants_menu(): void
    {
        $other = $this->menu('t-2');
        $this->patron('t-1');

        $this->get(route('tagtoa.menu.dashboard.items.create', $other->id))->assertNotFound();
        $this->post(route('tagtoa.menu.dashboard.items.store', $other->id), [
            'new_category_name' => 'Plats',
            'item_name'          => 'Intrus',
            'item_price'         => 100,
        ])->assertNotFound();
    }

    public function test_a_tenant_cannot_see_or_edit_another_tenants_product(): void
    {
        $other = $this->menu('t-2');
        $cat = Category::create(['menu_id' => $other->id, 'name' => 'Plats', 'is_active' => true]);
        $item = Item::create(['menu_id' => $other->id, 'category_id' => $cat->id, 'name' => 'Secret', 'price' => 999, 'is_available' => true]);

        $this->patron('t-1');

        $this->get(route('tagtoa.menu.dashboard.items.edit', [$other->id, $item->id]))->assertNotFound();
        $this->put(route('tagtoa.menu.dashboard.items.update', [$other->id, $item->id]), [
            'category_id' => $cat->id,
            'item_name'    => 'Volé',
            'item_price'   => 1,
        ])->assertNotFound();

        $this->assertSame('Secret', $item->fresh()->name);
        $this->assertEquals(999, $item->fresh()->price);
    }

    public function test_the_products_list_screen_groups_items_by_category(): void
    {
        $this->patron();
        $menu = $this->menu();
        $cat = Category::create(['menu_id' => $menu->id, 'name' => 'Boissons', 'is_active' => true]);
        Item::create(['menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Cola', 'price' => 100, 'is_available' => true]);

        $html = $this->get(route('tagtoa.menu.dashboard.items.index', $menu->id))->assertOk()->getContent();

        $this->assertStringContainsString('Boissons', $html);
        $this->assertStringContainsString('Cola', $html);
    }
}
