<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA — fondation du système de conception partagé
|--------------------------------------------------------------------------
| Signalé avec des captures d'écran : les formulaires de TAGTOA n'ont pas
| l'air professionnels sur mobile — champs trop grands ou mal alignés,
| boutons qui se coupent sur plusieurs lignes. En creusant, ce n'était pas
| UN défaut mais CINQ implémentations différentes du même composant, une
| par module :
|   - un sélecteur « champs courts » (.pf) redéfini avec une largeur
|     différente dans pos/settings, pos/lots, stand/reseller… ;
|   - un interrupteur on/off (.switch) réinventé sous quatre autres noms
|     (.sells, .catchip, .sw, une case à cocher nue) ;
|   - un sélecteur de couleur haut de 38px dans POS et de 48px dans MENU.
|
| Ces tests protègent la FONDATION commune (posée une seule fois dans
| layouts/dashboard.blade.php) que chaque module migre ensuite vers elle,
| plutôt que de continuer à réinventer la sienne.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\Tests\TestCase;

class DesignSystemFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function ecran(): string
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-1', 'name' => 'Roosevelt']));

        return $this->get('/tagtoa/home')->assertOk()->getContent();
    }

    public function test_the_short_fields_grid_is_declared_once_globally(): void
    {
        $html = $this->ecran();

        $this->assertStringContainsString('.pf{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr))', $html);
        $this->assertStringContainsString('.pf label,.pf .lbl{', $html);
    }

    public function test_a_single_color_picker_height_applies_everywhere(): void
    {
        $html = $this->ecran();

        $this->assertStringContainsString('.inp[type="color"]{padding:4px;height:44px', $html);
    }

    public function test_a_single_multi_select_chip_component_exists(): void
    {
        $html = $this->ecran();

        $this->assertStringContainsString('.chipwrap{display:flex;flex-wrap:wrap', $html);
        $this->assertStringContainsString('.chip input:checked+span{', $html);
    }

    public function test_fields_and_buttons_meet_the_touch_target_minimum_but_small_buttons_stay_compact(): void
    {
        $html = $this->ecran();

        $this->assertStringContainsString(
            "@media(hover:none){.nav a,.nav summary,.subnav a,.top .burger,.top .home,.top .out,.inp,.sel,.btn:not(.btn-sm){min-height:44px}}",
            $html
        );
    }
}
