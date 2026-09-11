<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Catalog\ProductCode;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Pos\Product;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Services\Catalog\ProductCodes;
use Modules\Tagtoa\App\Services\Pos\PosCatalog;
use Modules\Tagtoa\App\Support\Catalog\Barcode;
use Modules\Tagtoa\App\Support\Pos\CatalogRef;
use Modules\Tagtoa\Tests\TestCase;

/**
 * Retrouver un article par son code — et jamais celui du voisin.
 *
 * Un code n'identifie pas un produit dans l'absolu : la même bouteille de Coca
 * porte le même code chez tout le monde. Scanner chez l'un ne doit jamais
 * donner l'article, le prix ou le stock de l'autre.
 */
class ProductCodesTest extends TestCase
{
    use RefreshDatabase;

    private const COCA = '5449000000996'; // code réel, chiffre de contrôle juste

    private function bouton(string $tenantId, string $nom, float $prix = 75, ?int $stock = null): Product
    {
        $caisse = Terminal::firstOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'Caisse'],
            ['currency' => 'HTG', 'is_active' => true]
        );

        return app(PosCatalog::class)->save($caisse, [
            'name' => $nom, 'price' => $prix, 'stock' => $stock, 'is_active' => true,
        ]);
    }

    private function plat(string $tenantId, string $nom, float $prix = 350): Item
    {
        $menu = Menu::firstOrCreate(
            ['tenant_id' => $tenantId, 'alias' => 'carte-'.$tenantId],
            ['name' => 'Carte', 'currency' => 'HTG', 'is_active' => true]
        );
        $cat = Category::firstOrCreate(['menu_id' => $menu->id, 'name' => 'Plats'], ['is_active' => true]);

        return Item::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id,
            'name' => $nom, 'price' => $prix, 'is_available' => true,
        ]);
    }

    private function codes(): ProductCodes
    {
        return app(ProductCodes::class);
    }

    public function test_scanning_finds_the_article_of_the_shop_that_scans(): void
    {
        $coca = $this->bouton('t-1', 'Coca 500ml', 75);
        $this->codes()->attach('t-1', CatalogRef::make('pos', $coca->id), self::COCA);

        $trouve = $this->codes()->find('t-1', self::COCA);

        $this->assertNotNull($trouve);
        $this->assertSame('Coca 500ml', $trouve->name);
    }

    public function test_two_shops_may_sell_the_same_product_with_the_same_code(): void
    {
        // Le point capital. Une unicité mondiale empêcherait la seconde boutique
        // d'enregistrer son Coca — alors que c'est le cas le plus banal qui soit.
        $chezA = $this->bouton('t-1', 'Coca chez A', 75);
        $chezB = $this->bouton('t-2', 'Coca chez B', 90);

        $this->assertNotNull($this->codes()->attach('t-1', CatalogRef::make('pos', $chezA->id), self::COCA));
        $this->assertNotNull($this->codes()->attach('t-2', CatalogRef::make('pos', $chezB->id), self::COCA));

        // Et chacun retrouve le SIEN, avec SON prix.
        $this->assertSame('Coca chez A', $this->codes()->find('t-1', self::COCA)->name);
        $this->assertSame('Coca chez B', $this->codes()->find('t-2', self::COCA)->name);
        $this->assertEquals(90.0, (float) $this->codes()->find('t-2', self::COCA)->price);
    }

    public function test_scanning_never_reaches_a_neighbours_article(): void
    {
        $chezVoisin = $this->bouton('t-2', 'Chez le voisin', 75);
        $this->codes()->attach('t-2', CatalogRef::make('pos', $chezVoisin->id), self::COCA);

        // Le commerce t-1 n'a pas ce code : la recherche ne doit rien rendre,
        // surtout pas l'article du voisin.
        $this->assertNull($this->codes()->find('t-1', self::COCA));
    }

    public function test_inside_one_shop_a_code_designates_one_article_only(): void
    {
        // Sinon scanner deviendrait un tirage au sort.
        $premier = $this->bouton('t-1', 'Premier', 75);
        $second  = $this->bouton('t-1', 'Second', 90);

        $this->assertNotNull($this->codes()->attach('t-1', CatalogRef::make('pos', $premier->id), self::COCA));
        $this->assertNull($this->codes()->attach('t-1', CatalogRef::make('pos', $second->id), self::COCA),
            'Le second doit être refusé, pas réaffecté en silence.');

        $this->assertSame('Premier', $this->codes()->find('t-1', self::COCA)->name);
    }

    public function test_attaching_the_same_code_to_the_same_article_twice_is_harmless(): void
    {
        // Le marchand rescanne la même bouteille : ce n'est pas une erreur.
        $coca = $this->bouton('t-1', 'Coca', 75);
        $ref = CatalogRef::make('pos', $coca->id);

        $a = $this->codes()->attach('t-1', $ref, self::COCA);
        $b = $this->codes()->attach('t-1', $ref, self::COCA);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, ProductCode::count());
    }

    public function test_a_misread_code_is_refused_rather_than_stored(): void
    {
        // Un chiffre de contrôle faux vient d'une lecture erronée. L'accepter
        // créerait un article que personne ne retrouvera jamais en scannant.
        $coca = $this->bouton('t-1', 'Coca', 75);

        $this->assertNull($this->codes()->attach('t-1', CatalogRef::make('pos', $coca->id), '5449000000997'));
        $this->assertSame(0, ProductCode::count());
    }

    public function test_an_article_may_carry_several_codes(): void
    {
        // Le carton de 24 et la bouteille à l'unité n'ont pas le même code.
        $coca = $this->bouton('t-1', 'Coca', 75);
        $ref = CatalogRef::make('pos', $coca->id);

        $this->codes()->attach('t-1', $ref, self::COCA, ['label' => 'À l\'unité']);
        $this->codes()->attach('t-1', $ref, '4006381333931', ['label' => 'Carton de 24']);

        $this->assertCount(2, $this->codes()->forArticle('t-1', $ref));
        // Les deux mènent au même article.
        $this->assertSame($coca->id, $this->codes()->find('t-1', self::COCA)->id);
        $this->assertSame($coca->id, $this->codes()->find('t-1', '4006381333931')->id);
    }

    public function test_the_first_code_becomes_the_main_one(): void
    {
        $coca = $this->bouton('t-1', 'Coca', 75);
        $ref = CatalogRef::make('pos', $coca->id);

        $premier = $this->codes()->attach('t-1', $ref, self::COCA);
        $second  = $this->codes()->attach('t-1', $ref, '4006381333931');

        $this->assertTrue($premier->is_primary);
        $this->assertFalse($second->is_primary);
    }

    /* ------------------------------------------------------------------
       Produits locaux sans code-barres — la majorité d'une boutique de
       quartier : pâté, fresco, sachet dlo, manje kwit.
       ------------------------------------------------------------------ */

    public function test_a_local_product_gets_a_code_it_can_print(): void
    {
        $pate = $this->bouton('t-1', 'Pâté bœuf', 50);
        $ref = CatalogRef::make('pos', $pate->id);

        $code = $this->codes()->generate('t-1', $ref);

        $this->assertNotNull($code);
        $this->assertTrue($code->isInternal());
        $this->assertSame('Pâté bœuf', $this->codes()->find('t-1', $code->code)->name);
    }

    public function test_a_generated_code_for_a_dish_never_points_at_a_till_button(): void
    {
        // Les deux catalogues numérotent à partir de 1. Sans décalage, le plat
        // n°1 et le bouton n°1 recevraient le même code imprimé.
        $bouton = $this->bouton('t-1', 'Sachet dlo', 25);
        $plat   = $this->plat('t-1', 'Griot', 350);

        $codeBouton = $this->codes()->generate('t-1', CatalogRef::make('pos', $bouton->id));
        $codePlat   = $this->codes()->generate('t-1', CatalogRef::make('menu', $plat->id));

        $this->assertNotSame($codeBouton->code, $codePlat->code);
        $this->assertSame('Sachet dlo', $this->codes()->find('t-1', $codeBouton->code)->name);
        $this->assertSame('Griot', $this->codes()->find('t-1', $codePlat->code)->name);
    }

    public function test_a_reprinted_label_still_works_if_the_code_was_lost(): void
    {
        // Étiquette rééditée, base restaurée : le code TAGTOA porte lui-même le
        // numéro de l'article, on peut donc retomber dessus.
        $pate = $this->bouton('t-1', 'Pâté bœuf', 50);
        $code = Barcode::internal($pate->id);

        $this->assertSame(0, ProductCode::count());
        $this->assertSame('Pâté bœuf', $this->codes()->find('t-1', $code)->name);
    }

    public function test_even_that_fallback_stays_inside_the_shop(): void
    {
        // Le repli ne doit pas devenir une porte dérobée vers le voisin.
        $chezVoisin = $this->bouton('t-2', 'Chez le voisin', 50);

        $this->assertNull($this->codes()->find('t-1', Barcode::internal($chezVoisin->id)));
    }

    public function test_an_empty_or_absurd_code_finds_nothing(): void
    {
        $this->bouton('t-1', 'Coca', 75);

        foreach (['', '  ', null, 'A', '!!!'] as $rien) {
            $this->assertNull($this->codes()->find('t-1', $rien));
        }
    }

    public function test_the_merchant_stays_in_charge_of_his_labels(): void
    {
        $coca = $this->bouton('t-1', 'Coca', 75);
        $code = $this->codes()->attach('t-1', CatalogRef::make('pos', $coca->id), self::COCA);

        // Et ne peut pas retirer celle du voisin.
        $this->assertFalse($this->codes()->detach('t-2', $code->id));
        $this->assertTrue($this->codes()->detach('t-1', $code->id));
        $this->assertNull($this->codes()->find('t-1', self::COCA));
    }
}
