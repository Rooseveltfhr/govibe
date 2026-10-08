<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA MENU — les écrans de service sans numéro de menu dans l'URL
|--------------------------------------------------------------------------
| Un commerce n'a qu'UN menu (voir DashboardController::menuExistant()) :
| la sidebar lie donc « Commandes », « Cuisine », « Livraison » et
| « Tables » sans connaître son id — même raison que « la caisse sans
| numéro » côté POS (PosController::caisseCourante()).
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Support\Tenant;
use Modules\Tagtoa\Tests\TestCase;

class MenuCurrentScreensTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));
        Tenant::flush();
    }

    private function menu(string $tenantId = 't-1'): Menu
    {
        return Menu::create(['tenant_id' => $tenantId, 'name' => 'Lounge', 'alias' => 'lounge-'.uniqid(), 'currency' => 'HTG']);
    }

    public function test_current_orders_resolves_to_the_tenant_s_only_menu(): void
    {
        $this->patron();
        $menu = $this->menu();

        $this->get(route('tagtoa.menu.dashboard.orders.current'))
            ->assertOk()
            ->assertViewIs('tagtoa::menu.orders')
            ->assertViewHas('menu', fn ($m) => $m->id === $menu->id);
    }

    public function test_current_kitchen_resolves_to_the_tenant_s_only_menu(): void
    {
        $this->patron();
        $menu = $this->menu();

        $this->get(route('tagtoa.menu.dashboard.kitchen.current'))
            ->assertOk()
            ->assertViewIs('tagtoa::menu.kitchen')
            ->assertViewHas('menu', fn ($m) => $m->id === $menu->id);
    }

    public function test_current_delivery_resolves_to_the_tenant_s_only_menu(): void
    {
        $this->patron();
        $menu = $this->menu();

        $this->get(route('tagtoa.menu.dashboard.delivery.current'))
            ->assertOk()
            ->assertViewIs('tagtoa::menu.delivery')
            ->assertViewHas('menu', fn ($m) => $m->id === $menu->id);
    }

    public function test_current_tables_resolves_to_the_tenant_s_only_menu(): void
    {
        $this->patron();
        $menu = $this->menu();

        $this->get(route('tagtoa.menu.dashboard.tables.current'))
            ->assertOk()
            ->assertViewIs('tagtoa::menu.tables')
            ->assertViewHas('menu', fn ($m) => $m->id === $menu->id);
    }

    public function test_current_screens_redirect_to_the_wizard_when_no_menu_exists_yet(): void
    {
        $this->patron();

        foreach (['orders.current', 'kitchen.current', 'delivery.current', 'tables.current'] as $nom) {
            $this->get(route('tagtoa.menu.dashboard.'.$nom))
                ->assertRedirect(route('tagtoa.menu.dashboard.wizard'));
        }
    }

    public function test_current_screens_stay_scoped_to_the_right_tenant(): void
    {
        $this->menu('t-autre'); // un menu qui n'appartient PAS au commerce connecté
        $this->patron('t-1');

        $this->get(route('tagtoa.menu.dashboard.orders.current'))
            ->assertRedirect(route('tagtoa.menu.dashboard.wizard'));
    }
}
