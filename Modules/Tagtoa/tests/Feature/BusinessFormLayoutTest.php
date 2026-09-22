<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA PARAMÈTRES — business/form.blade.php rejoint la fondation partagée
|--------------------------------------------------------------------------
| Nom/Type restaient empilés alors que Menu et Event groupent déjà la même
| paire (un nom + un type) côte à côte. « Ce que vous vendez » et
| « Vos catégories » réinventaient chacun leur propre pastille de choix
| multiple (.sells, .catchip) au lieu du composant .chip partagé. Et la
| grille des champs de taxe avait sa propre largeur de grille, différente
| de .pf utilisé ailleurs.
|
| La Devise reste un champ texte libre (pas un <select>) : c'est voulu — le
| marchand peut saisir une devise absente de la liste suggérée
| (BusinessTest::test_the_merchant_may_use_a_currency_we_do_not_list).
| Uniformiser ce champ aurait RETIRÉ une fonctionnalité déjà testée, donc
| on ne le touche pas.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\Tests\TestCase;

class BusinessFormLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function ecran(): string
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-1', 'name' => 'Roosevelt']));

        return $this->get(route('tagtoa.business.create'))->assertOk()->getContent();
    }

    public function test_name_and_type_sit_side_by_side(): void
    {
        $html = $this->ecran();

        $this->assertMatchesRegularExpression(
            '/<div class="row">\s*<div>\s*<label class="lbl"[^>]*>Nom du commerce/s',
            $html
        );
        $this->assertStringContainsString('name="type" id="btype"', $html);
    }

    public function test_what_you_sell_uses_the_shared_chip_component(): void
    {
        $html = $this->ecran();

        $this->assertStringContainsString('<div class="chipwrap">', $html);
        $this->assertStringContainsString('<label class="chip">', $html);
        $this->assertStringNotContainsString('class="sells"', $html);
        $this->assertStringNotContainsString('.sells input{', $html);
    }

    public function test_the_category_picker_uses_the_shared_chip_component_in_js_too(): void
    {
        $html = $this->ecran();

        $this->assertStringContainsString('<div id="cats" class="chipwrap"', $html);
        $this->assertStringContainsString("el.className = 'chip';", $html);
        // Note : le mot « catchip » reste cité comme exemple dans le
        // commentaire du composant .chip partagé (layouts/dashboard) — on
        // vérifie donc l'usage réel (class="catchip" ou la règle CSS), pas
        // la simple présence du mot.
        $this->assertStringNotContainsString('class="catchip"', $html);
        $this->assertStringNotContainsString('.catchip{', $html);
    }

    public function test_the_currency_field_stays_a_free_text_input_not_a_dropdown(): void
    {
        // Fonctionnalité déjà testée ailleurs (BusinessTest) : ne pas la
        // retirer au nom de la cohérence visuelle.
        $html = $this->ecran();

        $this->assertStringContainsString('<input class="inp" name="currency" list="devises"', $html);
        $this->assertStringNotContainsString('<select class="sel" name="currency"', $html);
    }

    public function test_the_tax_fields_use_the_shared_short_fields_grid(): void
    {
        $html = $this->ecran();

        $this->assertStringContainsString('<div id="taxBox" class="pf"', $html);
        $this->assertStringNotContainsString('grid-template-columns:repeat(auto-fit,minmax(180px,1fr))', $html);
    }
}
