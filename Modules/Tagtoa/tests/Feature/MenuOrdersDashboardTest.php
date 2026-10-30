<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA MENU — le tableau de bord des commandes et le suivi client
|--------------------------------------------------------------------------
| Demandé par le fondateur :
|   1. la page de suivi doit permettre au client de continuer à voir le menu ;
|   2. les commandes s'affichent sans clic, et une nouvelle commande sonne ;
|   3. le marchand voit si une commande est payée ; seule la personne à la
|      caisse confirme, en choisissant le moyen (espèces, MonCash…) — ou la
|      passerelle la marque payée toute seule.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Menu\Order;
use Modules\Tagtoa\App\Services\Menu\MenuOrderService;
use Modules\Tagtoa\Tests\TestCase;

class MenuOrdersDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function menu(string $tenant = 't-1', string $alias = 'chez-rose'): Menu
    {
        return Menu::create(['tenant_id' => $tenant, 'name' => 'Chez Rose', 'alias' => $alias,
            'currency' => 'HTG', 'is_active' => true, 'ordering_enabled' => true]);
    }

    private function order(Menu $menu): Order
    {
        $cat = Category::create(['menu_id' => $menu->id, 'name' => 'Plats', 'is_active' => true]);
        $item = Item::create(['menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Griot', 'price' => 350, 'is_available' => true]);

        return app(MenuOrderService::class)->placeOrder($menu, ['items' => [['id' => $item->id, 'qty' => 2]]]);
    }

    private function asMerchant(string $tenant = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenant, 'name' => 'Rose']));
    }

    /* ---------- 1. suivi client ---------- */

    public function test_the_tracking_page_lets_the_customer_keep_browsing_the_menu(): void
    {
        $order = $this->order($this->menu());

        $this->get(route('tagtoa.menu.track', $order->reference))
            ->assertOk()
            ->assertSee(route('tagtoa.menu.show', 'chez-rose'), false)
            ->assertSee('Continuer à voir le menu');
    }

    /* ---------- 3. paiement : qui confirme, et comment ---------- */

    public function test_an_unpaid_order_is_shown_as_unpaid_with_a_method_choice_and_no_default(): void
    {
        $menu = $this->menu();
        $this->order($menu);
        $this->asMerchant();

        $html = $this->get(route('tagtoa.menu.dashboard.orders', $menu->id))->assertOk()->getContent();

        $this->assertStringContainsString('Non payé', $html);
        $this->assertStringContainsString('name="payment_method"', $html);
        $this->assertStringContainsString('<option value="" selected disabled>', $html);
    }

    public function test_the_cashier_must_choose_a_method_to_collect(): void
    {
        $menu = $this->menu();
        $order = $this->order($menu);
        $this->asMerchant();

        $this->post(route('tagtoa.menu.dashboard.orders.paid', $order->id))->assertSessionHasErrors('payment_method');
        $this->post(route('tagtoa.menu.dashboard.orders.paid', $order->id), ['payment_method' => 'bitcoin-ou-autre'])
            ->assertSessionHasErrors('payment_method');

        $this->assertFalse($order->fresh()->isPaid());
    }

    public function test_collecting_records_the_method_the_person_and_the_time(): void
    {
        $menu = $this->menu();
        $order = $this->order($menu);
        $this->asMerchant();

        $this->post(route('tagtoa.menu.dashboard.orders.paid', $order->id), ['payment_method' => 'cash'])
            ->assertSessionHasNoErrors();

        $o = $order->fresh();
        $this->assertTrue($o->isPaid());
        $this->assertSame('cash', $o->payment_method);
        $this->assertSame('Rose', $o->paid_by);
        $this->assertNotNull($o->paid_at);

        $html = $this->get(route('tagtoa.menu.dashboard.orders', $menu->id))->getContent();
        $this->assertStringContainsString('Payé', $html);
        $this->assertStringContainsString('Espèces', $html);
    }

    public function test_a_gateway_payment_marks_the_order_paid_with_the_gateway_and_nobody(): void
    {
        $order = $this->order($this->menu());

        app(MenuOrderService::class)->markPaid($order, 'moncash');

        $o = $order->fresh();
        $this->assertTrue($o->isPaid());
        $this->assertSame('moncash', $o->payment_method);
        $this->assertNull($o->paid_by);
    }

    /**
     * Deux clics rapprochés sur « Encaisser » : chacun a chargé la commande
     * AVANT que l'autre n'écrive, donc chacun la croit non payée.
     */
    public function test_two_stale_collections_keep_the_first_collectors_record(): void
    {
        $order = $this->order($this->menu());
        $a = Order::find($order->id);
        $b = Order::find($order->id);

        app(MenuOrderService::class)->markPaid($a, 'cash', 'Rose');
        app(MenuOrderService::class)->markPaid($b, 'moncash', 'Jean');

        // Sans le verrou, la copie périmée de $b se croit « non payée » et
        // réécrit moyen et caissier — et rejoue commission et points fidélité.
        $o = $order->fresh();
        $this->assertSame('cash', $o->payment_method, 'Le premier encaissement fait foi.');
        $this->assertSame('Rose', $o->paid_by);
    }

    /* ---------- 2. fil en direct + alerte ---------- */

    public function test_the_feed_reports_the_newest_order_and_the_list_html(): void
    {
        $menu = $this->menu();
        $order = $this->order($menu);
        $this->asMerchant();

        $this->getJson(route('tagtoa.menu.dashboard.orders.feed', $menu->id))
            ->assertOk()
            ->assertJsonPath('latest_id', $order->id)
            ->assertJsonPath('pending', 1)
            ->assertJsonPath('unpaid', 1)
            ->assertJsonFragment(['signature' => $order->id.':pending:'.$order->payment_status])
            ->assertSee($order->reference, false);
    }

    public function test_the_feed_never_shows_another_merchants_orders(): void
    {
        $mine = $this->menu('t-1', 'chez-rose');
        $other = $this->menu('t-2', 'chez-paul');
        $this->order($other);
        $this->asMerchant('t-1');

        $this->getJson(route('tagtoa.menu.dashboard.orders.feed', $other->id))->assertNotFound();
        $this->getJson(route('tagtoa.menu.dashboard.orders.feed', $mine->id))->assertOk()->assertJsonPath('latest_id', 0);
    }

    public function test_the_orders_page_polls_the_feed_and_rings_for_fifteen_seconds(): void
    {
        $menu = $this->menu();
        $this->asMerchant();

        $html = $this->get(route('tagtoa.menu.dashboard.orders', $menu->id))->assertOk()->getContent();

        $this->assertStringContainsString(route('tagtoa.menu.dashboard.orders.feed', $menu->id), str_replace('\/', '/', $html));
        $this->assertStringContainsString('ALERTE_SECONDES = 15', $html);
        $this->assertStringContainsString('Arrêter le son', $html);
    }

    public function test_the_sound_library_rings_between_ten_and_twenty_seconds(): void
    {
        $js = file_get_contents(__DIR__.'/../../resources/assets/tagtoa/tagtoa-sound.js');

        $this->assertStringContainsString('alerte: function', $js);
        $this->assertStringContainsString('alerteStop: function', $js);
        $this->assertStringContainsString('Math.min(20, Math.max(10,', $js);
    }

    public function test_inline_scripts_of_the_orders_page_parse(): void
    {
        if (trim((string) shell_exec('command -v node')) === '') {
            $this->markTestSkipped('node indisponible');
        }
        $menu = $this->menu();
        $this->asMerchant();
        $html = $this->get(route('tagtoa.menu.dashboard.orders', $menu->id))->getContent();
        preg_match_all('#<script(?![^>]*\bsrc=)[^>]*>(.*?)</script>#s', $html, $m);

        $this->assertNotEmpty($m[1]);
        foreach ($m[1] as $i => $js) {
            $f = tempnam(sys_get_temp_dir(), 'ordjs').'.js';
            file_put_contents($f, $js);
            exec('node --check '.escapeshellarg($f).' 2>&1', $out, $code);
            @unlink($f);
            $this->assertSame(0, $code, "Script n°$i :\n".implode("\n", $out));
            $out = [];
        }
    }
}
