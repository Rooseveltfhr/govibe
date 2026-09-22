<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA MENU — « Publier » (assistant) et « Partager ce menu » (édition)
|--------------------------------------------------------------------------
| Dernière pièce de la maquette. Le lien public, le QR et le code
| d'intégration n'existent qu'APRÈS l'enregistrement (le menu n'a pas
| d'alias avant) : ils vivent sur l'écran d'édition, jamais dans
| l'assistant de création, qui ne montre qu'un écran « prêt à publier ».
|--------------------------------------------------------------------------
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\Tests\TestCase;

class MenuPublishShareTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));
    }

    private function menu(string $tenantId = 't-1'): Menu
    {
        return Menu::create(['tenant_id' => $tenantId, 'name' => 'Lounge', 'alias' => 'lounge-'.uniqid(), 'currency' => 'HTG']);
    }

    /* ---------- assistant : écran « prêt à publier » ---------- */

    public function test_the_wizard_shows_a_ready_to_publish_screen(): void
    {
        $this->patron();

        $html = $this->get(route('tagtoa.menu.dashboard.wizard'))->assertOk()->getContent();

        $this->assertStringContainsString('Votre menu est prêt', $html);
    }

    public function test_the_wizard_s_submit_button_says_publish_not_save(): void
    {
        $this->patron();

        $html = $this->get(route('tagtoa.menu.dashboard.wizard'))->assertOk()->getContent();

        $this->assertStringContainsString('Publier maintenant', $html);
    }

    public function test_the_classic_creation_form_still_says_save_not_publish(): void
    {
        $this->patron();

        $html = $this->get(route('tagtoa.menu.dashboard.create'))->assertOk()->getContent();

        $this->assertStringContainsString('Enregistrer le menu', $html);
        $this->assertStringNotContainsString('Publier maintenant', $html);
    }

    public function test_editing_an_existing_menu_still_says_save_even_from_the_wizard_s_shared_partial(): void
    {
        // L'assistant ne sert qu'à CRÉER (voir DashboardController::wizard()),
        // mais le bouton est défini dans le gabarit partagé : le label ne
        // doit dépendre que de $editing, jamais du seul fait d'être dans
        // l'assistant.
        $this->patron();
        $menu = $this->menu();

        $html = $this->get(route('tagtoa.menu.dashboard.edit', $menu->id))->assertOk()->getContent();

        $this->assertStringContainsString('Enregistrer le menu', $html);
        $this->assertStringNotContainsString('Publier maintenant', $html);
    }

    /* ---------- édition : la carte « Partager » ---------- */

    public function test_the_edit_screen_shows_the_public_link(): void
    {
        $this->patron();
        $menu = $this->menu();

        $html = $this->get(route('tagtoa.menu.dashboard.edit', $menu->id))->assertOk()->getContent();

        $this->assertStringContainsString($menu->public_url, $html);
    }

    public function test_the_edit_screen_links_to_the_shared_qr_generator(): void
    {
        // Le lien tagtoa.qr.index existe déjà dans la barre latérale sur
        // toute page du tableau de bord — on vérifie donc le BOUTON propre
        // à la carte « Partager » (son libellé), pas la simple présence de
        // la route quelque part sur la page.
        $this->patron();
        $menu = $this->menu();

        $html = $this->get(route('tagtoa.menu.dashboard.edit', $menu->id))->assertOk()->getContent();

        $this->assertStringContainsString('QR code &amp; affiche', $html);
    }

    public function test_the_edit_screen_offers_an_embed_snippet_pointing_at_the_menu(): void
    {
        $this->patron();
        $menu = $this->menu();

        $html = $this->get(route('tagtoa.menu.dashboard.edit', $menu->id))->assertOk()->getContent();

        $this->assertStringContainsString('<iframe src="'.$menu->public_url.'"', $html);
    }

    public function test_the_creation_screen_never_shows_the_share_card(): void
    {
        // Rien à partager avant l'enregistrement : pas d'alias réel, pas de
        // lien qui mène quelque part.
        $this->patron();

        $html = $this->get(route('tagtoa.menu.dashboard.create'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Partager ce menu', $html);
        $this->assertStringNotContainsString('menuEmbedCode', $html);
    }

    public function test_the_wizard_never_shows_the_share_card_either(): void
    {
        $this->patron();

        $html = $this->get(route('tagtoa.menu.dashboard.wizard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Partager ce menu', $html);
    }
}
