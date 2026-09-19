<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Pos\Product;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Services\Pos\PosCatalog;
use Modules\Tagtoa\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| TAGTOA POS — un hôtel vend des nuitées, une clinique des consultations,
| un spa des soins : rien de tout cela n'a de stock à compter ni de
| code-barres à scanner. Le formulaire produit posait pourtant Stock et
| Alerte sous pour tout article sans distinction. « Service » est une
| case, jamais une restriction — un commerce mixte peut vendre les deux.
|--------------------------------------------------------------------------
*/
class PosServiceProductTest extends TestCase
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

    public function test_a_service_article_is_added_with_no_stock_even_if_one_was_typed(): void
    {
        // Le champ Stock reste dans le formulaire (il n'est que masqué en
        // JS) : si un navigateur l'envoyait quand même, le stock d'un
        // service ne doit JAMAIS s'ouvrir — un compteur n'a pas de sens pour
        // une nuitée.
        $this->patron();
        $caisse = $this->caisse();

        $this->post(route('tagtoa.pos.products.add', $caisse->id), [
            'name' => 'Chambre Double', 'price' => 3500, 'stock' => 12, 'is_service' => '1',
        ])->assertRedirect();

        $chambre = Product::where('name', 'Chambre Double')->firstOrFail();
        $this->assertTrue($chambre->is_service);
        $this->assertNull($chambre->stock);
    }

    public function test_a_physical_article_keeps_a_normal_stock(): void
    {
        $this->patron();
        $caisse = $this->caisse();

        $this->post(route('tagtoa.pos.products.add', $caisse->id), [
            'name' => 'Coca', 'price' => 75, 'stock' => 24,
        ])->assertRedirect();

        $coca = Product::where('name', 'Coca')->firstOrFail();
        $this->assertFalse($coca->is_service);
        $this->assertSame(24.0, $coca->stock);
    }

    public function test_marking_an_existing_product_as_a_service_clears_its_stock(): void
    {
        $this->patron();
        $caisse = $this->caisse();
        $article = app(PosCatalog::class)->save($caisse, [
            'name' => 'Consultation', 'price' => 1500, 'stock' => 5, 'is_active' => true,
        ]);

        $this->post(route('tagtoa.pos.products.save', $caisse->id), [
            'form_end' => 1,
            'products' => [0 => [
                'id' => $article->id, 'name' => 'Consultation', 'price' => 1500,
                // Stock et seuil ENVOYÉS quand même : un navigateur qui n'a
                // pas exécuté le JS (ou un formulaire trafiqué) ne doit pas
                // pouvoir rouvrir un compteur pour un service.
                'is_service' => '1', 'stock' => 8, 'low_stock_threshold' => 2,
            ]],
        ])->assertRedirect();

        $frais = $article->fresh();
        $this->assertTrue($frais->is_service);
        $this->assertNull($frais->stock);
        $this->assertNull($frais->low_stock_threshold);
    }
}
