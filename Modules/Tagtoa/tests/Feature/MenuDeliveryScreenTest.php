<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA MENU — écran livraison : le trajet d'une commande LIVRAISON après
| « Prête ». Le patron/gérant assigne un livreur (nouveau rôle Staff), qui
| fait ensuite avancer la commande lui-même : Prête (assignée) → Récupérée
| → Terminée. Jamais avant qu'un livreur soit assigné, jamais par un livreur
| qui n'est pas celui assigné à CETTE commande.
|--------------------------------------------------------------------------
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Menu\Order;
use Modules\Tagtoa\App\Models\Staff\Staff;
use Modules\Tagtoa\App\Services\Event\StaffPinService;
use Modules\Tagtoa\Tests\TestCase;

class MenuDeliveryScreenTest extends TestCase
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

    private function order(Menu $menu, array $attrs = []): Order
    {
        return Order::create(array_merge([
            'menu_id' => $menu->id, 'tenant_id' => $menu->tenant_id, 'reference' => Order::generateReference(),
            'subtotal' => 500, 'total' => 500, 'tip' => 0, 'currency' => 'HTG',
            'status' => 'ready', 'payment_status' => 'unpaid', 'channel' => 'menu', 'order_type' => 'delivery',
            'placed_at' => now(),
        ], $attrs));
    }

    private function courier(string $tenantId = 't-1', string $name = 'Junior'): Staff
    {
        return Staff::create([
            'tenant_id' => $tenantId, 'name' => $name, 'role' => 'courier', 'is_active' => true,
            'pin_hash' => StaffPinService::hashPin('1111'),
        ]);
    }

    /* ---------- écran + fil ---------- */

    public function test_the_screen_renders_for_its_owner(): void
    {
        $this->patron();
        $menu = $this->menu();

        $this->get(route('tagtoa.menu.dashboard.delivery', $menu->id))->assertOk()->assertSee('Livraison');
    }

    public function test_the_feed_lists_only_ready_and_picked_up_delivery_orders(): void
    {
        $this->patron();
        $menu = $this->menu();
        $prete = $this->order($menu, ['status' => 'ready']);
        $recuperee = $this->order($menu, ['status' => 'picked_up']);
        $this->order($menu, ['status' => 'preparing']);
        $this->order($menu, ['status' => 'completed']);
        // Même « Prête », mais pas une livraison — n'a rien à faire sur cet écran.
        $this->order($menu, ['status' => 'ready', 'order_type' => 'dine_in']);

        $refs = collect($this->getJson(route('tagtoa.menu.dashboard.delivery.feed', $menu->id))->assertOk()->json('orders'))
            ->pluck('reference');

        $this->assertCount(2, $refs);
        $this->assertTrue($refs->contains($prete->reference));
        $this->assertTrue($refs->contains($recuperee->reference));
    }

    public function test_the_feed_lists_the_active_couriers_of_the_tenant(): void
    {
        $this->patron();
        $menu = $this->menu();
        $actif = $this->courier('t-1', 'Junior');
        $inactif = $this->courier('t-1', 'Ancien');
        $inactif->update(['is_active' => false]);
        $autreCommerce = $this->courier('t-2', 'Étranger');

        $noms = collect($this->getJson(route('tagtoa.menu.dashboard.delivery.feed', $menu->id))->assertOk()->json('couriers'))
            ->pluck('name');

        $this->assertTrue($noms->contains('Junior'));
        $this->assertFalse($noms->contains('Ancien'));
        $this->assertFalse($noms->contains('Étranger'));
    }

    /* ---------- assignation ---------- */

    public function test_the_owner_can_assign_a_courier_to_a_ready_delivery_order(): void
    {
        $this->patron();
        $menu = $this->menu();
        $order = $this->order($menu, ['status' => 'ready']);
        $livreur = $this->courier();

        $this->post(route('tagtoa.menu.dashboard.delivery.assign', [$menu->id, $order->id]), ['courier_id' => $livreur->id])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame($livreur->id, $order->courier_id);
        $this->assertNotNull($order->courier_assigned_at);
        // Assigner ne change jamais le statut — la commande reste « Prête »
        // tant que personne ne l'a physiquement récupérée.
        $this->assertSame('ready', $order->status);
    }

    public function test_assigning_a_courier_never_touches_a_dine_in_order(): void
    {
        $this->patron();
        $menu = $this->menu();
        $order = $this->order($menu, ['status' => 'ready', 'order_type' => 'dine_in']);
        $livreur = $this->courier();

        $this->post(route('tagtoa.menu.dashboard.delivery.assign', [$menu->id, $order->id]), ['courier_id' => $livreur->id])
            ->assertRedirect();

        $this->assertNull($order->fresh()->courier_id);
    }

    public function test_assigning_a_courier_that_belongs_to_another_tenant_is_ignored(): void
    {
        $this->patron('t-1');
        $menu = $this->menu('t-1');
        $order = $this->order($menu, ['status' => 'ready']);
        $etranger = $this->courier('t-2', 'Étranger');

        $this->post(route('tagtoa.menu.dashboard.delivery.assign', [$menu->id, $order->id]), ['courier_id' => $etranger->id])
            ->assertRedirect();

        $this->assertNull($order->fresh()->courier_id);
    }

    public function test_a_cashier_without_the_assign_ability_cannot_assign_a_courier(): void
    {
        $this->patron();
        $menu = $this->menu();
        $order = $this->order($menu, ['status' => 'ready']);
        $livreur = $this->courier();
        $caissier = Staff::create([
            'tenant_id' => 't-1', 'name' => 'Junior Caissier', 'role' => 'cashier', 'is_active' => true,
            'pin_hash' => StaffPinService::hashPin('1111'),
        ]);
        session(['tagtoa_menu_staff.'.$menu->id => $caissier->id]);

        $this->post(route('tagtoa.menu.dashboard.delivery.assign', [$menu->id, $order->id]), ['courier_id' => $livreur->id])
            ->assertForbidden();

        $this->assertNull($order->fresh()->courier_id);
    }

    public function test_a_manager_with_the_assign_ability_can_assign_a_courier(): void
    {
        $this->patron();
        $menu = $this->menu();
        $order = $this->order($menu, ['status' => 'ready']);
        $livreur = $this->courier();
        $gerant = Staff::create([
            'tenant_id' => 't-1', 'name' => 'Gérante', 'role' => 'manager', 'is_active' => true,
            'pin_hash' => StaffPinService::hashPin('1111'),
        ]);
        session(['tagtoa_menu_staff.'.$menu->id => $gerant->id]);

        $this->post(route('tagtoa.menu.dashboard.delivery.assign', [$menu->id, $order->id]), ['courier_id' => $livreur->id])
            ->assertRedirect();

        $this->assertSame($livreur->id, $order->fresh()->courier_id);
    }

    /* ---------- avancement ---------- */

    public function test_advancing_a_ready_order_without_an_assigned_courier_does_nothing(): void
    {
        $this->patron();
        $menu = $this->menu();
        $order = $this->order($menu, ['status' => 'ready']);

        $this->post(route('tagtoa.menu.dashboard.delivery.advance', [$menu->id, $order->id]))->assertRedirect();

        $this->assertSame('ready', $order->fresh()->status);
    }

    public function test_advancing_a_ready_assigned_order_marks_it_picked_up(): void
    {
        $this->patron();
        $menu = $this->menu();
        $livreur = $this->courier();
        $order = $this->order($menu, ['status' => 'ready', 'courier_id' => $livreur->id]);

        $this->post(route('tagtoa.menu.dashboard.delivery.advance', [$menu->id, $order->id]))->assertRedirect();

        $order->refresh();
        $this->assertSame('picked_up', $order->status);
        $this->assertNotNull($order->picked_up_at);
        $this->assertFalse($order->isPaid());
    }

    public function test_advancing_a_picked_up_order_marks_it_completed_and_paid(): void
    {
        $this->patron();
        $menu = $this->menu();
        $livreur = $this->courier();
        $order = $this->order($menu, ['status' => 'picked_up', 'courier_id' => $livreur->id]);

        $this->post(route('tagtoa.menu.dashboard.delivery.advance', [$menu->id, $order->id]))->assertRedirect();

        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertTrue($order->isPaid());
    }

    public function test_a_courier_can_advance_their_own_assigned_delivery(): void
    {
        $this->patron();
        $menu = $this->menu();
        $livreur = $this->courier();
        $order = $this->order($menu, ['status' => 'ready', 'courier_id' => $livreur->id]);
        session(['tagtoa_menu_staff.'.$menu->id => $livreur->id]);

        $this->post(route('tagtoa.menu.dashboard.delivery.advance', [$menu->id, $order->id]))->assertRedirect();

        $this->assertSame('picked_up', $order->fresh()->status);
    }

    public function test_a_courier_cannot_advance_a_delivery_assigned_to_someone_else(): void
    {
        $this->patron();
        $menu = $this->menu();
        $livreur = $this->courier('t-1', 'Junior');
        $collegue = $this->courier('t-1', 'Autre');
        $order = $this->order($menu, ['status' => 'ready', 'courier_id' => $livreur->id]);
        session(['tagtoa_menu_staff.'.$menu->id => $collegue->id]);

        $this->post(route('tagtoa.menu.dashboard.delivery.advance', [$menu->id, $order->id]))->assertForbidden();

        $this->assertSame('ready', $order->fresh()->status);
    }

    public function test_picking_up_an_order_reflects_as_shipped_on_the_common_spine(): void
    {
        // « picked_up » n'existe que dans le vocabulaire du menu — la colonne
        // vertébrale commune (OrderSpine/OrderStatus) n'a pas ce mot, mais a
        // déjà « shipped » (« partie en livraison ») qui veut dire exactement
        // ça. Sans traduction, OrderSpine::touch() ignorerait silencieusement
        // le changement (voir OrderStatus::isValid()) et le rapport commun
        // montrerait la commande encore « prête ».
        $this->patron();
        $menu = $this->menu();
        $livreur = $this->courier();
        $order = $this->order($menu, ['status' => 'ready', 'courier_id' => $livreur->id]);
        app(\Modules\Tagtoa\App\Services\Order\OrderSpine::class)->record([
            'tenant_id' => $menu->tenant_id, 'channel' => 'menu', 'source_type' => 'menu_order',
            'source_id' => $order->id, 'reference' => $order->reference, 'currency' => 'HTG',
            'total' => 500, 'status' => 'ready',
        ]);

        $this->post(route('tagtoa.menu.dashboard.delivery.advance', [$menu->id, $order->id]))->assertRedirect();

        $spine = \Modules\Tagtoa\App\Models\Order\Order::where('source_type', 'menu_order')->where('source_id', $order->id)->first();
        $this->assertSame('shipped', $spine->status);
    }

    public function test_a_foreign_tenants_order_cannot_be_assigned_or_advanced(): void
    {
        $this->patron('t-1');
        $mine = $this->menu('t-1');
        $other = $this->menu('t-2');
        $leur = $this->order($other, ['status' => 'ready']);
        $livreur = $this->courier('t-1');

        $this->post(route('tagtoa.menu.dashboard.delivery.assign', [$mine->id, $leur->id]), ['courier_id' => $livreur->id])
            ->assertNotFound();
        $this->post(route('tagtoa.menu.dashboard.delivery.advance', [$mine->id, $leur->id]))
            ->assertNotFound();
    }
}
