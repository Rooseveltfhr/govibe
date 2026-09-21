<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Pos\Category;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Services\Pos\PosCatalog;
use Modules\Tagtoa\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| TAGTOA POS — les rayons existaient depuis longtemps côté back-office
| (créer, renommer, compter les articles), mais n'atteignaient JAMAIS la
| grille de vente : PosCatalog::sellable() figeait `group` à null pour tout
| article POS, et register.blade.php n'affichait aucun onglet — le rayon ne
| servait qu'à un attribut `title` invisible sur le bouton.
|--------------------------------------------------------------------------
*/
class PosRegisterCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));
    }

    private function caisse(string $tenantId = 't-1'): Terminal
    {
        return Terminal::create(['tenant_id' => $tenantId, 'name' => 'Caisse', 'currency' => 'HTG', 'is_active' => true]);
    }

    public function test_sellable_carries_the_products_category_name(): void
    {
        $this->patron();
        $caisse = $this->caisse();
        $boissons = Category::create(['tenant_id' => 't-1', 'name' => 'Boissons', 'sort' => 1, 'is_active' => true]);

        app(PosCatalog::class)->save($caisse, [
            'name' => 'Coca', 'price' => 75, 'is_active' => true, 'category_id' => $boissons->id,
        ]);
        app(PosCatalog::class)->save($caisse, [
            'name' => 'Savon', 'price' => 50, 'is_active' => true,
        ]);

        $sellable = collect(app(PosCatalog::class)->sellable('t-1'))->keyBy('name');

        $this->assertSame('Boissons', $sellable['Coca']['group']);
        $this->assertNull($sellable['Savon']['group']);
    }

    public function test_the_register_screen_shows_a_tab_per_category(): void
    {
        $this->patron();
        $caisse = $this->caisse();
        $boissons = Category::create(['tenant_id' => 't-1', 'name' => 'Boissons', 'sort' => 1, 'is_active' => true]);
        app(PosCatalog::class)->save($caisse, [
            'name' => 'Coca', 'price' => 75, 'is_active' => true, 'category_id' => $boissons->id,
        ]);

        $html = $this->get(route('tagtoa.pos.register', $caisse->id))->assertOk()->getContent();

        $this->assertStringContainsString('data-rayon="Boissons"', $html);
        $this->assertStringContainsString('data-group="Boissons"', $html);
        // L'onglet « Tout » reste toujours présent, actif par défaut.
        $this->assertStringContainsString('data-rayon=""', $html);
    }

    public function test_no_category_bar_when_the_shop_has_none(): void
    {
        // Un commerce qui n'a jamais créé de rayon ne doit pas voir une barre
        // avec pour seul choix « Tout » — ça n'aiderait à rien.
        $this->patron();
        $caisse = $this->caisse();
        app(PosCatalog::class)->save($caisse, ['name' => 'Coca', 'price' => 75, 'is_active' => true]);

        $html = $this->get(route('tagtoa.pos.register', $caisse->id))->assertOk()->getContent();

        $this->assertStringNotContainsString('id="rayons"', $html);
    }
}
