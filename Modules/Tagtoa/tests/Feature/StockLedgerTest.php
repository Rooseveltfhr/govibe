<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Inventory\StockMovement;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Pos\Product;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Models\Staff\Staff;
use Modules\Tagtoa\App\Services\Inventory\StockLedger;
use Modules\Tagtoa\App\Services\Pos\PosCatalog;
use Modules\Tagtoa\App\Services\Pos\PosService;
use Modules\Tagtoa\App\Support\Inventory\MovementType;
use Modules\Tagtoa\Tests\TestCase;

/**
 * Le journal du stock : qui, quel article, avant, après, pourquoi, quand.
 *
 * Le stock était un nombre qu'on écrasait. Quand il ne correspondait plus à
 * l'étagère — et cela arrive tous les mois — personne ne pouvait remonter le
 * fil. C'est la question que ces tests vérifient qu'on sait enfin poser.
 */
class StockLedgerTest extends TestCase
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
            'name' => 'Coca', 'price' => 75, 'stock' => 20, 'is_active' => true,
        ], $attrs));
    }

    private function ledger(): StockLedger
    {
        return app(StockLedger::class);
    }

    /* ------------------------------------------------------------------
       Ce que le journal garde.
       ------------------------------------------------------------------ */

    public function test_a_movement_keeps_the_stock_before_and_after(): void
    {
        // Pas seulement l'écart : c'est le « avant » qui permet de retrouver le
        // point exact où le compte a divergé.
        $this->patron();
        $coca = $this->article(['stock' => 20]);

        $m = $this->ledger()->remove($coca, 3, MovementType::LOSS, ['reason' => 'Cassé au transport']);

        $this->assertSame(20.0, $m->stock_before);
        $this->assertSame(17.0, $m->stock_after);
        $this->assertSame(-3.0, $m->delta);
        $this->assertSame('Cassé au transport', $m->reason);
        $this->assertSame(17.0, $coca->fresh()->stock);
    }

    public function test_a_movement_names_who_did_it(): void
    {
        $this->patron();
        $coca = $this->article();

        $sansEmploye = $this->ledger()->remove($coca, 1, MovementType::LOSS);
        $this->assertSame('Roosevelt', $sansEmploye->actor_name, 'Sans employé, c\'est le patron.');

        $staff = Staff::create(['tenant_id' => 't-1', 'name' => 'Jacqueline', 'pin_hash' => 'x', 'is_active' => true]);
        $avecEmploye = $this->ledger()->remove($coca, 1, MovementType::LOSS, ['staff' => $staff]);

        $this->assertSame($staff->id, $avecEmploye->staff_id);
        $this->assertSame('Jacqueline', $avecEmploye->actor_name);
    }

    public function test_the_name_is_frozen_so_the_history_stays_readable(): void
    {
        // L'article peut être renommé ou supprimé : l'historique doit rester
        // lisible sans lui.
        $this->patron();
        $coca = $this->article(['name' => 'Coca 8oz']);

        $this->ledger()->remove($coca, 2, MovementType::LOSS);
        $coca->update(['name' => 'Coca petite']);

        $this->assertSame('Coca 8oz', StockMovement::first()->product_name);
    }

    public function test_an_untracked_article_is_never_given_a_counter(): void
    {
        // Un service, un plat préparé à la commande : rien à compter. Lui
        // inventer un compteur le rendrait « épuisé » du jour au lendemain.
        $this->patron();
        $pate = $this->article(['name' => 'Pâté', 'stock' => null]);

        $this->assertNull($this->ledger()->remove($pate, 5, MovementType::SALE));
        $this->assertNull($pate->fresh()->stock);
        $this->assertSame(0, StockMovement::count());
    }

    /* ------------------------------------------------------------------
       Le comptage physique.
       ------------------------------------------------------------------ */

    public function test_a_physical_count_computes_the_gap_itself(): void
    {
        // On ne demande PAS l'écart au patron : c'est justement le chiffre
        // qu'il ne connaît pas et qu'il vient chercher.
        $this->patron();
        $coca = $this->article(['stock' => 20]);

        $m = $this->ledger()->count($coca, 17, ['reason' => 'Inventaire du samedi']);

        $this->assertSame(MovementType::COUNT, $m->type);
        $this->assertSame(-3.0, $m->delta, 'Trois bouteilles manquent à l\'appel.');
        $this->assertSame(17.0, $coca->fresh()->stock);
    }

    public function test_a_count_that_matches_writes_nothing(): void
    {
        $this->patron();
        $coca = $this->article(['stock' => 20]);
        StockMovement::query()->delete();

        $this->assertNull($this->ledger()->count($coca, 20));
        $this->assertSame(0, StockMovement::count(), 'L\'étagère et l\'écran sont d\'accord : rien à raconter.');
    }

    public function test_counting_an_untracked_article_opens_its_tracking(): void
    {
        $this->patron();
        $pate = $this->article(['name' => 'Pâté', 'stock' => null]);

        $m = $this->ledger()->count($pate, 12);

        $this->assertSame(MovementType::OPENING, $m->type);
        $this->assertNull($m->stock_before, 'Il n\'y avait pas d\'« avant » : ne pas mentir avec 0.');
        $this->assertSame(12.0, $m->stock_after);
        $this->assertSame(12.0, $pate->fresh()->stock);
    }

    /* ------------------------------------------------------------------
       Le journal suit la vraie vie du commerce.
       ------------------------------------------------------------------ */

    public function test_a_sale_at_the_counter_leaves_its_trace(): void
    {
        $this->patron();
        $coca = $this->article(['stock' => 20]);
        StockMovement::query()->delete();

        $vente = app(PosService::class)->recordSale($this->caisse(), [
            'items' => [['ref' => 'pos:'.$coca->id, 'qty' => 4]],
        ]);

        $m = StockMovement::firstOrFail();

        $this->assertSame(MovementType::SALE, $m->type);
        $this->assertSame(-4.0, $m->delta);
        $this->assertSame('pos_sale', $m->origin_type);
        $this->assertSame($vente->id, (int) $m->origin_id,
            'On doit pouvoir remonter du stock manquant à la vente qui l\'explique.');
    }

    public function test_a_weighed_sale_is_traced_to_the_gram(): void
    {
        $this->patron();
        $riz = $this->article(['name' => 'Riz', 'price' => 120, 'unit' => 'lb', 'stock' => 50]);
        StockMovement::query()->delete();

        app(PosService::class)->recordSale($this->caisse(), [
            'items' => [['ref' => 'pos:'.$riz->id, 'qty' => 2.5]],
        ]);

        $this->assertSame(-2.5, StockMovement::firstOrFail()->delta);
        $this->assertSame(47.5, $riz->fresh()->stock);
    }

    public function test_entering_a_stock_in_the_catalogue_is_journalled_too(): void
    {
        // C'est la correction la plus fréquente, et c'était le trou le plus
        // grand : elle ne laissait aucune trace.
        $this->patron();
        $coca = $this->article(['stock' => 20]);
        StockMovement::query()->delete();

        app(PosCatalog::class)->save($this->caisse(),
            ['name' => 'Coca', 'price' => 75, 'stock' => 14, 'is_active' => true], $coca->id);

        $m = StockMovement::firstOrFail();

        $this->assertSame(MovementType::COUNT, $m->type);
        $this->assertSame(-6.0, $m->delta);
        $this->assertSame(20.0, $m->stock_before);
        $this->assertSame(14.0, $m->stock_after);
    }

    public function test_a_new_article_records_its_opening_stock(): void
    {
        $this->patron();

        $coca = $this->article(['name' => 'Prestige', 'stock' => 48]);

        $m = StockMovement::where('product_name', 'Prestige')->firstOrFail();

        $this->assertSame(MovementType::OPENING, $m->type);
        $this->assertSame(48.0, $m->stock_after);
        $this->assertNull($m->stock_before);
    }

    public function test_turning_tracking_off_is_a_decision_not_a_movement(): void
    {
        $this->patron();
        $coca = $this->article(['stock' => 20]);
        StockMovement::query()->delete();

        app(PosCatalog::class)->save($this->caisse(),
            ['name' => 'Coca', 'price' => 75, 'stock' => null, 'is_active' => true], $coca->id);

        $this->assertNull($coca->fresh()->stock);
        $this->assertSame(0, StockMovement::count(), 'Il n\'y a plus rien à compter : rien à journaliser.');
    }

    /* ------------------------------------------------------------------
       Le menu partage le même journal (un seul catalogue depuis B-3).
       ------------------------------------------------------------------ */

    public function test_a_dish_from_the_menu_is_journalled_under_its_own_source(): void
    {
        $this->patron();
        $menu = Menu::create(['tenant_id' => 't-1', 'name' => 'Carte', 'alias' => 'carte', 'currency' => 'HTG']);
        $cat  = Category::create(['menu_id' => $menu->id, 'name' => 'Plats', 'is_active' => true]);
        $plat = Item::create(['menu_id' => $menu->id, 'category_id' => $cat->id,
            'name' => 'Griot', 'price' => 350, 'stock' => 10, 'is_available' => true]);

        $m = $this->ledger()->remove($plat, 2, MovementType::INTERNAL, ['reason' => 'Repas du personnel']);

        $this->assertSame(StockMovement::SOURCE_MENU, $m->source);
        $this->assertSame('menu:'.$plat->id, $m->ref);
        $this->assertSame('t-1', $m->tenant_id, 'Le commerce vient du menu, pas du hasard.');
    }

    /* ------------------------------------------------------------------
       Cloisonnement et intégrité.
       ------------------------------------------------------------------ */

    public function test_the_neighbour_never_sees_this_journal(): void
    {
        $this->patron('t-1');
        $this->ledger()->remove($this->article(['stock' => 20]), 3, MovementType::LOSS);

        $this->patron('t-2');
        $this->assertSame(0, StockMovement::count(), 'Le journal est cloisonné par commerce.');
    }

    public function test_an_unknown_reason_is_refused_outright(): void
    {
        $this->patron();
        $coca = $this->article();

        $this->expectException(\InvalidArgumentException::class);
        $this->ledger()->apply($coca, -1, 'disparu');
    }

    public function test_a_purchase_records_what_it_cost(): void
    {
        // C'est ce qui permettra de dire ce que vaut le stock en réserve.
        $this->patron();
        $coca = $this->article(['stock' => 20]);

        $m = $this->ledger()->add($coca, 24, MovementType::PURCHASE, ['unit_cost' => 55]);

        $this->assertSame(44.0, $coca->fresh()->stock);
        $this->assertEquals(55.0, (float) $m->unit_cost);
        $this->assertSame(1320.0, $m->value, '24 × 55.');
    }
}
