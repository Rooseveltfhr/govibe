<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA MENU — un client pouvait commander à n'importe quelle heure, même
| quand le commerce a explicitement dit qu'il était fermé : le formulaire
| public ne consultait jamais les horaires. Ces tests figent le refus
| réel de la commande, pas seulement son affichage.
|--------------------------------------------------------------------------
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Menu\Order;
use Modules\Tagtoa\App\Services\Menu\MenuOrderService;
use Modules\Tagtoa\Tests\TestCase;

class MenuBusinessHoursTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));
    }

    private function menu(array $attrs = []): Menu
    {
        return Menu::create(array_merge([
            'tenant_id' => 't-1', 'name' => 'Lounge Test', 'alias' => 'lounge-'.uniqid(),
            'currency' => 'HTG', 'is_active' => true, 'ordering_enabled' => true,
        ], $attrs));
    }

    private function item(Menu $menu): Item
    {
        $cat = Category::create(['menu_id' => $menu->id, 'name' => 'Plats', 'is_active' => true]);

        return Item::create(['menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Plat', 'price' => 100, 'is_available' => true]);
    }

    public function test_a_menu_without_hours_configured_always_accepts_an_order(): void
    {
        $menu = $this->menu();
        $item = $this->item($menu);

        $order = app(MenuOrderService::class)->placeOrder($menu, ['items' => [['id' => $item->id, 'qty' => 1]]]);

        $this->assertSame(1, Order::count());
        $this->assertSame($order->id, Order::first()->id);
    }

    public function test_an_order_is_rejected_outside_the_configured_hours(): void
    {
        // Mardi 03h00 : hors de la seule plage configurée (lundi 08h-20h).
        \Illuminate\Support\Carbon::setTestNow('2026-09-15 03:00:00');
        $menu = $this->menu(['hours' => ['mon' => ['open' => '08:00', 'close' => '20:00']], 'timezone' => 'UTC']);
        $item = $this->item($menu);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('closed');

        try {
            app(MenuOrderService::class)->placeOrder($menu, ['items' => [['id' => $item->id, 'qty' => 1]]]);
        } finally {
            \Illuminate\Support\Carbon::setTestNow();
        }
    }

    public function test_an_order_is_accepted_inside_the_configured_hours(): void
    {
        // Lundi 12h00 : dans la plage 08h-20h.
        \Illuminate\Support\Carbon::setTestNow('2026-09-14 12:00:00');
        $menu = $this->menu(['hours' => ['mon' => ['open' => '08:00', 'close' => '20:00']], 'timezone' => 'UTC']);
        $item = $this->item($menu);

        $order = app(MenuOrderService::class)->placeOrder($menu, ['items' => [['id' => $item->id, 'qty' => 1]]]);

        \Illuminate\Support\Carbon::setTestNow();
        $this->assertSame(1, Order::count());
        $this->assertNotNull($order->id);
    }

    public function test_the_public_endpoint_translates_the_closed_error_without_a_500(): void
    {
        \Illuminate\Support\Carbon::setTestNow('2026-09-15 03:00:00');
        $menu = $this->menu(['hours' => ['mon' => ['open' => '08:00', 'close' => '20:00']], 'timezone' => 'UTC']);
        $item = $this->item($menu);

        $response = $this->postJson(route('tagtoa.menu.order', $menu->alias), [
            'items' => [['id' => $item->id, 'qty' => 1]],
        ]);

        \Illuminate\Support\Carbon::setTestNow();
        $response->assertStatus(422)->assertJsonPath('ok', false);
        $this->assertSame(0, Order::count());
    }

    /* ------------------------------------------------------------------
       Le formulaire propriétaire : enregistrer et relire les horaires.
       ------------------------------------------------------------------ */

    public function test_saving_the_menu_form_stores_sanitized_hours(): void
    {
        $this->patron();
        $menu = $this->menu();

        $this->put(route('tagtoa.menu.dashboard.update', $menu->id), [
            'name' => 'Lounge Test', 'currency' => 'HTG', 'show_hours' => '1', 'timezone' => 'America/Port-au-Prince',
            'hours' => [
                'mon' => ['open' => '08:00', 'close' => '20:00'],
                'tue' => ['closed' => '1'],
            ],
            'form_end' => '1',
        ])->assertRedirect();

        $menu->refresh();
        $this->assertTrue($menu->show_hours);
        $this->assertSame('America/Port-au-Prince', $menu->timezone);
        $this->assertSame(['open' => '08:00', 'close' => '20:00'], $menu->hours['mon']);
        $this->assertNull($menu->hours['tue']);
    }

    public function test_the_public_page_shows_the_open_pill_only_when_show_hours_is_on(): void
    {
        \Illuminate\Support\Carbon::setTestNow('2026-09-14 12:00:00');
        $menu = $this->menu([
            'hours' => ['mon' => ['open' => '08:00', 'close' => '20:00']], 'timezone' => 'UTC', 'show_hours' => true,
        ]);

        $html = $this->get('/menu/'.$menu->alias)->assertOk()->getContent();
        \Illuminate\Support\Carbon::setTestNow();

        $this->assertStringContainsString('Ouvert maintenant', $html);
    }

    public function test_the_public_page_never_shows_a_pill_when_show_hours_is_off(): void
    {
        $menu = $this->menu(['hours' => ['mon' => ['open' => '08:00', 'close' => '20:00']], 'show_hours' => false]);

        $html = $this->get('/menu/'.$menu->alias)->assertOk()->getContent();

        $this->assertStringNotContainsString('Ouvert maintenant', $html);
        $this->assertStringNotContainsString('Fermé maintenant', $html);
    }
}
