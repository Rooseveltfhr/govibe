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
        $this->travelTo(Carbon::parse('2026-11-10 15:00:00', 'UTC'));   // 10 h à Port-au-Prince
        $m = $this->menu();
        $this->place($m);
        $this->place($m);

        $this->travelTo(Carbon::parse('2026-11-11 15:00:00', 'UTC'));
        $demain = $this->place($m);
        $this->assertSame(1, $demain->order_no, 'Un nouveau jour, un nouveau ticket n° 1.');
        $this->assertSame('2026-11-11', $demain->order_day->toDateString());

        $this->assertSame(2, $this->place($m)->order_no);
    }

    public function test_the_day_follows_the_menu_s_local_time_not_utc(): void
    {
        // 23 h 30 UTC = 18 h 30 à Port-au-Prince : en plein dîner, on est toujours dans la MÊME journée (en UTC on aurait changé de jour à 19 h).
        $m = $this->menu();
        $this->travelTo(Carbon::parse('2026-11-10 22:30:00', 'UTC'));
        $a = $this->place($m);
        $this->travelTo(Carbon::parse('2026-11-11 00:30:00', 'UTC'));   // 19 h 30 locale : UTC a changé de jour, pas le restaurant
        $b = $this->place($m);

        $this->assertSame('2026-11-10', $a->order_day->toDateString());
        $this->assertSame('2026-11-10', $b->order_day->toDateString());
        $this->assertSame([1, 2], [$a->order_no, $b->order_no], 'La numérotation ne repart pas à 19 h en plein coup de feu.');
    }

    public function test_a_night_service_stays_in_the_previous_service_day_until_4am_local(): void
    {
        $m = $this->menu();
        $this->travelTo(Carbon::parse('2026-11-10 07:30:00', 'UTC'));   // 2 h 30 du matin à Port-au-Prince
        $nuit = $this->place($m);
        $this->travelTo(Carbon::parse('2026-11-10 09:00:00', 'UTC'));   // 4 h 00 locale : nouvelle journée de service
        $matin = $this->place($m);

        $this->assertSame('2026-11-09', $nuit->order_day->toDateString(), 'Une commande de 2 h 30 appartient au service de la veille.');
        $this->assertSame('2026-11-10', $matin->order_day->toDateString());
        $this->assertSame(1, $matin->order_no);
    }

    public function test_a_menu_with_its_own_timezone_uses_it(): void
    {
        // 05 h 00 UTC : à Paris il est 6 h (jour de service du 10) ; à Port-au-Prince il est 0 h (encore le service du 9).
        $this->travelTo(Carbon::parse('2026-11-10 05:00:00', 'UTC'));
        $paris = $this->menu('t-1', 'paris');
        $paris->update(['timezone' => 'Europe/Paris']);
        $haiti = $this->menu('t-2', 'haiti');

        $this->assertSame('2026-11-10', $this->place($paris->fresh())->order_day->toDateString(), 'Le fuseau du menu est celui du menu.');
        $this->assertSame('2026-11-09', $this->place($haiti)->order_day->toDateString(), 'Sans fuseau : Port-au-Prince.');
        $this->assertSame('2026-11-10', Order::serviceDay('Europe/Paris'));
        $this->assertSame('2026-11-09', Order::serviceDay('America/Port-au-Prince'));
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

    public function test_the_stored_day_is_always_plain_y_m_d_whatever_is_assigned(): void
    {
        $m = $this->menu();
        foreach ([Carbon::parse('2026-11-10 13:45:00'), '2026-11-10', '2026-11-10 00:00:00'] as $i => $v) {
            $o = Order::create(['tenant_id' => 't-1', 'menu_id' => $m->id, 'reference' => 'CMD-DAY'.$i, 'status' => 'pending', 'payment_status' => 'unpaid',
                'order_type' => 'dine_in', 'currency' => 'HTG', 'subtotal' => 1, 'total' => 1, 'order_day' => $v, 'order_no' => $i + 1, 'placed_at' => now()]);
            $this->assertSame('2026-11-10', \Illuminate\Support\Facades\DB::table('tagtoa_menu_orders')->where('id', $o->id)->value('order_day'));
        }
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
        $this->assertNull($a->fresh()->order_no, 'NULL, jamais 0 : une colonne nullable, sans valeur inventée.');
        $this->assertNull($a->fresh()->order_day);
        $this->assertSame(1, $this->place($m)->order_no, 'Les anciennes commandes ne décalent pas la numérotation.');
    }

    /* ---------- deux commandes qui prennent le même numéro : on réessaie, on ne répond pas 500 ---------- */

    private function collision(): \Illuminate\Database\QueryException
    {
        $pdo = new \PDOException("SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '1-2026-11-10-3' for key 'tagtoa_mo_day_no_uq'");
        $pdo->errorInfo = ['23000', 1062, "Duplicate entry '1-2026-11-10-3' for key 'tagtoa_mo_day_no_uq'"];

        return new \Illuminate\Database\QueryException('testbench', 'insert into tagtoa_menu_orders', [], $pdo);
    }

    private function serviceFailing(int $times): MenuOrderService
    {
        $self = $this;

        return new class($times, $this->collision()) extends MenuOrderService {
            public int $appels = 0;
            public function __construct(private int $fail, private \Illuminate\Database\QueryException $erreur) {}
            protected function insertOrder(Menu $menu, array $payload, ?string $uuid): Order
            {
                $this->appels++;
                if ($this->appels <= $this->fail) {
                    throw $this->erreur;
                }

                return parent::insertOrder($menu, $payload, $uuid);
            }
        };
    }

    public function test_a_number_collision_is_retried_and_the_order_is_not_lost(): void
    {
        $m = $this->menu();
        $item = Item::where('menu_id', $m->id)->first();
        $svc = $this->serviceFailing(2);

        $order = $svc->placeOrder($m, ['items' => [['id' => $item->id, 'qty' => 1]]]);

        $this->assertSame(3, $svc->appels, 'Deux collisions, puis la commande passe.');
        $this->assertSame(1, $order->order_no);
        $this->assertSame(1, Order::where('menu_id', $m->id)->count());
    }

    public function test_the_retry_is_bounded_and_a_persistent_collision_is_reported(): void
    {
        $m = $this->menu();
        $item = Item::where('menu_id', $m->id)->first();
        $svc = $this->serviceFailing(99);

        try {
            $svc->placeOrder($m, ['items' => [['id' => $item->id, 'qty' => 1]]]);
            $this->fail('Une collision persistante doit remonter.');
        } catch (\Illuminate\Database\QueryException) {
            $this->assertSame(MenuOrderService::NUMBER_ATTEMPTS, $svc->appels, 'Jamais de boucle infinie.');
        }
        $this->assertSame(0, Order::where('menu_id', $m->id)->count());
    }

    public function test_only_a_number_collision_is_retried_other_database_errors_are_not(): void
    {
        $pdo = new \PDOException('SQLSTATE[HY000]: General error: 1205 Lock wait timeout');
        $pdo->errorInfo = ['HY000', 1205, 'Lock wait timeout'];
        $autre = new \Illuminate\Database\QueryException('testbench', 'insert', [], $pdo);

        $this->assertFalse(\Modules\Tagtoa\App\Support\Order\UniqueViolation::onOrderNumber($autre));
        $this->assertTrue(\Modules\Tagtoa\App\Support\Order\UniqueViolation::onOrderNumber($this->collision()));

        $uuid = new \PDOException("Duplicate entry 'x' for key 'tagtoa_mo_menu_uuid_unique'");
        $uuid->errorInfo = ['23000', 1062, "Duplicate entry 'x' for key 'tagtoa_mo_menu_uuid_unique'"];
        $this->assertFalse(\Modules\Tagtoa\App\Support\Order\UniqueViolation::onOrderNumber(new \Illuminate\Database\QueryException('testbench', 'insert', [], $uuid)),
            'Un doublon d\'identifiant client reste géré par son propre chemin.');
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
        $this->assertStringContainsString('Order::nextNumber($menu)', $src);
        $this->assertMatchesRegularExpression("/'order_no'\s*=>\s*\\\$orderNo/", $src);
        $this->assertSame(1, substr_count($src, '$menu->orders()->create(['), 'Un seul endroit crée une commande de menu : il numérote.');
    }

    public function test_a_hash_search_also_finds_a_phone_number_ending_the_same_way(): void
    {
        $m = $this->menu();
        $this->place($m);   // n° 1
        $tel = Order::create(['tenant_id' => 't-1', 'menu_id' => $m->id, 'reference' => 'CMD-TELEPHONE', 'status' => 'pending', 'payment_status' => 'unpaid',
            'order_type' => 'dine_in', 'currency' => 'HTG', 'subtotal' => 1, 'total' => 1, 'customer_phone' => '50937004242', 'placed_at' => now()]);
        $this->asMerchant();

        $html = $this->get(route('tagtoa.menu.dashboard.orders', $m->id).'?q='.urlencode('#42'))->assertOk()->getContent();
        $this->assertStringContainsString('CMD-TELEPHONE', $html, '« #42 » cherche le n° 42 ET le téléphone (sans le « # »).');
    }

    public function test_the_number_is_taken_under_a_lock_on_the_menu_row(): void
    {
        // SQLite ignore FOR UPDATE : on ne peut pas l'observer ici ; le verrou réel se juge sur MySQL. On fige au moins qu'il est demandé.
        $src = file_get_contents(__DIR__.'/../../app/Models/Menu/Order.php');
        $this->assertMatchesRegularExpression('/Menu::whereKey\(\$menuId\)->lockForUpdate\(\)/', $src);
        $this->assertMatchesRegularExpression('/->lockForUpdate\(\)->max\(\'order_no\'\)/', $src, 'Le max est lu en lecture VERROUILLANTE : sous MySQL REPEATABLE READ une lecture simple verrait un vieil instantané.');
    }

    public function test_a_numeric_search_still_finds_names_and_references_as_before(): void
    {
        $m = $this->menu();
        $this->place($m);
        Order::create(['tenant_id' => 't-1', 'menu_id' => $m->id, 'reference' => 'CMD-TABLE12AB', 'status' => 'pending', 'payment_status' => 'unpaid',
            'order_type' => 'dine_in', 'currency' => 'HTG', 'subtotal' => 1, 'total' => 1, 'customer_name' => 'Table 12', 'placed_at' => now()]);
        $this->asMerchant();

        $html = $this->get(route('tagtoa.menu.dashboard.orders', $m->id).'?q=12')->assertOk()->getContent();
        $this->assertStringContainsString('CMD-TABLE12AB', $html, '« 12 » trouve toujours « Table 12 » (nom) et les références qui le contiennent.');
    }

    public function test_a_one_digit_search_is_the_short_number_only(): void
    {
        $m = $this->menu();
        $un = $this->place($m);
        $this->place($m);
        Order::create(['tenant_id' => 't-1', 'menu_id' => $m->id, 'reference' => 'CMD-ABCDE1FGH', 'status' => 'pending', 'payment_status' => 'unpaid',
            'order_type' => 'dine_in', 'currency' => 'HTG', 'subtotal' => 1, 'total' => 1, 'placed_at' => now()]);
        $this->asMerchant();

        foreach (['1', '#1'] as $q) {
            $html = $this->get(route('tagtoa.menu.dashboard.orders', $m->id).'?q='.urlencode($q))->assertOk()->getContent();
            $this->assertStringContainsString($un->reference, $html);
            $this->assertStringNotContainsString('CMD-ABCDE1FGH', $html, 'Un chiffre ne ratisse pas toutes les références.');
        }
    }

    public function test_a_short_number_search_covers_today_and_yesterday_but_not_older_days(): void
    {
        $m = $this->menu();
        $this->travelTo(Carbon::parse('2026-11-05 15:00:00', 'UTC'));
        $vieille = $this->place($m);                                     // n° 1 d'il y a 5 jours
        $this->travelTo(Carbon::parse('2026-11-09 15:00:00', 'UTC'));
        $hier = $this->place($m);                                        // n° 1 d'hier
        $this->travelTo(Carbon::parse('2026-11-10 15:00:00', 'UTC'));
        $auj = $this->place($m);                                         // n° 1 d'aujourd'hui
        $this->asMerchant();

        $html = $this->get(route('tagtoa.menu.dashboard.orders', $m->id).'?q=1')->assertOk()->getContent();
        $this->assertStringContainsString($auj->reference, $html);
        $this->assertStringContainsString($hier->reference, $html);
        $this->assertStringNotContainsString($vieille->reference, $html, 'Un « 1 » ne remonte pas le n° 1 de tous les jours de l\'histoire.');
    }

    public function test_a_non_number_database_error_is_not_retried(): void
    {
        $m = $this->menu();
        $item = Item::where('menu_id', $m->id)->first();
        $pdo = new \PDOException('SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded');
        $pdo->errorInfo = ['HY000', 1205, 'Lock wait timeout exceeded'];
        $erreur = new \Illuminate\Database\QueryException('testbench', 'insert into tagtoa_menu_orders', [], $pdo);

        $svc = new class($erreur) extends MenuOrderService {
            public int $appels = 0;
            public function __construct(private \Illuminate\Database\QueryException $e) {}
            protected function insertOrder(Menu $menu, array $payload, ?string $uuid): Order
            {
                $this->appels++;
                throw $this->e;
            }
        };

        try {
            $svc->placeOrder($m, ['items' => [['id' => $item->id, 'qty' => 1]]]);
            $this->fail('L\'erreur doit remonter.');
        } catch (\Illuminate\Database\QueryException) {
            $this->assertSame(1, $svc->appels, 'Seul un doublon de numéro est réessayé : un autre incident n\'est pas masqué par des essais répétés.');
        }
    }
}
