<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA POS — CS-8 : on rend ce qui a été PAYÉ, ni plus ni moins
|--------------------------------------------------------------------------
| Avant : remboursement d'une ligne = prix × quantité + taxe.
|   - Vente TTC (taxe comprise, usage haïtien) : la taxe est DÉJÀ dans le prix ;
|     l'ajouter faisait sortir de la caisse la taxe en trop (110 payés, 121 rendus).
|   - Vente remisée : le prix de la ligne est celui d'AVANT remise : la remise
|     était rendue en plus.
|
| Maintenant : (payé pour la ligne) × (fraction rendue), cumulé au centime près.
| Les ventes sont faites par le vrai PosService : les montants testés sont ceux
| que la caisse enregistre réellement, pas des valeurs écrites à la main.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Models\Pos\Product;
use Modules\Tagtoa\App\Models\Pos\Sale;
use Modules\Tagtoa\App\Models\Pos\SaleReturn;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Services\Pos\PosCatalog;
use Modules\Tagtoa\App\Services\Pos\PosService;
use Modules\Tagtoa\App\Services\Pos\ReturnService;
use Modules\Tagtoa\Tests\TestCase;

class PosReturnArithmeticTest extends TestCase
{
    use RefreshDatabase;

    private function commerce(bool $inclusive, float $rate = 10): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-1', 'name' => 'Roosevelt']));
        Business::updateOrCreate(['id' => 't-1'], ['account_id' => 't-1', 'name' => 'Boutik', 'currency' => 'HTG', 'is_active' => true,
            'tax_enabled' => true, 'tax_rate' => $rate, 'tax_inclusive' => $inclusive, 'tax_label' => 'TCA']);
    }

    private function caisse(): Terminal
    {
        return Terminal::firstOrCreate(['tenant_id' => 't-1', 'name' => 'Caisse'], ['currency' => 'HTG', 'is_active' => true]);
    }

    private function article(string $name, float $price): Product
    {
        return app(PosCatalog::class)->save($this->caisse(), ['name' => $name, 'price' => $price, 'is_active' => true, 'stock' => 100]);
    }

    private function vendre(array $lignes, float $remise = 0): Sale
    {
        $items = array_map(fn ($l) => ['ref' => 'pos:'.$l[0]->id, 'qty' => $l[1]], $lignes);

        return app(PosService::class)->recordSale($this->caisse(), ['items' => $items, 'discount' => $remise])->load('items');
    }

    private function rendre(Sale $sale, array $parLigne, string $key = null): SaleReturn
    {
        $map = [];
        foreach ($parLigne as $i => $qty) {
            $map[$sale->items[$i]->id] = $qty;
        }
        $r = app(ReturnService::class)->record($sale, $map, ['tenant_id' => 't-1', 'idempotency_key' => $key ?? uniqid('k', true)]);
        $this->assertSame(ReturnService::OK, $r['result']);

        return $r['return'];
    }

    public function test_tax_included_no_discount_refunds_exactly_the_sticker_price(): void
    {
        $this->commerce(inclusive: true);
        $coca = $this->article('Coca', 110);
        $sale = $this->vendre([[$coca, 1]]);
        $this->assertEquals(110.0, (float) $sale->total);

        $r = $this->rendre($sale, [0 => 1]);

        $this->assertEquals(110.0, (float) $r->total, 'Payé 110, rendu 110 (avant : 121).');
        $this->assertEquals(10.0, (float) $r->tax_total);
        $this->assertEquals(100.0, (float) $r->subtotal);
    }

    public function test_tax_excluded_no_discount_refunds_price_plus_tax(): void
    {
        $this->commerce(inclusive: false);
        $coca = $this->article('Coca', 100);
        $sale = $this->vendre([[$coca, 2]]);
        $this->assertEquals(220.0, (float) $sale->total);

        $r = $this->rendre($sale, [0 => 1]);

        $this->assertEquals(110.0, (float) $r->total);
        $this->assertEquals(10.0, (float) $r->tax_total);
        $this->assertEquals(100.0, (float) $r->subtotal);
    }

    public function test_tax_included_with_discount_refunds_the_discounted_amount(): void
    {
        $this->commerce(inclusive: true);
        $coca = $this->article('Coca', 110);
        $sale = $this->vendre([[$coca, 2]], remise: 20);          // 220 − 20 = 200 payés
        $this->assertEquals(200.0, (float) $sale->total);

        $r = $this->rendre($sale, [0 => 1]);

        $this->assertEquals(100.0, (float) $r->total, 'Une unité sur deux de 200 payés = 100 (avant : 121).');
    }

    public function test_tax_excluded_with_discount_refunds_the_discounted_amount_plus_its_tax(): void
    {
        $this->commerce(inclusive: false);
        $coca = $this->article('Coca', 100);
        $sale = $this->vendre([[$coca, 2]], remise: 20);          // base 180, taxe 18, total 198
        $this->assertEquals(198.0, (float) $sale->total);

        $r = $this->rendre($sale, [0 => 1]);

        $this->assertEquals(99.0, (float) $r->total, 'Moitié de 198 (avant : 110).');
        $this->assertEquals(9.0, (float) $r->tax_total);
    }

    public function test_the_cumulative_refunds_never_exceed_what_was_paid_even_with_awkward_cents(): void
    {
        $this->commerce(inclusive: true, rate: 18);
        $a = $this->article('A', 33.33);
        $b = $this->article('B', 7.77);
        $sale = $this->vendre([[$a, 3], [$b, 7]], remise: 5.55);
        $paye = (float) $sale->total;

        $sum = 0.0;
        foreach ([1, 1, 1] as $i => $_) {
            $sum += (float) $this->rendre($sale, [0 => 1], 'a'.$i)->total;
        }
        for ($i = 0; $i < 7; $i++) {
            $sum += (float) $this->rendre($sale, [1 => 1], 'b'.$i)->total;
        }

        $this->assertEqualsWithDelta($paye, $sum, 0.0001, 'Tout rendre = rendre exactement ce qui a été payé, au centime près.');
        $this->assertLessThanOrEqual($paye + 0.0001, (float) SaleReturn::where('sale_id', $sale->id)->sum('total'));
    }

    public function test_base_plus_tax_always_equals_the_amount_returned(): void
    {
        $this->commerce(inclusive: true, rate: 18);
        $a = $this->article('A', 33.33);
        $sale = $this->vendre([[$a, 3]], remise: 4.40);

        for ($i = 0; $i < 3; $i++) {
            $r = $this->rendre($sale, [0 => 1], 'k'.$i);
            $this->assertEqualsWithDelta((float) $r->total, (float) $r->subtotal + (float) $r->tax_total, 0.0001);
        }
    }

    public function test_a_sale_without_tax_still_refunds_what_was_paid(): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-1', 'name' => 'X']));
        Business::updateOrCreate(['id' => 't-1'], ['account_id' => 't-1', 'name' => 'Boutik', 'currency' => 'HTG', 'is_active' => true]);
        $c = $this->article('Riz', 100);
        $sale = $this->vendre([[$c, 2]], remise: 20);

        $this->assertEquals(90.0, (float) $this->rendre($sale, [0 => 1])->total);
    }

    public function test_a_full_refund_of_a_discounted_sale_marks_the_order_fully_refunded_total_equals_sale_total(): void
    {
        $this->commerce(inclusive: false);
        $coca = $this->article('Coca', 100);
        $sale = $this->vendre([[$coca, 2]], remise: 20);

        $r = $this->rendre($sale, [0 => 2]);

        $this->assertEquals((float) $sale->total, (float) $r->total);
    }
}
