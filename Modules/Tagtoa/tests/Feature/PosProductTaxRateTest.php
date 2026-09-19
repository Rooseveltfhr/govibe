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

/*
|--------------------------------------------------------------------------
| TAGTOA POS — la taxe par article était calculée bout en bout
| (TaxProfile::rateFor lit déjà Product::tax_rate en priorité) mais AUCUN
| formulaire ne permettait de la saisir. Un pharmacien ne pouvait jamais
| exonérer un médicament précis sans désactiver la taxe pour tout le
| commerce.
|--------------------------------------------------------------------------
*/
class PosProductTaxRateTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenantId = 't-1'): Business
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));

        return Business::create([
            'id' => $tenantId, 'account_id' => $tenantId, 'name' => 'Pharmacie Test', 'type' => 'pharmacy',
            'currency' => 'HTG', 'tax_enabled' => true, 'tax_rate' => 10, 'tax_inclusive' => true, 'tax_label' => 'TCA',
        ]);
    }

    private function caisse(string $tenantId = 't-1'): Terminal
    {
        return Terminal::firstOrCreate(['tenant_id' => $tenantId, 'name' => 'Caisse'],
            ['currency' => 'HTG', 'is_active' => true]);
    }

    public function test_a_tax_rate_can_be_set_when_adding_a_product(): void
    {
        $this->patron();
        $caisse = $this->caisse();

        $this->post(route('tagtoa.pos.products.add', $caisse->id), [
            'name' => 'Amoxicilline', 'price' => 200, 'tax_rate' => 0,
        ])->assertRedirect();

        $article = Product::where('name', 'Amoxicilline')->firstOrFail();
        // « 0 » saisi doit rester 0 EXONÉRÉ, pas disparaître en NULL « non
        // renseigné » — d'où assertNotNull en plus de la valeur.
        $this->assertNotNull($article->tax_rate);
        $this->assertSame(0.0, (float) $article->tax_rate);
    }

    public function test_an_empty_tax_rate_inherits_the_business_rate_instead_of_zero(): void
    {
        // « Non renseigné » n'est pas « exonéré » : enregistrer 0 par défaut
        // taxerait à 0 % tout article dont le marchand n'a rien dit, alors que
        // l'intention est qu'il suive le taux du commerce.
        $this->patron();
        $caisse = $this->caisse();

        $this->post(route('tagtoa.pos.products.add', $caisse->id), [
            'name' => 'Riz', 'price' => 100, 'tax_rate' => '',
        ])->assertRedirect();

        $this->assertNull(Product::where('name', 'Riz')->value('tax_rate'));
    }

    public function test_an_out_of_bounds_tax_rate_is_refused(): void
    {
        $this->patron();
        $caisse = $this->caisse();

        $this->post(route('tagtoa.pos.products.add', $caisse->id), [
            'name' => 'Riz', 'price' => 100, 'tax_rate' => 150,
        ])->assertSessionHasErrors('tax_rate');

        $this->assertSame(0, Product::count());
    }

    public function test_the_tax_rate_can_be_changed_when_editing_an_existing_product(): void
    {
        $this->patron();
        $caisse = $this->caisse();
        $article = app(PosCatalog::class)->save($caisse, ['name' => 'Sirop', 'price' => 300, 'is_active' => true]);

        $this->post(route('tagtoa.pos.products.save', $caisse->id), [
            'form_end' => 1,
            'products' => [0 => [
                'id' => $article->id, 'name' => 'Sirop', 'price' => 300, 'tax_rate' => 0,
            ]],
        ])->assertRedirect();

        $frais = $article->fresh();
        $this->assertNotNull($frais->tax_rate);
        $this->assertSame(0.0, (float) $frais->tax_rate);
    }

    public function test_an_article_exempted_from_tax_is_not_taxed_on_sale(): void
    {
        // L'intégration bout en bout : un article passé par CE formulaire, à
        // 0 %, ne doit vraiment rien ajouter à l'encaissement — alors que le
        // commerce lui-même taxe à 10 %.
        $this->patron();
        $caisse = $this->caisse();

        $this->post(route('tagtoa.pos.products.add', $caisse->id), [
            'name' => 'Amoxicilline', 'price' => 200, 'tax_rate' => 0,
        ]);
        $article = Product::where('name', 'Amoxicilline')->firstOrFail();

        $sale = app(PosService::class)->recordSale($caisse, [
            'items' => [['product_id' => $article->id, 'qty' => 1]],
        ]);

        $this->assertSame(0.0, (float) $sale->tax_total);
        $this->assertSame(200.0, (float) $sale->total);
    }
}
