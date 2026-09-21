<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA MENU — l'écran cuisine n'existait pas : pour savoir ce qu'il
| restait à préparer, quelqu'un devait ouvrir l'écran « Commandes »,
| pensé pour le back-office, pas pour rester affiché sur une tablette
| au-dessus du plan de travail. Ces tests figent le contenu du flux JSON
| que cet écran interroge, et l'isolation entre commerces.
|--------------------------------------------------------------------------
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Menu\Order;
use Modules\Tagtoa\App\Models\Menu\OrderItem;
use Modules\Tagtoa\Tests\TestCase;

class MenuKitchenScreenTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));
    }

    private function menu(string $tenantId = 't-1'): Menu
    {
        return Menu::create([
            'tenant_id' => $tenantId, 'name' => 'Lounge Test', 'alias' => 'lounge-'.uniqid(), 'currency' => 'HTG',
        ]);
    }

    private function order(Menu $menu, string $status, ?\DateTimeInterface $placedAt = null): Order
    {
        $order = Order::create([
            'menu_id' => $menu->id, 'tenant_id' => $menu->tenant_id, 'reference' => Order::generateReference(),
            'subtotal' => 100, 'total' => 100, 'tip' => 0, 'currency' => 'HTG',
            'status' => $status, 'payment_status' => 'unpaid', 'channel' => 'menu', 'order_type' => 'dine_in',
            'table_label' => '4', 'placed_at' => $placedAt ?? now(),
        ]);
        OrderItem::create(['order_id' => $order->id, 'name' => 'Griot', 'price' => 100, 'qty' => 2, 'line_total' => 200]);

        return $order;
    }

    public function test_the_screen_renders_for_its_owner(): void
    {
        $this->patron();
        $menu = $this->menu();

        $this->get(route('tagtoa.menu.dashboard.kitchen', $menu->id))->assertOk()->assertSee('Cuisine');
    }

    public function test_a_foreign_tenant_cannot_open_another_commerces_kitchen_screen(): void
    {
        $this->patron('t-1');
        $menu = $this->menu('t-2');

        $this->get(route('tagtoa.menu.dashboard.kitchen', $menu->id))->assertNotFound();
    }

    public function test_the_feed_lists_only_orders_still_to_prepare_oldest_first(): void
    {
        $this->patron();
        $menu = $this->menu();
        $vieille = $this->order($menu, 'pending', now()->subMinutes(20));
        $recente = $this->order($menu, 'preparing', now()->subMinutes(2));
        $this->order($menu, 'ready'); // déjà prête : ne doit plus apparaître ici
        $this->order($menu, 'completed');
        $this->order($menu, 'cancelled');

        $json = $this->getJson(route('tagtoa.menu.dashboard.kitchen.feed', $menu->id))->assertOk()->json('orders');

        $this->assertCount(2, $json);
        $this->assertSame($vieille->reference, $json[0]['reference']);
        $this->assertSame($recente->reference, $json[1]['reference']);
        $this->assertSame('Griot', $json[0]['items'][0]['name']);
        $this->assertSame(2, $json[0]['items'][0]['qty']);
    }

    public function test_the_feed_never_leaks_another_tenants_orders(): void
    {
        $this->patron('t-1');
        $mine = $this->menu('t-1');
        $other = $this->menu('t-2');
        $this->order($mine, 'pending');
        $this->order($other, 'pending');

        $json = $this->getJson(route('tagtoa.menu.dashboard.kitchen.feed', $mine->id))->assertOk()->json('orders');

        $this->assertCount(1, $json);
    }
}
