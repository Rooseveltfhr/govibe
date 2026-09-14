<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Pos\Product;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\Tests\TestCase;

/**
 * LA VITRINE — ce que voit celui qui commande.
 *
 * Même exigence des deux côtés : le client attablé devant son QR et le caissier
 * au comptoir regardent le même catalogue. Une liste de lignes montre deux
 * plats par écran de téléphone ; une grille de cartes en montre six, avec la
 * photo en grand — et c'est la photo qui fait commander, pas le nom.
 */
class CatalogueVitrineTest extends TestCase
{
    use RefreshDatabase;

    private function menu(array $attrs = []): Menu
    {
        return Menu::create(array_merge([
            'tenant_id' => 't-1', 'name' => 'Chez Rose', 'alias' => 'chez-rose',
            'type' => 'restaurant', 'currency' => 'HTG', 'is_active' => true,
            'show_prices' => true,
        ], $attrs));
    }

    private function plat(Category $c, array $attrs = []): Item
    {
        return Item::create(array_merge([
            'menu_id' => $c->menu_id, 'category_id' => $c->id,
            'name' => 'Griot', 'price' => 350, 'is_available' => true,
        ], $attrs));
    }

    private function categorie(Menu $m, string $nom, ?string $icon = null): Category
    {
        return Category::create([
            'menu_id' => $m->id, 'name' => $nom, 'icon' => $icon,
            'sort' => 0, 'is_active' => true,
        ]);
    }

    /* ------------------------------------------------------------------
       Le menu du client.
       ------------------------------------------------------------------ */

    public function test_the_dishes_are_laid_out_as_a_grid_of_cards(): void
    {
        $m = $this->menu();
        $this->plat($this->categorie($m, 'Plats'));

        $this->get('/menu/'.$m->alias)->assertOk()
            ->assertSee('class="grille"', false)
            ->assertSee('Griot');
    }

    public function test_a_category_gets_a_real_icon_without_anyone_configuring_it(): void
    {
        // Presque aucun marchand ne saisit d'icône : sans déduction, la barre
        // de catégories serait nue sur la quasi-totalité des menus.
        $m = $this->menu();
        $this->plat($this->categorie($m, 'Boissons'));

        $this->get('/menu/'.$m->alias)->assertOk()->assertSee('fa-mug-hot', false);
    }

    public function test_an_old_emoji_category_no_longer_prints_its_emoji(): void
    {
        // Les catégories existantes portent des emojis : ils tombent en carré
        // blanc sur beaucoup d'Android bon marché, juste à côté du nom du
        // restaurant — c'est-à-dire là où la page doit inspirer confiance.
        $m = $this->menu();
        $this->plat($this->categorie($m, 'Desserts', '🍰'));

        $reponse = $this->get('/menu/'.$m->alias)->assertOk();
        $reponse->assertSee('fa-ice-cream', false);
        $reponse->assertDontSee('🍰');
    }

    public function test_a_dish_without_a_photo_shows_its_initial_not_an_emoji(): void
    {
        $m = $this->menu();
        $this->plat($this->categorie($m, 'Plats'), ['name' => 'Griot', 'emoji' => '🍽️']);

        $this->get('/menu/'.$m->alias)->assertOk()->assertDontSee('🍽️');
    }

    public function test_the_page_carries_the_sound_that_confirms_an_add(): void
    {
        // Sur un téléphone, le panier est en bas de page : le client qui touche
        // « + » ne voit RIEN bouger. Sans bruit, il retouche — et commande deux
        // fois le même plat.
        $m = $this->menu(['ordering_enabled' => true, 'whatsapp' => '+509 3123 4567']);
        $this->plat($this->categorie($m, 'Plats'));

        $this->get('/menu/'.$m->alias)->assertOk()
            ->assertSee('tagtoa-sound.js', false)
            ->assertSee("son('add')", false);
    }

    public function test_the_cover_still_heads_the_page(): void
    {
        $m = $this->menu();
        $this->plat($this->categorie($m, 'Plats'));

        $this->get('/menu/'.$m->alias)->assertOk()->assertSee('class="cover"', false);
    }

    /* ------------------------------------------------------------------
       La caisse.
       ------------------------------------------------------------------ */

    public function test_a_till_button_shows_the_photo_when_there_is_one(): void
    {
        Storage::fake('public');
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-1', 'name' => 'Roosevelt']));
        $caisse = Terminal::create(['tenant_id' => 't-1', 'name' => 'Caisse', 'currency' => 'HTG', 'is_active' => true]);

        $this->post(route('tagtoa.pos.products.add', $caisse->id), [
            'name' => 'Prestige', 'price' => 150,
            'image' => UploadedFile::fake()->image('biere.jpg'),
        ])->assertRedirect();

        $chemin = Product::where('name', 'Prestige')->firstOrFail()->image_path;

        $this->get(route('tagtoa.pos.register', $caisse->id))->assertOk()
            ->assertSee(basename($chemin), false);
    }

    public function test_a_till_button_without_a_photo_falls_back_to_the_initial(): void
    {
        // Jamais un emoji : sur l'écran où le caissier tape sans regarder, un
        // carré blanc et un burger se ressemblent trop.
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-1', 'name' => 'Roosevelt']));
        $caisse = Terminal::create(['tenant_id' => 't-1', 'name' => 'Caisse', 'currency' => 'HTG', 'is_active' => true]);
        $this->post(route('tagtoa.pos.products.add', $caisse->id), ['name' => 'Zoranj', 'price' => 50]);

        $this->get(route('tagtoa.pos.register', $caisse->id))->assertOk()
            ->assertSee('>Z</span>', false)
            ->assertDontSee('🛒');
    }

    public function test_the_till_loads_the_shared_sound_module(): void
    {
        // Le gain d'origine (0,1) s'entendait dans un bureau silencieux, pas
        // dans une salle pleine — c'est-à-dire jamais au moment où il sert.
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-1', 'name' => 'Roosevelt']));
        $caisse = Terminal::create(['tenant_id' => 't-1', 'name' => 'Caisse', 'currency' => 'HTG', 'is_active' => true]);

        $this->get(route('tagtoa.pos.register', $caisse->id))->assertOk()
            ->assertSee('tagtoa-sound.js', false);
    }

    public function test_the_sound_module_is_actually_served(): void
    {
        // Un <script src> vers un fichier absent échoue en SILENCE : la caisse
        // resterait muette sans qu'aucune erreur ne remonte.
        $this->get(route('tagtoa.asset', 'tagtoa-sound.js'))->assertOk()
            ->assertHeader('content-type', 'application/javascript; charset=utf-8');
    }

    public function test_the_sound_module_needs_no_file_to_download(): void
    {
        // Un .mp3, c'est une requête réseau de plus au moment précis où la
        // connexion est mauvaise — et un silence quand elle est coupée,
        // c'est-à-dire quand la caisse hors ligne doit rassurer.
        $js = (string) file_get_contents(__DIR__.'/../../resources/assets/tagtoa/tagtoa-sound.js');

        $this->assertStringContainsString('createOscillator', $js);

        // Aucun chargement d'aucune sorte : ni balise Audio, ni requête.
        foreach (['new Audio(', 'fetch(', 'XMLHttpRequest', '.src ='] as $interdit) {
            $this->assertStringNotContainsString($interdit, $js,
                "Le module sonore ne doit RIEN télécharger : « $interdit » trouvé.");
        }
    }
}
