<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA MENU — le client d'une commande LIVRAISON est prévenu par WhatsApp
| à chaque étape qui compte (confirmée, en route, livrée), depuis les trois
| écrans qui font avancer le statut d'une commande : Commandes (setStatus),
| Cuisine (kitchenAdvance) et Caisse (counterComplete).
|
| LE CÂBLAGE (cette classe, moitié « Wiring ») : ces trois actions doivent
| appeler NotificationService::notifyOrderStatus() APRÈS un VRAI changement
| de statut — jamais si le statut envoyé est déjà celui de la commande, sinon
| un marchand qui re-sélectionne le même statut par erreur renverrait le
| même message au client. Vérifié via un double de NotificationService,
| jamais le réseau — même principe que LoyaltyNotificationTest.
|
| LE FILTRE (cette classe, moitié « Gating ») : notifyOrderStatus() lui-même
| ne doit envoyer QUE pour une commande livraison avec un téléphone, et
| seulement sur un statut qui dit quelque chose de neuf au client. Vérifié
| via un double PARTIEL de NotificationService (seule sa méthode whatsapp()
| est doublée) : on affirme QU'IL appelle ou N'appelle PAS whatsapp(), sans
| jamais toucher au réseau (guzzlehttp/guzzle est absent du vendor de ce
| module dans cet environnement de test — voir LoyaltyNotificationTest).
|--------------------------------------------------------------------------
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Menu\Order;
use Modules\Tagtoa\App\Services\Notifications\NotificationService;
use Modules\Tagtoa\Tests\TestCase;

