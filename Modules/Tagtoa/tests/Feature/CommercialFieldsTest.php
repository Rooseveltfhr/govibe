<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Pos\Product;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Services\Pos\PosCatalog;
use Modules\Tagtoa\Tests\TestCase;

/**
 * Prix d'achat, unité et seuil — des deux côtés du catalogue.
 *
 * La caisse vend le menu ET ses propres boutons : un plat a un coût matière
 * comme un article a un prix d'achat. Les colonnes existent donc des deux
 * côtés, mais la logique n'est écrite qu'une fois.
 */
class CommercialFieldsTest extends TestCase
{
    use RefreshDatabase;

    private function bouton(array $attrs = []): Product
    {
        $caisse = Terminal::firstOrCreate(
            ['tenant_id' => 't-1', 'name' => 'Caisse'],
            ['currency' => 'HTG', 'is_active' => true]
        );

        return app(PosCatalog::class)->save($caisse, array_merge([
            'name' => 'Coca', 'price' => 75, 'is_active' => true,
        ], $attrs));
    }

    private function plat(array $attrs = []): Item
    {
        $menu = Menu::firstOrCreate(['tenant_id' => 't-1', 'alias' => 'carte'],
            ['name' => 'Carte', 'currency' => 'HTG', 'is_active' => true]);
        $cat = Category::firstOrCreate(['menu_id' => $menu->id, 'name' => 'Plats'], ['is_active' => true]);

        return Item::create(array_merge([
            'menu_id' => $menu->id, 'category_id' => $cat->id,
            'name' => 'Griot', 'price' => 350, 'is_available' => true,
        ], $attrs));
    }

    public function test_both_catalogues_compute_their_margin_the_same_way(): void
    {
        $bouton = $this->bouton(['price' => 75, 'cost_price' => 60]);
        $plat   = $this->plat(['price' => 350, 'cost_price' => 210]);

        $this->assertSame(15.0, $bouton->margin);
        $this->assertSame(20.0, $bouton->margin_percent);

        $this->assertSame(140.0, $plat->margin);
        $this->assertSame(40.0, $plat->margin_percent);
    }

    public function test_an_article_without_a_cost_price_says_so_rather_than_zero(): void
    {
        // « 0 » laisserait croire que le commerce ne gagne rien.
        $bouton = $this->bouton(['price' => 75]);

        $this->assertNull($bouton->cost_price);
        $this->assertNull($bouton->margin);
        $this->assertNull($bouton->margin_percent);
        $this->assertFalse($bouton->isSoldAtLoss());
    }

    public function test_selling_below_cost_is_flagged(): void
    {
        $solde = $this->bouton(['price' => 50, 'cost_price' => 60]);

        $this->assertTrue($solde->isSoldAtLoss());
        $this->assertSame(-10.0, $solde->margin);
    }

    public function test_rice_is_sold_by_the_mamit_and_survives_the_database(): void
    {
        $riz = $this->bouton(['name' => 'Diri', 'price' => 150, 'unit' => 'mamit']);

        $frais = Product::find($riz->id);
        $this->assertSame('mamit', $frais->unit_key);
        $this->assertSame('Mamit', $frais->unit_label);
        $this->assertTrue($frais->allowsDecimalQty());
    }

    public function test_an_unknown_unit_falls_back_instead_of_breaking_the_screen(): void
    {
        $bizarre = $this->bouton(['unit' => 'galon-brouette']);

        $this->assertSame('piece', $bizarre->fresh()->unit_key);
        $this->assertFalse($bizarre->fresh()->allowsDecimalQty());
    }

    public function test_the_price_is_shown_with_its_unit_only_when_useful(): void
    {
        $riz  = $this->bouton(['name' => 'Diri', 'price' => 150, 'unit' => 'mamit']);
        $coca = $this->bouton(['name' => 'Coca', 'price' => 75, 'unit' => 'piece']);

        $this->assertStringContainsString('/ Mamit', $riz->priceWithUnit('HTG'));
        // Personne ne dit « 75 / pièce ».
        $this->assertStringNotContainsString('/', $coca->priceWithUnit('HTG'));
    }

    public function test_a_shop_that_set_no_threshold_is_still_warned(): void
    {
        $presqueVide = $this->bouton(['stock' => 3]);
        $bienFourni  = $this->bouton(['name' => 'Autre', 'stock' => 40]);

        $this->assertTrue($presqueVide->isLowStock());
        $this->assertFalse($bienFourni->isLowStock());
    }

