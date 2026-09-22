<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA MENU — « Langues du menu »
|--------------------------------------------------------------------------
| Un menu peut restreindre les langues offertes au client à un sous-ensemble
| des 4 langues globales de TAGTOA. Le calcul pur (quelles langues survivent,
| pourquoi la langue par défaut ne peut jamais être retirée) est couvert par
| LocaleLanguagesTest ; ici : le câblage formulaire → base, et le sélecteur
| réellement affiché au client sur la page publique.
|--------------------------------------------------------------------------
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\Tests\TestCase;

class MenuLanguagesTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));
    }

    private function menu(array $attrs = []): Menu
    {
        return Menu::create(array_merge([
            'tenant_id' => 't-1', 'name' => 'Lounge', 'alias' => 'lounge-'.uniqid(),
            'currency' => 'HTG', 'is_active' => true,
        ], $attrs));
    }

    public function test_saving_the_menu_form_persists_the_chosen_languages(): void
    {
        $this->patron();
        $menu = $this->menu();

        $this->put(route('tagtoa.menu.dashboard.update', $menu->id), [
            'name' => 'Lounge', 'currency' => 'HTG', 'form_end' => '1', 'languages' => ['fr', 'ht'],
        ])->assertRedirect();

        $this->assertSame(['fr', 'ht'], $menu->fresh()->languages);
    }

    public function test_saving_without_the_default_locale_keeps_it_anyway(): void
    {
        $this->patron();
        $menu = $this->menu();

        $this->put(route('tagtoa.menu.dashboard.update', $menu->id), [
            'name' => 'Lounge', 'currency' => 'HTG', 'form_end' => '1', 'languages' => ['en'],
        ])->assertRedirect();

        $this->assertContains('fr', $menu->fresh()->languages);
    }

    public function test_the_creation_form_shows_a_checkbox_per_language(): void
    {
        $this->patron();

        $html = $this->get(route('tagtoa.menu.dashboard.create'))->assertOk()->getContent();

        $this->assertStringContainsString('name="languages[]"', $html);
        $this->assertStringContainsString('Kreyòl', $html);
    }

    public function test_the_public_page_offers_no_switcher_when_the_menu_is_not_restricted(): void
    {
        $menu = $this->menu();

        $html = $this->get('/menu/'.$menu->alias)->assertOk()->getContent();

        // Sans restriction, les 4 langues sont offertes — le sélecteur existe.
        $this->assertStringContainsString('lang=fr', $html);
        $this->assertStringContainsString('lang=es', $html);
    }

    public function test_the_public_page_offers_only_the_menu_s_chosen_languages(): void
    {
        $menu = $this->menu(['languages' => ['fr', 'ht']]);

        $html = $this->get('/menu/'.$menu->alias)->assertOk()->getContent();

        $this->assertStringContainsString('lang=fr', $html);
        $this->assertStringContainsString('lang=ht', $html);
        $this->assertStringNotContainsString('lang=en', $html);
        $this->assertStringNotContainsString('lang=es', $html);
    }

    public function test_the_public_page_hides_the_switcher_entirely_for_a_single_language_menu(): void
    {
        $menu = $this->menu(['languages' => ['fr']]);

        $html = $this->get('/menu/'.$menu->alias)->assertOk()->getContent();

        $this->assertStringNotContainsString('lang=fr', $html);
    }
}
