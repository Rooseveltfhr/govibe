<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Support\DashboardModules;
use PHPUnit\Framework\TestCase;

/**
 * Ce que le tableau de bord met en avant — et pourquoi la liste a bougé.
 *
 * Elle tenait d'abord en quatre : MENU, POS, ÉVÉNEMENTS, PAIEMENTS. Deux
 * manquaient, et le manque s'est vu en salle :
 *
 *   • LE MATÉRIEL QUE LE MARCHAND A PAYÉ. Les Smart Stands étaient rangés dans
 *     le groupe 'feature', atteints depuis « Menu ». Quelqu'un qui tient un
 *     chevalet dans la main ne pense pas « Menu » : il cherche « Stand ». Le
 *     module était servi, activé — et introuvable, le pire des trois états.
 *
 *   • LES CARTES NFC. La clé `cards` n'était pas dans la liste, et l'entrée
 *     « Cartes TAGTOA » du menu Paiements dépend d'elle (`needs`). Le lien ne
 *     s'affichait donc NULLE PART : il n'existait aucun chemin, dans toute
 *     l'application, pour activer une carte.
 *
 * Un TROISIÈME manque s'est vu ensuite : les deux entrées ci-dessus existaient
 * bien, mais chacune sous SON module — un marchand devait déjà savoir si son
 * carton contient un Stand ou une Carte pour trouver le bon menu. `activate`
 * pose la question en premier (« que tenez-vous ? ») avant de renvoyer vers
 * l'un des deux mécanismes existants ; c'est pourquoi elle précède tout le
 * reste dans le catalogue.
 *
 * Point capital, inchangé : masquer n'est PAS supprimer. Un module absent du
 * menu garde ses routes et ses données.
 */
class DashboardModulesTest extends TestCase
{
    public function test_the_promoted_modules_are_the_business_ones_plus_the_hardware(): void
    {
        $modules = array_keys(DashboardModules::enabled('module'));

        $this->assertSame(['activate', 'menu', 'pos', 'event', 'pay', 'stands', 'cards'], $modules);
    }

    public function test_the_hardware_the_merchant_bought_is_never_buried(): void
    {
        // GARDE. Un objet payé qu'on ne trouve pas dans l'application est un
        // objet qui finit dans un tiroir — et c'est exactement ce que compte
        // le chiffre « vendus et muets » de la console du fondateur.
        foreach (['stands', 'cards'] as $key) {
            $this->assertTrue(DashboardModules::isEnabled($key), "$key doit rester visible.");
            $this->assertSame('module', DashboardModules::CATALOG[$key]['group'],
                "« $key » doit avoir sa propre entrée : on ne cherche pas son matériel dans un sous-menu.");
        }
    }

    public function test_activating_comes_first_in_the_hardware_menus(): void
    {
        // C'est le geste du jour où l'on déballe le carton, et le seul qui
        // presse. Le mettre en troisième position le rend introuvable à celui
        // qui ouvre le menu pour ça et rien d'autre.
        foreach (['stands', 'cards'] as $key) {
            $premier = DashboardModules::children($key)[0]['label'] ?? '';
            $this->assertStringContainsString('Activer', $premier,
                "La première entrée de « $key » doit être l'activation, pas la liste.");
        }
    }

    public function test_modules_set_aside_are_hidden_but_still_catalogued(): void
    {
        foreach (['site', 'store', 'loyalty', 'links', 'booking'] as $key) {
            $this->assertFalse(DashboardModules::isEnabled($key), "$key ne doit plus être mis en avant.");
            // Toujours au catalogue : ses routes et ses données restent servies.
            $this->assertArrayHasKey($key, DashboardModules::CATALOG);
        }
    }

    public function test_support_screens_the_merchant_still_needs_are_kept(): void
    {
        foreach (['plan', 'qr', 'analytics', 'customers', 'reviews'] as $key) {
            $this->assertTrue(DashboardModules::isEnabled($key), "$key doit rester accessible.");
        }
    }

    public function test_display_order_follows_the_catalogue_not_the_config(): void
    {
        // L'ordre du catalogue fait foi : réordonner la config ne doit pas
        // réordonner le menu du marchand.
        $keys = array_keys(DashboardModules::enabled());
        $catalogOrder = array_values(array_intersect(array_keys(DashboardModules::CATALOG), $keys));

        $this->assertSame($catalogOrder, $keys);
    }

    public function test_every_enabled_module_carries_a_key_and_a_url(): void
    {
        foreach (DashboardModules::enabled() as $key => $m) {
            $this->assertSame($key, $m['key']);
            $this->assertSame('/tagtoa/'.$key, $m['url']);
            $this->assertNotEmpty($m['label']);
            $this->assertNotEmpty($m['icon']);
            // 'feature' : écran réel, mais atteint depuis son module parent
            // plutôt que depuis le premier niveau (voir DashboardModules).
            $this->assertContains($m['group'], ['module', 'account', 'feature']);
        }
    }

    public function test_every_default_key_exists_in_the_catalogue(): void
    {
        // Une clé mal orthographiée dans la config produirait un lien mort :
        // enabledKeys() doit l'écarter, pas la propager jusqu'à la vue.
        foreach (DashboardModules::DEFAULT_ENABLED as $key) {
            $this->assertArrayHasKey($key, DashboardModules::CATALOG);
        }
    }
}