    public function test_a_bakery_sets_its_own_threshold(): void
    {
        // Un commerce qui vend 200 pains par jour n'alerte pas à 5.
        $pain = $this->bouton(['name' => 'Pain', 'stock' => 40, 'low_stock_threshold' => 50]);

        $this->assertTrue($pain->isLowStock());
        $this->assertSame(50.0, $pain->fresh()->low_stock_threshold);
    }

    public function test_an_untracked_stock_never_raises_an_alert(): void
    {
        // Un plat préparé à la commande : rien à compter.
        $this->assertFalse($this->plat(['stock' => null])->isLowStock());
    }

    public function test_the_internal_reference_is_kept(): void
    {
        $article = $this->bouton(['sku' => 'BOI-COCA-500']);

        $this->assertSame('BOI-COCA-500', Product::find($article->id)->sku);
    }

    public function test_saving_through_the_catalogue_keeps_the_commercial_fields(): void
    {
        // Le chemin réel : le formulaire passe par PosCatalog::save().
        $article = $this->bouton([
            'name' => 'Diri', 'price' => 150, 'cost_price' => 110,
            'unit' => 'mamit', 'low_stock_threshold' => 12, 'sku' => 'GRA-DIRI',
        ]);

        $frais = Product::find($article->id);
        $this->assertEquals(110.0, (float) $frais->cost_price);
        $this->assertSame('mamit', $frais->unit_key);
        $this->assertSame(12.0, $frais->low_stock_threshold);
        $this->assertSame('GRA-DIRI', $frais->sku);
        $this->assertSame(40.0, $frais->margin);
    }

    /* ------------------------------------------------------------------
       Le bénéfice : ce que beaucoup de commerces ne savent pas calculer.
       ------------------------------------------------------------------ */

    public function test_the_owner_finally_sees_what_he_actually_earned(): void
    {
        $caisse = Terminal::firstOrCreate(['tenant_id' => 't-1', 'name' => 'Caisse'],
            ['currency' => 'HTG', 'is_active' => true]);
        $coca = $this->bouton(['name' => 'Coca', 'price' => 75, 'cost_price' => 60]);

        app(\Modules\Tagtoa\App\Services\Pos\PosService::class)->recordSale($caisse, [
            'items' => [['ref' => 'pos:'.$coca->id, 'qty' => 10]],
        ]);

        $bilan = app(\Modules\Tagtoa\App\Services\Pos\PosSales::class)
            ->profit(app(\Modules\Tagtoa\App\Services\Pos\PosSales::class)->forOwner('t-1'));

        $this->assertEquals(750.0, $bilan['sales']);
        $this->assertEquals(600.0, $bilan['cost']);
        $this->assertEquals(150.0, $bilan['profit']);
        $this->assertEquals(20.0, $bilan['margin']);
        $this->assertEquals(100.0, $bilan['known']);
    }

    public function test_changing_the_purchase_price_never_rewrites_last_months_profit(): void
    {
        // Le point capital. Le marchand change de fournisseur, l'inflation
        // passe : le bénéfice déjà réalisé ne doit pas bouger tout seul.
        $caisse = Terminal::firstOrCreate(['tenant_id' => 't-1', 'name' => 'Caisse'],
            ['currency' => 'HTG', 'is_active' => true]);
        $coca = $this->bouton(['name' => 'Coca', 'price' => 75, 'cost_price' => 60]);

        app(\Modules\Tagtoa\App\Services\Pos\PosService::class)->recordSale($caisse, [
            'items' => [['ref' => 'pos:'.$coca->id, 'qty' => 10]],
        ]);

        // Le prix d'achat monte APRÈS la vente.
        $coca->update(['cost_price' => 70]);

        $bilan = app(\Modules\Tagtoa\App\Services\Pos\PosSales::class)
            ->profit(app(\Modules\Tagtoa\App\Services\Pos\PosSales::class)->forOwner('t-1'));

        $this->assertEquals(600.0, $bilan['cost'], 'Le coût figé le jour de la vente doit tenir.');
        $this->assertEquals(150.0, $bilan['profit']);
    }

