<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA MENU — page publique de suivi (menu/track.blade.php), le volet
| livraison : une commande LIVRAISON gagne une étape « Récupérée » que
| sur-place/à-emporter n'ont jamais, et affiche le livreur assigné dès
| qu'il l'est — avant même que le statut n'ait bougé (l'assignation ne
| change pas le statut, voir MenuDeliveryScreenTest).
|--------------------------------------------------------------------------
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Menu\Order;
use Modules\Tagtoa\App\Models\Staff\Staff;
use Modules\Tagtoa\App\Services\Event\StaffPinService;
use Modules\Tagtoa\Tests\TestCase;

class MenuTrackDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private function menu(): Menu
    {
        return Menu::create(['tenant_id' => 't-1', 'name' => 'Lounge', 'alias' => 'lounge-'.uniqid(), 'currency' => 'HTG']);
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

    private function courier(): Staff
    {
        return Staff::create([
            'tenant_id' => 't-1', 'name' => 'Junior', 'role' => 'courier', 'is_active' => true,
            'pin_hash' => StaffPinService::hashPin('1111'),
        ]);
    }

    public function test_a_delivery_order_shows_a_picked_up_step(): void
    {
        $menu = $this->menu();
        $order = $this->order($menu, ['status' => 'picked_up']);

        $html = $this->get(route('tagtoa.menu.track', $order->reference))->assertOk()->getContent();

        $this->assertStringContainsString('data-step="picked_up"', $html);
    }

    public function test_a_dine_in_order_never_shows_a_picked_up_step(): void
    {
        $menu = $this->menu();
        $order = $this->order($menu, ['status' => 'ready', 'order_type' => 'dine_in']);

        $html = $this->get(route('tagtoa.menu.track', $order->reference))->assertOk()->getContent();

        $this->assertStringNotContainsString('data-step="picked_up"', $html);
    }

    public function test_an_assigned_courier_is_shown_on_the_page(): void
    {
        $menu = $this->menu();
        $livreur = $this->courier();
        // Assignée mais toujours « Prête » — l'assignation ne fait pas
        // avancer le statut (voir MenuDeliveryScreenTest).
        $order = $this->order($menu, ['status' => 'ready', 'courier_id' => $livreur->id]);

        $html = $this->get(route('tagtoa.menu.track', $order->reference))->assertOk()->getContent();

        $this->assertStringContainsString('id="courierName">Junior', $html);
        $this->assertStringNotContainsString('display:none" id="courierLine"', $html);
    }

    public function test_an_unassigned_order_never_shows_a_courier_line(): void
    {
        $menu = $this->menu();
        $order = $this->order($menu, ['status' => 'ready']);

        $html = $this->get(route('tagtoa.menu.track', $order->reference))->assertOk()->getContent();

        $this->assertStringContainsString('display:none" id="courierLine"', $html);
    }

    public function test_the_status_endpoint_reports_the_assigned_courier(): void
    {
        $menu = $this->menu();
        $livreur = $this->courier();
        $order = $this->order($menu, ['status' => 'picked_up', 'courier_id' => $livreur->id]);

        $json = $this->getJson(route('tagtoa.menu.track.status', $order->reference))->assertOk()->json();

        $this->assertSame('picked_up', $json['status']);
        $this->assertSame('Junior', $json['courier']['name']);
    }

    public function test_the_status_endpoint_never_leaks_the_couriers_phone(): void
    {
        $menu = $this->menu();
        $livreur = $this->courier();
        $livreur->update(['phone' => '+50938887777']);
        $order = $this->order($menu, ['status' => 'ready', 'courier_id' => $livreur->id]);

        $json = $this->getJson(route('tagtoa.menu.track.status', $order->reference))->assertOk()->json();

        $this->assertArrayNotHasKey('phone', $json['courier']);
    }

    public function test_the_status_endpoint_reports_no_courier_when_none_is_assigned(): void
    {
        $menu = $this->menu();
        $order = $this->order($menu, ['status' => 'ready']);

        $json = $this->getJson(route('tagtoa.menu.track.status', $order->reference))->assertOk()->json();

        $this->assertNull($json['courier']);
    }
}
