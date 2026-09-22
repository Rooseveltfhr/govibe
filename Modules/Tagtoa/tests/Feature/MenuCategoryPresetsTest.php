<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA MENU — les mêmes rayons courants qu'en caisse, en plus du métier
|--------------------------------------------------------------------------
| MENU proposait déjà des catégories par métier (BusinessProfile::categories
| — « Entrées, Plats… » pour un restaurant). Ce qui manquait : les rayons
| génériques d'un petit commerce (Boisson, Alimentation, Légumes, Alcool…),
| identiques à ceux maintenant proposés côté POS (CategoryPresets::COMMON) —
| une seule liste, lue par les deux écrans.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Support\Catalog\CategoryPresets;
use Modules\Tagtoa\Tests\TestCase;

class MenuCategoryPresetsTest extends TestCase
{
    use RefreshDatabase;

    private function ecran(): string
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-1', 'name' => 'Roosevelt']));

        return $this->get(route('tagtoa.menu.dashboard.create'))->assertOk()->getContent();
    }

    public function test_the_common_pos_presets_are_available_to_the_javascript_renderer(): void
    {
        $html = $this->ecran();

        $this->assertStringContainsString('var CATEGORY_PRESETS_COMMON = ', $html);

        // @json échappe les accents et « & » en JSON pur (é, &…) :
        // on décode la valeur réellement rendue plutôt que de deviner son
        // échappement exact.
        $this->assertMatchesRegularExpression(
            '/var CATEGORY_PRESETS_COMMON = (\[.*?\]);/',
            $html,
            'Variable JS introuvable dans le HTML rendu.'
        );
        preg_match('/var CATEGORY_PRESETS_COMMON = (\[.*?\]);/', $html, $m);
        $this->assertSame(CategoryPresets::COMMON, json_decode($m[1], true));
    }

    public function test_the_generic_list_is_appended_after_the_profile_specific_one(): void
    {
        $html = $this->ecran();

        $this->assertStringContainsString(
            "(currentProfile().categories || []).concat(CATEGORY_PRESETS_COMMON).forEach(function(name){",
            $html
        );
    }

    public function test_presets_never_offer_the_same_name_twice(): void
    {
        $html = $this->ecran();

        $this->assertStringContainsString('dejaSuggere.indexOf(cle) !== -1', $html);
        $this->assertStringContainsString('dejaSuggere.push(cle);', $html);
    }
}
