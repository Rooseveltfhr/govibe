<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA MENU — assistant : lot de corrections demandées après revue
|--------------------------------------------------------------------------
| Titre d'étape 1, alias auto-généré (jamais tapé sur l'assistant), aperçu
| de l'image de couverture (manquant jusqu'ici, contrairement au logo),
| explication claire des trois façons d'accepter un paiement, pays AVANT
| ville/commune sur une zone de livraison, et horaires qui ne s'affichent
| que si « Afficher les horaires » est coché.
|
| Deux demandes volontairement HORS de ce lot, faute de préférence tranchée
| de l'utilisateur et vu le risque sur des fonctions POS déjà en place
| (stock, lots/péremption, code-barres) : fusionner le catalogue Menu/POS
| en une seule table, et sous-découper l'étape Catégories→Plats.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\DeliveryZone;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Support\Tenant;
use Modules\Tagtoa\Tests\TestCase;

class MenuWizardRefinementsTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));
        Tenant::flush();
    }

    /* ---------- 1. Titre de l'étape 1 ---------- */

    public function test_the_first_step_says_add_an_establishment_when_creating(): void
    {
        $this->patron();

        $html = $this->get(route('tagtoa.menu.dashboard.wizard'))->assertOk()->getContent();

        $this->assertStringContainsString('Ajouter un établissement', $html);
    }

    public function test_the_first_step_keeps_the_plain_title_when_editing(): void
    {
        $this->patron();
        $menu = Menu::create(['tenant_id' => 't-1', 'name' => 'Lounge', 'alias' => 'lounge-'.uniqid(), 'currency' => 'HTG']);

        $html = $this->get(route('tagtoa.menu.dashboard.edit', $menu->id))->assertOk()->getContent();

        $this->assertStringNotContainsString('Ajouter un établissement', $html);
    }

    /* ---------- 2. Alias auto-généré sur l'assistant ---------- */

    public function test_the_wizard_never_shows_an_editable_alias_field(): void
    {
        $this->patron();

        $html = $this->get(route('tagtoa.menu.dashboard.wizard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('name="alias" value="" placeholder', $html);
        $this->assertStringContainsString('type="hidden" name="alias"', $html);
    }

    public function test_the_classic_form_still_lets_you_type_an_alias(): void
    {
        $this->patron();

        $html = $this->get(route('tagtoa.menu.dashboard.create'))->assertOk()->getContent();

        $this->assertStringContainsString('placeholder="'.__('auto si vide').'"', $html);
    }

    public function test_creating_via_the_wizard_still_auto_generates_a_unique_alias(): void
    {
        $this->patron();

        $response = $this->postJson(route('tagtoa.menu.dashboard.store'), [
            'name' => 'Lakay Grill', 'type' => 'restaurant', 'currency' => 'HTG', 'form_end' => '1',
        ]);

        $response->assertOk();
        $menu = Menu::where('name', 'Lakay Grill')->sole();
        $this->assertNotEmpty($menu->alias);
        $this->assertStringStartsNotWith('-', $menu->alias);
    }

    /* ---------- 3. Aperçu de la couverture ---------- */

    public function test_the_cover_field_now_wires_a_live_preview_like_the_logo(): void
    {
        $this->patron();

        $html = $this->get(route('tagtoa.menu.dashboard.wizard'))->assertOk()->getContent();

        $this->assertStringContainsString('onchange="previewCover(this)"', $html);
        $this->assertStringContainsString('id="coverPreview"', $html);
        $this->assertStringContainsString('function previewCover(input)', $html);
    }

    /* ---------- 4. Explication des trois façons de payer ---------- */

    public function test_the_payment_field_explains_automatic_manual_and_both(): void
    {
        $this->patron();

        $html = $this->get(route('tagtoa.menu.dashboard.wizard'))->assertOk()->getContent();

        $this->assertStringContainsString('<b>A.</b>', $html);
        $this->assertStringContainsString('<b>B.</b>', $html);
        $this->assertStringContainsString('<b>C.</b>', $html);
        $this->assertStringContainsString(route('tagtoa.pay.dashboard.create'), $html);
        $this->assertStringContainsString(route('tagtoa.pay.methods'), $html);
    }

    /* ---------- 5. Zone de livraison : pays avant ville/commune ---------- */

    public function test_the_delivery_zone_template_asks_for_a_country_before_the_city(): void
    {
        $this->patron();

        $html = $this->get(route('tagtoa.menu.dashboard.wizard'))->assertOk()->getContent();

        $this->assertStringContainsString('class="sel zonecountry"', $html);
        $this->assertStringContainsString('>Haïti<', $html);
        $this->assertStringContainsString('>République Dominicaine<', $html);
        $this->assertStringContainsString('>États-Unis<', $html);
        $this->assertStringContainsString('>Canada<', $html);
        // La ville/commune est désactivée tant qu'aucun pays n'est choisi.
        $this->assertStringContainsString('class="inp zonename" placeholder="'.__('Ville / commune').'" disabled', $html);
    }

    public function test_submitting_a_delivery_zone_persists_its_country(): void
    {
        $this->patron();

        $response = $this->postJson(route('tagtoa.menu.dashboard.store'), [
            'name' => 'Ti Jaden', 'type' => 'restaurant', 'currency' => 'HTG', 'form_end' => '1',
            'delivery_zones' => [
                ['country' => 'Haïti', 'name' => 'Pétion-Ville', 'fee' => 150],
            ],
        ]);

        $response->assertOk();
        $menu = Menu::where('name', 'Ti Jaden')->sole();
        $zone = DeliveryZone::where('menu_id', $menu->id)->sole();
        $this->assertSame('Haïti', $zone->country);
        $this->assertSame('Pétion-Ville', $zone->name);
    }

    /* ---------- 6. Horaires masqués tant que non activés ---------- */

    public function test_the_hours_fields_are_hidden_by_default_on_a_new_menu(): void
    {
        $this->patron();

        $html = $this->get(route('tagtoa.menu.dashboard.wizard'))->assertOk()->getContent();

        $this->assertStringContainsString('id="hoursFields" style="display:none"', $html);
    }

    public function test_the_hours_fields_show_when_a_menu_already_displays_its_hours(): void
    {
        $this->patron();
        $menu = Menu::create([
            'tenant_id' => 't-1', 'name' => 'Lounge', 'alias' => 'lounge-'.uniqid(),
            'currency' => 'HTG', 'show_hours' => true,
        ]);

        $html = $this->get(route('tagtoa.menu.dashboard.edit', $menu->id))->assertOk()->getContent();

        $this->assertStringContainsString('id="hoursFields" style=""', $html);
    }

    /* ---------- 7. « Ajouter un produit » introuvable depuis la liste ---------- */

    public function test_the_menu_list_links_directly_to_the_products_section(): void
    {
        // Signalé d'abord : « pa jwenn kote vre pou ajouter produit » — réglé
        // ici par un ANCRAGE sur le gros formulaire. Signalé ENSUITE (vidéo) :
        // cet ancrage ouvrait encore le même formulaire que l'établissement.
        // Le lien mène maintenant à un écran à part (ItemController), qui ne
        // montre que le produit — voir MenuItemScreenTest.
        $this->patron();
        $menu = Menu::create(['tenant_id' => 't-1', 'name' => 'Lounge', 'alias' => 'lounge-'.uniqid(), 'currency' => 'HTG']);

        $html = $this->get(route('tagtoa.menu.dashboard.index'))->assertOk()->getContent();

        $this->assertStringContainsString(
            'href="'.route('tagtoa.menu.dashboard.items.create', $menu->id).'"',
            $html
        );
        $this->assertStringContainsString(__('Ajouter un produit'), $html);
    }

    public function test_the_products_section_carries_the_anchor_the_list_links_to(): void
    {
        $this->patron();
        $menu = Menu::create(['tenant_id' => 't-1', 'name' => 'Lounge', 'alias' => 'lounge-'.uniqid(), 'currency' => 'HTG']);

        $html = $this->get(route('tagtoa.menu.dashboard.edit', $menu->id))->assertOk()->getContent();

        $this->assertStringContainsString('id="categories-produits"', $html);
    }

    /* ---------- 8. Un seul bouton pour créer un menu — l'assistant ---------- */

    public function test_the_empty_menu_list_offers_exactly_one_create_button(): void
    {
        // Signalé : deux boutons différents (« Assistant guidé » et
        // « Nouveau menu ») pour la même action prêtait à confusion. Créer un
        // menu, c'est l'assistant — il n'y a plus de second chemin affiché.
        $this->patron();

        $html = $this->get(route('tagtoa.menu.dashboard.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString(__('Assistant guidé'), $html);
        // La sidebar lie aussi « Nouveau menu » vers l'assistant (voir
        // DashboardModules) — on compte seulement les boutons de LA PAGE,
        // pas le lien de navigation.
        $this->assertSame(2, substr_count($html, 'href="'.route('tagtoa.menu.dashboard.wizard').'" class="btn btn-p'),
            'Devrait y avoir exactement deux boutons "Créer mon menu" (h-row + état vide), pas plus.');
        $this->assertStringNotContainsString('href="'.route('tagtoa.menu.dashboard.create').'"', $html);
    }

    /* ---------- 9. Le nom/slogan suggéré suit le type choisi ---------- */

    public function test_the_establishment_name_placeholder_follows_the_default_restaurant_type(): void
    {
        // Le type par défaut du <select> est 'restaurant' ($menu->type ?:
        // 'restaurant') — le repli serveur (avant que le JS tourne) doit
        // suivre la même règle, pas rester figé sur « Lounge 509 ».
        $this->patron();

        $html = $this->get(route('tagtoa.menu.dashboard.wizard'))->assertOk()->getContent();

        $this->assertStringContainsString('class="inp tt-name" name="name"', $html);
        $this->assertStringContainsString('placeholder="Ex. Lakay Grill"', $html);
        $this->assertStringNotContainsString('placeholder="Ex. Lounge 509"', $html);
    }

    public function test_the_javascript_adapts_the_name_and_tagline_placeholders_per_type(): void
    {
        $this->patron();

        $html = $this->get(route('tagtoa.menu.dashboard.wizard'))->assertOk()->getContent();

        $this->assertStringContainsString("el.placeholder = 'Ex. ' + (p.name_example || '')", $html);
        $this->assertStringContainsString('el.placeholder = p.tagline_example', $html);
        // Les exemples par métier voyagent bien jusqu'au navigateur.
        $this->assertStringContainsString('"name_example":"Pharmacie Lakay"', $html);
    }
}
