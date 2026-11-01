<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA POS — politique de stock à la reprise hors ligne (CS-13, D4)
|--------------------------------------------------------------------------
| Une vente faite hors ligne pendant que le stock s'épuisait ailleurs ne doit
| pas DISPARAÎTRE : la marchandise est partie, l'argent est pris. Elle est
| enregistrée, le stock passe sous zéro (signé exprès), le mouvement est marqué
| et un audit est écrit ; le patron voit « stock négatif à vérifier ».
| En LIGNE, la caisse a le client devant elle : le refus (409) reste.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Audit\AuditLog;
use Modules\Tagtoa\App\Models\Inventory\StockMovement;
use Modules\Tagtoa\App\Models\Pos\Product;
use Modules\Tagtoa\App\Models\Pos\Sale;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Services\Inventory\StockLedger;
use Modules\Tagtoa\App\Services\Pos\PosCatalog;
use Modules\Tagtoa\Tests\TestCase;

class PosOfflineStockPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Terminal $terminal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->terminal = Terminal::create(['tenant_id' => 't-1', 'name' => 'Caisse 1', 'currency' => 'HTG', 'is_active' => true]);
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-1', 'name' => 'Rose']));
    }

    private function article(float $stock, string $name = 'Prestige'): Product
    {
        return app(PosCatalog::class)->save($this->terminal, ['name' => $name, 'price' => 150, 'stock' => $stock, 'is_active' => true]);
    }

    private function sale(string $uuid, Product $p, float $qty): array
    {
        return ['client_uuid' => $uuid, 'items' => [['product_id' => $p->id, 'qty' => $qty, 'name' => $p->name, 'price' => 150]]];
    }

    private function sync(array $sales)
    {
        return $this->postJson(route('tagtoa.pos.sync', $this->terminal->id), ['sales' => $sales]);
    }

    public function test_an_offline_sale_beyond_stock_is_recorded_stock_goes_negative_and_is_flagged(): void
    {
        $p = $this->article(2);

        $res = $this->sync([$this->sale('A', $p, 5)])->assertOk()->json('results.0');

        $this->assertTrue($res['ok']);
        $this->assertSame([['code' => 'stock_shortfall', 'items' => ['Prestige']]], $res['warnings']);
        $this->assertSame(1, Sale::count(), 'La vente encaissée est enregistrée.');
        $this->assertSame(-3.0, (float) $p->fresh()->stock);

        $m = StockMovement::where('product_id', $p->id)->where('type', 'sale')->latest('id')->first();
        $this->assertSame(-5.0, (float) $m->delta);
        $this->assertSame(2.0, (float) $m->stock_before);
        $this->assertSame(-3.0, (float) $m->stock_after);
        $this->assertSame(StockLedger::REASON_SHORTFALL, $m->reason);
        $this->assertSame('pos_sale', $m->origin_type);

        $audit = AuditLog::where('action', 'pos.sale_stock_shortfall')->first();
        $this->assertNotNull($audit);
        $this->assertStringContainsString('Prestige (-3)', (string) $audit->description);
    }

    public function test_a_sale_within_stock_is_unchanged_no_flag_no_audit(): void
    {
        $p = $this->article(5);

        $res = $this->sync([$this->sale('A', $p, 2)])->assertOk()->json('results.0');

        $this->assertTrue($res['ok']);
        $this->assertArrayNotHasKey('warnings', $res);
        $this->assertSame(3.0, (float) $p->fresh()->stock);
        $this->assertSame(0, AuditLog::where('action', 'pos.sale_stock_shortfall')->count());
        $this->assertNull(StockMovement::where('type', 'sale')->first()->reason);
    }

    public function test_the_shortfall_counts_only_what_was_missing_when_stock_is_already_negative(): void
    {
        $p = $this->article(0);
        $this->sync([$this->sale('A', $p, 2)])->assertOk();            // -2
        $this->sync([$this->sale('B', $p, 1)])->assertOk();            // -3

        $this->assertSame(-3.0, (float) $p->fresh()->stock);
        $last = AuditLog::where('action', 'pos.sale_stock_shortfall')->latest('id')->first();
        $this->assertStringContainsString('Prestige (-1)', (string) $last->description);
    }

    public function test_replaying_the_same_offline_sale_does_not_double_the_shortfall(): void
    {
        $p = $this->article(1);

        $this->sync([$this->sale('A', $p, 3)])->assertOk();
        $again = $this->sync([$this->sale('A', $p, 3)])->assertOk()->json('results.0');

        $this->assertTrue($again['ok']);
        $this->assertArrayNotHasKey('warnings', $again, 'Une reprise ne ré-annonce pas un manque déjà enregistré.');
        $this->assertSame(1, Sale::count());
        $this->assertSame(-2.0, (float) $p->fresh()->stock, 'Le stock n\'est retiré qu\'une fois.');
        $this->assertSame(1, AuditLog::where('action', 'pos.sale_stock_shortfall')->count());
    }

    public function test_an_online_sale_beyond_stock_is_still_refused_with_409(): void
    {
        $p = $this->article(1);

        $this->postJson(route('tagtoa.pos.sale', $this->terminal->id), ['items' => [['product_id' => $p->id, 'qty' => 5, 'name' => 'Prestige', 'price' => 150]]])
            ->assertStatus(409);

        $this->assertSame(0, Sale::count());
        $this->assertSame(1.0, (float) $p->fresh()->stock);
    }

    public function test_negative_stock_blocks_further_online_sales_until_a_count_fixes_it(): void
    {
        $p = $this->article(0);
        $this->sync([$this->sale('A', $p, 2)])->assertOk();

        $this->postJson(route('tagtoa.pos.sale', $this->terminal->id), ['items' => [['product_id' => $p->id, 'qty' => 1, 'name' => 'Prestige', 'price' => 150]]])
            ->assertStatus(409);

        // Le comptage physique est la correction assumée : il garde la trace de l'écart.
        app(StockLedger::class)->count($p->fresh(), 4.0);
        $this->assertSame(4.0, (float) $p->fresh()->stock);
        $this->postJson(route('tagtoa.pos.sale', $this->terminal->id), ['items' => [['product_id' => $p->id, 'qty' => 1, 'name' => 'Prestige', 'price' => 150]]])->assertOk();
    }

    public function test_only_the_offline_path_may_go_below_zero_the_ledger_default_is_unchanged(): void
    {
        $p = $this->article(1);

        $this->expectException(\Modules\Tagtoa\App\Exceptions\InsufficientStockException::class);
        app(StockLedger::class)->remove($p, 3, 'sale');
    }

    public function test_the_inventory_screen_shows_the_negative_stock_warning(): void
    {
        $p = $this->article(0, 'Rhum Barbancourt');
        $this->sync([$this->sale('A', $p, 2)])->assertOk();

        $this->get(route('tagtoa.inventory.index'))->assertOk()
            ->assertSee('Stock négatif à vérifier')->assertSee('Rhum Barbancourt');

        app(StockLedger::class)->count($p->fresh(), 3.0);
        $this->get(route('tagtoa.inventory.index'))->assertOk()->assertDontSee('Stock négatif à vérifier');
    }

    public function test_a_shortfall_in_one_sale_does_not_affect_another_merchant(): void
    {
        $autre = Terminal::create(['tenant_id' => 't-2', 'name' => 'Caisse B', 'currency' => 'HTG', 'is_active' => true]);
        $mien = $this->article(0);
        $this->sync([$this->sale('A', $mien, 1)])->assertOk();

        $this->assertSame(0, Product::allTenants()->where('tenant_id', 't-2')->count());
        $this->assertSame(0, StockMovement::where('tenant_id', 't-2')->count());
    }
}
