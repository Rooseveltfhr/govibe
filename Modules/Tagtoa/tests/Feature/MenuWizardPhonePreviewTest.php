<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA MENU — l'étape « Aperçu » de l'assistant montre un téléphone
|--------------------------------------------------------------------------
| wizardApercu() est du JS pur (relit le DOM déjà rempli, aucun appel
| serveur) — non testable ici. Ce qui l'est : que les blocs qu'il remplit
| existent bien dans la page (phoneScreen/phoneName/phoneLogo/…), que le
| logo gagne enfin un aperçu en direct (previewLogo(), absent avant ce
| correctif — un logo fraîchement choisi ne s'affichait nulle part avant
| l'enregistrement), et que rien de tout cela ne fuit vers le formulaire
| classique, qui n'a pas d'étape « Aperçu ».
|--------------------------------------------------------------------------
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\Tests\TestCase;

class MenuWizardPhonePreviewTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));
    }

    public function test_the_wizard_renders_a_phone_frame_for_the_preview_step(): void
    {
        $this->patron();

        $html = $this->get(route('tagtoa.menu.dashboard.wizard'))->assertOk()->getContent();

        foreach (['class="phone-frame"', 'id="phoneScreen"', 'id="phoneName"', 'id="phoneType"', 'id="phoneLogo"', 'id="phoneTabs"', 'id="phoneItems"'] as $morceau) {
            $this->assertStringContainsString($morceau, $html);
        }
    }

    public function test_the_wizard_calls_the_preview_function_when_reaching_that_step(): void
    {
        $this->patron();

        $html = $this->get(route('tagtoa.menu.dashboard.wizard'))->assertOk()->getContent();

        $this->assertStringContainsString('if (n === 6) { wizardApercu(); }', $html);
    }

    public function test_a_freshly_chosen_logo_gets_a_live_preview(): void
    {
        $this->patron();

        $html = $this->get(route('tagtoa.menu.dashboard.wizard'))->assertOk()->getContent();

        $this->assertStringContainsString('onchange="previewLogo(this)"', $html);
        $this->assertStringContainsString('id="logoPreview"', $html);
    }

    public function test_the_classic_form_never_shows_the_phone_preview(): void
    {
        $this->patron();

        $html = $this->get(route('tagtoa.menu.dashboard.create'))->assertOk()->getContent();

        $this->assertStringNotContainsString('class="phone-frame"', $html);
        // Le champ logo lui-même reste partagé : seul le CADRE de l'aperçu
        // doit être propre à l'assistant.
        $this->assertStringContainsString('id="logoPreview"', $html);
    }
}
