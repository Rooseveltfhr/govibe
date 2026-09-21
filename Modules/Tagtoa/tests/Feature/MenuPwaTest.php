<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA MENU — offline/PWA sur le menu public. La connexion en Haïti (et
| ailleurs) est souvent lente ou coupée : un client qui a déjà ouvert une
| carte doit pouvoir la rouvrir sans réseau, et une commande composée hors
| ligne ne doit jamais se perdre. Ces tests figent le manifeste, le service
| worker et l'isolation entre menus (chaque menu son propre service worker,
| jamais mêlé à celui du voisin).
|--------------------------------------------------------------------------
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\Tests\TestCase;

class MenuPwaTest extends TestCase
{
    use RefreshDatabase;

    private function menu(array $attrs = []): Menu
    {
        return Menu::create(array_merge([
            'tenant_id' => 't-1', 'name' => 'Lounge 509', 'alias' => 'lounge-'.uniqid(),
            'currency' => 'HTG', 'is_active' => true,
        ], $attrs));
    }

    public function test_the_manifest_names_this_menu_and_scopes_to_its_own_url(): void
    {
        $menu = $this->menu(['accent_color' => '#ff0000']);

        $json = $this->getJson(route('tagtoa.menu.manifest', $menu->alias))->assertOk()->json();

        $this->assertSame('Lounge 509 — TAGTOA Menu', $json['name']);
        $this->assertSame(url('/menu/'.$menu->alias), $json['scope']);
        $this->assertSame(url('/menu/'.$menu->alias), $json['start_url']);
        $this->assertSame('#ff0000', $json['theme_color']);
        $this->assertSame('standalone', $json['display']);
    }

    public function test_the_manifest_falls_back_to_a_safe_color_when_accent_is_invalid(): void
    {
        // Un accent_color corrompu ne doit jamais fabriquer un manifeste
        // invalide ou fuiter du HTML/CSS injecté dans le JSON.
        $menu = $this->menu(['accent_color' => '<script>alert(1)</script>']);

        $json = $this->getJson(route('tagtoa.menu.manifest', $menu->alias))->assertOk()->json();

        $this->assertSame('#2cb809', $json['theme_color']);
    }

    public function test_an_inactive_menu_has_no_public_manifest(): void
    {
        $menu = $this->menu(['is_active' => false]);

        $this->getJson(route('tagtoa.menu.manifest', $menu->alias))->assertNotFound();
    }

    public function test_the_icon_is_a_safe_svg_using_the_menus_initial(): void
    {
        $menu = $this->menu(['name' => 'Zanmi Lakay']);

        $response = $this->get(route('tagtoa.menu.icon', $menu->alias))->assertOk();

        $response->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('>Z<', $response->getContent());
    }

    public function test_the_icon_escapes_a_special_character_instead_of_breaking_the_svg(): void
    {
        // La première lettre d'un nom qui commence par « & » ou « < » doit
        // rester un SVG valide (le navigateur affiche l'icône), pas un
        // document XML cassé par un caractère brut.
        $menu = $this->menu(['name' => '&Bar']);

        $svg = $this->get(route('tagtoa.menu.icon', $menu->alias))->assertOk()->getContent();

        $this->assertStringContainsString('>&amp;<', $svg);
        $this->assertStringNotContainsString('>&<', $svg);
    }

    public function test_the_service_worker_is_scoped_to_this_menu_only(): void
    {
        $menu = $this->menu();

        $response = $this->get(route('tagtoa.menu.sw', $menu->alias))->assertOk();

        $response->assertHeader('Content-Type', 'application/javascript');
        $response->assertHeader('Service-Worker-Allowed', url('/menu/'.$menu->alias));
        $this->assertStringContainsString('tagtoa-menu-'.$menu->alias, $response->getContent());
    }

    public function test_the_service_worker_never_intercepts_non_get_requests(): void
    {
        // Une commande (POST) doit échouer nettement hors ligne pour que la
        // file d'attente côté client prenne le relais — jamais une réponse
        // mise en cache par erreur qui ferait croire qu'elle est passée.
        $menu = $this->menu();

        $sw = $this->get(route('tagtoa.menu.sw', $menu->alias))->getContent();

        $this->assertStringContainsString("req.method !== 'GET'", $sw);
    }

    public function test_two_menus_never_share_a_cache_name(): void
    {
        $a = $this->menu(['alias' => 'menu-a']);
        $b = $this->menu(['alias' => 'menu-b']);

        $swA = $this->get(route('tagtoa.menu.sw', $a->alias))->getContent();
        $swB = $this->get(route('tagtoa.menu.sw', $b->alias))->getContent();

        $this->assertStringContainsString('tagtoa-menu-menu-a', $swA);
        $this->assertStringContainsString('tagtoa-menu-menu-b', $swB);
        $this->assertStringNotContainsString('tagtoa-menu-menu-b', $swA);
    }

    public function test_the_public_page_registers_the_service_worker_scoped_to_its_own_url(): void
    {
        $menu = $this->menu();

        $html = $this->get('/menu/'.$menu->alias)->assertOk()->getContent();

        $this->assertStringContainsString(route('tagtoa.menu.sw', $menu->alias), $html);
        $this->assertStringContainsString('rel="manifest"', $html);
    }
}
