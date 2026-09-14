<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Tagtoa\App\Support\DashboardModules;
use Modules\Tagtoa\Tests\TestCase;

/**
 * Garde-fou : la navigation doit rester HIÉRARCHIQUE et sans impasse.
 *
 * La barre latérale était plate : « Stock » et « Équipe » côtoyaient « Caisse »
 * alors qu'on ne les ouvre jamais pour eux-mêmes. En les rangeant DANS leur
 * module, on crée deux risques neufs que ce fichier ferme :
 *
 *  1. l'écran orphelin — un écran retiré du premier niveau et rattaché à aucun
 *     module : il existe, il est activé, et plus personne ne peut le trouver ;
 *  2. le lien mort — un sous-lien écrit à la main qui ne correspond à aucune
 *     route : il ne casse rien au démarrage, il casse au clic du marchand.
 *
 * Aucun des deux ne produit d'erreur : c'est exactement pour ça qu'ils ont
 * besoin d'un test.
 */
class DashboardNavigationTest extends TestCase
{
    use RefreshDatabase;

    /** Tous les enfants déclarés, module par module, sans filtrage. */
    private function tousLesEnfants(): array
    {
        $out = [];
        foreach (DashboardModules::CATALOG as $key => $meta) {
            foreach ($meta['children'] ?? [] as $c) {
                $out[] = [$key, $c];
            }
        }

        return $out;
    }

    public function test_every_sub_link_points_at_a_real_route(): void
    {
        // Le vrai test : on demande au routeur de résoudre l'URL, comme le
        // navigateur du marchand le ferait. Une faute de frappe dans un
        // préfixe ne peut donc plus atteindre la production.
        foreach ($this->tousLesEnfants() as [$module, $c]) {
            $url = $c['url'];

            $this->assertStringStartsWith('/tagtoa/', $url,
                "« {$c['label']} » ($module) doit pointer dans l'espace marchand.");

            try {
                Route::getRoutes()->match(Request::create($url, 'GET'));
            } catch (\Throwable $e) {
                $this->fail("Lien mort dans le menu : « {$c['label']} » ($module) → $url\n".
                    "Aucune route ne répond à cette adresse. Corrigez l'URL dans ".
                    "DashboardModules::CATALOG, ou déclarez la route.");
            }
        }
    }

    public function test_every_module_url_still_resolves(): void
    {
        // Les entrées de premier niveau dérivent leur URL de leur clé
        // (/tagtoa/<clé>) : renommer une clé sans renommer le préfixe de route
        // produirait un menu entier de liens morts.
        foreach (DashboardModules::enabled() as $key => $m) {
            try {
                $route = Route::getRoutes()->match(Request::create($m['url'], 'GET'));
            } catch (\Throwable $e) {
                $this->fail("Module « $key » : /tagtoa/$key ne correspond à aucune route.");
            }
            $this->assertNotNull($route, "Module « $key » : /tagtoa/$key ne mène nulle part.");
        }
    }

    public function test_no_screen_is_left_orphaned(): void
    {
        // Un écran du groupe 'feature' a QUITTÉ le premier niveau : il n'est
        // donc atteignable que si un module le liste. S'il n'en fait plus
        // partie, il devient invisible — activé, servi, et introuvable.
        $lies = [];
        foreach ($this->tousLesEnfants() as [, $c]) {
            $lies[rtrim($c['url'], '/')] = true;
        }

        $orphelins = [];
        foreach (DashboardModules::CATALOG as $key => $meta) {
            if (($meta['group'] ?? null) !== 'feature') {
                continue;
            }
            if (! isset($lies['/tagtoa/'.$key])) {
                $orphelins[] = $key;
            }
        }

        $this->assertSame([], $orphelins, "\n".
            "Ces écrans ne sont plus au premier niveau ET aucun module ne les liste :\n  - ".
            implode("\n  - ", $orphelins)."\n\n".
            "Ajoutez-les dans les `children` d'un module, ou rendez-leur le groupe 'module'/'account'.\n");
    }

    public function test_groups_stay_within_the_three_known_kinds(): void
    {
        // Un groupe mal orthographié ne lèverait rien : l'entrée disparaîtrait
        // simplement des deux listes de la barre latérale.
        foreach (DashboardModules::CATALOG as $key => $meta) {
            $this->assertContains($meta['group'] ?? null, ['module', 'account', 'feature'],
                "Le module « $key » a un groupe inconnu — il ne s'afficherait nulle part.");
        }
    }

