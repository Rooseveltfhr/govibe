<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA MENU — l'écran cuisine (F2) restait lecture seule : faire avancer
| une commande obligeait à ouvrir l'écran « Commandes », pensé pour le
| patron, pas pour un cuisinier sur une tablette partagée. F3 ajoute UNE
| action étroite (avancer d'une étape), gardée par un droit qui n'a rien à
| voir avec les rôles POS — un employé « cuisine » ne doit jamais hériter
| des droits de caisse (encaisser, remise) par accident.
|--------------------------------------------------------------------------
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Menu\Order;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Models\Staff\Staff;
use Modules\Tagtoa\App\Services\Event\StaffPinService;
use Modules\Tagtoa\Tests\TestCase;

class MenuKitchenRoleTest extends TestCase
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

    private function order(Menu $menu, string $status = 'pending'): Order
    {
        return Order::create([
            'menu_id' => $menu->id, 'tenant_id' => $menu->tenant_id, 'reference' => Order::generateReference(),
            'subtotal' => 100, 'total' => 100, 'tip' => 0, 'currency' => 'HTG',
            'status' => $status, 'payment_status' => 'unpaid', 'channel' => 'menu', 'order_type' => 'dine_in',
            'placed_at' => now(),
        ]);
    }

    private function staff(string $tenantId, array $attrs = []): Staff
    {
        return Staff::create(array_merge([
            'tenant_id' => $tenantId, 'name' => 'Junior', 'role' => 'cashier', 'is_active' => true,
            'pin_hash' => StaffPinService::hashPin('4242'),
        ], $attrs));
    }

    /* ------------------------------------------------------------------
       Sans employé identifié : le patron opère directement, toujours permis.
       ------------------------------------------------------------------ */

    public function test_the_owner_can_advance_an_order_without_any_staff_identified(): void
    {
        $this->patron();
        $menu = $this->menu();
        $order = $this->order($menu, 'pending');

        $this->post(route('tagtoa.menu.dashboard.kitchen.advance', [$menu->id, $order->id]))->assertRedirect();

        $this->assertSame('preparing', $order->fresh()->status);
    }

    public function test_advancing_moves_through_the_kitchen_cycle_one_step_at_a_time(): void
    {
        $this->patron();
        $menu = $this->menu();
        $order = $this->order($menu, 'preparing');

        $this->post(route('tagtoa.menu.dashboard.kitchen.advance', [$menu->id, $order->id]));
        $this->assertSame('ready', $order->fresh()->status);

        // Déjà « Prête » : rien au-delà (servir/encaisser reste sur « Commandes »).
        $this->post(route('tagtoa.menu.dashboard.kitchen.advance', [$menu->id, $order->id]));
        $this->assertSame('ready', $order->fresh()->status);
    }

    /* ------------------------------------------------------------------
       Un employé identifié SANS le droit cuisine ne peut PAS avancer.
       ------------------------------------------------------------------ */

    public function test_a_cashier_without_the_kitchen_flag_cannot_advance_an_order(): void
    {
        $this->patron();
        $menu = $this->menu();
        $order = $this->order($menu, 'pending');
        $caissier = $this->staff('t-1', ['role' => 'cashier', 'is_kitchen' => false]);
        session(['tagtoa_menu_staff.'.$menu->id => $caissier->id]);

        $this->post(route('tagtoa.menu.dashboard.kitchen.advance', [$menu->id, $order->id]))->assertForbidden();

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_a_cashier_granted_the_kitchen_flag_can_advance_an_order(): void
    {
        $this->patron();
        $menu = $this->menu();
        $order = $this->order($menu, 'pending');
        $cuisinier = $this->staff('t-1', ['role' => 'cashier', 'is_kitchen' => true]);
        session(['tagtoa_menu_staff.'.$menu->id => $cuisinier->id]);

        $this->post(route('tagtoa.menu.dashboard.kitchen.advance', [$menu->id, $order->id]))->assertRedirect();

        $this->assertSame('preparing', $order->fresh()->status);
    }

    public function test_a_manager_can_advance_even_without_the_kitchen_flag(): void
    {
        $this->patron();
        $menu = $this->menu();
        $order = $this->order($menu, 'pending');
        $gerant = $this->staff('t-1', ['role' => 'manager', 'is_kitchen' => false]);
        session(['tagtoa_menu_staff.'.$menu->id => $gerant->id]);

        $this->post(route('tagtoa.menu.dashboard.kitchen.advance', [$menu->id, $order->id]))->assertRedirect();

        $this->assertSame('preparing', $order->fresh()->status);
    }

    public function test_a_deactivated_kitchen_staff_is_treated_as_unidentified_and_blocked(): void
    {
        $this->patron();
        $menu = $this->menu();
        $order = $this->order($menu, 'pending');
        $exEmploye = $this->staff('t-1', ['is_kitchen' => true, 'is_active' => false]);
        session(['tagtoa_menu_staff.'.$menu->id => $exEmploye->id]);

        // forMenu() ne retrouve plus un employé désactivé : la session pointe
        // sur un id que la requête suivante ne résout plus vers personne, donc
        // c'est le patron qui reprend la main — jamais un blocage silencieux.
        $this->post(route('tagtoa.menu.dashboard.kitchen.advance', [$menu->id, $order->id]))->assertRedirect();
        $this->assertSame('preparing', $order->fresh()->status);
    }

    /* ------------------------------------------------------------------
       Identification par code — espace de session distinct de la caisse.
       ------------------------------------------------------------------ */

    public function test_logging_in_with_the_right_pin_identifies_the_staff_member(): void
    {
        $this->patron();
        $menu = $this->menu();
        $this->staff('t-1', ['name' => 'Junior', 'pin_hash' => StaffPinService::hashPin('7777')]);

        $this->post(route('tagtoa.menu.dashboard.kitchen.staff.login', $menu->id), ['pin' => '7777'])
            ->assertRedirect();

        $json = $this->getJson(route('tagtoa.menu.dashboard.kitchen.feed', $menu->id))->json();
        $this->assertSame('Junior', $json['staff']['name']);
    }

    public function test_a_wrong_pin_is_rejected_without_identifying_anyone(): void
    {
        $this->patron();
        $menu = $this->menu();
        $this->staff('t-1', ['pin_hash' => StaffPinService::hashPin('7777')]);

        $this->post(route('tagtoa.menu.dashboard.kitchen.staff.login', $menu->id), ['pin' => '0000'])
            ->assertSessionHasErrors('pin');

        $json = $this->getJson(route('tagtoa.menu.dashboard.kitchen.feed', $menu->id))->json();
        $this->assertNull($json['staff']);
    }

    public function test_logging_out_clears_the_identification(): void
    {
        $this->patron();
        $menu = $this->menu();
        $staff = $this->staff('t-1');
        session(['tagtoa_menu_staff.'.$menu->id => $staff->id]);

        $this->post(route('tagtoa.menu.dashboard.kitchen.staff.logout', $menu->id))->assertRedirect();

        $json = $this->getJson(route('tagtoa.menu.dashboard.kitchen.feed', $menu->id))->json();
        $this->assertNull($json['staff']);
    }

    public function test_a_pos_terminal_bound_staff_member_can_still_log_into_the_kitchen_screen(): void
    {
        // authenticate() n'applique le filtre par poste QUE si on lui donne un
        // id de poste ; le login cuisine n'en donne aucun — un employé assigné
        // à une caisse précise doit rester identifiable en cuisine.
        $this->patron();
        $menu = $this->menu();
        $terminal = Terminal::create(['tenant_id' => 't-1', 'name' => 'Poste 3', 'currency' => 'HTG', 'is_active' => true]);
        $this->staff('t-1', ['name' => 'Poste 3', 'terminal_id' => $terminal->id, 'pin_hash' => StaffPinService::hashPin('5555')]);

        $this->post(route('tagtoa.menu.dashboard.kitchen.staff.login', $menu->id), ['pin' => '5555'])
            ->assertRedirect()->assertSessionDoesntHaveErrors();
    }

    /* ------------------------------------------------------------------
       Isolation entre commerces.
       ------------------------------------------------------------------ */

    public function test_a_foreign_tenants_order_cannot_be_advanced(): void
    {
        $this->patron('t-1');
        $mine = $this->menu('t-1');
        $other = $this->menu('t-2');
        $leur = $this->order($other, 'pending');

        $this->post(route('tagtoa.menu.dashboard.kitchen.advance', [$mine->id, $leur->id]))->assertNotFound();
    }
}
