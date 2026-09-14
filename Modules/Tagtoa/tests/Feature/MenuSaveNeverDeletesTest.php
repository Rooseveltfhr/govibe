<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\Tests\TestCase;

/**
 * Enregistrer un menu ne supprime jamais un article.
 *
 * Le formulaire effaçait tout ce qui n'était pas renvoyé. Or un envoi peut
 * être incomplet sans que personne le veuille — et la cause la plus fréquente
 * n'est pas humaine : PHP tronque $_POST au-delà de max_input_vars SANS rien
 * dire. Une carte de soixante plats dépasse ce plafond.
 *
 * Depuis B-3 la caisse vend ce catalogue : un article effacé emportait son
 * stock et disparaissait aussi du comptoir.
 */
class MenuSaveNeverDeletesTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId]));
    }

    /** Un menu déjà rempli : une catégorie, deux plats. */
    private function menuGarni(): Menu
    {
        $menu = Menu::create([
            'tenant_id' => 't-1', 'name' => 'Carte', 'alias' => 'carte', 'currency' => 'HTG',
        ]);
        $cat = Category::create(['menu_id' => $menu->id, 'name' => 'Plats', 'is_active' => true]);

        Item::create(['menu_id' => $menu->id, 'category_id' => $cat->id,
            'name' => 'Griot', 'price' => 350, 'stock' => 10, 'is_available' => true]);
        Item::create(['menu_id' => $menu->id, 'category_id' => $cat->id,
            'name' => 'Poisson gros sel', 'price' => 600, 'stock' => 4, 'is_available' => true]);

        return $menu;
    }

    /** Renvoie SEULEMENT le premier plat — comme un envoi tronqué. */
    private function envoiPartiel(Menu $menu, array $extra = []): \Illuminate\Testing\TestResponse
    {
        $cat  = $menu->categories()->first();
        $plat = $cat->items()->where('name', 'Griot')->first();

        return $this->put(route('tagtoa.menu.dashboard.update', $menu->id), array_merge([
            'name'     => 'Carte',
            'currency' => 'HTG',
            'cats'     => [[
                'id'    => $cat->id,
                'name'  => 'Plats',
                'items' => [[
                    'id' => $plat->id, 'name' => 'Griot', 'price' => 350, 'stock' => 10,
                ]],
            ]],
        ], $extra));
    }

    public function test_a_truncated_submission_no_longer_wipes_the_menu(): void
    {
        // LE test de cette correction. Le second plat n'est pas renvoyé : avant,
        // il était effacé sans un mot.
        $this->patron();
        $menu = $this->menuGarni();

        $this->envoiPartiel($menu, ['form_end' => 1])->assertRedirect();

        $this->assertSame(2, Item::where('menu_id', $menu->id)->count(),
            'Un article absent de l\'envoi ne doit jamais être supprimé.');
        $this->assertSame(4.0, Item::where('name', 'Poisson gros sel')->value('stock'),
            'Son stock non plus.');
    }

    public function test_a_submission_cut_short_is_refused_outright(): void
    {
        // Sans le jeton de fin, l'envoi est arrivé coupé : rien ne doit être
        // écrit, même partiellement, et le marchand doit l'apprendre.
        $this->patron();
        $menu = $this->menuGarni();

        $this->put(route('tagtoa.menu.dashboard.update', $menu->id), [
            'name'     => 'Nom changé',
            'currency' => 'HTG',
            'cats'     => [['id' => $menu->categories()->first()->id, 'name' => 'Plats']],
        ])->assertSessionHasErrors('cats');

        $this->assertSame('Carte', $menu->fresh()->name,
            'Un envoi coupé ne doit rien écrire du tout, pas même l\'en-tête.');
        $this->assertSame(2, Item::where('menu_id', $menu->id)->count());
    }

    public function test_a_menu_without_content_still_saves(): void
    {
        // Le jeton n'est exigé que si du contenu a été envoyé : un appel qui ne
        // touche pas aux catégories reste valable.
        $this->patron();
        $menu = $this->menuGarni();

        $this->put(route('tagtoa.menu.dashboard.update', $menu->id), [
            'name' => 'Carte du soir', 'currency' => 'HTG',
        ])->assertRedirect();

        $this->assertSame('Carte du soir', $menu->fresh()->name);
        $this->assertSame(2, Item::where('menu_id', $menu->id)->count());
    }

    public function test_a_category_left_out_survives_too(): void
    {
        $this->patron();
        $menu = $this->menuGarni();
        Category::create(['menu_id' => $menu->id, 'name' => 'Boissons', 'is_active' => true]);

        $this->envoiPartiel($menu, ['form_end' => 1])->assertRedirect();

        $this->assertSame(2, $menu->categories()->count(),
            'Une catégorie absente de l\'envoi ne doit pas disparaître.');
    }

    /* ------------------------------------------------------------------
       Supprimer reste possible — mais c'est un acte à part, délibéré.
       ------------------------------------------------------------------ */

    public function test_the_owner_can_still_delete_one_article_on_purpose(): void
    {
        $this->patron();
        $menu = $this->menuGarni();
        $plat = Item::where('name', 'Griot')->firstOrFail();

        $this->delete(route('tagtoa.menu.dashboard.items.destroy', [$menu->id, $plat->id]))
            ->assertRedirect();

        $this->assertSame(0, Item::where('name', 'Griot')->count());
        $this->assertSame(1, Item::where('menu_id', $menu->id)->count(), 'Les autres restent.');
    }

    public function test_deleting_a_category_takes_its_articles_and_says_so(): void
    {
        $this->patron();
        $menu = $this->menuGarni();
        $cat  = $menu->categories()->first();

        $this->delete(route('tagtoa.menu.dashboard.categories.destroy', [$menu->id, $cat->id]))
            ->assertRedirect();

        $this->assertSame(0, $menu->categories()->count());
        $this->assertSame(0, Item::where('menu_id', $menu->id)->count());
    }

    public function test_the_neighbour_cannot_delete_an_article_here(): void
    {
        // L'isolation vaut aussi pour les suppressions : un identifiant deviné
        // ne doit rien pouvoir atteindre chez le voisin.
        $menu = $this->menuGarni();
        $plat = Item::where('name', 'Griot')->firstOrFail();

        $this->patron('t-2');

        $this->delete(route('tagtoa.menu.dashboard.items.destroy', [$menu->id, $plat->id]))
            ->assertNotFound();

        $this->assertSame(1, Item::where('name', 'Griot')->count());
    }

    public function test_an_unknown_article_is_never_deleted_from_another_menu(): void
    {
        $this->patron();
        $menu  = $this->menuGarni();
        $autre = Menu::create(['tenant_id' => 't-1', 'name' => 'Bar', 'alias' => 'bar', 'currency' => 'HTG']);
        $cat   = Category::create(['menu_id' => $autre->id, 'name' => 'Boissons', 'is_active' => true]);
        $biere = Item::create(['menu_id' => $autre->id, 'category_id' => $cat->id,
            'name' => 'Prestige', 'price' => 150, 'is_available' => true]);

        // On demande la suppression via le MAUVAIS menu, avec un id valide.
        $this->delete(route('tagtoa.menu.dashboard.items.destroy', [$menu->id, $biere->id]))
            ->assertNotFound();

        $this->assertSame(1, Item::where('name', 'Prestige')->count());
    }
}
