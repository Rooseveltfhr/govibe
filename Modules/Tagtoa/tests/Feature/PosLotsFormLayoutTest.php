<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA POS — pos/lots.blade.php rejoint la fondation partagée
|--------------------------------------------------------------------------
| Cinquième réapparition du défaut cité par le commit de fondation : cet
| écran redéfinissait sa propre grille de champs courts (.pf, largeur
| minmax(120px,1fr) au lieu de 130px) et sa propre classe de champ (.ic) au
| lieu des .inp/.sel partagés — l'un des trois exemples NOMMÉS par ce
| commit (« pos/settings, pos/lots, stand/reseller »).
|
| Migration markup/CSS seule : aucun champ, aucune validation, aucune
| logique serveur n'a changé (voir BatchController@store, non touché).
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\Tests\TestCase;

class PosLotsFormLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function ecran(): string
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-1', 'name' => 'Roosevelt']));

        return $this->get(route('tagtoa.pos.lots'))->assertOk()->getContent();
    }

    public function test_the_form_uses_the_shared_short_fields_grid_not_a_local_one(): void
    {
        $html = $this->ecran();

        $this->assertStringContainsString('<div class="pf">', $html);
        $this->assertStringNotContainsString('grid-template-columns:repeat(auto-fit,minmax(120px,1fr))', $html);
    }

    public function test_fields_use_the_shared_input_and_select_classes_not_the_local_ic_one(): void
    {
        $html = $this->ecran();

        $this->assertStringContainsString('<select class="sel" id="lProduit"', $html);
        $this->assertStringContainsString('<input class="inp" id="lQte"', $html);
        $this->assertStringContainsString('<input class="inp" id="lPeremp"', $html);
        $this->assertStringContainsString('<input class="inp" id="lRecu"', $html);
        $this->assertStringContainsString('<input class="inp" id="lNote"', $html);
        // Préfixe précis (guillemet fermant) : évite tout faux positif avec
        // une autre classe qui commencerait aussi par « ic ».
        $this->assertStringNotContainsString('class="ic"', $html);
        $this->assertStringNotContainsString('.ic{width:100%', $html);
    }

    public function test_the_product_field_still_spans_two_columns_via_the_shared_w2_helper(): void
    {
        $html = $this->ecran();

        $this->assertStringContainsString('<div class="w2">', $html);
        // La RÈGLE .pf .w2{grid-column:span 2} reste légitimement dans le
        // <style> — c'est le style EN LIGNE qui ne doit plus exister.
        $this->assertStringNotContainsString('style="grid-column:span 2"', $html);
    }
}
