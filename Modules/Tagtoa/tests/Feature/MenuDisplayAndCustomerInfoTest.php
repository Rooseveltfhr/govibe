<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA MENU — deux réglages vus sur la maquette assistant
|--------------------------------------------------------------------------
| « Afficher les images » et « Demander les informations client » :
| défauts choisis pour que les menus déjà en service ne changent pas tant
| que leur propriétaire n'a pas coché/décoché quoi que ce soit.
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Services\Menu\MenuOrderService;
use Modules\Tagtoa\Tests\TestCase;

class MenuDisplayAndCustomerInfoTest extends TestCase
{
    use RefreshDatabase;

    private function menu(array $attrs = []): Menu
    {
        return Menu::create(array_merge([
            'tenant_id' => 't-1', 'name' => 'Chez Rose', 'alias' => 'chez-rose-'.uniqid(),
            'currency' => 'HTG', 'is_active' => true, 'ordering_enabled' => true,
            'whatsapp' => '+509 3000 0000',
        ], $attrs));
    }

    private function item(Menu $menu, array $attrs = []): Item
    {
        $cat = Category::create(['menu_id' => $menu->id, 'name' => 'Plats', 'is_active' => true]);

        return Item::create(array_merge([
            'menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Griot',
            'price' => 350, 'is_available' => true, 'image_path' => 'menu/griot.jpg',
        ], $attrs));
    }

    /* ------------------------------------------------------------------
       Afficher les images.
       ------------------------------------------------------------------ */

    public function test_item_photos_show_by_default(): void
    {
        $menu = $this->menu();
        $this->item($menu);

        $html = $this->get('/menu/'.$menu->alias)->assertOk()->getContent();

        $this->assertStringContainsString('class="ph"', $html);
    }

    public function test_disabling_show_images_hides_every_item_photo(): void
    {
        $menu = $this->menu(['show_images' => false]);
        $this->item($menu);

        $html = $this->get('/menu/'.$menu->alias)->assertOk()->getContent();

        $this->assertStringNotContainsString('class="ph"', $html);
    }

    /* ------------------------------------------------------------------
       Demander les informations client.
       ------------------------------------------------------------------ */

    public function test_customer_info_stays_optional_by_default(): void
    {
        $menu = $this->menu();
        $item = $this->item($menu);

        $order = app(MenuOrderService::class)->placeOrder($menu, [
            'items' => [['id' => $item->id, 'qty' => 1]],
        ]);

        $this->assertNotNull($order->id);
    }

    public function test_requiring_customer_info_rejects_an_order_without_it(): void
    {
        $menu = $this->menu(['require_customer_info' => true]);
        $item = $this->item($menu);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('missing_customer_info');

        app(MenuOrderService::class)->placeOrder($menu, [
            'items' => [['id' => $item->id, 'qty' => 1]],
        ]);
    }

    public function test_requiring_customer_info_rejects_a_name_only_order(): void
    {
        $menu = $this->menu(['require_customer_info' => true]);
        $item = $this->item($menu);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('missing_customer_info');

        app(MenuOrderService::class)->placeOrder($menu, [
            'items' => [['id' => $item->id, 'qty' => 1]],
            'customer_name' => 'Roosevelt',
        ]);
    }

    public function test_requiring_customer_info_accepts_an_order_with_both_fields(): void
    {
        $menu = $this->menu(['require_customer_info' => true]);
        $item = $this->item($menu);

        $order = app(MenuOrderService::class)->placeOrder($menu, [
            'items' => [['id' => $item->id, 'qty' => 1]],
            'customer_name' => 'Roosevelt',
            'customer_phone' => '+509 3000 0000',
        ]);

        $this->assertSame('Roosevelt', $order->customer_name);
    }

    public function test_the_public_endpoint_translates_the_missing_customer_info_error_without_a_500(): void
    {
        $menu = $this->menu(['require_customer_info' => true]);
        $item = $this->item($menu);

        $response = $this->postJson(route('tagtoa.menu.order', $menu->alias), [
            'items' => [['id' => $item->id, 'qty' => 1]],
        ]);

        $response->assertStatus(422)->assertJsonPath('ok', false);
    }
}
