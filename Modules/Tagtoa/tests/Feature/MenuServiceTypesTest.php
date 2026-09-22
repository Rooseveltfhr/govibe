<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA MENU — « Modes de service offerts »
|--------------------------------------------------------------------------
| Un menu peut restreindre les modes offerts au client (sur place / à
| emporter / livraison) à un sous-ensemble des trois. Le calcul pur est
| couvert par OrderServiceTypesTest ; ici : le câblage formulaire → base,
| l'écran public (boutons + défaut), et l'application côté serveur — un
| appel direct ne doit jamais réussir à passer une commande dans un mode que
| CE menu n'offre pas.
|--------------------------------------------------------------------------
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Services\Menu\MenuOrderService;
use Modules\Tagtoa\Tests\TestCase;

class MenuServiceTypesTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));
    }

    private function menu(array $attrs = []): Menu
    {
        return Menu::create(array_merge([
            'tenant_id' => 't-1', 'name' => 'Lounge', 'alias' => 'lounge-'.uniqid(),
            'currency' => 'HTG', 'is_active' => true,
            'ordering_enabled' => true, 'whatsapp' => '+509 0000 0000',
        ], $attrs));
    }

    private function item(Menu $menu): Item
    {
        $cat = Category::create(['menu_id' => $menu->id, 'name' => 'Plats', 'is_active' => true]);

        return Item::create(['menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Plat', 'price' => 100, 'is_available' => true]);
    }

    public function test_saving_the_menu_form_persists_the_chosen_service_types(): void
    {
        $this->patron();
        $menu = $this->menu();

        $this->put(route('tagtoa.menu.dashboard.update', $menu->id), [
            'name' => 'Lounge', 'currency' => 'HTG', 'form_end' => '1',
            'service_types' => ['pickup', 'delivery'],
        ])->assertRedirect();

        $this->assertSame(['delivery', 'pickup'], $menu->fresh()->service_types);
    }

    public function test_the_creation_form_shows_a_chip_per_service_mode(): void
    {
        $this->patron();

        $html = $this->get(route('tagtoa.menu.dashboard.create'))->assertOk()->getContent();

        $this->assertStringContainsString('name="service_types[]"', $html);
        $this->assertStringContainsString('Livraison', $html);
    }

    public function test_the_public_page_shows_all_three_modes_when_unrestricted(): void
    {
        $menu = $this->menu();

        $html = $this->get('/menu/'.$menu->alias)->assertOk()->getContent();

        $this->assertStringContainsString('data-type="dine_in"', $html);
        $this->assertStringContainsString('data-type="pickup"', $html);
        $this->assertStringContainsString('data-type="delivery"', $html);
    }

    public function test_the_public_page_shows_only_the_menu_s_chosen_modes(): void
    {
        $menu = $this->menu(['service_types' => ['delivery']]);

        $html = $this->get('/menu/'.$menu->alias)->assertOk()->getContent();

        $this->assertStringContainsString('data-type="delivery"', $html);
        $this->assertStringNotContainsString('data-type="dine_in"', $html);
        $this->assertStringNotContainsString('data-type="pickup"', $html);
    }

    public function test_a_delivery_only_menu_defaults_to_delivery_not_dine_in(): void
    {
        $menu = $this->menu(['service_types' => ['delivery']]);

        $html = $this->get('/menu/'.$menu->alias)->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/class="otbtn\s+on\s*"\s*data-type="delivery"/', $html);
    }

    public function test_an_order_in_a_mode_the_menu_does_not_offer_falls_back_to_an_offered_mode(): void
    {
        $menu = $this->menu(['service_types' => ['pickup']]);
        $item = $this->item($menu);

        // Appel direct au service, comme un client contournant l'écran :
        // demande « livraison » sur un menu qui n'offre QUE le retrait.
        $order = app(MenuOrderService::class)->placeOrder($menu, [
            'items' => [['id' => $item->id, 'qty' => 1]], 'order_type' => 'delivery',
        ]);

        $this->assertSame('pickup', $order->order_type);
        // Confirme aussi que le frais de livraison ne s'applique pas à une
        // commande retombée en retrait.
        $this->assertSame('0.00', $order->delivery_fee);
    }

    public function test_an_order_in_an_offered_mode_is_accepted_as_is(): void
    {
        $menu = $this->menu(['service_types' => ['pickup', 'delivery']]);
        $item = $this->item($menu);

        $order = app(MenuOrderService::class)->placeOrder($menu, [
            'items' => [['id' => $item->id, 'qty' => 1]], 'order_type' => 'delivery',
        ]);

        $this->assertSame('delivery', $order->order_type);
    }
}
