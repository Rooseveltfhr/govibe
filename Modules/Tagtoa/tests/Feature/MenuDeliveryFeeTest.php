<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA MENU — le mode « Livraison » existait déjà (order_type, adresse)
| mais aucun frais ne s'y ajoutait jamais : un commerce qui paie un
| coursier de sa poche ne pouvait pas le répercuter sans en discuter à
| part. `delivery_fee` sur le menu s'ajoute désormais au total, jamais
| taxé (même règle que le pourboire), UNIQUEMENT en mode livraison, et est
| figé sur la commande pour que changer le tarif demain ne change jamais
| le sens d'une commande déjà passée.
|--------------------------------------------------------------------------
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Services\Menu\MenuOrderService;
use Modules\Tagtoa\Tests\TestCase;

class MenuDeliveryFeeTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));
    }

    private function menu(array $attrs = []): Menu
    {
        return Menu::create(array_merge([
            'tenant_id' => 't-1', 'name' => 'Lounge', 'alias' => 'lounge-'.uniqid(), 'currency' => 'HTG',
        ], $attrs));
    }

    private function item(Menu $menu): Item
    {
        $cat = Category::create(['menu_id' => $menu->id, 'name' => 'Plats', 'is_active' => true]);

        return Item::create(['menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Plat', 'price' => 100, 'is_available' => true]);
    }

    public function test_a_delivery_order_is_charged_the_configured_fee(): void
    {
        $menu = $this->menu(['delivery_fee' => 50]);
        $item = $this->item($menu);

        $order = app(MenuOrderService::class)->placeOrder($menu, [
            'items' => [['id' => $item->id, 'qty' => 1]], 'order_type' => 'delivery',
        ]);

        $this->assertSame('50.00', $order->delivery_fee);
        $this->assertSame('150.00', $order->total);
    }

    public function test_dine_in_and_pickup_are_never_charged_the_delivery_fee(): void
    {
        $menu = $this->menu(['delivery_fee' => 50]);
        $item = $this->item($menu);

        $dineIn = app(MenuOrderService::class)->placeOrder($menu, [
            'items' => [['id' => $item->id, 'qty' => 1]], 'order_type' => 'dine_in',
        ]);
        $pickup = app(MenuOrderService::class)->placeOrder($menu, [
            'items' => [['id' => $item->id, 'qty' => 1]], 'order_type' => 'pickup', 'client_uuid' => 'p1',
        ]);

        $this->assertSame('0.00', $dineIn->delivery_fee);
        $this->assertSame('100.00', $dineIn->total);
        $this->assertSame('0.00', $pickup->delivery_fee);
        $this->assertSame('100.00', $pickup->total);
    }

    public function test_a_menu_without_a_configured_fee_offers_free_delivery_as_before(): void
    {
        // Régression : aucun frais configuré ne doit rien changer au
        // comportement d'avant cette fonctionnalité.
        $menu = $this->menu();
        $item = $this->item($menu);

        $order = app(MenuOrderService::class)->placeOrder($menu, [
            'items' => [['id' => $item->id, 'qty' => 1]], 'order_type' => 'delivery',
        ]);

        $this->assertSame('0.00', $order->delivery_fee);
        $this->assertSame('100.00', $order->total);
    }

    public function test_the_delivery_fee_is_never_taxed(): void
    {
        Business::updateOrCreate(['id' => 't-1'], [
            'account_id' => 't-1', 'name' => 'Lounge', 'currency' => 'HTG', 'is_active' => true,
            'tax_enabled' => true, 'tax_rate' => 10, 'tax_inclusive' => false,
        ]);
        $menu = $this->menu(['delivery_fee' => 50]);
        $item = $this->item($menu);

        $order = app(MenuOrderService::class)->placeOrder($menu, [
            'items' => [['id' => $item->id, 'qty' => 1]], 'order_type' => 'delivery',
        ]);

        // 100 HT + 10 (taxe, jamais sur les 50 de livraison) + 50 (livraison) = 160.
        $this->assertSame('160.00', $order->total);
        $this->assertSame(10.0, (float) $order->tax_total);
    }

    public function test_the_fee_charged_is_the_one_at_order_time_even_if_the_menu_changes_later(): void
    {
        $menu = $this->menu(['delivery_fee' => 50]);
        $item = $this->item($menu);

        $order = app(MenuOrderService::class)->placeOrder($menu, [
            'items' => [['id' => $item->id, 'qty' => 1]], 'order_type' => 'delivery',
        ]);

        $menu->update(['delivery_fee' => 200]);

        $this->assertSame('50.00', $order->fresh()->delivery_fee);
    }

    public function test_saving_the_menu_form_stores_the_delivery_fee(): void
    {
        $this->patron();
        $menu = $this->menu();

        $this->put(route('tagtoa.menu.dashboard.update', $menu->id), [
            'name' => 'Lounge', 'currency' => 'HTG', 'delivery_fee' => '75.50', 'form_end' => '1',
        ])->assertRedirect();

        $this->assertSame('75.50', $menu->fresh()->delivery_fee);
    }
}
