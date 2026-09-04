<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Support\DashboardModules;
use PHPUnit\Framework\TestCase;

/**
 * Le tableau de bord ne met en avant que MENU, POS, ÉVÉNEMENTS et PAIEMENTS.
 *
 * Point capital : masquer n'est PAS supprimer. Un module absent du menu garde
 * ses routes et ses données — un marchand qui a déjà des liens NFC imprimés ou
 * des cartes de fidélité ne perd rien.
 */
class DashboardModulesTest extends TestCase
{
    public function test_the_four_business_modules_are_the_ones_promoted(): void
    {
        $modules = array_keys(DashboardModules::enabled('module'));

        $this->assertSame(['menu', 'pos', 'event', 'pay'], $modules);
    }

    public function test_modules_set_aside_are_hidden_but_still_catalogued(): void
    {
        foreach (['site', 'store', 'cards', 'loyalty', 'links', 'booking'] as $key) {
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
            $this->assertContains($m['group'], ['module', 'account']);
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
