<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA MENU — le formulaire proposait un champ « emoji » pour l'icône de
| catégorie et pour chaque article, alors que ni l'un ni l'autre ne
| s'affiche nulle part (l'icône de catégorie est déduite du nom par
| CategoryIcon, et l'emoji d'article n'est lu par aucune vue) : de la
| saisie perdue, sur un formulaire déjà dense. Ce test fige leur absence.
|
| Il fige aussi deux ajouts demandés par le fondateur :
|   - la photo d'un article peut venir directement de l'appareil photo
|     (capture="environment"), pas seulement de la galerie ;
|   - un menu sans couverture envoyée affiche l'icône du métier en
|     filigrane plutôt qu'un bandeau vide.
|--------------------------------------------------------------------------
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\Tests\TestCase;

class MenuFormPresentationTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));
    }

    private function menu(string $tenantId = 't-1', array $attrs = []): Menu
    {
        return Menu::create(array_merge([
            'tenant_id' => $tenantId, 'name' => 'Carte', 'alias' => 'carte-'.uniqid(),
            'currency' => 'HTG', 'is_active' => true,
        ], $attrs));
    }

    public function test_the_creation_form_offers_no_emoji_field(): void
    {
        $this->patron();

        $html = $this->get(route('tagtoa.menu.dashboard.create'))->assertOk()->getContent();

        $this->assertStringNotContainsString('[icon]', $html);
        $this->assertStringNotContainsString('[emoji]', $html);
        $this->assertStringNotContainsString('placeholder="🍔"', $html);
    }

    public function test_the_edit_form_of_a_menu_with_existing_emoji_data_still_renders_without_the_field(): void
    {
        $this->patron();
        $menu = $this->menu();
        $cat = Category::create(['menu_id' => $menu->id, 'name' => 'Plats', 'icon' => '🍽️', 'is_active' => true]);
        Item::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Griot',
            'price' => 250, 'emoji' => '🍖',
        ]);

        // Une catégorie/un article qui portent encore un emoji d'avant cette
        // évolution ne doivent jamais faire planter l'édition du menu.
        $html = $this->get(route('tagtoa.menu.dashboard.edit', $menu->id))->assertOk()->getContent();

        $this->assertStringNotContainsString('[icon]', $html);
        $this->assertStringNotContainsString('[emoji]', $html);
    }

    public function test_the_item_photo_field_accepts_a_direct_camera_capture(): void
    {
        $this->patron();

        $html = $this->get(route('tagtoa.menu.dashboard.create'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/name="cats\[CIDX\]\[items\]\[IIDX\]\[image\]"[^>]*capture="environment"/',
            $html
        );
    }

    public function test_a_menu_without_a_cover_shows_the_business_type_icon_as_a_watermark(): void
    {
        $menu = $this->menu('t-2', ['type' => 'bar']);

        $html = $this->get('/menu/'.$menu->alias)->assertOk()->getContent();

        // La classe `.cover-fallback` est déclarée dans le <style> de toute
        // page (qu'elle serve ou non) : on cherche la BALISE réellement
        // rendue, pas la simple présence du mot dans la feuille de style.
        $this->assertStringContainsString('<i class="cover-fallback fa-solid fa-martini-glass"', $html);
    }

    public function test_a_menu_with_a_cover_never_shows_the_fallback_watermark(): void
    {
        $menu = $this->menu('t-3', ['cover_path' => 'tagtoa/menu-covers/vraie-photo.jpg']);

        $html = $this->get('/menu/'.$menu->alias)->assertOk()->getContent();

        $this->assertStringNotContainsString('<i class="cover-fallback', $html);
    }
}
