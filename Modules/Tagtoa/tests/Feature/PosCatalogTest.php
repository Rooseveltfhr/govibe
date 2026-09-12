<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Tagtoa\App\Models\Pos\Product;
use Modules\Tagtoa\App\Models\Pos\Sale;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Services\Pos\PosCatalog;
use Modules\Tagtoa\App\Services\Pos\PosService;
use Modules\Tagtoa\Tests\TestCase;

/**
 * POS — le catalogue appartient au COMMERCE, plus à la caisse.
 *
 * Un commerce à deux caisses saisissait ses produits deux fois, et un article
 * créé sur la caisse 1 restait introuvable depuis la caisse 2. C'est aussi ce
 * qui rendait le scan de code-barres impossible à faire correctement : un code
 * identifie un produit du commerce, jamais celui d'une caisse.
 */
class PosCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function terminal(string $tenantId, string $name): Terminal
    {
        return Terminal::create([
            'tenant_id' => $tenantId, 'name' => $name, 'currency' => 'HTG', 'is_active' => true,
        ]);
    }

    public function test_two_tills_of_the_same_shop_share_one_catalogue(): void
    {
        $salle    = $this->terminal('t-1', 'Salle');
        $terrasse = $this->terminal('t-1', 'Terrasse');

        // Saisi sur une seule caisse…
        app(PosCatalog::class)->save($salle, ['name' => 'Coca 500ml', 'price' => 75, 'is_active' => true]);

        // …vendable depuis l'autre.
        $this->assertSame(['Coca 500ml'], app(PosCatalog::class)->active($terrasse->tenant_id)->pluck('name')->all());
        $this->assertSame(1, Product::count(), 'Le produit ne doit pas être saisi deux fois.');
    }

    public function test_a_product_entered_on_one_till_can_be_sold_on_another(): void
    {
        $salle    = $this->terminal('t-1', 'Salle');
        $terrasse = $this->terminal('t-1', 'Terrasse');

        $coca = app(PosCatalog::class)->save($salle, ['name' => 'Coca 500ml', 'price' => 75, 'stock' => 10, 'is_active' => true]);

        $vente = app(PosService::class)->recordSale($terrasse, [
            'items' => [['product_id' => $coca->id, 'qty' => 2]],
        ]);

        // Le prix vient du catalogue (imposé côté serveur), pas du client.
        $this->assertEquals(150.0, (float) $vente->total);
        $this->assertSame(8.0, $coca->fresh()->stock, 'Le stock du catalogue partagé doit bouger.');
        // Et la vente dit bien SUR QUELLE caisse elle a eu lieu.
        $this->assertSame($terrasse->id, $vente->terminal_id);
    }

    public function test_a_shop_never_sells_a_neighbours_product(): void
    {
        $chezA = $this->terminal('t-1', 'Boulangerie');
        $chezB = $this->terminal('t-2', 'Bar');

        $produitDeA = app(PosCatalog::class)->save($chezA, ['name' => 'Pain', 'price' => 50, 'is_active' => true]);

        // Identifiant deviné : la recherche ne doit rien rendre…
        $this->assertNull(app(PosCatalog::class)->find($chezB->tenant_id, $produitDeA->id));

        // …et une vente qui le réclame retombe sur un article libre, sans le prix
        // ni le nom du voisin.
        $vente = app(PosService::class)->recordSale($chezB, [
            'items' => [['product_id' => $produitDeA->id, 'qty' => 1, 'price' => 5, 'name' => 'Autre']],
        ]);

        $this->assertEquals(5.0, (float) $vente->total);
        $this->assertSame('Autre', $vente->items->first()->name);
        $this->assertSame(50, (int) $produitDeA->fresh()->price, 'Le produit du voisin ne doit pas bouger.');
    }

    public function test_the_catalogue_survives_the_removal_of_a_till(): void
    {
        // La clé étrangère supprime encore en cascade. Comme le catalogue est
        // maintenant PARTAGÉ, supprimer une caisse effacerait les articles de
        // tout le commerce — d'où le garde-fou sur le modèle.
        $salle    = $this->terminal('t-1', 'Salle');
        $terrasse = $this->terminal('t-1', 'Terrasse');

        app(PosCatalog::class)->save($salle, ['name' => 'Coca', 'price' => 75, 'is_active' => true]);

        $salle->delete();

        $this->assertSame(1, Product::count(), 'Le catalogue ne doit pas partir avec la caisse.');
        $this->assertSame(['Coca'], app(PosCatalog::class)->active($terrasse->tenant_id)->pluck('name')->all());
    }

    /**
     * Reprise de données : chaque article rejoint le catalogue de son commerce,
     * et AUCUN doublon n'est fusionné — les fusionner obligerait à réaffecter
     * `sale_items.product_id`, donc à réécrire des ventes déjà encaissées.
     */
    public function test_the_migration_keeps_every_existing_product_and_its_sales(): void
    {
        $salle    = $this->terminal('t-1', 'Salle');
        $terrasse = $this->terminal('t-1', 'Terrasse');

        // Données « d'avant » : le même Coca saisi sur les deux caisses.
        foreach ([$salle, $terrasse] as $caisse) {
            DB::table('tagtoa_pos_products')->insert([
                'tenant_id' => null, 'terminal_id' => $caisse->id, 'name' => 'Coca',
                'price' => 75, 'is_active' => true, 'sort' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->runCatalogMigration();

        $catalogue = app(PosCatalog::class)->all('t-1');

        $this->assertCount(2, $catalogue, 'Les doublons sont conservés, pas fusionnés.');
        $this->assertSame(['Coca', 'Coca'], $catalogue->pluck('name')->all());
        // Chacun garde la trace de sa caisse de saisie.
        $this->assertEqualsCanonicalizing(
            [$salle->id, $terrasse->id],
            $catalogue->pluck('terminal_id')->all()
        );
    }

    public function test_the_migration_never_mixes_two_shops(): void
    {
        $chezA = $this->terminal('t-1', 'Boulangerie');
        $chezB = $this->terminal('t-2', 'Bar');

        foreach ([[$chezA, 'Pain'], [$chezB, 'Bière']] as [$caisse, $nom]) {
            DB::table('tagtoa_pos_products')->insert([
                'tenant_id' => null, 'terminal_id' => $caisse->id, 'name' => $nom,
                'price' => 50, 'is_active' => true, 'sort' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->runCatalogMigration();

        $this->assertSame(['Pain'],  app(PosCatalog::class)->all('t-1')->pluck('name')->all());
        $this->assertSame(['Bière'], app(PosCatalog::class)->all('t-2')->pluck('name')->all());
    }

    /* ------------------------------------------------------------------
       Le catalogue est celui du PATRON. Une caisse vend, elle ne supprime pas.
       ------------------------------------------------------------------ */

    public function test_saving_the_catalogue_never_deletes_what_is_missing(): void
    {
        // Le formulaire effaçait tout article absent de l'envoi. Le catalogue
        // étant partagé, un envoi partiel — connexion coupée, deux personnes qui
        // modifient en même temps — effaçait les articles de TOUT le commerce.
        $caisse = $this->terminal('t-1', 'Caisse');

        $coca = app(PosCatalog::class)->save($caisse, ['name' => 'Coca', 'price' => 75, 'is_active' => true]);
        $pain = app(PosCatalog::class)->save($caisse, ['name' => 'Pain', 'price' => 50, 'is_active' => true]);

        // Un envoi qui ne contient QUE le Coca ne doit pas emporter le Pain.
        app(PosCatalog::class)->save($caisse, ['name' => 'Coca', 'price' => 80, 'is_active' => true], $coca->id);

        $this->assertSame(2, Product::count(), 'Enregistrer ne doit jamais supprimer.');
        $this->assertNotNull($pain->fresh());
        $this->assertEquals(80.0, (float) $coca->fresh()->price);
    }

    public function test_taking_an_item_off_sale_keeps_it_and_its_stock(): void
    {
        // La façon normale de retirer un article de la vente : l'interrupteur.
        // Rien n'est perdu — ni l'article, ni son stock, ni son historique.
        $caisse = $this->terminal('t-1', 'Caisse');
        $coca = app(PosCatalog::class)->save($caisse, ['name' => 'Coca', 'price' => 75, 'stock' => 12, 'is_active' => true]);

        app(PosCatalog::class)->save($caisse, ['name' => 'Coca', 'price' => 75, 'stock' => 12, 'is_active' => false], $coca->id);

        $this->assertSame([], app(PosCatalog::class)->active('t-1')->pluck('name')->all());
        $this->assertCount(1, app(PosCatalog::class)->all('t-1'));
        $this->assertSame(12.0, $coca->fresh()->stock);
    }

    public function test_deleting_an_item_leaves_past_sales_untouched(): void
    {
        // Supprimer un article ne doit pas réécrire ce qui a déjà été encaissé :
        // la ligne de vente garde le nom et le prix du jour de la vente.
        $caisse = $this->terminal('t-1', 'Caisse');
        $coca = app(PosCatalog::class)->save($caisse, ['name' => 'Coca', 'price' => 75, 'is_active' => true]);

        $vente = app(PosService::class)->recordSale($caisse, [
            'items' => [['product_id' => $coca->id, 'qty' => 2]],
        ]);

        $coca->delete();

        $ligne = $vente->fresh()->items->first();
        $this->assertSame('Coca', $ligne->name);
        $this->assertEquals(75.0, (float) $ligne->price);
        $this->assertEquals(150.0, (float) $vente->fresh()->total);
        $this->assertSame(1, Sale::count());
    }

        /** Rejoue la reprise de données sur un jeu réaliste. */
    private function runCatalogMigration(): void
    {
        $file = __DIR__.'/../../Database/migrations/2026_09_10_000117_move_pos_catalog_to_merchant.php';
        $this->assertFileExists($file);
        (require $file)->up();

        $this->assertSame(0, DB::table('tagtoa_pos_products')->whereNull('tenant_id')->count(),
            'Aucun article ne doit rester sans commerce.');
    }
}