    public function test_a_profit_computed_on_half_the_sales_says_so(): void
    {
        // Un bénéfice calculé sur 30 % des ventes n'est pas un bénéfice : le
        // marchand doit le voir plutôt que de croire un chiffre creux.
        $caisse = Terminal::firstOrCreate(['tenant_id' => 't-1', 'name' => 'Caisse'],
            ['currency' => 'HTG', 'is_active' => true]);
        $connu   = $this->bouton(['name' => 'Coca', 'price' => 100, 'cost_price' => 60]);
        $inconnu = $this->bouton(['name' => 'Pâté', 'price' => 100]);

        $svc = app(\Modules\Tagtoa\App\Services\Pos\PosService::class);
        $svc->recordSale($caisse, ['items' => [['ref' => 'pos:'.$connu->id, 'qty' => 1]]]);
        $svc->recordSale($caisse, ['items' => [['ref' => 'pos:'.$inconnu->id, 'qty' => 1]]]);

        $bilan = app(\Modules\Tagtoa\App\Services\Pos\PosSales::class)
            ->profit(app(\Modules\Tagtoa\App\Services\Pos\PosSales::class)->forOwner('t-1'));

        $this->assertEquals(200.0, $bilan['sales']);
        $this->assertEquals(50.0, $bilan['known'], 'La moitié du chiffre seulement a un coût connu.');
        // Le bénéfice ne porte que sur la part connue, pas sur tout.
        $this->assertEquals(40.0, $bilan['profit']);
    }

    public function test_with_no_cost_anywhere_nothing_is_invented(): void
    {
        $caisse = Terminal::firstOrCreate(['tenant_id' => 't-1', 'name' => 'Caisse'],
            ['currency' => 'HTG', 'is_active' => true]);
        $pate = $this->bouton(['name' => 'Pâté', 'price' => 50]);

        app(\Modules\Tagtoa\App\Services\Pos\PosService::class)->recordSale($caisse, [
            'items' => [['ref' => 'pos:'.$pate->id, 'qty' => 4]],
        ]);

        $bilan = app(\Modules\Tagtoa\App\Services\Pos\PosSales::class)
            ->profit(app(\Modules\Tagtoa\App\Services\Pos\PosSales::class)->forOwner('t-1'));

        $this->assertEquals(200.0, $bilan['sales']);
        $this->assertEquals(0.0, $bilan['known'], 'Aucun coût connu : le marchand doit le savoir.');
        $this->assertNull($bilan['margin'], 'Pas de marge inventée à partir de rien.');
    }

    /* ------------------------------------------------------------------
       Vendre au poids et à la mesure — le cœur du commerce de quartier.
       ------------------------------------------------------------------ */

    private function caisse(): Terminal
    {
        return Terminal::firstOrCreate(['tenant_id' => 't-1', 'name' => 'Caisse'],
            ['currency' => 'HTG', 'is_active' => true]);
    }

    public function test_two_and_a_half_pounds_of_rice_are_charged_as_two_and_a_half(): void
    {
        // LE test de cette phase. Tant que la quantité était un entier, le
        // client emportait 2,5 livres et n'en payait que 2 : le commerce
        // perdait une demi-livre à chaque vente, sans jamais le voir.
        $riz = $this->bouton(['name' => 'Riz', 'price' => 120, 'unit' => 'lb', 'stock' => 50]);

        $vente = app(\Modules\Tagtoa\App\Services\Pos\PosService::class)
            ->recordSale($this->caisse(), ['items' => [['ref' => 'pos:'.$riz->id, 'qty' => 2.5]]]);

        $ligne = $vente->items()->first();

        $this->assertSame(2.5, (float) $ligne->qty, 'La quantité vendue doit rester 2,5.');
        $this->assertEquals(300.0, (float) $ligne->line_total, '2,5 × 120 = 300, pas 240.');
        $this->assertEquals(300.0, (float) $vente->total);
        $this->assertSame(47.5, $riz->fresh()->stock, 'Le stock doit descendre de 2,5 exactement.');
    }

    public function test_a_bottle_is_never_sold_in_halves(): void
    {
        // L'inverse est tout aussi important : une unité indivisible ne se
        // vend pas par 2,5. On arrondit vers le HAUT — le client emporte
        // 3 bouteilles et en paie 3, jamais 3 pour le prix de 2.
        $coca = $this->bouton(['name' => 'Coca', 'price' => 75, 'unit' => 'piece', 'stock' => 20]);

        $vente = app(\Modules\Tagtoa\App\Services\Pos\PosService::class)
            ->recordSale($this->caisse(), ['items' => [['ref' => 'pos:'.$coca->id, 'qty' => 2.5]]]);

        $this->assertSame(3.0, (float) $vente->items()->first()->qty);
        $this->assertEquals(225.0, (float) $vente->total);
        $this->assertSame(17.0, $coca->fresh()->stock);
    }