class MenuOrderStatusNotificationTest extends TestCase
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
            'subtotal' => 100, 'total' => 100, 'tip' => 0, 'currency' => 'HTG',
            'status' => 'pending', 'payment_status' => 'unpaid', 'channel' => 'menu', 'order_type' => 'delivery',
            'customer_phone' => '38112345', 'placed_at' => now(),
        ], $attrs));
    }

    private function espionner(): \Mockery\MockInterface
    {
        $spy = \Mockery::mock(NotificationService::class);
        $this->app->instance(NotificationService::class, $spy);

        return $spy;
    }

    /* ---------- câblage : les trois écrans qui changent le statut ---------- */

    public function test_orders_screen_notifies_on_a_real_status_change(): void
    {
        $this->patron();
        $menu = $this->menu();
        $order = $this->order($menu, ['status' => 'pending']);
        $spy = $this->espionner();
        $spy->shouldReceive('notifyOrderStatus')->once()->withArgs(fn ($o) => $o->id === $order->id);

        $this->post(route('tagtoa.menu.dashboard.orders.status', $order->id), ['status' => 'confirmed'])
            ->assertRedirect();
    }

    public function test_orders_screen_never_notifies_when_the_status_does_not_actually_change(): void
    {
        $this->patron();
        $menu = $this->menu();
        $order = $this->order($menu, ['status' => 'confirmed']);
        $spy = $this->espionner();
        $spy->shouldNotReceive('notifyOrderStatus');

        $this->post(route('tagtoa.menu.dashboard.orders.status', $order->id), ['status' => 'confirmed'])
            ->assertRedirect();
    }

    public function test_the_kitchen_screen_notifies_when_it_advances_a_status(): void
    {
        $this->patron();
        $menu = $this->menu();
        $order = $this->order($menu, ['status' => 'confirmed']);
        $spy = $this->espionner();
        $spy->shouldReceive('notifyOrderStatus')->once()->withArgs(fn ($o) => $o->id === $order->id);

        $this->post(route('tagtoa.menu.dashboard.kitchen.advance', [$menu->id, $order->id]))
            ->assertRedirect();
    }

    public function test_the_counter_screen_notifies_when_it_completes_a_ready_order(): void
    {
        // Sur place — une livraison ne passe plus par le comptoir depuis
        // l'écran livraison (voir les tests dédiés plus bas).
        $this->patron();
        $menu = $this->menu();
        $order = $this->order($menu, ['status' => 'ready', 'order_type' => 'dine_in']);
        $spy = $this->espionner();
        $spy->shouldReceive('notifyOrderStatus')->once()->withArgs(fn ($o) => $o->id === $order->id);

        $this->post(route('tagtoa.menu.dashboard.counter.complete', [$menu->id, $order->id]))
            ->assertRedirect();
    }

    public function test_the_counter_screen_never_notifies_an_order_that_was_not_ready(): void
    {
        $this->patron();
        $menu = $this->menu();
        $order = $this->order($menu, ['status' => 'pending', 'order_type' => 'dine_in']);
        $spy = $this->espionner();
        $spy->shouldNotReceive('notifyOrderStatus');

        $this->post(route('tagtoa.menu.dashboard.counter.complete', [$menu->id, $order->id]))
            ->assertRedirect();
    }

    public function test_the_counter_screen_never_notifies_a_ready_delivery_order_either(): void
    {
        // Le comptoir ignore désormais les livraisons — aucun changement de
        // statut, donc aucune notification depuis CET écran.
        $this->patron();
        $menu = $this->menu();
        $order = $this->order($menu, ['status' => 'ready', 'order_type' => 'delivery']);
        $spy = $this->espionner();
        $spy->shouldNotReceive('notifyOrderStatus');

        $this->post(route('tagtoa.menu.dashboard.counter.complete', [$menu->id, $order->id]))
            ->assertRedirect();

        $this->assertSame('ready', $order->fresh()->status);
    }

    public function test_the_delivery_screen_notifies_when_a_courier_picks_up_an_order(): void
    {
        $this->patron();
        $menu = $this->menu();
        $courier = \Modules\Tagtoa\App\Models\Staff\Staff::create([
            'tenant_id' => 't-1', 'name' => 'Junior', 'role' => 'courier', 'is_active' => true,
            'pin_hash' => \Modules\Tagtoa\App\Services\Event\StaffPinService::hashPin('1111'),
        ]);
        $order = $this->order($menu, ['status' => 'ready', 'courier_id' => $courier->id]);
        $spy = $this->espionner();
        $spy->shouldReceive('notifyOrderStatus')->once()->withArgs(fn ($o) => $o->id === $order->id);

        $this->post(route('tagtoa.menu.dashboard.delivery.advance', [$menu->id, $order->id]))
            ->assertRedirect();
    }

    public function test_the_delivery_screen_notifies_when_a_courier_delivers_an_order(): void
    {
        $this->patron();
        $menu = $this->menu();
        $order = $this->order($menu, ['status' => 'picked_up']);
        $spy = $this->espionner();
        $spy->shouldReceive('notifyOrderStatus')->once()->withArgs(fn ($o) => $o->id === $order->id);

        $this->post(route('tagtoa.menu.dashboard.delivery.advance', [$menu->id, $order->id]))
            ->assertRedirect();
    }

    public function test_the_delivery_screen_never_notifies_a_ready_order_with_no_courier_assigned(): void
    {
        $this->patron();
        $menu = $this->menu();
        $order = $this->order($menu, ['status' => 'ready']);
        $spy = $this->espionner();
        $spy->shouldNotReceive('notifyOrderStatus');

        $this->post(route('tagtoa.menu.dashboard.delivery.advance', [$menu->id, $order->id]))
            ->assertRedirect();

        $this->assertSame('ready', $order->fresh()->status);
    }

    /* ---------- filtre : à qui notifyOrderStatus() parle vraiment ---------- */

    private function partiel(): \Mockery\MockInterface
    {
        return \Mockery::mock(NotificationService::class)->makePartial();
    }

    public function test_a_delivery_order_with_a_phone_and_a_meaningful_status_reaches_whatsapp(): void
    {
        $menu = $this->menu();
        $order = $this->order($menu, ['status' => 'confirmed', 'order_type' => 'delivery', 'customer_phone' => '38112345']);
        $service = $this->partiel();
        $service->shouldReceive('whatsapp')->once()
            ->withArgs(fn ($to, $body) => $to === '38112345' && str_contains($body, $order->reference));

        $service->notifyOrderStatus($order);
    }

    public function test_a_dine_in_order_never_reaches_whatsapp_even_on_a_meaningful_status(): void
    {
        $menu = $this->menu();
        $order = $this->order($menu, ['status' => 'confirmed', 'order_type' => 'dine_in', 'customer_phone' => '38112345']);
        $service = $this->partiel();
        $service->shouldNotReceive('whatsapp');

        $service->notifyOrderStatus($order);
    }

    public function test_a_pickup_order_never_reaches_whatsapp(): void
    {
        $menu = $this->menu();
        $order = $this->order($menu, ['status' => 'ready', 'order_type' => 'pickup', 'customer_phone' => '38112345']);
        $service = $this->partiel();
        $service->shouldNotReceive('whatsapp');

        $service->notifyOrderStatus($order);
    }

    public function test_a_delivery_order_without_a_phone_never_reaches_whatsapp(): void
    {
        $menu = $this->menu();
        $order = $this->order($menu, ['status' => 'confirmed', 'order_type' => 'delivery', 'customer_phone' => null]);
        $service = $this->partiel();
        $service->shouldNotReceive('whatsapp');

        $service->notifyOrderStatus($order);
    }

    public function test_a_status_that_tells_the_customer_nothing_new_never_reaches_whatsapp(): void
    {
        $menu = $this->menu();
        $order = $this->order($menu, ['status' => 'preparing', 'order_type' => 'delivery', 'customer_phone' => '38112345']);
        $service = $this->partiel();
        $service->shouldNotReceive('whatsapp');

        $service->notifyOrderStatus($order);
    }
}
