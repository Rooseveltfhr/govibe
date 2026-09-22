<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA MENU — l'assistant présente le type d'établissement en cartes
|--------------------------------------------------------------------------
| Demandé sur une maquette : au lieu d'un <select> nu, une grille de cartes
| (icône + nom) pour choisir le type. Le <select> reste le SEUL champ
| réellement soumis — la grille n'est qu'une autre façon de le remplir, et
| ne doit exister QUE sur l'assistant : le formulaire classique garde son
| <select> visible tel quel, personne n'a demandé à le changer.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\Tests\TestCase;

class MenuWizardTypePickerTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));
    }

    public function test_the_wizard_offers_a_card_for_every_menu_type(): void
    {
        $this->patron();

        $html = $this->get(route('tagtoa.menu.dashboard.wizard'))->assertOk()->getContent();

        foreach (Menu::TYPES as $meta) {
            $this->assertStringContainsString(__($meta['label']), $html);
        }
        $this->assertStringContainsString('class="type-grid"', $html);
        $this->assertStringContainsString('choseType(', $html);
    }

    public function test_the_classic_form_never_shows_the_type_card_grid(): void
    {
        $this->patron();

        $html = $this->get(route('tagtoa.menu.dashboard.create'))->assertOk()->getContent();

        $this->assertStringNotContainsString('class="type-grid"', $html);
    }

    public function test_the_wizard_still_submits_the_type_through_the_underlying_select(): void
    {
        $this->patron();

        $html = $this->get(route('tagtoa.menu.dashboard.wizard'))->assertOk()->getContent();

        // Le <select> existe toujours dans le DOM (masqué visuellement,
        // jamais retiré) : c'est lui qui part avec le formulaire.
        $this->assertMatchesRegularExpression('/<select class="sel" name="type">/', $html);
    }

    public function test_the_wizard_header_and_stepper_are_present(): void
    {
        $this->patron();

        $html = $this->get(route('tagtoa.menu.dashboard.wizard'))->assertOk()->getContent();

        $this->assertStringContainsString('Créer votre menu en quelques étapes', $html);
        $this->assertStringContainsString('wizard-badge', $html);
    }
}
