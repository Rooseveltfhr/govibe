<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA MENU — l'assistant de création en sept étapes
|--------------------------------------------------------------------------
| Même création que le formulaire classique (menu.dashboard.create) :
| l'assistant ne fait que la re-présenter en sept écrans (Établissement,
| Info, Paramètres, Catégories, Plats, Aperçu, Publier) via le même
| gabarit partagé (menu/_form-body.blade.php) et le même formulaire, posté
| vers la même route que toujours. On ne teste donc pas à nouveau
| l'enregistrement lui-même (voir MenuInheritsBusinessTest, PosMenuTest,
| etc.) : seulement que l'assistant s'affiche, propose bien les sept étapes,
| et que le pré-remplissage depuis le commerce y arrive aussi.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Services\Business\BusinessService;
use Modules\Tagtoa\App\Support\Tenant;
use Modules\Tagtoa\Tests\TestCase;

class MenuWizardTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenantId = 'compte-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId]));
        Tenant::flush();
    }

    public function test_the_wizard_page_shows_the_seven_steps(): void
    {
        $this->patron();

        $html = $this->get(route('tagtoa.menu.dashboard.wizard'))->assertOk()->getContent();

        foreach (['Établissement', 'Info', 'Paramètres', 'Catégories', 'Plats', 'Aperçu', 'Publier'] as $etape) {
            $this->assertStringContainsString($etape, $html);
        }
    }

    public function test_the_wizard_posts_to_the_same_store_route_as_the_classic_form(): void
    {
        $this->patron();

        $html = $this->get(route('tagtoa.menu.dashboard.wizard'))->assertOk()->getContent();

        $this->assertStringContainsString('action="'.route('tagtoa.menu.dashboard.store').'"', $html);
    }

    public function test_the_wizard_also_prefills_from_the_business(): void
    {
        $this->patron();
        app(BusinessService::class)->create('compte-1', [
            'name' => 'Bar Puya', 'type' => 'bar', 'address' => '2861 Somerset Drive',
            'phone' => '19548361449', 'currency' => 'USD',
        ]);

        $html = $this->get(route('tagtoa.menu.dashboard.wizard'))->assertOk()->getContent();

        $this->assertStringContainsString('value="2861 Somerset Drive"', $html);
        $this->assertMatchesRegularExpression('/<option value="bar" selected>/', $html);
    }

    public function test_the_index_page_links_to_the_wizard(): void
    {
        $this->patron();

        $html = $this->get(route('tagtoa.menu.dashboard.index'))->assertOk()->getContent();

        $this->assertStringContainsString(route('tagtoa.menu.dashboard.wizard'), $html);
    }

    public function test_the_classic_form_still_renders_without_the_wizard_navigation(): void
    {
        $this->patron();

        $html = $this->get(route('tagtoa.menu.dashboard.create'))->assertOk()->getContent();

        $this->assertStringNotContainsString('wizard-nav', $html);
        $this->assertStringNotContainsString('wizard-shell', $html);
    }
}
