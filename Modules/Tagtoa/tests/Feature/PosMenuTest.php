<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Pos\Category;
use Modules\Tagtoa\App\Models\Pos\Product;
use Modules\Tagtoa\App\Models\Pos\Sale;
use Modules\Tagtoa\App\Models\Pos\SaleItem;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Support\DashboardModules;
use Modules\Tagtoa\Tests\TestCase;

/**
 * LE MENU DE LA CAISSE — treize écrans, enfin atteignables.
 *
 * La caisse n'avait qu'une entrée au menu, non par choix mais parce que TOUTES
 * ses URL exigeaient le numéro du poste (`/tagtoa/pos/7/products`) — et qu'un
 * menu ne connaît pas le numéro 7. Ces tests vérifient que chaque écran s'ouvre
 * désormais SANS numéro, et que le menu les liste tous.
 */
class PosMenuTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));
    }

    /* ------------------------------------------------------------------
       La caisse s'ouvre sans numéro.
       ------------------------------------------------------------------ */

    public function test_every_pos_screen_opens_without_a_terminal_number(): void
    {
        $this->patron();

        foreach ([
            '/tagtoa/pos', '/tagtoa/pos/products', '/tagtoa/pos/categories',
            '/tagtoa/pos/tickets', '/tagtoa/pos/returns', '/tagtoa/pos/reports',
            '/tagtoa/pos/settings',
        ] as $url) {
            $this->get($url)->assertOk();
        }

        // « Nouvelle vente » mène à l'application caisse, qui garde son numéro :
        // elle est installable, et deux adresses en feraient deux installations
        // avec deux files d'attente hors ligne distinctes.
        $this->get('/tagtoa/pos/sell')->assertRedirect();
    }

    public function test_a_merchant_without_a_till_gets_one_rather_than_an_error(): void
    {
        // Demander à un marchand de créer « une caisse » avant de pouvoir
        // ouvrir « Produits » est une marche qui n'apprend rien et sur laquelle
        // on trébuche.
        $this->patron('t-neuf');
        $this->assertSame(0, Terminal::where('tenant_id', 't-neuf')->count());

        $this->get('/tagtoa/pos/products')->assertOk();

        $this->assertSame(1, Terminal::where('tenant_id', 't-neuf')->count());
    }

    public function test_opening_the_same_screen_twice_does_not_create_two_tills(): void
    {
        $this->patron('t-neuf');

        $this->get('/tagtoa/pos/products')->assertOk();
        $this->get('/tagtoa/pos/reports')->assertOk();
        $this->get('/tagtoa/pos/products')->assertOk();

        $this->assertSame(1, Terminal::where('tenant_id', 't-neuf')->count(),
            'Chaque écran créerait sa propre caisse — et le catalogue se scinderait.');
    }

    public function test_a_word_is_never_taken_for_a_till_number(): void
    {
        // `/{id}/products` et `/products` cohabitent : sans contrainte
        // numérique, « products » serait pris pour un identifiant de poste et
        // l'écran répondrait 404.
        $this->patron();
        $this->get('/tagtoa/pos/products')->assertOk();
        $this->get('/tagtoa/pos/categories')->assertOk();
        $this->get('/tagtoa/pos/settings')->assertOk();
    }

    /* ------------------------------------------------------------------
       Le menu lui-même.
       ------------------------------------------------------------------ */

    public function test_the_menu_lists_the_thirteen_screens_of_the_till(): void
    {
        $enfants = DashboardModules::children('pos');

        $this->assertGreaterThanOrEqual(13, count($enfants),
            'La caisse a treize écrans : le menu doit les montrer.');
    }

    public function test_the_menu_is_cut_into_blocks(): void
    {
        // Treize entrées d'affilée se lisent comme une liste de courses.
        $blocs = array_values(array_filter(array_column(
            DashboardModules::CATALOG['pos']['children'], 'sep'
        )));

        $this->assertSame(['Vendre', 'Catalogue', 'Gens & argent', 'Le poste', 'Réglages'], $blocs);
    }

    public function test_the_first_block_starts_the_list(): void
    {
        // Un bloc qui commencerait au milieu laisserait les premières entrées
        // orphelines, sans titre au-dessus.
        $premier = DashboardModules::CATALOG['pos']['children'][0];

        $this->assertArrayHasKey('sep', $premier);
    }

    public function test_the_sidebar_renders_the_block_titles(): void
    {
        $this->patron();

        $this->get('/tagtoa/pos/products')->assertOk()
            ->assertSee('class="bloc"', false)
            ->assertSee('Catalogue');
    }

    /* ------------------------------------------------------------------
       Catégories.
       ------------------------------------------------------------------ */

    public function test_a_category_can_be_created_and_edited(): void
    {
        $this->patron();

        $this->post(route('tagtoa.pos.categories.store'), ['name' => 'Boissons'])
            ->assertRedirect()->assertSessionHas('success');

        $c = Category::firstOrFail();
        $this->assertSame('Boissons', $c->name);
        // Sans icône choisie, elle se déduit du nom : presque personne ne
        // choisit, et une barre de rayons sans icônes ne sert à rien de plus
        // qu'une liste de mots.
        $this->assertSame('fa-mug-hot', $c->icon_class);

        $this->put(route('tagtoa.pos.categories.update', $c->id),
            ['name' => 'Bwason', 'icon' => 'fa-bottle-water', 'is_active' => 1])->assertRedirect();

        $this->assertSame('fa-bottle-water', $c->refresh()->icon_class);
    }

    public function test_an_icon_that_is_not_a_class_is_refused(): void
    {
        // La valeur part dans un attribut `class` : une chaîne libre y ferait
        // entrer ce qu'on veut.
        $this->patron();

        $this->post(route('tagtoa.pos.categories.store'),
            ['name' => 'Piégé', 'icon' => '" onload="alert(1)'])->assertSessionHasErrors('icon');

        $this->assertSame(0, Category::count());
    }

    public function test_deleting_a_category_never_deletes_its_products(): void
    {
        // LA règle de cet écran. Supprimer un rayon avec ses produits effacerait
        // un catalogue entier d'un clic, et le stock avec.
        $this->patron();
        $caisse = Terminal::create(['tenant_id' => 't-1', 'name' => 'C', 'currency' => 'HTG', 'is_active' => true]);
        $c = Category::create(['tenant_id' => 't-1', 'name' => 'Boissons', 'sort' => 0, 'is_active' => true]);
        $p = Product::create(['tenant_id' => 't-1', 'terminal_id' => $caisse->id,
            'category_id' => $c->id, 'name' => 'Coca', 'price' => 75, 'is_active' => true]);

        $this->delete(route('tagtoa.pos.categories.destroy', $c->id))->assertRedirect();

        $p->refresh();
        $this->assertNotNull($p, 'L\'article doit survivre à son rayon.');
        $this->assertNull($p->category_id, 'Il doit être détaché, pas orphelin d\'un rayon disparu.');
        $this->assertTrue((bool) $p->is_active, 'Il doit rester vendable.');
    }

    public function test_a_category_of_another_business_is_out_of_reach(): void
    {
        $this->patron('t-1');
        $sienne = Category::create(['tenant_id' => 't-2', 'name' => 'Chez lui', 'sort' => 0, 'is_active' => true]);

        $this->delete(route('tagtoa.pos.categories.destroy', $sienne->id))->assertNotFound();
        $this->put(route('tagtoa.pos.categories.update', $sienne->id), ['name' => 'Volé'])->assertNotFound();

        $this->assertSame('Chez lui', Category::withoutGlobalScopes()->find($sienne->id)->name);
    }

    public function test_a_product_cannot_be_filed_in_the_neighbour_s_aisle(): void
    {
        // L'identifiant vient du navigateur : sans filtre, un numéro deviné
        // rangerait l'article dans le rayon du voisin — et le ferait apparaître
        // dans SA grille de caisse.
        $this->patron('t-1');
        $caisse = Terminal::create(['tenant_id' => 't-1', 'name' => 'C', 'currency' => 'HTG', 'is_active' => true]);
        $sienne = Category::create(['tenant_id' => 't-2', 'name' => 'Chez lui', 'sort' => 0, 'is_active' => true]);

        $this->post(route('tagtoa.pos.products.add', $caisse->id),
            ['name' => 'Coca', 'price' => 75, 'category_id' => $sienne->id])->assertRedirect();

        $this->assertNull(Product::where('name', 'Coca')->firstOrFail()->category_id);
    }

    /* ------------------------------------------------------------------
       Scanner pour créer.
       ------------------------------------------------------------------ */

    public function test_scanning_an_unknown_code_creates_and_saves_the_article(): void
    {
        // Le geste d'un commerce qui reçoit un carton : on passe la douchette
        // sur trente articles d'affilée, on les nomme ensuite, assis.
        $this->patron();
        $caisse = Terminal::create(['tenant_id' => 't-1', 'name' => 'C', 'currency' => 'HTG', 'is_active' => true]);

        $this->postJson(route('tagtoa.pos.products.scan', $caisse->id), ['code' => '7640140160016'])
            ->assertOk()->assertJsonPath('result', 'created');

        $p = Product::firstOrFail();
        $this->assertStringContainsString('7640140160016', $p->name);

        // INACTIF tant qu'il n'a pas de prix : un bouton à zéro gourde en
        // caisse, c'est une vente encaissée pour rien.
        $this->assertFalse((bool) $p->is_active);

        // Et le code est accroché : l'article se revend en le scannant.
        $this->postJson(route('tagtoa.catalog.scan'), ['code' => '7640140160016'])
            ->assertOk()->assertJsonPath('article.ref', 'pos:'.$p->id);
    }

    public function test_scanning_a_known_code_creates_nothing(): void
    {
        // Deux articles pour le même produit, c'est un stock coupé en deux —
        // et un inventaire qui ne retombe jamais juste.
        $this->patron();
        $caisse = Terminal::create(['tenant_id' => 't-1', 'name' => 'C', 'currency' => 'HTG', 'is_active' => true]);

        $this->postJson(route('tagtoa.pos.products.scan', $caisse->id), ['code' => '7640140160016']);
        $this->postJson(route('tagtoa.pos.products.scan', $caisse->id), ['code' => '7640140160016'])
            ->assertOk()->assertJsonPath('result', 'exists');

        $this->assertSame(1, Product::count());
    }

    public function test_scanning_cannot_fill_another_business_catalogue(): void
    {
        $this->patron('t-1');
        $autre = Terminal::create(['tenant_id' => 't-2', 'name' => 'Voisin', 'currency' => 'HTG', 'is_active' => true]);

        $this->postJson(route('tagtoa.pos.products.scan', $autre->id), ['code' => '123456'])
            ->assertNotFound();

        $this->assertSame(0, Product::withoutGlobalScopes()->count());
    }

    /* ------------------------------------------------------------------
       Tickets et reçus.
       ------------------------------------------------------------------ */

    private function vente(string $tenantId = 't-1'): Sale
    {
        $caisse = Terminal::firstOrCreate(['tenant_id' => $tenantId, 'name' => 'Caisse'],
            ['currency' => 'HTG', 'is_active' => true]);

        $s = Sale::create([
            'terminal_id' => $caisse->id, 'reference' => 'V-'.random_int(1000, 9999),
            'subtotal' => 150, 'discount' => 0, 'tax_base' => 150, 'tax_total' => 0,
            'total' => 150, 'currency' => 'HTG', 'status' => 1,
            'payments' => [['method' => 'cash', 'amount' => 150]], 'sold_at' => now(),
        ]);
        SaleItem::create(['sale_id' => $s->id, 'source' => 'pos', 'name' => 'Prestige',
            'price' => 150, 'qty' => 1, 'line_total' => 150]);

        return $s;
    }

    public function test_a_past_sale_can_be_found_again(): void
    {
        // Le rapport Z donne un TOTAL ; il ne répond pas à « la dame de ce
        // matin dit qu'elle a payé 500, qu'est-ce qu'elle a pris ? ».
        $this->patron();
        $s = $this->vente();

        $this->get(route('tagtoa.pos.tickets'))->assertOk()->assertSee($s->reference);
        $this->get(route('tagtoa.pos.tickets', ['q' => $s->reference]))->assertOk()->assertSee($s->reference);
    }

    public function test_a_receipt_shows_what_was_sold(): void
    {
        $this->patron();
        $s = $this->vente();

        $this->get(route('tagtoa.pos.ticket', $s->id))->assertOk()
            ->assertSee('Prestige')->assertSee($s->reference);
    }

    public function test_a_receipt_of_another_business_is_out_of_reach(): void
    {
        // Un ticket porte un poste, pas un commerce : sans le filtre par
        // caisses, un identifiant deviné donnerait le ticket du voisin — avec
        // ses prix, ses clients et son chiffre d'affaires.
        $sienne = $this->vente('t-2');

        $this->patron('t-1');
        $this->get(route('tagtoa.pos.ticket', $sienne->id))->assertNotFound();
        $this->get(route('tagtoa.pos.tickets'))->assertOk()->assertDontSee($sienne->reference);
    }

    /* ------------------------------------------------------------------
       Paramètres.
       ------------------------------------------------------------------ */

    public function test_a_till_can_be_renamed(): void
    {
        $this->patron();
        $t = Terminal::create(['tenant_id' => 't-1', 'name' => 'Caisse', 'currency' => 'HTG', 'is_active' => true]);

        $this->put(route('tagtoa.pos.settings.update', $t->id),
            ['name' => 'Comptoir', 'currency' => 'USD', 'is_active' => 1])->assertRedirect();

        $t->refresh();
        $this->assertSame('Comptoir', $t->name);
        $this->assertSame('USD', $t->currency);
    }

    public function test_the_neighbour_s_till_cannot_be_renamed(): void
    {
        $this->patron('t-1');
        $sienne = Terminal::create(['tenant_id' => 't-2', 'name' => 'Chez lui', 'currency' => 'HTG', 'is_active' => true]);

        $this->put(route('tagtoa.pos.settings.update', $sienne->id), ['name' => 'Volée'])->assertNotFound();

        $this->assertSame('Chez lui', $sienne->refresh()->name);
    }

    public function test_settings_do_not_duplicate_what_belongs_to_the_business(): void
    {
        // La taxe et les moyens de paiement appartiennent au COMMERCE. Les
        // recopier ici donnerait deux endroits pour un seul réglage — et le
        // jour où ils divergent, deux caisses délivrent des reçus avec des
        // taxes différentes.
        $vue = (string) file_get_contents(__DIR__.'/../../resources/views/pos/settings.blade.php');

        $this->assertStringNotContainsString('name="tax_rate"', $vue);
        $this->assertStringNotContainsString('name="tax_enabled"', $vue);
        // On MÈNE à l'écran propriétaire, au lieu de recopier.
        $this->assertStringContainsString('tagtoa.business.index', $vue);
    }
}