    public function test_every_dependency_named_by_a_sub_link_exists(): void
    {
        // `needs` sert à masquer un lien quand le module dont il dépend est
        // éteint. Une clé inexistante masquerait le lien POUR TOUJOURS, sans
        // que rien ne le signale.
        foreach ($this->tousLesEnfants() as [$module, $c]) {
            if (! isset($c['needs'])) {
                continue;
            }
            $this->assertArrayHasKey($c['needs'], DashboardModules::CATALOG,
                "« {$c['label']} » ($module) dépend de « {$c['needs'] }», qui n'existe pas au catalogue : ".
                'ce lien serait masqué en permanence.');
        }
    }

    public function test_every_sub_link_is_complete(): void
    {
        foreach ($this->tousLesEnfants() as [$module, $c]) {
            foreach (['label', 'icon', 'url'] as $champ) {
                $this->assertArrayHasKey($champ, $c, "Un écran de « $module » n'a pas de « $champ ».");
                $this->assertNotSame('', trim((string) $c[$champ]));
            }
        }
    }

    public function test_a_module_without_children_still_offers_one_screen(): void
    {
        // Les vues bouclent toujours sur children() : un tableau vide y
        // afficherait un module ouvrable sur rien.
        foreach (DashboardModules::enabled() as $key => $m) {
            $this->assertNotEmpty($m['children'], "Le module « $key » n'ouvre aucun écran.");
        }
    }

    public function test_a_shared_screen_belongs_to_the_module_that_owns_it(): void
    {
        // Le stock est listé sous Menu ET sous Caisse — c'est le même stock.
        // Mais un seul groupe doit s'ouvrir, et c'est celui de la Caisse :
        // le raccourci côté Menu est marqué `alias`, il n'attire pas l'écran.
        [$module, $ecran] = DashboardModules::locate('/tagtoa/inventory');

        $this->assertSame('pos', $module);
        $this->assertSame('/tagtoa/inventory', $ecran);
    }

    public function test_the_deepest_screen_wins(): void
    {
        // /tagtoa/inventory/suppliers commence par /tagtoa/inventory : sans
        // préférence pour l'URL la plus longue, « Fournisseurs » s'ouvrirait en
        // soulignant « Stock ».
        [$module, $ecran] = DashboardModules::locate('/tagtoa/inventory/suppliers');

        $this->assertSame('pos', $module);
        $this->assertSame('/tagtoa/inventory/suppliers', $ecran);
    }

    public function test_a_page_outside_every_module_locates_nothing(): void
    {
        // L'accueil n'appartient à aucun module : la barre d'écrans ne doit pas
        // s'y afficher, et surtout aucun groupe ne doit s'ouvrir au hasard.
        $this->assertSame([null, null], DashboardModules::locate('/tagtoa/home'));
    }

    public function test_a_sub_link_disappears_when_its_module_is_off(): void
    {
        // Un marchand qui n'a pas le stock ne doit pas voir « Stock » sous sa
        // caisse : le lien mènerait à un module qu'il n'a pas.
        config(['tagtoa.modules_enabled' => ['pos', 'menu']]);

        $urls = array_column(DashboardModules::children('pos'), 'url');

        $this->assertContains('/tagtoa/pos', $urls);
        $this->assertNotContains('/tagtoa/inventory', $urls);
        $this->assertNotContains('/tagtoa/staff', $urls);
    }

    public function test_opening_a_screen_shows_everything_its_module_contains(): void
    {
        // La demande du marchand, telle quelle : « quand on clique sur POS, on
        // doit voir tout ce qu'il y a dans POS ». On ouvre donc le Stock — un
        // écran DE la caisse — et la page doit afficher ses écrans voisins.
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-nav', 'name' => 'Roosevelt']));

        $page = $this->get('/tagtoa/inventory')->assertOk();

        $page->assertSee('class="subnav"', false);
        foreach (['Mes caisses', 'Mouvements', 'Fournisseurs', 'Équipe'] as $voisin) {
            $page->assertSee($voisin, false);
        }
    }

    public function test_the_sidebar_actually_renders_the_hierarchy(): void
    {
        // Sans cette vérification, quelqu'un pourrait remettre une boucle plate
        // dans la vue : le catalogue resterait hiérarchique et l'écran, lui,
        // redeviendrait la liste de treize entrées qu'on vient de corriger.
        $vue = (string) file_get_contents(__DIR__.'/../../resources/views/layouts/dashboard.blade.php');

        $this->assertStringContainsString("\$m['children']", $vue,
            'La barre latérale n\'ouvre plus les écrans de chaque module.');
        $this->assertStringContainsString('class="subnav"', $vue,
            'La barre d\'écrans a disparu : sur téléphone, plus rien ne montre le contenu d\'un module.');
        $this->assertStringContainsString('locate(', $vue,
            'La vue ne demande plus où l\'on se trouve : aucun groupe ne s\'ouvrirait.');
    }
}
