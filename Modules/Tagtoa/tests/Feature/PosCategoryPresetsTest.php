<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA POS — des rayons suggérés, pas un champ vide à inventer
|--------------------------------------------------------------------------
| L'écran « Nouveau rayon » ne proposait que des icônes, jamais de nom : un
| marchand devant un champ vide n'invente rien et n'en crée aucun (le même
| défaut déjà corrigé côté MENU par BusinessProfile::categories). Un clic sur
| une suggestion (Boisson, Alimentation, Légumes, Alcool…) pré-remplit
| désormais le nom — jamais à la place de la saisie manuelle, qui reste
| entièrement libre.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Support\Catalog\CategoryPresets;
use Modules\Tagtoa\Tests\TestCase;

class PosCategoryPresetsTest extends TestCase
{
    use RefreshDatabase;

    private function ecran(): string
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-1', 'name' => 'Roosevelt']));

        return $this->get(route('tagtoa.pos.categories'))->assertOk()->getContent();
    }

    public function test_every_common_preset_appears_as_a_one_click_suggestion(): void
    {
        $html = $this->ecran();

        foreach (CategoryPresets::COMMON as $nom) {
            // Le nom part dans un attribut HTML : Blade l'échappe (« & » →
            // « &amp; »), comme tout le reste de cette vue.
            $this->assertStringContainsString('data-nom="'.e($nom).'"', $html);
        }
    }

    public function test_a_suggestion_only_fills_the_name_field_it_never_submits_by_itself(): void
    {
        $html = $this->ecran();

        $this->assertStringContainsString("champ.value = b.dataset.nom;", $html);
        // Le champ manuel reste intact : aucune valeur imposée dessus.
        $this->assertStringContainsString('<input class="ic" id="cname" name="name" required maxlength="80"', $html);
    }
}
