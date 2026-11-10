<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA MENU (UX-2) — le numéro court de commande (#042)
|--------------------------------------------------------------------------
| « CMD-WKJJGOPL » ne se dit pas en cuisine. Chaque commande reçoit aussi un numéro court : 1, 2, 3… pour CE menu et CE jour,
| qui repart à 1 chaque matin. Les commandes d'avant la numérotation gardent leur référence seule.
*/

use Carbon\Carbon;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Menu\Order;
use Modules\Tagtoa\App\Services\Menu\MenuOrderService;
use Modules\Tagtoa\Tests\TestCase;

class MenuOrderNumberTest extends TestCase
{
    use RefreshDatabase;

    private function menu(string $tenant = 't-1', string $alias = 'chez-rose'): Menu
    {
        $m = Menu::create(['tenant_id' => $tenant, 'name' => 'Chez '.$alias, 'alias' => $alias, 'currency' => 'HTG', 'is_active' => true, 'ordering_enabled' => true]);
        $c = Category::create(['menu_id' => $m->id, 'name' => 'Plats', 'is_active' => true]);
        Item::create(['menu_id' => $m->id, 'category_id' => $c->id, 'name' => 'Griot', 'price' => 350, 'is_available' => true]);

        return $m;
    }

    private function place(Menu $m): Order
    {
        $item = Item::where('menu_id', $m->id)->first();

        return app(MenuOrderService::class)->placeOrder($m, ['items' => [['id' => $item->id, 'qty' => 1]]]);
    }

    private function asMerchant(string $tenant = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenant, 'name' => 'Rose']));
    }

    public function test_orders_are_numbered_one_two_three_with_a_padded_short_number(): void
    {
        $m = $this->menu();
        $a = $this->place($m);
        $b = $this->place($m);
        $c = $this->place($m);

        $this->assertSame([1, 2, 3], [$a->order_no, $b->order_no, $c->order_no]);
        $this->assertSame('#001', $a->short_no);
        $this->assertSame('#003', $c->short_no);
        $this->assertStringStartsWith('CMD-', $a->reference, 'La référence (suivi client) ne change pas.');
    }

    public function test_the_number_restarts_at_one_each_day(): void
    {
        $m = $this->menu();
        $this->place($m);
        $this->place($m);

        $this->travelTo(Carbon::now()->addDay()->setTime(8, 0));
        $demain = $this->place($m);
        $this->assertSame(1, $demain->order_no, 'Un nouveau jour, un nouveau ticket n° 1.');
        $this->assertSame(Carbon::today()->toDateString(), $demain->order_day->toDateString());

        $this->assertSame(2, $this->place($m)->order_no);
    }

    public function test_each_menu_has_its_own_sequence_and_never_sees_the_others(): void
    {
        $rose = $this->menu('t-1', 'rose');
        $paul = $this->menu('t-2', 'paul');

        $this->place($rose);
        $this->place($rose);
        $premierPaul = $this->place($paul);

        $this->assertSame(1, $premierPaul->order_no, 'Le restaurant de Paul démarre à 1, quel que soit celui de Rose.');
        $this->assertSame(3, $this->place($rose)->order_no);
    }

    public function test_the_database_refuses_two_orders_with_the_same_menu_day_and_number(): void
    {
        $m = $this->menu();
        $a = $this->place($m);

        $this->expectException(QueryException::class);
        Order::create(['tenant_id' => 't-1', 'menu_id' => $m->id, 'reference' => 'CMD-DOUBLON1', 'status' => 'pending', 'payment_status' => 'unpaid',
            'order_type' => 'dine_in', 'currency' => 'HTG', 'subtotal' => 1, 'total' => 1, 'order_day' => $a->order_day, 'order_no' => $a->order_no, 'placed_at' => now()]);
    }

    public function test_orders_from_before_the_numbering_are_unaffected_and_do_not_collide(): void
    {
        $m = $this->menu();
        $vieille = fn (string $ref) => Order::create(['tenant_id' => 't-1', 'menu_id' => $m->id, 'reference' => $ref, 'status' => 'pending', 'payment_status' => 'unpaid',
            'order_type' => 'dine_in', 'currency' => 'HTG', 'subtotal' => 1, 'total' => 1, 'placed_at' => now()]);
        $a = $vieille('CMD-ANCIEN001');
        $b = $vieille('CMD-ANCIEN002');   // deux « sans numéro » : NULL n'est pas égal à NULL

        $this->assertNull($a->short_no);
        $this->assertNull($b->short_no);
        $this->assertSame(1, $this->place($m)->order_no, 'Les anciennes commandes ne décalent pas la numérotation.');
    }

    public function test_the_migration_adds_nullable_columns_and_the_unique_index_and_is_replayable_and_reversible(): void
    {
        $this->assertTrue(Schema::hasColumns('tagtoa_menu_orders', ['order_no', 'order_day']));
        $indexes = collect(Schema::getIndexes('tagtoa_menu_orders'))->firstWhere('name', 'tagtoa_mo_day_no_uq');
        $this->assertNotNull($indexes);
        $this->assertTrue($indexes['unique']);
        $this->assertSame(['menu_id', 'order_day', 'order_no'], $indexes['columns']);

        $mig = require __DIR__.'/../../Database/migrations/2026_11_10_000198_add_order_no_to_menu_orders.php';
        $mig->up();   // rejouable : aucune erreur, aucun doublon
        $this->assertTrue(Schema::hasColumns('tagtoa_menu_orders', ['order_no', 'order_day']));

        $mig->down();
        $this->assertFalse(Schema::hasColumn('tagtoa_menu_orders', 'order_no'));
        $this->assertFalse(Schema::hasColumn('tagtoa_menu_orders', 'order_day'));
        $mig->down();   // réversible deux fois sans erreur
        $mig->up();
        $this->assertTrue(Schema::hasColumn('tagtoa_menu_orders', 'order_no'));
    }

    public function test_the_dashboard_shows_the_short_number_big_and_the_reference_small(): void
    {
        $m = $this->menu();
        $o = $this->place($m);
        $this->asMerchant();

        $html = $this->get(route('tagtoa.menu.dashboard.orders', $m->id))->assertOk()->getContent();
        $this->assertStringContainsString('class="ord-no"', $html);
        $this->assertStringContainsString('#001', $html);
        $this->assertStringContainsString($o->reference, $html);
    }

    public function test_an_old_order_without_a_number_still_shows_its_reference(): void
    {
        $m = $this->menu();
        Order::create(['tenant_id' => 't-1', 'menu_id' => $m->id, 'reference' => 'CMD-ANCIEN777', 'status' => 'pending', 'payment_status' => 'unpaid',
            'order_type' => 'dine_in', 'currency' => 'HTG', 'subtotal' => 1, 'total' => 1, 'placed_at' => now()]);
        $this->asMerchant();

        $html = $this->get(route('tagtoa.menu.dashboard.orders', $m->id))->assertOk()->getContent();
        $this->assertStringContainsString('CMD-ANCIEN777', $html);
        $this->assertStringNotContainsString('class="ord-no"', preg_replace('#<style.*?</style>#s', '', $html));
    }

    public function test_search_by_number_with_or_without_the_hash_and_zero_padding(): void
    {
        $m = $this->menu();
        $un = $this->place($m);
        $deux = $this->place($m);
        $this->asMerchant();

        foreach (['2', '#2', '#002', '002'] as $q) {
            $html = $this->get(route('tagtoa.menu.dashboard.orders', $m->id).'?q='.urlencode($q))->assertOk()->getContent();
            $this->assertStringContainsString($deux->reference, $html, "« $q » trouve le n° 2");
            $this->assertStringNotContainsString($un->reference, $html, "« $q » ne montre pas le n° 1");
        }
    }

    public function test_search_by_number_never_leaks_another_merchants_order(): void
    {
        $rose = $this->menu('t-1', 'rose');
        $paul = $this->menu('t-2', 'paul');
        $this->place($rose);
        $chezPaul = $this->place($paul);
        $this->asMerchant('t-1');

        $html = $this->get(route('tagtoa.menu.dashboard.orders', $rose->id).'?q=1')->assertOk()->getContent();
        $this->assertStringNotContainsString($chezPaul->reference, $html);
    }

    public function test_the_kitchen_counter_and_delivery_screens_receive_the_short_number(): void
    {
        $m = $this->menu();
        $o = $this->place($m);
        $o->update(['status' => 'confirmed']);
        $this->asMerchant();

        $json = $this->getJson(route('tagtoa.menu.dashboard.kitchen.feed', $m->id))->assertOk()->json();
        $this->assertSame('#001', collect($json['orders'] ?? $json)->first()['short_no'] ?? null);
    }

    public function test_the_customer_tracking_page_shows_the_number_to_say_at_pickup(): void
    {
        $m = $this->menu();
        $o = $this->place($m);

        $this->get(route('tagtoa.menu.track', $o->reference))->assertOk()->assertSee('#001');
    }

    public function test_the_screens_prefer_the_short_number_and_fall_back_to_the_reference(): void
    {
        foreach (['kitchen' => 'kw-ref', 'counter' => 'ct-ref', 'delivery' => 'dv-ref'] as $screen => $cls) {
            $src = file_get_contents(__DIR__.'/../../resources/views/menu/'.$screen.'.blade.php');
            $this->assertStringContainsString("esc(o.short_no || o.reference)", $src, $screen);
        }
    }

    public function test_the_only_creation_site_of_a_menu_order_numbers_it(): void
    {
        $src = file_get_contents(__DIR__.'/../../app/Services/Menu/MenuOrderService.php');
        $this->assertStringContainsString('Order::nextNumber($menu->id)', $src);
        $this->assertMatchesRegularExpression("/'order_no'\s*=>\s*\\\$orderNo/", $src);
        $this->assertSame(1, substr_count($src, '$menu->orders()->create(['), 'Un seul endroit crée une commande de menu : il numérote.');
    }
}