    public function test_a_dish_from_the_menu_is_also_sold_by_weight(): void
    {
        // Un plat au menu suit la même règle : un seul catalogue, une seule
        // arithmétique (B-3).
        $griot = $this->plat(['name' => 'Griot', 'price' => 200, 'unit' => 'lb', 'stock' => 10]);

        $vente = app(\Modules\Tagtoa\App\Services\Pos\PosService::class)
            ->recordSale($this->caisse(), ['items' => [['ref' => 'menu:'.$griot->id, 'qty' => 1.5]]]);

        $this->assertEquals(300.0, (float) $vente->total);
        $this->assertSame(8.5, $griot->fresh()->stock);
    }

    public function test_a_line_at_zero_is_not_a_sale(): void
    {
        // Avant, une quantité nulle ou négative était ramenée à 1 : la caisse
        // facturait un article que personne n'avait demandé.
        $coca = $this->bouton(['name' => 'Coca', 'price' => 75, 'stock' => 20]);

        $vente = app(\Modules\Tagtoa\App\Services\Pos\PosService::class)
            ->recordSale($this->caisse(), ['items' => [
                ['ref' => 'pos:'.$coca->id, 'qty' => 0],
                ['ref' => 'pos:'.$coca->id, 'qty' => -3],
            ]]);

        $this->assertSame(0, $vente->items()->count(), 'Aucune ligne ne doit être encaissée.');
        $this->assertEquals(0.0, (float) $vente->total);
        $this->assertSame(20.0, $coca->fresh()->stock, 'Le stock ne doit pas bouger.');
    }

    public function test_the_profit_on_a_weighed_sale_is_exact(): void
    {
        // Le bénéfice doit suivre la même quantité décimale, sinon le coût est
        // faux d'une demi-livre à chaque ligne.
        $riz = $this->bouton(['name' => 'Riz', 'price' => 120, 'cost_price' => 90, 'unit' => 'lb', 'stock' => 50]);

        app(\Modules\Tagtoa\App\Services\Pos\PosService::class)
            ->recordSale($this->caisse(), ['items' => [['ref' => 'pos:'.$riz->id, 'qty' => 2.5]]]);

        $ventes = app(\Modules\Tagtoa\App\Services\Pos\PosSales::class);
        $bilan  = $ventes->profit($ventes->forOwner('t-1'));

        $this->assertEquals(300.0, $bilan['sales']);
        $this->assertEquals(225.0, $bilan['cost'], '2,5 × 90 = 225.');
        $this->assertEquals(75.0, $bilan['profit']);
    }

    public function test_a_low_stock_alert_follows_the_article_threshold(): void
    {
        // Une seule règle dans tout TAGTOA : celle de StockService.
        $pain = $this->plat(['name' => 'Pain', 'price' => 25, 'stock' => 40, 'low_stock_threshold' => 50]);
        $rare = $this->plat(['name' => 'Homard', 'price' => 2000, 'stock' => 40]);

        $this->assertTrue($pain->isLowStock(), 'La boulangerie alerte à son propre seuil.');
        $this->assertFalse($rare->isLowStock(), 'Sans seuil, le plancher commun suffit.');

        // La même règle, cette fois côté base de données.
        $this->assertSame(1, Item::lowStock()->where('name', 'Pain')->count());
        $this->assertSame(0, Item::lowStock()->where('name', 'Homard')->count());
    }

