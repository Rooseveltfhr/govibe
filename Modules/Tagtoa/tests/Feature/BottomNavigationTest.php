<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Support\DashboardModules;
use Modules\Tagtoa\Tests\TestCase;

/**
 * La barre du bas — cinq destinations, sur tout TAGTOA.
 *
 * Sur un téléphone, la barre latérale est un tiroir : deux gestes pour
 * atteindre n'importe quoi, et rien à l'écran qui dise où l'on peut aller. Les
 * cinq endroits où un marchand retourne toute la journée restent donc visibles
 * en permanence.
 *
 * Le risque neuf que ces tests ferment : une barre qui existe mais dont un
 * onglet ne s'allume jamais, ou qui laisse un module introuvable parce que ni
 * la barre ni « Plus » ne le listent.
 */
class BottomNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function patron(): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-1', 'name' => 'Roosevelt']));
    }

    public function test_the_bar_has_exactly_five_destinations(): void
    {
        // Cinq, pas six : au-delà, les libellés se coupent et les cibles
        // passent sous le pouce. Le cinquième ouvre le reste.
        $this->assertCount(5, DashboardModules::BOTTOM);
    }

    public function test_every_tab_leads_somewhere_real(): void
    {
        $this->patron();

        foreach (DashboardModules::BOTTOM as $t) {
            if ($t['url'] === null) {
                continue; // « Plus » ouvre une feuille, pas une page
            }
            $this->get($t['url'])->assertSuccessful();
        }
    }

    public function test_the_right_tab_lights_up(): void
    {
        foreach ([
            'tagtoa/home'              => 'home',
            'tagtoa/pos'               => 'pos',
            'tagtoa/pos/3/products'    => 'pos',
            'tagtoa/menu'              => 'menu',
            'tagtoa/orders'            => 'orders',
        ] as $chemin => $attendu) {
            $this->assertSame($attendu, DashboardModules::bottomActive($chemin), $chemin);
        }
    }

    public function test_a_screen_that_belongs_to_a_tab_lights_that_tab(): void
    {
        // Le stock appartient à la caisse : y arriver ne doit pas éteindre
        // l'onglet, sinon le marchand croit avoir quitté son module.
        $this->assertSame('pos', DashboardModules::bottomActive('tagtoa/inventory'));
        $this->assertSame('pos', DashboardModules::bottomActive('tagtoa/inventory/suppliers'));
        $this->assertSame('pos', DashboardModules::bottomActive('tagtoa/catalog/codes'));
        $this->assertSame('menu', DashboardModules::bottomActive('tagtoa/stands/activate'));
    }

    public function test_a_page_outside_every_tab_lights_none(): void
    {
        // Mieux vaut aucun onglet allumé qu'un onglet faux : un faux fait
        // croire au marchand qu'il est ailleurs qu'il n'est.
        $this->assertNull(DashboardModules::bottomActive('tagtoa/plan'));
        $this->assertNull(DashboardModules::bottomActive('tagtoa/analytics'));
    }

    public function test_no_module_is_lost_between_the_bar_and_the_sheet(): void
    {
        // LE test de la barre. Quatre modules passent en bas ; tous les autres
        // doivent se retrouver dans « Plus ». Un module qui n'est ni dans l'un
        // ni dans l'autre reste servi, reste activé — et devient introuvable
        // sur téléphone, sans qu'aucune erreur ne se produise.
        $enBas  = array_filter(array_column(DashboardModules::BOTTOM, 'key'), fn ($k) => $k !== 'more');
        $dansLe = array_keys(DashboardModules::more());
        $couvert = array_merge($enBas, $dansLe);

        $perdus = array_values(array_diff(array_keys(DashboardModules::enabled()), $couvert));

        $this->assertSame([], $perdus, "\n".
            "Ces modules ne sont ni dans la barre du bas ni dans « Plus » :\n  - ".
            implode("\n  - ", $perdus)."\n");
    }

    public function test_the_sheet_never_repeats_what_is_already_under_the_thumb(): void
    {
        $enBas = array_column(DashboardModules::BOTTOM, 'key');

        foreach (array_keys(DashboardModules::more()) as $k) {
            $this->assertNotContains($k, $enBas, "« $k » est à la fois en bas et dans « Plus ».");
        }
    }

    public function test_the_bar_is_rendered_on_every_dashboard_screen(): void
    {
        $this->patron();

        foreach (['/tagtoa/home', '/tagtoa/orders', '/tagtoa/analytics'] as $url) {
            $page = $this->get($url)->assertOk();
            $page->assertSee('class="tabs"', false);
            $page->assertSee('id="sheetMore"', false);
        }
    }

    public function test_the_bar_declares_an_icon_and_a_name_for_each_tab(): void
    {
        // « Icône + nom » : une barre d'icônes seules oblige à deviner, et un
        // marchand qui devine se trompe.
        foreach (DashboardModules::BOTTOM as $t) {
            $this->assertNotEmpty($t['label']);
            $this->assertMatchesRegularExpression('/^fa-[a-z0-9-]+$/', $t['icon']);
        }
    }

    public function test_the_layout_leaves_room_for_the_bar(): void
    {
        // La barre recouvre le bas de l'écran. Sans réserve, le dernier bouton
        // de chaque page se retrouve dessous et devient inatteignable — un
        // défaut qu'aucun test fonctionnel ne verrait.
        $vue = (string) file_get_contents(__DIR__.'/../../resources/views/layouts/dashboard.blade.php');

        $this->assertStringContainsString('.content{padding-bottom:calc(74px', $vue,
            'Le contenu ne réserve plus la hauteur de la barre du bas.');
    }
}
