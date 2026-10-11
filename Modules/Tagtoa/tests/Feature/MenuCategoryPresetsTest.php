<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA MENU — les catégories suggérées viennent UNIQUEMENT du métier
|--------------------------------------------------------------------------
| Signalé : un restaurant se voyait suggérer « Nettoyage & hygiène » et
| « Cosmétique » — des rayons de boutique/épicerie (CategoryPresets::COMMON),
| mélangés aux catégories de son propre métier. Un restaurant ne vend ni
| détergent ni cosmétique : la liste proposée doit rester celle de
| BusinessProfile, sans rien d'autre mêlé dedans. Ce mélange reste correct
| côté POS (écran Catégories), qui n'a pas de métier déclaré par article —
| seul l'assistant MENU est concerné ici.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Support\Menu\BusinessProfile;
use Modules\Tagtoa\Tests\TestCase;

class MenuCategoryPresetsTest extends TestCase
{
    use RefreshDatabase;

    private function ecran(): string
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-1', 'name' => 'Roosevelt']));

        return $this->get(route('tagtoa.menu.dashboard.create'))->assertOk()->getContent();
    }

    public function test_the_generic_shop_presets_are_no_longer_mixed_into_the_wizard(): void
    {
        $html = $this->ecran();

        $this->assertStringNotContainsString('CATEGORY_PRESETS_COMMON', $html);
    }

    public function test_the_suggested_categories_come_only_from_the_business_profile(): void
    {
        $html = $this->ecran();

        $this->assertStringContainsString(
            "(currentProfile().categories || []).forEach(function(name){",
            $html
        );
    }

    public function test_presets_never_offer_the_same_name_twice(): void
    {
        $html = $this->ecran();

        $this->assertStringContainsString('dejaSuggere.indexOf(cle) !== -1', $html);
        $this->assertStringContainsString('dejaSuggere.push(cle);', $html);
    }

    public function test_every_type_still_has_its_own_categories_to_suggest(): void
    {
        // Sans la liste générique en complément, un métier dont la liste
        // serait vide se retrouverait sans AUCUNE suggestion.
        foreach (BusinessProfile::PROFILES as $type => $profile) {
            $this->assertNotEmpty($profile['categories'], "$type : aucune catégorie à suggérer sans la liste générique.");
        }
    }
}
