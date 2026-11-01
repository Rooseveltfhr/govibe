<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA DEVISES (CUR-2) — caisse hors ligne et taux de change
|--------------------------------------------------------------------------
| La caisse hors ligne a chargé son catalogue avec le taux d'alors. À la reprise, le serveur garde
| le prix réellement encaissé s'il correspond à un taux valable dans les 24 h avant la vente ;
| sinon il recalcule au taux du jour ET le signale. Jamais de rejet, jamais de réécriture
| silencieuse ; en ligne, le prix envoyé par la caisse n'est jamais cru.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Pos\Sale;
use Modules\Tagtoa\App\Models\Pos\SaleItem;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Services\Currency\CurrencyRateService;
use Modules\Tagtoa\App\Support\Tenant;
use Modules\Tagtoa\Tests\TestCase;

class CurrencyOfflinePosTest extends TestCase
{
    use RefreshDatabase;

    private Terminal $caisse;

    private Item $steak;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-01 12:00:00'));
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-1', 'name' => 'Rose']));
        Tenant::flush();
        $this->caisse = Terminal::create(['tenant_id' => 't-1', 'name' => 'Caisse', 'currency' => 'HTG', 'is_active' => true]);
        $menu = Menu::create(['tenant_id' => 't-1', 'name' => 'Carte', 'alias' => 'carte', 'currency' => 'HTG', 'is_active' => true]);
        $cat = Category::create(['menu_id' => $menu->id, 'name' => 'Plats', 'is_active' => true]);
        $this->steak = Item::create(['menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Steak', 'price' => 5, 'currency' => 'USD', 'is_available' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Pose le taux USD→HTG tel qu'il était il y a $heures heures. */
    private function tauxIlYa(float $heures, float $taux): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-01 12:00:00')->subMinutes((int) round($heures * 60)));
        app(CurrencyRateService::class)->save('t-1', 'HTG', ['code' => 'USD', 'rate' => $taux, 'confirm_jump' => true]);
        Carbon::setTestNow(Carbon::parse('2026-11-01 12:00:00'));
    }

    private function desactiverIlYa(float $heures): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-01 12:00:00')->subMinutes((int) round($heures * 60)));
        app(CurrencyRateService::class)->toggle(\Modules\Tagtoa\App\Models\Currency\CurrencyRate::allTenants()->where('tenant_id', 't-1')->sole());
        Carbon::setTestNow(Carbon::parse('2026-11-01 12:00:00'));
    }

    /** Vente reprise hors ligne : vendue il y a $minutes minutes, au prix $prix (HTG) annoncé par la caisse. */
    private function reprise(string $uuid, float $prix, int $minutes = 90)
    {
        return $this->postJson(route('tagtoa.pos.sync', $this->caisse->id), ['sales' => [[
            'client_uuid' => $uuid, 'client_created_at' => now()->subMinutes($minutes)->toIso8601String(),
            'items' => [['ref' => 'menu:'.$this->steak->id, 'name' => 'Steak', 'qty' => 1, 'price' => $prix]],
        ]]])->assertOk()->json('results.0');
    }

    private function ligne(): SaleItem
    {
        return SaleItem::query()->latest('id')->firstOrFail();
    }

    /* ---------- fenêtre de validité des taux ---------- */

    public function test_the_rate_history_gives_every_rate_that_was_active_in_a_window(): void
    {
        $this->tauxIlYa(50, 120);     // 120 il y a 50 h
        $this->tauxIlYa(5, 130);      // 130 il y a 5 h
        $this->tauxIlYa(2, 135);      // 135 il y a 2 h
        $s = app(CurrencyRateService::class);

        $fenetre = fn (float $de, float $a) => $s->ratesActiveBetween('t-1', 'USD', 'HTG', now()->subMinutes((int) ($de * 60)), now()->subMinutes((int) ($a * 60)));

        $this->assertEqualsCanonicalizing([130.0, 135.0], $fenetre(4, 1), '130 jusqu\'à il y a 2 h, puis 135.');
        $this->assertEqualsCanonicalizing([120.0, 130.0, 135.0], $fenetre(6, 1), '120 a cessé il y a 5 h : encore dans la fenêtre qui commence il y a 6 h.');
        $this->assertSame([135.0], $fenetre(1, 0));
        $this->assertSame([120.0], $fenetre(49, 48), '120 : valable jusqu\'à il y a 5 h.');
        $this->assertSame([], $fenetre(80, 60), 'Avant la création : aucun taux.');
    }

    public function test_a_deactivated_period_is_not_a_valid_rate(): void
    {
        $this->tauxIlYa(10, 130);
        $this->desactiverIlYa(4);     // désactivé il y a 4 h
        $s = app(CurrencyRateService::class);

        $this->assertSame([], $s->ratesActiveBetween('t-1', 'USD', 'HTG', now()->subHours(3), now()->subHours(1)));
        $this->assertSame([130.0], $s->ratesActiveBetween('t-1', 'USD', 'HTG', now()->subHours(6), now()->subHours(5)));
    }

    public function test_another_business_history_is_never_used(): void
    {
        app(CurrencyRateService::class)->save('t-2', 'HTG', ['code' => 'USD', 'rate' => 999, 'confirm_jump' => true]);

        $this->assertSame([], app(CurrencyRateService::class)->ratesActiveBetween('t-1', 'USD', 'HTG', now()->subDay(), now()));
    }

    /* ---------- reprise hors ligne ---------- */

