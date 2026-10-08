<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA POS — pos/products.blade.php rejoint la fondation partagée
|--------------------------------------------------------------------------
| Cet écran redéfinissait sa propre grille de champs courts (.pf, largeur
| minmax(100px,1fr) au lieu de 130px) et sa propre classe de champ (.ic) au
| lieu des .inp/.sel partagés — l'exact exemple « pos/settings, pos/lots » cité
| par le commit de fondation, ici retrouvé une troisième fois. Le sélecteur de
| couleur du bouton d'article était figé à 38px de haut par un style en ligne,
| l'exemple même (« 38px dans POS ») qui a motivé la règle .inp[type=color]
| partagée à 44px.
|
| Migration markup/CSS seule : aucun champ, aucune validation, aucune logique
| serveur n'a changé (voir DashboardController@addProduct/@saveProducts,
| non touchés).
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\Tests\TestCase;

class PosProductsFormLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function ecran(): string
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-1', 'name' => 'Roosevelt']));
        $terminal = Terminal::create(['tenant_id' => 't-1', 'name' => 'Caisse', 'currency' => 'HTG', 'is_active' => true]);

        return $this->get(route('tagtoa.pos.products.terminal', $terminal->id))->assertOk()->getContent();
    }

    public function test_the_add_form_uses_the_shared_short_fields_grid_not_a_local_one(): void
    {
        $html = $this->ecran();

        $this->assertStringContainsString('<div class="pf">', $html);
        $this->assertStringNotContainsString('grid-template-columns:repeat(auto-fit,minmax(100px,1fr))', $html);
    }

    public function test_text_fields_use_the_shared_input_class_not_the_local_ic_one(): void
    {
        $html = $this->ecran();

        $this->assertStringContainsString('<input class="inp w2" id="aName"', $html);
        $this->assertStringNotContainsString('class="ic', $html);
        $this->assertStringNotContainsString('.ic{width:100%', $html);
    }

    public function test_dropdowns_use_the_shared_select_class(): void
    {
        $html = $this->ecran();

        $this->assertStringContainsString('<select class="sel" id="aUnit"', $html);
        $this->assertStringContainsString('<select class="sel" id="aRayon"', $html);
        $this->assertStringNotContainsString('<select class="ic"', $html);
    }

    public function test_the_button_color_picker_no_longer_forces_a_38px_height(): void
    {
        $html = $this->ecran();

        $this->assertStringNotContainsString('height:38px', $html);
        // La hauteur de 44px vient de la règle partagée .inp[type="color"]
        // (déjà protégée par DesignSystemFoundationTest) : cet écran n'a
        // plus besoin — et ne doit plus avoir — sa propre règle.
        $this->assertStringContainsString('id="aColor" name="color" type="color"', $html);
    }
}
