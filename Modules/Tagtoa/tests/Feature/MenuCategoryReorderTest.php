<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA MENU — les catégories se réordonnent par glisser-déposer
|--------------------------------------------------------------------------
| La poignée (menu/_form-body.blade.php, #cattpl) et le glisser-déposer
| lui-même sont du JS pur, non testables ici. Ce qui l'est — et qui compte
| vraiment — c'est que l'ordre soit VRAIMENT enregistré : le navigateur
| sérialise un formulaire dans l'ordre du DOM à l'envoi, donc un glissé se
| traduit par des clés cats[] soumises dans un ordre qui ne correspond plus
| à leur valeur numérique d'origine (une catégorie créée en 3ᵉ position,
| glissée en tête, arrive toujours sous la clé cats[2] — seul son RANG
| D'ENVOI a changé). C'est ce rang, jamais la clé, qui doit devenir `sort`.
|--------------------------------------------------------------------------
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\Tests\TestCase;

class MenuCategoryReorderTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));
    }

    private function menu(string $tenantId = 't-1'): Menu
    {
        return Menu::create(['tenant_id' => $tenantId, 'name' => 'Lounge', 'alias' => 'lounge-'.uniqid(), 'currency' => 'HTG']);
    }

    public function test_the_save_order_of_submitted_categories_becomes_their_stored_sort(): void
    {
        $this->patron();
        $menu = $this->menu();

        // Simule un glisser-déposer : « Boissons » (créée en 3e, clé 2)
        // envoyée AVANT « Plats » (créée en 1re, clé 0) — c'est l'ORDRE
        // D'ENVOI du tableau associatif PHP qui porte le glissé, pas les
        // clés elles-mêmes (elles ne bougent jamais).
        $this->put(route('tagtoa.menu.dashboard.update', $menu->id), [
            'name' => 'Lounge', 'currency' => 'HTG', 'form_end' => '1',
            'cats' => [
                2 => ['name' => 'Boissons'],
                0 => ['name' => 'Plats'],
            ],
        ])->assertRedirect();

        $boissons = Category::where('menu_id', $menu->id)->where('name', 'Boissons')->firstOrFail();
        $plats = Category::where('menu_id', $menu->id)->where('name', 'Plats')->firstOrFail();

        $this->assertLessThan($plats->sort, $boissons->sort);
    }

    public function test_categories_load_back_in_their_dragged_order(): void
    {
        $this->patron();
        $menu = $this->menu();

        $this->put(route('tagtoa.menu.dashboard.update', $menu->id), [
            'name' => 'Lounge', 'currency' => 'HTG', 'form_end' => '1',
            'cats' => [
                5 => ['name' => 'Desserts'],
                1 => ['name' => 'Entrées'],
                3 => ['name' => 'Plats'],
            ],
        ])->assertRedirect();

        $ordre = $menu->fresh()->categories()->orderBy('sort')->pluck('name')->all();

        $this->assertSame(['Desserts', 'Entrées', 'Plats'], $ordre);
    }

    public function test_the_form_carries_a_drag_handle_per_category(): void
    {
        $this->patron();

        $html = $this->get(route('tagtoa.menu.dashboard.create'))->assertOk()->getContent();

        $this->assertStringContainsString('draggable="true"', $html);
        $this->assertStringContainsString('draghandle', $html);
    }
}
