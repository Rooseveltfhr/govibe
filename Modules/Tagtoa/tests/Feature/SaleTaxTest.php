<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Models\Pos\Product;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Services\Pos\PosCatalog;
use Modules\Tagtoa\App\Services\Pos\PosService;
use Modules\Tagtoa\Tests\TestCase;

/**
 * La taxe sur une vente encaissée.
 *
 * Se tromper de convention, c'est soit facturer 10 % de trop au client, soit
 * payer la taxe de sa poche à chaque vente. Et un reçu dont les lignes ne
 * totalisent pas le montant encaissé est un reçu qu'un comptable refuse.
 */
class SaleTaxTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));
    }

    private function commerce(array $taxe = [], string $tenantId = 't-1'): Business
    {
        return Business::updateOrCreate(['id' => $tenantId], array_merge([
            'account_id' => $tenantId, 'name' => 'Boutik Lakay', 'currency' => 'HTG', 'is_active' => true,
        ], $taxe));
    }

    private function caisse(string $tenantId = 't-1'): Terminal
    {
        return Terminal::firstOrCreate(['tenant_id' => $tenantId, 'name' => 'Caisse'],
            ['currency' => 'HTG', 'is_active' => true]);
    }

    private function article(array $attrs = []): Product
    {
        return app(PosCatalog::class)->save($this->caisse(), array_merge([
            'name' => 'Coca', 'price' => 110, 'is_active' => true,
        ], $attrs));
    }

    private function vendre(array $items, float $remise = 0)
    {
        return app(PosService::class)->recordSale($this->caisse(), [
            'items' => $items, 'discount' => $remise,
        ]);
    }

    /* ------------------------------------------------------------------
       Le commerce qui ne facture pas de taxe : rien ne change.
       ------------------------------------------------------------------ */

    public function test_a_shop_without_tax_is_left_exactly_as_before(): void
    {
        // La majorité des petits commerces ne facturent pas de taxe. Leur en
        // ajouter une en silence à la mise à jour serait la pire chose à faire.
        $this->patron();
        $this->commerce();
        $coca = $this->article(['price' => 100]);

        $vente = $this->vendre([['ref' => 'pos:'.$coca->id, 'qty' => 2]]);

        $this->assertEquals(200.0, (float) $vente->total);
        $this->assertEquals(0.0, (float) $vente->tax_total);
        $this->assertNull($vente->tax_label);
    }

    public function test_a_rate_without_the_switch_changes_nothing(): void
    {
        // Renseigner un taux sans activer la taxe ne doit rien déclencher :
        // le marchand prépare son passage à la TCA, il ne l'a pas encore fait.
        $this->patron();
        $this->commerce(['tax_rate' => 10, 'tax_enabled' => false]);
        $coca = $this->article(['price' => 100]);

        $this->assertEquals(0.0, (float) $this->vendre([['ref' => 'pos:'.$coca->id, 'qty' => 1]])->tax_total);
    }

    /* ------------------------------------------------------------------
       Prix taxe comprise — l'usage haïtien.
       ------------------------------------------------------------------ */

    public function test_with_prices_tax_included_the_customer_pays_the_sticker(): void
    {
        $this->patron();
        $this->commerce(['tax_enabled' => true, 'tax_rate' => 10, 'tax_inclusive' => true, 'tax_label' => 'TCA']);
        $coca = $this->article(['price' => 110]);

        $vente = $this->vendre([['ref' => 'pos:'.$coca->id, 'qty' => 1]]);

        $this->assertEquals(110.0, (float) $vente->total, 'Le prix affiché est ce que le client paie.');
        $this->assertEquals(100.0, (float) $vente->tax_base);
        $this->assertEquals(10.0, (float) $vente->tax_total);
        $this->assertSame('TCA 10 %', $vente->tax_label);
    }

    public function test_with_prices_tax_excluded_the_total_grows(): void
    {
        $this->patron();
        $this->commerce(['tax_enabled' => true, 'tax_rate' => 10, 'tax_inclusive' => false, 'tax_label' => 'TCA']);
        $coca = $this->article(['price' => 100]);

        $vente = $this->vendre([['ref' => 'pos:'.$coca->id, 'qty' => 1]]);

        $this->assertEquals(110.0, (float) $vente->total, 'La taxe s\'ajoute à la caisse.');
        $this->assertEquals(100.0, (float) $vente->tax_base);
        $this->assertEquals(10.0, (float) $vente->tax_total);
    }

    /* ------------------------------------------------------------------
       Le reçu doit tomber juste.
       ------------------------------------------------------------------ */

    public function test_the_line_taxes_add_up_to_the_sale_tax(): void
    {
        // Sans cela, le détail du reçu contredirait son pied de page.
        $this->patron();
        $this->commerce(['tax_enabled' => true, 'tax_rate' => 10, 'tax_inclusive' => true]);
        $a = $this->article(['name' => 'A', 'price' => 33.33]);
        $b = $this->article(['name' => 'B', 'price' => 77.77]);

        $vente = $this->vendre([
            ['ref' => 'pos:'.$a->id, 'qty' => 3],
            ['ref' => 'pos:'.$b->id, 'qty' => 1],
        ]);

        $somme = round((float) $vente->items()->sum('tax_amount'), 2);

        $this->assertEquals((float) $vente->tax_total, $somme);
    }

    public function test_base_plus_tax_equals_what_was_collected(): void
    {
        $this->patron();
        $this->commerce(['tax_enabled' => true, 'tax_rate' => 18, 'tax_inclusive' => true]);
        $coca = $this->article(['price' => 33.33]);

        $vente = $this->vendre([['ref' => 'pos:'.$coca->id, 'qty' => 7]]);

        $this->assertEquals(
            (float) $vente->total,
            round((float) $vente->tax_base + (float) $vente->tax_total, 2)
        );
    }

    /* ------------------------------------------------------------------
       Les exonérés.
       ------------------------------------------------------------------ */

    public function test_an_exempt_article_carries_no_tax(): void
    {
        // Le riz et les médicaments sont souvent exonérés là où l'alcool est
        // plein tarif.
        $this->patron();
        $this->commerce(['tax_enabled' => true, 'tax_rate' => 10, 'tax_inclusive' => true]);
        $riz = $this->article(['name' => 'Riz', 'price' => 100, 'tax_rate' => 0]);

        $vente = $this->vendre([['ref' => 'pos:'.$riz->id, 'qty' => 1]]);

        $this->assertEquals(0.0, (float) $vente->tax_total, 'Un 0 écrit sur l\'article est une décision, pas un oubli.');
        $this->assertEquals(100.0, (float) $vente->total);
    }

    public function test_the_receipt_separates_the_rates(): void
    {
        // Dès qu'un commerce vend de l'exonéré à côté du taxé, c'est ce que la
        // déclaration demande.
        $this->patron();
        $this->commerce(['tax_enabled' => true, 'tax_rate' => 10, 'tax_inclusive' => true]);
        $riz  = $this->article(['name' => 'Riz', 'price' => 100, 'tax_rate' => 0]);
        $rhum = $this->article(['name' => 'Rhum', 'price' => 110]);

        $vente = $this->vendre([
            ['ref' => 'pos:'.$riz->id, 'qty' => 1],
            ['ref' => 'pos:'.$rhum->id, 'qty' => 1],
        ]);

        $detail = collect($vente->tax_breakdown)->keyBy(fn ($l) => (string) $l['rate']);

        $this->assertEquals(10.0, $detail['10']['tax']);
        $this->assertEquals(0.0, $detail['0']['tax']);
        $this->assertEquals(10.0, (float) $vente->tax_total);
    }

    public function test_an_article_with_its_own_higher_rate_wins(): void
    {
        $this->patron();
        $this->commerce(['tax_enabled' => true, 'tax_rate' => 10, 'tax_inclusive' => true]);
        $rhum = $this->article(['name' => 'Rhum', 'price' => 118, 'tax_rate' => 18]);

        $vente = $this->vendre([['ref' => 'pos:'.$rhum->id, 'qty' => 1]]);

        $this->assertEquals(18.0, (float) $vente->tax_total);
        $this->assertEquals(100.0, (float) $vente->tax_base);
    }

    /* ------------------------------------------------------------------
       La remise.
       ------------------------------------------------------------------ */

    public function test_a_discount_lowers_the_tax_it_does_not_leave_it_behind(): void
    {
        // Sinon le commerce paierait une taxe sur de l'argent qu'il n'a pas
        // encaissé.
        $this->patron();
        $this->commerce(['tax_enabled' => true, 'tax_rate' => 10, 'tax_inclusive' => true]);
        $coca = $this->article(['price' => 110]);

        $vente = $this->vendre([['ref' => 'pos:'.$coca->id, 'qty' => 2]], 110);

        $this->assertEquals(110.0, (float) $vente->total);
        $this->assertEquals(10.0, (float) $vente->tax_total, 'La taxe suit le montant réellement encaissé.');
    }

    /* ------------------------------------------------------------------
       Ce qui est figé.
       ------------------------------------------------------------------ */

    public function test_raising_the_rate_never_rewrites_an_old_receipt(): void
    {
        // LE point : un reçu est une pièce comptable, pas un tableau vivant.
        $this->patron();
        $commerce = $this->commerce(['tax_enabled' => true, 'tax_rate' => 10, 'tax_inclusive' => true]);
        $coca = $this->article(['price' => 110]);

        $vente = $this->vendre([['ref' => 'pos:'.$coca->id, 'qty' => 1]]);

        // L'État relève la taxe l'année suivante.
        $commerce->update(['tax_rate' => 18, 'tax_label' => 'TCA']);

        $frais = $vente->fresh();
        $this->assertEquals(10.0, (float) $frais->tax_total);
        $this->assertEquals(100.0, (float) $frais->tax_base);
        $this->assertEquals(10.0, (float) $frais->items()->first()->tax_rate);
    }

    public function test_flipping_the_convention_never_flips_old_receipts(): void
    {
        $this->patron();
        $commerce = $this->commerce(['tax_enabled' => true, 'tax_rate' => 10, 'tax_inclusive' => true]);
        $coca = $this->article(['price' => 110]);

        $vente = $this->vendre([['ref' => 'pos:'.$coca->id, 'qty' => 1]]);

        $commerce->update(['tax_inclusive' => false]);

        $this->assertTrue($vente->fresh()->tax_inclusive);
        $this->assertEquals(110.0, (float) $vente->fresh()->total);
    }

    /* ------------------------------------------------------------------
       Cloisonnement.
       ------------------------------------------------------------------ */

    public function test_the_neighbour_tax_regime_never_applies_here(): void
    {
        $this->patron('t-2');
        $this->commerce(['tax_enabled' => true, 'tax_rate' => 18, 'tax_inclusive' => true], 't-2');

        $this->patron('t-1');
        $this->commerce([], 't-1');
        $coca = $this->article(['price' => 100]);

        $this->assertEquals(0.0, (float) $this->vendre([['ref' => 'pos:'.$coca->id, 'qty' => 1]])->tax_total);
    }

    public function test_a_shop_with_no_business_record_still_sells(): void
    {
        // Un marchand qui n'a pas encore créé son commerce doit continuer
        // d'encaisser : pas de taxe plutôt qu'une panne.
        $this->patron();
        $coca = $this->article(['price' => 100]);

        $vente = $this->vendre([['ref' => 'pos:'.$coca->id, 'qty' => 1]]);

        $this->assertEquals(100.0, (float) $vente->total);
        $this->assertEquals(0.0, (float) $vente->tax_total);
    }

    /* ------------------------------------------------------------------
       Ce qu'il faudra déclarer.
       ------------------------------------------------------------------ */

    public function test_the_report_says_what_must_be_declared(): void
    {
        $this->patron();
        $this->commerce(['tax_enabled' => true, 'tax_rate' => 10, 'tax_inclusive' => true, 'tax_label' => 'TCA']);
        $coca = $this->article(['price' => 110]);

        $this->vendre([['ref' => 'pos:'.$coca->id, 'qty' => 3]]);
        $this->vendre([['ref' => 'pos:'.$coca->id, 'qty' => 2]]);

        $ventes = app(\Modules\Tagtoa\App\Services\Pos\PosSales::class);
        $rapport = $ventes->taxReport($ventes->forOwner('t-1'));

        $this->assertEquals(50.0, $rapport['collected'], '5 × 10.');
        $this->assertEquals(500.0, $rapport['base']);
        $this->assertEquals(550.0, $rapport['total']);
        $this->assertSame(2, $rapport['sales']);
        $this->assertSame('TCA 10 %', $rapport['label']);
    }

    public function test_the_report_separates_the_rates(): void
    {
        $this->patron();
        $this->commerce(['tax_enabled' => true, 'tax_rate' => 10, 'tax_inclusive' => true]);
        $riz  = $this->article(['name' => 'Riz', 'price' => 100, 'tax_rate' => 0]);
        $rhum = $this->article(['name' => 'Rhum', 'price' => 118, 'tax_rate' => 18]);

        $this->vendre([
            ['ref' => 'pos:'.$riz->id, 'qty' => 2],
            ['ref' => 'pos:'.$rhum->id, 'qty' => 1],
        ]);

        $ventes = app(\Modules\Tagtoa\App\Services\Pos\PosSales::class);
        $rapport = $ventes->taxReport($ventes->forOwner('t-1'));

        $this->assertEquals(18.0, $rapport['collected']);
        // Le taux le plus élevé en premier.
        $this->assertEquals(18.0, $rapport['byRate'][0]['rate']);
        $this->assertEquals(18.0, $rapport['byRate'][0]['tax']);
        $this->assertEquals(0.0, $rapport['byRate'][1]['tax']);
    }

    public function test_the_report_does_not_move_when_the_rate_changes(): void
    {
        // Une déclaration qui changerait parce qu'on a modifié un taux depuis
        // ne vaudrait rien.
        $this->patron();
        $commerce = $this->commerce(['tax_enabled' => true, 'tax_rate' => 10, 'tax_inclusive' => true]);
        $coca = $this->article(['price' => 110]);

        $this->vendre([['ref' => 'pos:'.$coca->id, 'qty' => 1]]);
        $commerce->update(['tax_rate' => 18]);

        $ventes = app(\Modules\Tagtoa\App\Services\Pos\PosSales::class);

        $this->assertEquals(10.0, $ventes->taxReport($ventes->forOwner('t-1'))['collected']);
    }

    public function test_the_report_never_counts_the_neighbour_sales(): void
    {
        $this->patron('t-2');
        $this->commerce(['tax_enabled' => true, 'tax_rate' => 18, 'tax_inclusive' => true], 't-2');
        $chezLui = app(PosCatalog::class)->save($this->caisse('t-2'),
            ['name' => 'Rhum', 'price' => 118, 'is_active' => true]);
        app(\Modules\Tagtoa\App\Services\Pos\PosService::class)
            ->recordSale($this->caisse('t-2'), ['items' => [['ref' => 'pos:'.$chezLui->id, 'qty' => 1]]]);

        $this->patron('t-1');
        $this->commerce(['tax_enabled' => true, 'tax_rate' => 10, 'tax_inclusive' => true], 't-1');
        $coca = $this->article(['price' => 110]);
        $this->vendre([['ref' => 'pos:'.$coca->id, 'qty' => 1]]);

        $ventes = app(\Modules\Tagtoa\App\Services\Pos\PosSales::class);

        $this->assertEquals(10.0, $ventes->taxReport($ventes->forOwner('t-1'))['collected']);
    }

    /* ------------------------------------------------------------------
       Les écrans.
       ------------------------------------------------------------------ */

    public function test_the_till_announces_the_amount_including_tax(): void
    {
        // Avec des prix hors taxe, afficher le sous-total ferait annoncer au
        // client moins que ce qu'il paiera.
        $this->patron();
        $this->commerce(['tax_enabled' => true, 'tax_rate' => 10, 'tax_inclusive' => false, 'tax_label' => 'TCA']);
        $this->article(['price' => 100]);

        $this->get(route('tagtoa.pos.register', $this->caisse()->id))
            ->assertOk()
            ->assertSee('TCA 10 %')
            ->assertSee('taxeDuPanier', false);
    }

    public function test_the_z_report_shows_what_to_declare(): void
    {
        $this->patron();
        $this->commerce(['tax_enabled' => true, 'tax_rate' => 10, 'tax_inclusive' => true, 'tax_label' => 'TCA']);
        $coca = $this->article(['price' => 110]);
        $this->vendre([['ref' => 'pos:'.$coca->id, 'qty' => 1]]);

        $this->get(route('tagtoa.pos.report', $this->caisse()->id))
            ->assertOk()
            ->assertSee('TCA 10 %');
    }

    public function test_the_till_can_finally_sell_by_weight_over_http(): void
    {
        // L'endpoint exigeait un entier : 2,5 livres de riz étaient refusées
        // alors que la caisse et la base savent les traiter.
        $this->patron();
        $this->commerce();
        $riz = $this->article(['name' => 'Riz', 'price' => 120, 'unit' => 'lb', 'stock' => 50]);

        $this->postJson(route('tagtoa.pos.sale', $this->caisse()->id), [
            'items' => [['ref' => 'pos:'.$riz->id, 'name' => 'Riz', 'price' => 120, 'qty' => 2.5]],
        ])->assertOk()->assertJsonPath('total', 300);

        $this->assertSame(47.5, $riz->fresh()->stock);
    }
}
