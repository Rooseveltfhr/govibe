<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Catalog\ProductCode;
use Modules\Tagtoa\App\Models\Pos\Product;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Services\Catalog\ProductCodes;
use Modules\Tagtoa\App\Services\Pos\PosCatalog;
use Modules\Tagtoa\App\Support\Catalog\Barcode;
use Modules\Tagtoa\Tests\TestCase;

/**
 * Attribuer un code à un article, et fabriquer une étiquette pour ce qui n'en
 * a pas — la majorité de ce que vend une boutique de quartier.
 */
class ProductCodesScreenTest extends TestCase
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

    private function article(string $tenantId = 't-1', array $attrs = []): Product
    {
        return app(PosCatalog::class)->save($this->caisse($tenantId), array_merge([
            'name' => 'Coca', 'price' => 75, 'is_active' => true,
        ], $attrs));
    }

    /* ---------------- l'écran ---------------- */

    public function test_the_screen_shows_the_codes_of_an_article(): void
    {
        $this->patron();
        $coca = $this->article();
        app(ProductCodes::class)->attach('t-1', 'pos:'.$coca->id, '5449000000996');

        $this->get(route('tagtoa.catalog.codes.index', ['ref' => 'pos:'.$coca->id]))
            ->assertOk()
            ->assertSee('5449000000996');
    }

    public function test_the_neighbour_article_is_out_of_reach(): void
    {
        $this->patron('t-2');
        $chezLui = $this->article('t-2', ['name' => 'Rhum Barbancourt']);

        $this->patron('t-1');
        $this->caisse('t-1');

        $this->get(route('tagtoa.catalog.codes.index', ['ref' => 'pos:'.$chezLui->id]))
            ->assertNotFound();
    }

    /* ---------------- attribuer ---------------- */

    public function test_a_scanned_code_is_attached(): void
    {
        $this->patron();
        $coca = $this->article();

        $this->post(route('tagtoa.catalog.codes.attach'), [
            'ref' => 'pos:'.$coca->id, 'code' => '5449000000996',
        ])->assertRedirect();

        $this->assertSame(1, ProductCode::where('code', '5449000000996')->count());
    }

    public function test_a_misread_code_is_refused_with_a_reason(): void
    {
        // Chiffre de contrôle faux : accepter créerait un article que personne
        // ne retrouverait jamais en scannant.
        $this->patron();
        $coca = $this->article();

        $this->post(route('tagtoa.catalog.codes.attach'), [
            'ref' => 'pos:'.$coca->id, 'code' => '5449000000997',
        ])->assertSessionHasErrors('code');

        $this->assertSame(0, ProductCode::count());
    }

    public function test_a_code_taken_by_another_article_is_not_stolen_in_silence(): void
    {
        // Deux articles qui partagent un code rendraient le scan aléatoire.
        // C'est au marchand de dire lequel le garde.
        $this->patron();
        $coca  = $this->article('t-1', ['name' => 'Coca']);
        $pepsi = $this->article('t-1', ['name' => 'Pepsi']);
        app(ProductCodes::class)->attach('t-1', 'pos:'.$coca->id, '5449000000996');

        $this->post(route('tagtoa.catalog.codes.attach'), [
            'ref' => 'pos:'.$pepsi->id, 'code' => '5449000000996',
        ])->assertSessionHasErrors('code');

        $code = ProductCode::where('code', '5449000000996')->firstOrFail();
        $this->assertSame($coca->id, (int) $code->product_id, 'Le code reste chez son article.');
    }

    public function test_a_code_cannot_be_attached_to_the_neighbour_article(): void
    {
        $this->patron('t-2');
        $chezLui = $this->article('t-2');

        $this->patron('t-1');
        $this->caisse('t-1');

        $this->post(route('tagtoa.catalog.codes.attach'), [
            'ref' => 'pos:'.$chezLui->id, 'code' => '5449000000996',
        ])->assertNotFound();

        $this->assertSame(0, ProductCode::withoutGlobalScopes()->count());
    }

    /* ---------------- fabriquer une étiquette ---------------- */

    public function test_an_article_without_a_barcode_gets_a_tagtoa_label(): void
    {
        // Le pâté et le fresco n'ont pas de code-barres, et n'en auront jamais.
        // Sans cela, le scanner ne servirait qu'aux boutiques de marques.
        $this->patron();
        $pate = $this->article('t-1', ['name' => 'Pâté', 'price' => 50]);

        $this->post(route('tagtoa.catalog.codes.generate'), ['ref' => 'pos:'.$pate->id])
            ->assertRedirect();

        $code = ProductCode::firstOrFail();

        $this->assertSame(Barcode::internal($pate->id), $code->code);
        $this->assertTrue($code->is_primary);
    }

    public function test_the_generated_label_scans_back_to_its_article(): void
    {
        // Le test qui compte : l'étiquette imprimée doit redonner l'article.
        $this->patron();
        $pate = $this->article('t-1', ['name' => 'Pâté', 'price' => 50]);

        $this->post(route('tagtoa.catalog.codes.generate'), ['ref' => 'pos:'.$pate->id]);

        $code = ProductCode::firstOrFail()->code;

        $this->postJson(route('tagtoa.catalog.scan'), ['code' => $code])
            ->assertOk()
            ->assertJsonPath('article.name', 'Pâté');
    }

    public function test_the_label_is_drawn_as_real_bars(): void
    {
        // Une étiquette qui ne montre que des chiffres oblige à les taper à
        // chaque vente — exactement ce que le scanner devait éviter.
        $this->patron();
        $pate = $this->article('t-1', ['name' => 'Pâté']);
        $this->post(route('tagtoa.catalog.codes.generate'), ['ref' => 'pos:'.$pate->id]);

        $this->get(route('tagtoa.catalog.codes.index', ['ref' => 'pos:'.$pate->id]))
            ->assertOk()
            ->assertSee('<svg', false)
            ->assertSee('<rect', false);
    }

    /* ---------------- retirer ---------------- */

    public function test_the_owner_can_remove_a_code(): void
    {
        $this->patron();
        $coca = $this->article();
        $code = app(ProductCodes::class)->attach('t-1', 'pos:'.$coca->id, '5449000000996');

        $this->delete(route('tagtoa.catalog.codes.detach', $code->id))->assertRedirect();

        $this->assertSame(0, ProductCode::count());
    }

    public function test_the_neighbour_cannot_remove_my_code(): void
    {
        $this->patron('t-1');
        $coca = $this->article('t-1');
        $code = app(ProductCodes::class)->attach('t-1', 'pos:'.$coca->id, '5449000000996');

        $this->patron('t-2');

        $this->delete(route('tagtoa.catalog.codes.detach', $code->id))->assertNotFound();

        $this->assertSame(1, ProductCode::withoutGlobalScopes()->count());
    }

    /* ------------------------------------------------------------------
       La boucle complète : scanner un inconnu, le créer, le revendre.
       ------------------------------------------------------------------ */

    public function test_a_scanned_unknown_code_becomes_an_article_that_scans_back(): void
    {
        // C'est le test qui dit si le scanner sert vraiment : partir d'un
        // produit que le commerce ne connaît pas, et finir en le vendant sans
        // jamais avoir tapé un chiffre.
        $this->patron();

        $this->post(route('tagtoa.pos.products.save', $this->caisse()->id), [
            'products' => [[
                'name' => 'Prestige', 'price' => 150, 'is_active' => 1,
                'color' => '#2cb809', 'new_code' => '7640140160016',
            ]],
            // Sentinelle du formulaire : sans elle, un envoi tronqué par PHP
            // serait pris pour une liste complète.
            'form_end' => 1,
        ])->assertRedirect();

        $biere = Product::where('name', 'Prestige')->firstOrFail();
        $this->assertSame(1, ProductCode::where('code', '7640140160016')->count());

        $this->postJson(route('tagtoa.catalog.scan'), ['code' => '7640140160016'])
            ->assertOk()
            ->assertJsonPath('article.ref', 'pos:'.$biere->id)
            ->assertJsonPath('article.price', 150);
    }

    public function test_a_misread_code_never_silently_lands_on_a_new_article(): void
    {
        // L'article est créé — le patron a écrit son nom et son prix — mais le
        // code refusé ne doit pas s'y accrocher : il désignerait un article
        // que personne ne retrouverait en scannant.
        $this->patron();

        $this->post(route('tagtoa.pos.products.save', $this->caisse()->id), [
            'products' => [[
                'name' => 'Prestige', 'price' => 150, 'is_active' => 1,
                'color' => '#2cb809', 'new_code' => '7640140160017',
            ]],
            // Sentinelle du formulaire : sans elle, un envoi tronqué par PHP
            // serait pris pour une liste complète.
            'form_end' => 1,
        ])->assertRedirect();

        $this->assertSame(1, Product::where('name', 'Prestige')->count());
        $this->assertSame(0, ProductCode::count(), 'Un code au contrôle faux ne s\'attache pas.');
    }

    public function test_creating_with_a_code_the_shop_already_uses_changes_nothing(): void
    {
        // Deux articles pour le même produit couperaient le stock en deux.
        $this->patron();
        $coca = $this->article('t-1', ['name' => 'Coca']);
        app(ProductCodes::class)->attach('t-1', 'pos:'.$coca->id, '5449000000996');

        $this->post(route('tagtoa.pos.products.save', $this->caisse()->id), [
            'products' => [[
                'name' => 'Coca bis', 'price' => 80, 'is_active' => 1,
                'color' => '#2cb809', 'new_code' => '5449000000996',
            ]],
            // Sentinelle du formulaire : sans elle, un envoi tronqué par PHP
            // serait pris pour une liste complète.
            'form_end' => 1,
        ])->assertRedirect();

        $code = ProductCode::where('code', '5449000000996')->firstOrFail();
        $this->assertSame($coca->id, (int) $code->product_id, 'Le code reste sur l\'article d\'origine.');
        $this->assertSame(1, ProductCode::count());
    }

    public function test_the_catalogue_screen_carries_the_scanner(): void
    {
        $this->patron();
        $this->article();

        $this->get(route('tagtoa.pos.products.terminal', $this->caisse()->id))
            ->assertOk()
            ->assertSee('tagtoa-scanner.js', false)
            ->assertSee('new_code', false);
    }
}