    /* ------------------------------------------------------------------
       Le formulaire du patron — saisie et garde-fous.
       ------------------------------------------------------------------ */

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new \Illuminate\Auth\GenericUser(['id' => 1, 'tenant_id' => $tenantId]));
    }

    public function test_the_owner_can_finally_enter_his_purchase_price(): void
    {
        // Sans écran de saisie, les colonnes ne servent à rien : la phase ne
        // serait livrée qu'à moitié.
        $this->patron();
        $caisse = $this->caisse();

        $this->post(route('tagtoa.pos.products.save', $caisse->id), [
            'products' => [[
                'name' => 'Riz', 'price' => 120, 'cost_price' => 90,
                'unit' => 'lb', 'stock' => 12.5, 'low_stock_threshold' => 3.5,
                'sku' => 'RIZ-01', 'is_active' => 1, 'color' => '#2cb809',
            ]],
        ])->assertRedirect();

        $riz = Product::where('name', 'Riz')->firstOrFail();

        $this->assertEquals(90.0, (float) $riz->cost_price);
        $this->assertSame('lb', $riz->unit);
        $this->assertSame(12.5, $riz->stock, 'Le stock saisi au demi ne doit pas être tronqué.');
        $this->assertSame(3.5, $riz->low_stock_threshold);
        $this->assertSame('RIZ-01', $riz->sku);
        $this->assertSame(30.0, $riz->margin);
    }

    public function test_an_invented_unit_is_refused(): void
    {
        // « kilo-mamit » n'existe pas : l'accepter ferait calculer la caisse
        // avec une unité que personne ne sait interpréter.
        $this->patron();

        $this->post(route('tagtoa.pos.products.save', $this->caisse()->id), [
            'products' => [['name' => 'Riz', 'price' => 120, 'unit' => 'kilo-mamit']],
        ])->assertSessionHasErrors('products.0.unit');

        $this->assertSame(0, Product::where('name', 'Riz')->count());
    }

    public function test_a_negative_price_is_refused(): void
    {
        // Un prix négatif rendrait de l'argent à chaque vente.
        $this->patron();

        $this->post(route('tagtoa.pos.products.save', $this->caisse()->id), [
            'products' => [['name' => 'Coca', 'price' => -75]],
        ])->assertSessionHasErrors('products.0.price');

        $this->assertSame(0, Product::where('name', 'Coca')->count());
    }

    public function test_an_empty_purchase_price_stays_unknown(): void
    {
        // « Non renseigné » n'est pas « gratuit » : enregistrer 0 ferait croire
        // au marchand que sa marge est totale.
        $this->patron();

        $this->post(route('tagtoa.pos.products.save', $this->caisse()->id), [
            'products' => [['name' => 'Pâté', 'price' => 50, 'cost_price' => '', 'stock' => '']],
        ])->assertRedirect();

        $pate = Product::where('name', 'Pâté')->firstOrFail();

        $this->assertNull($pate->cost_price);
        $this->assertNull($pate->stock, 'Un stock vide reste non suivi, pas zéro.');
        $this->assertNull($pate->margin);
    }

    /* ------------------------------------------------------------------
       Le formulaire du menu — même volet commercial, mêmes garde-fous.
       ------------------------------------------------------------------ */

    /** Le menu du commerce, tel que le formulaire le renvoie. */
    private function envoiMenu(array $itemAttrs): \Illuminate\Testing\TestResponse
    {
        $menu = Menu::firstOrCreate(['tenant_id' => 't-1', 'alias' => 'carte'],
            ['name' => 'Carte', 'currency' => 'HTG', 'is_active' => true]);

        return $this->put(route('tagtoa.menu.dashboard.update', $menu->id), [
            'name'     => 'Carte',
            'currency' => 'HTG',
            'cats'     => [[
                'name'  => 'Plats',
                'items' => [$itemAttrs],
            ]],
        ]);
    }

    public function test_the_restaurant_can_enter_its_food_cost(): void
    {
        // Un plat a un coût matière comme un article a un prix d'achat : sans
        // lui, un restaurant ne sait pas quel plat le fait vivre.
        $this->patron();

        $this->envoiMenu([
            'name' => 'Griot', 'price' => 350, 'cost_price' => 210,
            'unit' => 'lb', 'stock' => 6.5, 'low_stock_threshold' => 2, 'sku' => 'GRIOT',
        ])->assertRedirect();

        $griot = Item::where('name', 'Griot')->firstOrFail();

        $this->assertEquals(210.0, (float) $griot->cost_price);
        $this->assertSame('lb', $griot->unit);
        $this->assertSame(6.5, $griot->stock, 'Le stock au demi ne doit pas être tronqué.');
        $this->assertSame(140.0, $griot->margin);
        $this->assertSame(40.0, $griot->margin_percent);
    }

    public function test_the_menu_form_refuses_an_invented_unit(): void
    {
        $this->patron();

        $this->envoiMenu(['name' => 'Griot', 'price' => 350, 'unit' => 'kilo-mamit'])
            ->assertSessionHasErrors('cats.0.items.0.unit');

        $this->assertSame(0, Item::where('name', 'Griot')->count());
    }

    public function test_the_menu_form_refuses_a_negative_price(): void
    {
        $this->patron();

        $this->envoiMenu(['name' => 'Griot', 'price' => -350])
            ->assertSessionHasErrors('cats.0.items.0.price');

        $this->assertSame(0, Item::where('name', 'Griot')->count());
    }

    public function test_an_edit_without_an_alias_no_longer_crashes(): void
    {
        // Régression : une clé absente de l'envoi rendait une page blanche au
        // marchand, au lieu de laisser le menu tel qu'il est.
        $this->patron();

        $this->envoiMenu(['name' => 'Griot', 'price' => 350])->assertRedirect();

        $this->assertSame('carte', Menu::where('tenant_id', 't-1')->value('alias'));
    }
}
