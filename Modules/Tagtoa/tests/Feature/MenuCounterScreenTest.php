<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA MENU — l'écran caisse complète le cycle ouvert par la cuisine :
| il ne montre QUE les commandes « Prête », et un seul geste sert et
| encaisse ensemble — jamais séparément, sinon une addition non réglée
| pourrait disparaître de la file sans que personne l'ait vue.
|--------------------------------------------------------------------------
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Menu\Order;
use Modules\Tagtoa\App\Models\Staff\Staff;
use Modules\Tagtoa\App\Services\Event\StaffPinService;
use Modules\Tagtoa\Tests\TestCase;

class MenuCounterScreenTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));
    }

    private function menu(string $tenantId = 't-1'): Menu
    {
        return Menu::create(['tenant_id' => $tenantId, 'name' => 'Lounge', 'alias' => 'lounge-'.uniqid(), 'currency' => 'HTG']);
    }

    private function order(Menu $menu, string $status): Order
    {
        return Order::create([
            'menu_id' => $menu->id, 'tenant_id' => $menu->tenant_id, 'reference' => Order::generateReference(),
            'subtotal' => 500, 'total' => 500, 'tip' => 0, 'currency' => 'HTG',
            'status' => $status, 'payment_status' => 'unpaid', 'channel' => 'menu', 'order_type' => 'dine_in',
            'placed_at' => now(),
        ]);
    }

    public function test_the_screen_renders_for_its_owner(): void
    {
        $this->patron();
        $menu = $this->menu();

        $this->get(route('tagtoa.menu.dashboard.counter', $menu->id))->assertOk()->assertSee('Caisse');
    }

    public function test_the_feed_lists_only_ready_orders(): void
    {
        $this->patron();
        $menu = $this->menu();
        $prete = $this->order($menu, 'ready');
        $this->order($menu, 'preparing');
        $this->order($menu, 'pending');
        $this->order($menu, 'completed');

        $json = $this->getJson(route('tagtoa.menu.dashboard.counter.feed', $menu->id))->assertOk()->json('orders');

        $this->assertCount(1, $json);
        $this->assertSame($prete->reference, $json[0]['reference']);
        $this->assertSame('500.00', $json[0]['total']);
    }

    public function test_the_feed_never_lists_a_ready_delivery_order(): void
    {
        // Depuis l'écran Livraison (MenuDeliveryScreenTest) : une livraison
        // « Prête » attend un livreur, elle ne se sert jamais au comptoir.
        $this->patron();
        $menu = $this->menu();
        Order::create([
            'menu_id' => $menu->id, 'tenant_id' => $menu->tenant_id, 'reference' => Order::generateReference(),
            'subtotal' => 500, 'total' => 500, 'tip' => 0, 'currency' => 'HTG',
            'status' => 'ready', 'payment_status' => 'unpaid', 'channel' => 'menu', 'order_type' => 'delivery',
            'placed_at' => now(),
        ]);

        $json = $this->getJson(route('tagtoa.menu.dashboard.counter.feed', $menu->id))->assertOk()->json('orders');

        $this->assertCount(0, $json);
    }

    public function test_completing_a_ready_order_marks_it_completed_and_paid(): void
    {
        $this->patron();
        $menu = $this->menu();
        $order = $this->order($menu, 'ready');

        $this->post(route('tagtoa.menu.dashboard.counter.complete', [$menu->id, $order->id]))->assertRedirect();

        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertTrue($order->isPaid());
    }

    public function test_completing_a_ready_delivery_order_does_nothing(): void
    {
        $this->patron();
        $menu = $this->menu();
        $order = Order::create([
            'menu_id' => $menu->id, 'tenant_id' => $menu->tenant_id, 'reference' => Order::generateReference(),
            'subtotal' => 500, 'total' => 500, 'tip' => 0, 'currency' => 'HTG',
            'status' => 'ready', 'payment_status' => 'unpaid', 'channel' => 'menu', 'order_type' => 'delivery',
            'placed_at' => now(),
        ]);

        $this->post(route('tagtoa.menu.dashboard.counter.complete', [$menu->id, $order->id]))->assertRedirect();

        $order->refresh();
        $this->assertSame('ready', $order->status);
        $this->assertFalse($order->isPaid());
    }

    public function test_completing_an_order_that_isnt_ready_does_nothing(): void
    {
        $this->patron();
        $menu = $this->menu();
        $order = $this->order($menu, 'preparing');

        $this->post(route('tagtoa.menu.dashboard.counter.complete', [$menu->id, $order->id]))->assertRedirect();

        $order->refresh();
        $this->assertSame('preparing', $order->status);
        $this->assertFalse($order->isPaid());
    }

    public function test_completing_an_already_completed_order_is_a_harmless_no_op(): void
    {
        $this->patron();
        $menu = $this->menu();
        $order = $this->order($menu, 'ready');

        $this->post(route('tagtoa.menu.dashboard.counter.complete', [$menu->id, $order->id]));
        // Un doigt impatient : un deuxième clic ne doit rien casser ni
        // ré-encaisser une commande déjà réglée (markPaid() est déjà
        // idempotent, testé ailleurs — ici on vérifie juste qu'un second
        // appel depuis CET écran ne fait pas régresser l'état).
        $this->post(route('tagtoa.menu.dashboard.counter.complete', [$menu->id, $order->id]))->assertRedirect();

        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertTrue($order->isPaid());
    }

    public function test_a_cashier_without_the_sell_ability_cannot_complete_an_order(): void
    {
        $this->patron();
        $menu = $this->menu();
        $order = $this->order($menu, 'ready');
        // Un rôle inconnu ('kitchen' n'existe pas pour StaffAccess) retombe
        // sur aucune ability du tout : ni 'sell', ni rien d'autre.
        $sansDroit = Staff::create([
            'tenant_id' => 't-1', 'name' => 'Stagiaire', 'role' => 'zzz-inconnu', 'is_active' => true,
            'pin_hash' => StaffPinService::hashPin('1111'),
        ]);
        session(['tagtoa_menu_staff.'.$menu->id => $sansDroit->id]);

        $this->post(route('tagtoa.menu.dashboard.counter.complete', [$menu->id, $order->id]))->assertForbidden();

        $this->assertSame('ready', $order->fresh()->status);
    }

    public function test_a_cashier_with_the_sell_ability_can_complete_an_order(): void
    {
        $this->patron();
        $menu = $this->menu();
        $order = $this->order($menu, 'ready');
        $caissier = Staff::create([
            'tenant_id' => 't-1', 'name' => 'Junior', 'role' => 'cashier', 'is_active' => true,
            'pin_hash' => StaffPinService::hashPin('1111'),
        ]);
        session(['tagtoa_menu_staff.'.$menu->id => $caissier->id]);

        $this->post(route('tagtoa.menu.dashboard.counter.complete', [$menu->id, $order->id]))->assertRedirect();

        $this->assertSame('completed', $order->fresh()->status);
    }

    public function test_a_foreign_tenants_order_cannot_be_completed(): void
    {
        $this->patron('t-1');
        $mine = $this->menu('t-1');
        $other = $this->menu('t-2');
        $leur = $this->order($other, 'ready');

        $this->post(route('tagtoa.menu.dashboard.counter.complete', [$mine->id, $leur->id]))->assertNotFound();
    }
}