    public function test_an_offline_sale_at_the_till_rate_of_the_moment_keeps_the_price_the_customer_paid(): void
    {
        $this->tauxIlYa(5, 130);
        $this->tauxIlYa(2, 135);       // le taux a changé depuis le chargement du catalogue de la caisse

        $res = $this->reprise('A', 650);   // 5 $ × 130 : prix de la caisse, vendu il y a 90 min

        $this->assertTrue($res['ok']);
        $l = $this->ligne();
        $this->assertEquals(650, (float) $l->price, 'Le client a payé 650 : la vente n\'est pas réécrite à 675.');
        $this->assertSame('USD', $l->currency_original);
        $this->assertEquals(5, (float) $l->unit_price_original);
        $this->assertEquals(130, (float) $l->rate_used, 'Le taux de la caisse est conservé.');
        $this->assertSame('till_rate', $l->price_check);
    }

    public function test_an_offline_price_matching_no_valid_rate_is_repriced_and_flagged_never_rejected(): void
    {
        $this->tauxIlYa(5, 130);
        $this->tauxIlYa(2, 135);

        $res = $this->reprise('B', 100);   // 5 $ vendus 100 G : ne correspond à aucun taux

        $this->assertTrue($res['ok'], 'Jamais de rejet : la marchandise est partie.');
        $l = $this->ligne();
        $this->assertEquals(675, (float) $l->price, 'Recalculé au taux du jour.');
        $this->assertEquals(135, (float) $l->rate_used);
        $this->assertSame('repriced', $l->price_check, 'Et signalé.');
    }

    public function test_a_rate_that_stopped_being_valid_more_than_24h_before_the_sale_is_not_accepted(): void
    {
        $this->tauxIlYa(60, 120);
        $this->tauxIlYa(40, 130);      // 120 a cessé il y a 40 h ; la vente date d'il y a 90 min

        $this->reprise('C', 600);      // 5 $ × 120

        $this->assertSame('repriced', $this->ligne()->price_check);
        $this->assertEquals(650, (float) $this->ligne()->price);
    }

    public function test_without_any_active_rate_the_price_actually_charged_is_kept_and_flagged(): void
    {
        $this->tauxIlYa(10, 130);
        $this->desactiverIlYa(3);

        $res = $this->reprise('D', 777, 30);   // vendu il y a 30 min, taux désactivé depuis ; prix sans rapport avec aucun taux

        $this->assertTrue($res['ok']);
        $l = $this->ligne();
        $this->assertEquals(777, (float) $l->price);
        $this->assertSame('no_rate', $l->price_check);
        $this->assertNull($l->rate_used);
    }

    public function test_a_rate_deactivated_after_the_till_loaded_its_catalog_still_confirms_the_price_it_charged(): void
    {
        $this->tauxIlYa(10, 130);
        $this->desactiverIlYa(3);

        $this->reprise('D2', 650, 30);   // la caisse avait encore le taux 130 : prix légitime

        $this->assertSame('till_rate', $this->ligne()->price_check);
        $this->assertEquals(650, (float) $this->ligne()->price);
    }

    public function test_an_online_sale_ignores_the_price_sent_by_the_till(): void
    {
        $this->tauxIlYa(5, 130);

        $this->postJson(route('tagtoa.pos.sale', $this->caisse->id), ['items' => [
            ['ref' => 'menu:'.$this->steak->id, 'name' => 'Steak', 'qty' => 1, 'price' => 1],
        ]])->assertOk();

        $l = $this->ligne();
        $this->assertEquals(650, (float) $l->price, 'Le serveur convertit : 1 G envoyé n\'est pas cru.');
        $this->assertNull($l->price_check);
        $this->assertEquals(130, (float) $l->rate_used);
    }

    public function test_items_without_a_foreign_currency_are_unchanged(): void
    {
        $this->steak->update(['currency' => null, 'price' => 250]);

        $this->reprise('E', 250);

        $l = $this->ligne();
        $this->assertEquals(250, (float) $l->price);
        $this->assertNull($l->currency_original);
        $this->assertNull($l->price_check);
    }

    public function test_replaying_the_same_offline_sale_twice_creates_one_sale(): void
    {
        $this->tauxIlYa(5, 130);

        $this->reprise('F', 650);
        $this->reprise('F', 650);

        $this->assertSame(1, Sale::count());
        $this->assertSame(1, SaleItem::count());
    }

    /* ---------- le patron voit ce qui est à vérifier ---------- */

    public function test_the_owner_report_lists_the_lines_whose_price_could_not_be_confirmed(): void
    {
        $this->tauxIlYa(5, 130);
        $this->reprise('G', 100);                 // à vérifier
        $this->reprise('H', 650, 120);            // confirmé (taux 130 valable il y a 2 h)

        $html = $this->get(route('tagtoa.pos.report', $this->caisse->id))->assertOk()->getContent();

        $this->assertStringContainsString('Taux à vérifier', $html);
        $this->assertSame(1, substr_count($html, 'Recalculé au taux du jour'), 'Une seule ligne à vérifier.');
    }

    public function test_the_report_has_no_warning_section_when_nothing_needs_checking(): void
    {
        $this->tauxIlYa(5, 130);
        $this->reprise('I', 650);

        $this->get(route('tagtoa.pos.report', $this->caisse->id))->assertOk()->assertDontSee('Taux à vérifier');
    }

    public function test_the_migration_is_replayable_and_reversible(): void
    {
        $mig = require __DIR__.'/../../Database/migrations/2026_11_01_000187_add_currency_to_pos_sale_items.php';
        $mig->up();
        $mig->up();
        $this->assertTrue(Schema::hasColumn('tagtoa_pos_sale_items', 'price_check'));
        $mig->down();
        $this->assertFalse(Schema::hasColumn('tagtoa_pos_sale_items', 'price_check'));
        $mig->up();
        $this->assertTrue(Schema::hasColumn('tagtoa_pos_sale_items', 'rate_used'));
    }
}
