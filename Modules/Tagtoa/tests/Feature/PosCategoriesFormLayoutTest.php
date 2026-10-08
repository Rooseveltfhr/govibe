<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA POS — pos/categories.blade.php rejoint la fondation partagée
|--------------------------------------------------------------------------
| Même défaut que pos/products (déjà migré) : cet écran redéfinissait sa
| propre grille de champs courts (.pf, largeur minmax(110px,1fr) au lieu de
| 130px) et sa propre classe de champ (.ic) au lieu des .inp partagés. Le
| sélecteur de couleur du rayon était figé à 38px de haut par un style en
| ligne, sur les deux formulaires (création et édition).
|
| Migration markup/CSS seule : aucun champ, aucune validation, aucune
| logique serveur n'a changé (voir CategoryController@store/@update, non
| touchés).
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Pos\Category;
use Modules\Tagtoa\Tests\TestCase;

class PosCategoriesFormLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function ecran(): string
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-1', 'name' => 'Roosevelt']));
        Category::create(['tenant_id' => 't-1', 'name' => 'Boissons', 'color' => '#2cb809', 'sort' => 0, 'is_active' => true]);

        return $this->get(route('tagtoa.pos.categories'))->assertOk()->getContent();
    }

    public function test_the_forms_use_the_shared_short_fields_grid_not_a_local_one(): void
    {
        $html = $this->ecran();

        $this->assertStringContainsString('<div class="pf">', $html);
        $this->assertStringNotContainsString('grid-template-columns:repeat(auto-fit,minmax(110px,1fr))', $html);
    }

    public function test_text_and_color_fields_use_the_shared_input_class_not_the_local_ic_one(): void
    {
        $html = $this->ecran();

        $this->assertStringContainsString('<input class="inp" id="cname"', $html);
        $this->assertStringContainsString('<input class="inp" id="ccolor"', $html);
        // Préfixe précis (guillemet fermant) : class="icones" (grille d'icônes,
        // légitime) commence aussi par « ic » et donnerait un faux positif.
        $this->assertStringNotContainsString('class="ic"', $html);
        $this->assertStringNotContainsString('.ic{width:100%', $html);
    }

    public function test_the_color_picker_no_longer_forces_a_38px_height_on_either_form(): void
    {
        $html = $this->ecran();

        $this->assertStringNotContainsString('height:38px', $html);
        // La hauteur de 44px vient de la règle partagée .inp[type="color"]
        // (déjà protégée par DesignSystemFoundationTest) : cet écran n'a
        // plus besoin — et ne doit plus avoir — sa propre règle.
        $this->assertStringContainsString('id="ccolor" name="color" type="color"', $html);
    }

    public function test_the_category_name_still_spans_two_columns_via_the_shared_w2_helper(): void
    {
        $html = $this->ecran();

        $this->assertStringContainsString('<div class="w2">', $html);
        // La RÈGLE .pf .w2{grid-column:span 2} reste légitimement dans le
        // <style> — c'est le style EN LIGNE sur le <div> qui ne doit plus
        // exister, remplacé par la classe partagée.
        $this->assertStringNotContainsString('style="grid-column:span 2"', $html);
    }
}
