<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA MENU — commandes en cours (cartes) / traitées (une ligne) / alerte globale
|--------------------------------------------------------------------------
| Demandé par le fondateur : (1) pastille + son sur toutes les pages du tableau
| de bord, sans ouvrir l'écran des commandes ; (2) les commandes traitées
| tiennent sur UNE ligne (date, client, numéro…), avec « Retour » ; (3) un
| changement de statut n'affiche jamais d'erreur si l'étape secondaire échoue.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Menu\Order;
use Modules\Tagtoa\App\Services\Menu\MenuOrderService;
use Modules\Tagtoa\App\Services\Order\OrderSpine;
use Modules\Tagtoa\Tests\TestCase;

class MenuOrdersCompactListTest extends TestCase
{
    use RefreshDatabase;

    private Menu $menu;
    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->menu = Menu::create(['tenant_id' => 't-1', 'name' => 'BS', 'alias' => 'bs', 'currency' => 'HTG', 'is_active' => true, 'ordering_enabled' => true]);
        $cat = Category::create(['menu_id' => $this->menu->id, 'name' => 'Plats', 'is_active' => true]);
        $this->item = Item::create(['menu_id' => $this->menu->id, 'category_id' => $cat->id, 'name' => 'Pizza', 'price' => 500, 'is_available' => true]);
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-1', 'name' => 'Rose']));
    }

    private function order(string $name, string $status): Order
    {
        $o = app(MenuOrderService::class)->placeOrder($this->menu, [
            'items' => [['id' => $this->item->id, 'qty' => 1]], 'customer_name' => $name, 'customer_phone' => '50933333333',
        ]);
        $o->update(['status' => $status]);

        return $o;
    }

    public function test_active_orders_are_cards_and_processed_ones_a_single_table_row(): void
    {
        $this->order('Ketia Active', 'pending');
        $this->order('Jean Termine', 'completed');
        $this->order('Marie Annulee', 'cancelled');

        $html = $this->get(route('tagtoa.menu.dashboard.orders', $this->menu->id))->assertOk()->getContent();

        $this->assertStringContainsString('Commandes traitées', $html);
        $this->assertStringContainsString('class="done-orders"', $html);
        // La commande en cours reste une carte avec ses boutons ; les traitées n'en ont pas.
        $this->assertSame(1, substr_count($html, 'name="payment_method"'), 'Seule la commande en cours (non payée) a « Encaisser ».');
        // Une ligne par commande traitée, avec date, client, numéro.
        preg_match_all('#<tr title=.*?</tr>#s', $html, $rows);
        $this->assertCount(2, $rows[0]);
        $this->assertStringContainsString('Jean Termine', $rows[0][0].$rows[0][1]);
        $this->assertStringContainsString('50933333333', $rows[0][0]);
        $this->assertStringContainsString('Retour', $rows[0][0]);
        $this->assertStringNotContainsString('Pizza</td>', $rows[0][0], 'Les articles ne prennent plus de place dans la ligne.');
    }

    public function test_return_puts_a_processed_order_back_in_progress(): void
    {
        $done = $this->order('Jean', 'completed');
        $cancelled = $this->order('Marie', 'cancelled');

        $this->post(route('tagtoa.menu.dashboard.orders.status', $done->id), ['status' => 'confirmed'])->assertRedirect();
        $this->post(route('tagtoa.menu.dashboard.orders.status', $cancelled->id), ['status' => 'pending'])->assertRedirect();

        $this->assertSame('confirmed', $done->fresh()->status);
        $this->assertSame('pending', $cancelled->fresh()->status);
        $html = $this->get(route('tagtoa.menu.dashboard.orders', $this->menu->id))->getContent();
        $this->assertStringNotContainsString('Commandes traitées', $html);
    }

    public function test_the_return_button_targets_confirmed_for_completed_and_pending_for_cancelled(): void
    {
        $this->order('Jean', 'completed');
        $html = $this->get(route('tagtoa.menu.dashboard.orders', $this->menu->id))->getContent();
        $this->assertMatchesRegularExpression('#name="status" value="confirmed"#', $html);

        Order::query()->update(['status' => 'cancelled']);
        $html = $this->get(route('tagtoa.menu.dashboard.orders', $this->menu->id))->getContent();
        $this->assertMatchesRegularExpression('#class="done-orders".*name="status" value="pending"#s', $html);
    }

    public function test_the_feed_carries_both_lists_and_changes_signature_when_an_order_is_processed(): void
    {
        $o = $this->order('Ketia', 'pending');
        $feed = route('tagtoa.menu.dashboard.orders.feed', $this->menu->id);
        $a = $this->getJson($feed)->assertOk()->json();
        $this->assertStringNotContainsString('Commandes traitées', $a['html']);

        $o->update(['status' => 'completed']);
        $b = $this->getJson($feed)->assertOk()->json();
        $this->assertNotSame($a['signature'], $b['signature']);
        $this->assertStringContainsString('Commandes traitées', $b['html']);
    }

    /* ---------- alerte sur toutes les pages ---------- */

    public function test_the_alert_endpoint_reports_only_my_business_orders(): void
    {
        $mine = $this->order('Ketia', 'pending');
        $other = Menu::create(['tenant_id' => 't-2', 'name' => 'Autre', 'alias' => 'autre', 'currency' => 'HTG', 'is_active' => true, 'ordering_enabled' => true]);
        $cat = Category::create(['menu_id' => $other->id, 'name' => 'X', 'is_active' => true]);
        $it = Item::create(['menu_id' => $other->id, 'category_id' => $cat->id, 'name' => 'Y', 'price' => 10, 'is_available' => true]);
        app(MenuOrderService::class)->placeOrder($other, ['items' => [['id' => $it->id, 'qty' => 1]]]);

        $this->getJson(route('tagtoa.menu.dashboard.orders.alerts'))->assertOk()
            ->assertJson(['latest_id' => $mine->id, 'pending' => 1]);
    }

    public function test_every_dashboard_page_carries_the_alert_script_and_the_orders_page_does_not_double_the_sound(): void
    {
        $home = $this->get('/tagtoa/home');
        $home->assertOk()->assertSee('id="ordAlert"', false)->assertSee('tagtoa_seen_order', false);

        $orders = $this->get(route('tagtoa.menu.dashboard.orders', $this->menu->id))->getContent();
        $this->assertStringContainsString('TAGTOA_ORDERS_PAGE = true', $orders);
        $this->assertStringContainsString('if (surPageCommandes) return;', $orders);
    }

    public function test_the_alert_endpoint_is_closed_without_a_business(): void
    {
        $this->be(new GenericUser(['id' => 2, 'tenant_id' => null]));
        $this->getJson(route('tagtoa.menu.dashboard.orders.alerts'))->assertStatus(403);
    }

    /* ---------- « Confirmer » ne doit jamais afficher d'erreur pour une suite ---------- */

    public function test_a_failing_follow_up_never_turns_a_saved_status_into_an_error(): void
    {
        $o = $this->order('Ketia', 'pending');
        $spine = \Mockery::mock(OrderSpine::class);
        $spine->shouldReceive('touch')->andThrow(new \RuntimeException('colonne vertébrale en panne'));
        $this->app->instance(OrderSpine::class, $spine);

        $this->post(route('tagtoa.menu.dashboard.orders.status', $o->id), ['status' => 'preparing'])
            ->assertRedirect()->assertSessionHas('success')->assertSessionMissing('errors');

        $this->assertSame('preparing', $o->fresh()->status);
    }
}
