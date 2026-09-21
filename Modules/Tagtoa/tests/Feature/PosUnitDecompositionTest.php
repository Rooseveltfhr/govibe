<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Pos\Product;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Services\Pos\PosCatalog;
use Modules\Tagtoa\App\Services\Pos\PosService;
use Modules\Tagtoa\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| TAGTOA POS — un bar tient son stock en bouteilles mais vend au verre. Sans
| lien entre les deux, le marchand créait deux articles indépendants et
| vendre un verre ne décrémentait jamais la bouteille dont il sortait
| réellement — gap explicitement cité dans l'audit multi-secteur.
|--------------------------------------------------------------------------
*/
class PosUnitDecompositionTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));
    }

    private function caisse(string $tenantId = 't-1'): Terminal
    {
        return Terminal::firstOrCreate(['tenant_id' => $tenantId, 'name' => 'Caisse'],
            ['currency' => 'HTG', 'is_active' => true]);
    }

    public function test_a_glass_can_be_linked_to_a_bottle_with_a_ratio(): void
    {
        $this->patron();
        $caisse = $this->caisse();
        $bouteille = app(PosCatalog::class)->save($caisse, ['name' => 'Rhum Barbancourt', 'price' => 2000, 'stock' => 10, 'is_active' => true]);

        $this->post(route('tagtoa.pos.products.add', $caisse->id), [
            'name' => 'Verre de Rhum', 'price' => 100,
            'parent_product_id' => $bouteille->id, 'units_per_parent' => 20,
        ])->assertRedirect();

        $verre = Product::where('name', 'Verre de Rhum')->firstOrFail();
        $this->assertSame($bouteille->id, $verre->parent_product_id);
        $this->assertSame(20.0, $verre->units_per_parent);
        // Le verre n'a pas SON propre stock : le sien vit sur la bouteille.
        $this->assertNull($verre->stock);
    }

    public function test_a_ratio_without_a_parent_is_simply_ignored(): void
    {
        // Un ratio seul ne veut rien dire : plutôt que de refuser tout
        // l'enregistrement pour un champ accessoire oublié, il est
        // silencieusement ignoré — même logique que tax_rate/is_service, qui
        // se corrigent plutôt que de bloquer un article par ailleurs valide.
        $this->patron();
        $caisse = $this->caisse();

        $this->post(route('tagtoa.pos.products.add', $caisse->id), [
            'name' => 'Verre', 'price' => 100, 'units_per_parent' => 20,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $verre = Product::where('name', 'Verre')->firstOrFail();
        $this->assertNull($verre->parent_product_id);
        $this->assertNull($verre->units_per_parent);
    }

    public function test_an_article_cannot_be_its_own_parent(): void
    {
        $this->patron();
        $caisse = $this->caisse();
        $article = app(PosCatalog::class)->save($caisse, ['name' => 'Verre', 'price' => 100, 'is_active' => true]);

        $this->post(route('tagtoa.pos.products.save', $caisse->id), [
            'form_end' => 1,
            'products' => [0 => [
                'id' => $article->id, 'name' => 'Verre', 'price' => 100,
                'parent_product_id' => $article->id, 'units_per_parent' => 5,
            ]],
        ])->assertRedirect();

        $this->assertNull($article->fresh()->parent_product_id);
    }

    public function test_selling_a_glass_decrements_the_bottle_not_itself(): void
    {
        $this->patron();
        $caisse = $this->caisse();
        $bouteille = app(PosCatalog::class)->save($caisse, ['name' => 'Prestige', 'price' => 2000, 'stock' => 3, 'is_active' => true]);
        $verre = app(PosCatalog::class)->save($caisse, [
            'name' => 'Verre', 'price' => 100, 'is_active' => true,
            'parent_product_id' => $bouteille->id, 'units_per_parent' => 25,
        ]);

        // 10 verres = 10/25 = 0,4 bouteille retirée.
        app(PosService::class)->recordSale($caisse, [
            'items' => [['product_id' => $verre->id, 'qty' => 10]],
        ]);

        $this->assertSame(2.6, round((float) $bouteille->fresh()->stock, 1));
        // Le verre lui-même reste sans stock propre.
        $this->assertNull($verre->fresh()->stock);
    }

    public function test_overselling_glasses_beyond_the_bottle_is_refused(): void
    {
        $this->patron();
        $caisse = $this->caisse();
        $bouteille = app(PosCatalog::class)->save($caisse, ['name' => 'Prestige', 'price' => 2000, 'stock' => 1, 'is_active' => true]);
        $verre = app(PosCatalog::class)->save($caisse, [
            'name' => 'Verre', 'price' => 100, 'is_active' => true,
            'parent_product_id' => $bouteille->id, 'units_per_parent' => 25,
        ]);

        // 30 verres demanderaient 1,2 bouteille : il n'en reste qu'une.
        $reponse = $this->postJson(route('tagtoa.pos.sale', $caisse->id), [
            'items' => [['product_id' => $verre->id, 'qty' => 30, 'name' => 'Verre', 'price' => 100]],
        ]);

        $reponse->assertStatus(409);
        $this->assertSame(1.0, (float) $bouteille->fresh()->stock);
    }

    public function test_the_sellable_grid_shows_glasses_left_derived_from_the_bottle(): void
    {
        $this->patron();
        $caisse = $this->caisse();
        $bouteille = app(PosCatalog::class)->save($caisse, ['name' => 'Prestige', 'price' => 2000, 'stock' => 2, 'is_active' => true]);
        app(PosCatalog::class)->save($caisse, [
            'name' => 'Verre', 'price' => 100, 'is_active' => true,
            'parent_product_id' => $bouteille->id, 'units_per_parent' => 25,
        ]);

        $sellable = collect(app(PosCatalog::class)->sellable('t-1'))->keyBy('name');

        // 2 bouteilles × 25 verres = 50 verres restants, pas le stock (null)
        // du verre lui-même.
        $this->assertSame(50.0, $sellable['Verre']['stock']);
    }

    public function test_a_missing_parent_blocks_nothing_it_just_skips_the_stock_movement(): void
    {
        $this->patron();
        $caisse = $this->caisse();
        $bouteille = app(PosCatalog::class)->save($caisse, ['name' => 'Prestige', 'price' => 2000, 'stock' => 5, 'is_active' => true]);
        $verre = app(PosCatalog::class)->save($caisse, [
            'name' => 'Verre', 'price' => 100, 'is_active' => true,
            'parent_product_id' => $bouteille->id, 'units_per_parent' => 25,
        ]);
        $bouteille->delete();

        // La bouteille a disparu (retirée, cassée…) : la vente du verre ne
        // doit pas planter pour autant, elle ne bouge simplement aucun stock.
        $this->postJson(route('tagtoa.pos.sale', $caisse->id), [
            'items' => [['product_id' => $verre->id, 'qty' => 2, 'name' => 'Verre', 'price' => 100]],
        ])->assertOk()->assertJson(['ok' => true]);
    }
}
