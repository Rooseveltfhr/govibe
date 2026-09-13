<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Inventory\StockMovement;
use Modules\Tagtoa\App\Models\Inventory\Supplier;
use Modules\Tagtoa\App\Models\Pos\Product;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Services\Inventory\StockLedger;
use Modules\Tagtoa\App\Services\Inventory\StockReport;
use Modules\Tagtoa\App\Services\Pos\PosCatalog;
use Modules\Tagtoa\App\Support\Inventory\MovementType;
use Modules\Tagtoa\Tests\TestCase;

/**
 * L'écran de stock du patron : ce qu'il reste, ce qui a bougé, chez qui acheter.
 */
class InventoryScreenTest extends TestCase
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

    private function article(array $attrs = []): Product
    {
        return app(PosCatalog::class)->save($this->caisse(), array_merge([
            'name' => 'Coca', 'price' => 75, 'cost_price' => 60, 'stock' => 20, 'is_active' => true,
        ], $attrs));
    }

    /* ------------------------------------------------------------------
       Les trois écrans s'ouvrent.
       ------------------------------------------------------------------ */

    public function test_the_three_screens_open(): void
    {
        $this->patron();
        $this->article();

        $this->get(route('tagtoa.inventory.index'))->assertOk()->assertSee('Coca');
        $this->get(route('tagtoa.inventory.movements'))->assertOk();
        $this->get(route('tagtoa.inventory.suppliers'))->assertOk();
    }

    /* ------------------------------------------------------------------
       Ce que le patron vient chercher.
       ------------------------------------------------------------------ */

    public function test_the_reserve_is_valued_only_on_what_is_known(): void
    {
        // Annoncer une valeur totale sans dire qu'elle ne porte que sur la
        // moitié des articles donnerait un chiffre que le marchand croirait
        // complet.
        $this->patron();
        $this->article(['name' => 'Coca', 'cost_price' => 60, 'stock' => 10]);
        $this->article(['name' => 'Pâté', 'cost_price' => null, 'stock' => 30]);

        $bilan = app(StockReport::class)->summary('t-1');

        $this->assertSame(600.0, $bilan['value'], '10 × 60, et rien d\'inventé pour le pâté.');
        $this->assertSame(2, $bilan['articles']);
        $this->assertSame(1, $bilan['known'], 'Un seul article a un prix d\'achat renseigné.');
    }

    public function test_what_must_be_reordered_comes_first(): void
    {
        $this->patron();
        $this->article(['name' => 'Bien fourni', 'stock' => 200]);
        $this->article(['name' => 'Presque vide', 'stock' => 2]);

        $articles = app(StockReport::class)->articles('t-1');

        $this->assertSame('Presque vide', $articles->first()->name,
            'Un commerçant regarde d\'abord ce qu\'il doit racheter.');
    }

    public function test_only_low_stock_can_be_listed(): void
    {
        $this->patron();
        $this->article(['name' => 'Bien fourni', 'stock' => 200]);
        $this->article(['name' => 'Presque vide', 'stock' => 2]);

        $this->get(route('tagtoa.inventory.index', ['low' => 1]))
            ->assertOk()
            ->assertSee('Presque vide')
            ->assertDontSee('Bien fourni');
    }

    public function test_what_vanished_without_a_sale_is_counted_apart(): void
    {
        // Le chiffre qui fait réagir : la casse coûte de l'argent sans jamais
        // apparaître dans les ventes.
        $this->patron();
        $coca = $this->article(['cost_price' => 60, 'stock' => 40]);

        app(StockLedger::class)->remove($coca, 3, MovementType::LOSS, ['reason' => 'Cassé']);
        app(StockLedger::class)->remove($coca, 2, MovementType::SALE);

        $manque = app(StockReport::class)->shrinkage('t-1');

        $this->assertSame(3.0, $manque['qty'], 'La vente ne compte pas comme une disparition.');
        $this->assertSame(180.0, $manque['value'], '3 × 60.');
    }

    /* ------------------------------------------------------------------
       Enregistrer un mouvement depuis l'écran.
       ------------------------------------------------------------------ */

    public function test_the_owner_records_a_breakage(): void
    {
        $this->patron();
        $coca = $this->article(['stock' => 20]);

        $this->post(route('tagtoa.inventory.move'), [
            'ref' => 'pos:'.$coca->id, 'type' => MovementType::LOSS,
            'qty' => 3, 'reason' => 'Cassé au transport',
        ])->assertRedirect();

        $this->assertSame(17.0, $coca->fresh()->stock);

        $m = StockMovement::where('type', MovementType::LOSS)->firstOrFail();
        $this->assertSame(-3.0, $m->delta);
        $this->assertSame('Cassé au transport', $m->reason);
        $this->assertSame('Roosevelt', $m->actor_name);
    }

    public function test_a_delivery_also_updates_the_purchase_price(): void
    {
        // C'est l'occasion la plus naturelle de tenir le prix d'achat à jour,
        // et sans lui aucune marge ne peut être calculée.
        $this->patron();
        $coca = $this->article(['cost_price' => 60, 'stock' => 20]);
        $depot = Supplier::create(['tenant_id' => 't-1', 'name' => 'Dépôt Bon Prix', 'is_active' => true]);

        $this->post(route('tagtoa.inventory.move'), [
            'ref' => 'pos:'.$coca->id, 'type' => MovementType::PURCHASE,
            'qty' => 24, 'unit_cost' => 68, 'supplier_id' => $depot->id,
        ])->assertRedirect();

        $frais = $coca->fresh();
        $this->assertSame(44.0, $frais->stock);
        $this->assertEquals(68.0, (float) $frais->cost_price, 'Le prix du jour remplace l\'ancien.');

        $m = StockMovement::where('type', MovementType::PURCHASE)->firstOrFail();
        $this->assertSame($depot->id, (int) $m->supplier_id);
    }

    public function test_a_physical_count_only_asks_what_was_counted(): void
    {
        $this->patron();
        $coca = $this->article(['stock' => 20]);

        // Le patron tape 17 — ce qu'il a compté — pas « -3 ».
        $this->post(route('tagtoa.inventory.move'), [
            'ref' => 'pos:'.$coca->id, 'type' => MovementType::COUNT,
            'qty' => 17, 'reason' => 'Inventaire du samedi',
        ])->assertRedirect();

        $this->assertSame(17.0, $coca->fresh()->stock);
        $this->assertSame(-3.0, StockMovement::where('type', MovementType::COUNT)->firstOrFail()->delta);
    }

    public function test_the_owner_cannot_forge_a_sale_by_hand(): void
    {
        // Fabriquer une vente ferait disparaître du stock sans qu'aucun argent
        // n'ait été encaissé — et sans que la caisse en sache rien.
        $this->patron();
        $coca = $this->article(['stock' => 20]);

        $this->post(route('tagtoa.inventory.move'), [
            'ref' => 'pos:'.$coca->id, 'type' => MovementType::SALE, 'qty' => 5,
        ])->assertSessionHasErrors('type');

        $this->assertSame(20.0, $coca->fresh()->stock);
    }

    public function test_an_invented_reason_is_refused(): void
    {
        $this->patron();
        $coca = $this->article(['stock' => 20]);

        $this->post(route('tagtoa.inventory.move'), [
            'ref' => 'pos:'.$coca->id, 'type' => 'disparu', 'qty' => 5,
        ])->assertSessionHasErrors('type');

        $this->assertSame(20.0, $coca->fresh()->stock);
    }

    public function test_a_movement_of_zero_is_refused(): void
    {
        $this->patron();
        $coca = $this->article(['stock' => 20]);

        $this->post(route('tagtoa.inventory.move'), [
            'ref' => 'pos:'.$coca->id, 'type' => MovementType::LOSS, 'qty' => 0,
        ])->assertSessionHasErrors('qty');

        $this->assertSame(20.0, $coca->fresh()->stock);
    }

    /* ------------------------------------------------------------------
       Cloisonnement — la partie qu'on ne peut pas se permettre de rater.
       ------------------------------------------------------------------ */

    public function test_a_movement_never_reaches_the_neighbour_stock(): void
    {
        // Un identifiant deviné ne doit pas permettre de décrémenter le stock
        // du commerce d'à côté.
        $this->patron('t-1');
        $chezMoi = $this->article(['name' => 'Coca', 'stock' => 20]);

        $this->patron('t-2');
        $this->caisse('t-2');

        $this->post(route('tagtoa.inventory.move'), [
            'ref' => 'pos:'.$chezMoi->id, 'type' => MovementType::LOSS, 'qty' => 5,
        ])->assertNotFound();

        $this->assertSame(20.0, Product::withoutGlobalScopes()->find($chezMoi->id)->stock);
    }

    public function test_the_neighbour_journal_stays_invisible(): void
    {
        $this->patron('t-1');
        app(StockLedger::class)->remove($this->article(['stock' => 20]), 3, MovementType::LOSS, ['reason' => 'Secret']);

        $this->patron('t-2');
        $this->get(route('tagtoa.inventory.movements'))->assertOk()->assertDontSee('Secret');
    }

    public function test_a_supplier_from_another_shop_is_never_attached(): void
    {
        $this->patron('t-2');
        $chezLeVoisin = Supplier::create(['tenant_id' => 't-2', 'name' => 'Dépôt du voisin', 'is_active' => true]);

        $this->patron('t-1');
        $coca = $this->article(['stock' => 20]);

        $this->post(route('tagtoa.inventory.move'), [
            'ref' => 'pos:'.$coca->id, 'type' => MovementType::PURCHASE,
            'qty' => 10, 'supplier_id' => $chezLeVoisin->id,
        ])->assertRedirect();

        $this->assertNull(StockMovement::where('type', MovementType::PURCHASE)->firstOrFail()->supplier_id,
            'Le fournisseur du voisin ne doit jamais se retrouver sur mon mouvement.');
    }

    public function test_the_neighbour_cannot_edit_my_supplier(): void
    {
        $this->patron('t-1');
        $depot = Supplier::create(['tenant_id' => 't-1', 'name' => 'Dépôt Bon Prix', 'is_active' => true]);

        $this->patron('t-2');

        $this->put(route('tagtoa.inventory.suppliers.update', $depot->id), ['name' => 'Détourné'])
            ->assertNotFound();

        $this->assertSame('Dépôt Bon Prix', Supplier::withoutGlobalScopes()->find($depot->id)->name);
    }

    /* ------------------------------------------------------------------
       Les fournisseurs.
       ------------------------------------------------------------------ */

    public function test_a_supplier_is_archived_never_deleted(): void
    {
        // L'historique des réceptions continue de le désigner : le faire
        // disparaître laisserait des mouvements dont on ignore l'origine.
        $this->patron();
        $depot = Supplier::create(['tenant_id' => 't-1', 'name' => 'Dépôt Bon Prix', 'is_active' => true]);

        $this->post(route('tagtoa.inventory.suppliers.toggle', $depot->id))->assertRedirect();

        $this->assertFalse($depot->fresh()->is_active);
        $this->assertSame(1, Supplier::count(), 'La fiche reste, seulement archivée.');
    }

    public function test_a_supplier_needs_a_name(): void
    {
        $this->patron();

        $this->post(route('tagtoa.inventory.suppliers.store'), ['phone' => '3000-0000'])
            ->assertSessionHasErrors('name');

        $this->assertSame(0, Supplier::count());
    }
}
