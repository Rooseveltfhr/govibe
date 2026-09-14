<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Tagtoa\App\Models\Catalog\ProductCode;
use Modules\Tagtoa\App\Models\Pos\Product;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\Tests\TestCase;

/**
 * Le formulaire produits du patron.
 *
 * Il empilait des lignes vides qu'il fallait penser à enregistrer à la fin.
 * Trois conséquences, toutes vécues : on en scanne cinq, le téléphone se
 * verrouille, tout est perdu ; on ne sait plus lesquels sont au catalogue ;
 * et au-delà de quelques dizaines de lignes PHP tronque l'envoi sans un mot.
 *
 * Un article s'ajoute désormais SEUL et part en base immédiatement.
 */
class PosProductFormTest extends TestCase
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

    /* ------------------------------------------------------------------
       Ajouter, c'est enregistrer.
       ------------------------------------------------------------------ */

    public function test_adding_one_article_saves_it_immediately(): void
    {
        $this->patron();

        $this->post(route('tagtoa.pos.products.add', $this->caisse()->id), [
            'name' => 'Riz national', 'price' => 120, 'stock' => 8, 'unit' => 'lb',
        ])->assertRedirect()->assertSessionHas('success');

        $riz = Product::where('name', 'Riz national')->firstOrFail();

        $this->assertEquals(120.0, (float) $riz->price);
        $this->assertSame(8.0, $riz->stock);
        $this->assertSame('lb', $riz->unit);
        $this->assertTrue((bool) $riz->is_active, 'Un article qu\'on vient d\'ajouter se vend.');
    }

    public function test_adding_several_articles_in_a_row_keeps_them_all(): void
    {
        // Le geste réel : on ajoute, l'article apparaît, on ajoute le suivant.
        // Aucun n'attend un enregistrement global qu'on pourrait oublier.
        $this->patron();
        $caisse = $this->caisse();

        foreach (['Coca', 'Prestige', 'Pâté', 'Riz'] as $nom) {
            $this->post(route('tagtoa.pos.products.add', $caisse->id), ['name' => $nom, 'price' => 50])
                ->assertRedirect();
        }

        $this->assertSame(4, Product::where('tenant_id', 't-1')->count());
    }

    public function test_an_added_article_goes_to_the_end_of_the_grid(): void
    {
        // Sans ordre explicite, chaque nouvel article prendrait la place 0 et
        // la grille de la caisse se réorganiserait à chaque ajout — sous les
        // doigts d'un caissier qui connaît ses boutons par cœur.
        $this->patron();
        $caisse = $this->caisse();

        $this->post(route('tagtoa.pos.products.add', $caisse->id), ['name' => 'Un', 'price' => 10]);
        $this->post(route('tagtoa.pos.products.add', $caisse->id), ['name' => 'Deux', 'price' => 10]);

        $this->assertSame(['Un', 'Deux'],
            Product::where('tenant_id', 't-1')->orderBy('sort')->pluck('name')->all());
    }

    public function test_an_article_without_a_name_is_refused(): void
    {
        // Un bouton sans nom est un bouton qu'aucun caissier ne peut lire.
        $this->patron();

        $this->post(route('tagtoa.pos.products.add', $this->caisse()->id), ['price' => 120])
            ->assertSessionHasErrors('name');

        $this->assertSame(0, Product::count());
    }

    public function test_a_negative_price_is_refused_on_add_too(): void
    {
        // Un prix négatif rendrait de l'argent à chaque vente. La garde qui
        // existait sur l'enregistrement global doit exister ici aussi, sinon
        // le nouveau chemin est la porte de service.
        $this->patron();

        $this->post(route('tagtoa.pos.products.add', $this->caisse()->id),
            ['name' => 'Coca', 'price' => -75])->assertSessionHasErrors('price');

        $this->assertSame(0, Product::count());
    }

    public function test_an_invented_unit_is_refused_on_add_too(): void
    {
        $this->patron();

        $this->post(route('tagtoa.pos.products.add', $this->caisse()->id),
            ['name' => 'Riz', 'price' => 120, 'unit' => 'kilo-mamit'])->assertSessionHasErrors('unit');

        $this->assertSame(0, Product::count());
    }

    public function test_an_empty_purchase_price_stays_unknown_on_add(): void
    {
        // « Non renseigné » n'est pas « gratuit » : enregistrer 0 ferait croire
        // au marchand que sa marge est totale.
        $this->patron();

        $this->post(route('tagtoa.pos.products.add', $this->caisse()->id),
            ['name' => 'Pâté', 'price' => 50, 'cost_price' => '', 'stock' => ''])->assertRedirect();

        $pate = Product::where('name', 'Pâté')->firstOrFail();

        $this->assertNull($pate->cost_price);
        $this->assertNull($pate->stock, 'Un stock vide reste non suivi, pas zéro.');
    }

    public function test_adding_belongs_to_the_business_not_the_till(): void
    {
        // Le catalogue appartient au commerce : un article ajouté depuis la
        // caisse 1 doit se vendre depuis la caisse 2.
        $this->patron();
        $caisse2 = Terminal::create(['tenant_id' => 't-1', 'name' => 'Caisse 2', 'currency' => 'HTG', 'is_active' => true]);

        $this->post(route('tagtoa.pos.products.add', $this->caisse()->id), ['name' => 'Coca', 'price' => 75]);

        $this->assertSame(1, app(\Modules\Tagtoa\App\Services\Pos\PosCatalog::class)
            ->all($caisse2->tenant_id)->count());
    }

    public function test_a_till_of_another_business_cannot_be_filled(): void
    {
        // Le cloisonnement doit tenir sur le NOUVEAU chemin aussi.
        $this->patron('t-1');
        $autre = Terminal::create(['tenant_id' => 't-2', 'name' => 'Voisin', 'currency' => 'HTG', 'is_active' => true]);

        $this->post(route('tagtoa.pos.products.add', $autre->id), ['name' => 'Intrus', 'price' => 10])
            ->assertNotFound();

        $this->assertSame(0, Product::withoutGlobalScopes()->where('name', 'Intrus')->count());
    }

    /* ------------------------------------------------------------------
       Scanner : le code se pose sur l'article qu'on vient d'enregistrer.
       ------------------------------------------------------------------ */

    public function test_scanning_then_adding_attaches_the_code(): void
    {
        // La boucle complète : un code inconnu remplit le formulaire, « Ajouter »
        // enregistre l'article AVEC son code, et l'article se revend ensuite en
        // le scannant — sans jamais taper un chiffre.
        $this->patron();

        $this->post(route('tagtoa.pos.products.add', $this->caisse()->id), [
            'name' => 'Prestige', 'price' => 150, 'new_code' => '7640140160016',
        ])->assertRedirect();

        $biere = Product::where('name', 'Prestige')->firstOrFail();

        $this->assertSame(1, ProductCode::where('code', '7640140160016')->count());

        $this->postJson(route('tagtoa.catalog.scan'), ['code' => '7640140160016'])
            ->assertOk()
            ->assertJsonPath('article.ref', 'pos:'.$biere->id);
    }

    public function test_a_misread_code_does_not_stick_to_the_new_article(): void
    {
        // Le contrôle de la clé refuse le code : l'article est créé — le patron
        // a écrit son nom et son prix — mais le code ne s'accroche pas, sinon il
        // désignerait un article que personne ne retrouverait en scannant.
        $this->patron();

        $this->post(route('tagtoa.pos.products.add', $this->caisse()->id), [
            'name' => 'Prestige', 'price' => 150, 'new_code' => '7640140160017',
        ])->assertRedirect();

        $this->assertSame(1, Product::where('name', 'Prestige')->count());
        $this->assertSame(0, ProductCode::count());
    }

    /* ------------------------------------------------------------------
       La photo.
       ------------------------------------------------------------------ */

    public function test_an_article_can_carry_a_photo(): void
    {
        // Un emoji ne distingue pas trois plats de riz ni quatre tailles de la
        // même bière — et c'est là que le caissier se trompe de bouton.
        Storage::fake('public');
        $this->patron();

        $this->post(route('tagtoa.pos.products.add', $this->caisse()->id), [
            'name'  => 'Riz collé',
            'price' => 250,
            'image' => UploadedFile::fake()->image('riz.jpg', 400, 400),
        ])->assertRedirect();

        $riz = Product::where('name', 'Riz collé')->firstOrFail();

        $this->assertNotNull($riz->image_path);
        Storage::disk('public')->assertExists($riz->image_path);
        $this->assertNotNull($riz->image_url);
    }

    public function test_a_file_that_is_not_an_image_is_refused(): void
    {
        Storage::fake('public');
        $this->patron();

        $this->post(route('tagtoa.pos.products.add', $this->caisse()->id), [
            'name'  => 'Piégé',
            'price' => 10,
            'image' => UploadedFile::fake()->create('charge.php', 12, 'application/x-php'),
        ])->assertSessionHasErrors('image');

        $this->assertSame(0, Product::count());
    }

    public function test_editing_a_price_never_erases_the_photo(): void
    {
        // Le piège classique : la colonne image est réécrite à chaque
        // enregistrement, et changer un prix efface la photo en silence.
        Storage::fake('public');
        $this->patron();
        $caisse = $this->caisse();

        $this->post(route('tagtoa.pos.products.add', $caisse->id), [
            'name' => 'Coca', 'price' => 75,
            'image' => UploadedFile::fake()->image('coca.jpg'),
        ]);

        $coca = Product::where('name', 'Coca')->firstOrFail();
        $avant = $coca->image_path;
        $this->assertNotNull($avant);

        $this->post(route('tagtoa.pos.products.save', $caisse->id), [
            'products' => [['id' => $coca->id, 'name' => 'Coca', 'price' => 90, 'is_active' => 1]],
            'form_end' => 1,
        ])->assertRedirect();

        $coca->refresh();
        $this->assertSame($avant, $coca->image_path, 'Changer un prix ne doit pas effacer la photo.');
        $this->assertEquals(90.0, (float) $coca->price);
    }

    public function test_the_photo_can_be_removed_on_purpose(): void
    {
        Storage::fake('public');
        $this->patron();
        $caisse = $this->caisse();

        $this->post(route('tagtoa.pos.products.add', $caisse->id), [
            'name' => 'Coca', 'price' => 75, 'image' => UploadedFile::fake()->image('coca.jpg'),
        ]);
        $coca = Product::where('name', 'Coca')->firstOrFail();

        $this->post(route('tagtoa.pos.products.save', $caisse->id), [
            'products' => [['id' => $coca->id, 'name' => 'Coca', 'price' => 75,
                'is_active' => 1, 'remove_image' => 1]],
            'form_end' => 1,
        ])->assertRedirect();

        $this->assertNull($coca->refresh()->image_path);
    }

    /* ------------------------------------------------------------------
       L'envoi tronqué — la panne silencieuse de PHP.
       ------------------------------------------------------------------ */

    public function test_a_truncated_submission_changes_nothing(): void
    {
        // Au-delà de max_input_vars, PHP coupe l'envoi SANS erreur. Sans
        // sentinelle, le marchand lit « Produits enregistrés » et une partie de
        // ses modifications n'est jamais arrivée. Même défaut, même remède
        // qu'au menu (0.2d) — il manquait ici.
        $this->patron();
        $caisse = $this->caisse();

        $this->post(route('tagtoa.pos.products.add', $caisse->id), ['name' => 'Coca', 'price' => 75]);
        $coca = Product::where('name', 'Coca')->firstOrFail();

        $this->post(route('tagtoa.pos.products.save', $caisse->id), [
            'products' => [['id' => $coca->id, 'name' => 'Coca', 'price' => 999, 'is_active' => 1]],
            // pas de form_end : la fin de la liste n'est jamais arrivée
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertEquals(75.0, (float) $coca->refresh()->price,
            'Un envoi amputé ne doit RIEN modifier.');
    }

    public function test_a_complete_submission_still_goes_through(): void
    {
        $this->patron();
        $caisse = $this->caisse();

        $this->post(route('tagtoa.pos.products.add', $caisse->id), ['name' => 'Coca', 'price' => 75]);
        $coca = Product::where('name', 'Coca')->firstOrFail();

        $this->post(route('tagtoa.pos.products.save', $caisse->id), [
            'products' => [['id' => $coca->id, 'name' => 'Coca', 'price' => 90, 'is_active' => 1]],
            'form_end' => 1,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertEquals(90.0, (float) $coca->refresh()->price);
    }

    /* ------------------------------------------------------------------
       L'écran.
       ------------------------------------------------------------------ */

    public function test_the_screen_shows_the_add_form_and_the_saved_articles(): void
    {
        $this->patron();
        $caisse = $this->caisse();
        $this->post(route('tagtoa.pos.products.add', $caisse->id), ['name' => 'Prestige', 'price' => 150]);

        $this->get(route('tagtoa.pos.products', $caisse->id))
            ->assertOk()
            ->assertSee(__('Ajouter un article'))
            ->assertSee('Prestige');
    }

    public function test_the_screen_carries_the_sentinel_when_there_is_something_to_save(): void
    {
        // Sans elle dans la VUE, la garde du contrôleur refuserait tous les
        // enregistrements — et le marchand ne pourrait plus rien modifier.
        $this->patron();
        $caisse = $this->caisse();
        $this->post(route('tagtoa.pos.products.add', $caisse->id), ['name' => 'Coca', 'price' => 75]);

        $this->get(route('tagtoa.pos.products', $caisse->id))
            ->assertOk()
            ->assertSee('name="form_end"', false);
    }
}
