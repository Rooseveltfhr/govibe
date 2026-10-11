<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA — « Commencer » (démarrage rapide) ne crée plus un menu sans type
|--------------------------------------------------------------------------
| Signalé (analyse de clarté) : le bouton le plus visible du tableau de
| bord d'un nouveau marchand (hub/index.blade.php) créait un menu sans
| aucun type — BusinessProfile retombait sur « Autre », et le <select> du
| gros formulaire présélectionnait silencieusement « Restaurant » au
| premier « Modifier », quel que soit le vrai métier du marchand.
|
| Ce démarrage rapide ne connaissait pas non plus la règle « un seul menu
| par commerce » : un second passage par « Commencer » pouvait empiler un
| second menu.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Services\Business\BusinessService;
use Modules\Tagtoa\App\Support\Tenant;
use Modules\Tagtoa\Tests\TestCase;

class HubQuickStartTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));
        Tenant::flush();
    }

    public function test_the_quick_start_screen_requires_a_type_for_the_menu_card(): void
    {
        $this->patron();

        $html = $this->get(route('tagtoa.start'))->assertOk()->getContent();

        $this->assertStringContainsString('name="type"', $html);
        $this->assertStringContainsString('— Choisissez —', $html);
    }

    public function test_creating_a_menu_without_a_type_is_refused(): void
    {
        $this->patron();

        $this->post(route('tagtoa.start.store'), [
            'kind' => 'menu', 'name' => 'Lakay Grill',
        ])->assertSessionHasErrors('type');

        $this->assertSame(0, Menu::count());
    }

    public function test_creating_a_menu_with_a_type_stores_it(): void
    {
        $this->patron();

        $this->post(route('tagtoa.start.store'), [
            'kind' => 'menu', 'name' => 'Lakay Grill', 'type' => 'restaurant',
        ])->assertRedirect();

        $menu = Menu::firstOrFail();
        $this->assertSame('restaurant', $menu->type);
    }

    public function test_a_merchant_who_already_declared_their_business_is_not_asked_again(): void
    {
        $this->patron();
        app(BusinessService::class)->create('t-1', ['name' => 'Salon Lakay', 'type' => 'salon', 'currency' => 'HTG']);

        // Un envoi direct sans `type` (comme le ferait un marchand qui n'a
        // pas touché la liste déjà pré-remplie par son commerce) hérite du
        // type du commerce plutôt que d'échouer.
        $this->post(route('tagtoa.start.store'), [
            'kind' => 'menu', 'name' => 'Salon Lakay',
        ])->assertSessionDoesntHaveErrors('type');

        $this->assertSame('salon', Menu::firstOrFail()->type);
    }

    public function test_a_second_quick_start_does_not_create_a_second_menu(): void
    {
        $this->patron();
        $existing = Menu::create([
            'tenant_id' => 't-1', 'name' => 'Déjà là', 'alias' => 'deja-la',
            'type' => 'bar', 'currency' => 'HTG', 'is_active' => true,
        ]);

        $this->post(route('tagtoa.start.store'), [
            'kind' => 'menu', 'name' => 'Encore un menu', 'type' => 'restaurant',
        ])->assertRedirect(route('tagtoa.menu.dashboard.edit', $existing->id));

        $this->assertSame(1, Menu::count());
        $this->assertSame('bar', $existing->fresh()->type);
    }
}
