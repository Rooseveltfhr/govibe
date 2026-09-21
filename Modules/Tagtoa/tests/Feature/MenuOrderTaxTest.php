<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA MENU — tax_rate existait déjà sur Item et Business (colonnes,
| formulaire de réglages) mais MenuOrderService::placeOrder() ne l'utilisait
| NULLE PART : un commerce qui active la taxe pour sa caisse ne la voyait
| jamais appliquée à ses commandes QR — écart comptable et risque de
| conformité fiscale.
|--------------------------------------------------------------------------
*/

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Menu\Order;
use Modules\Tagtoa\App\Services\Menu\MenuOrderService;
use Modules\Tagtoa\Tests\TestCase;

class MenuOrderTaxTest extends TestCase
{
    use RefreshDatabase;

    private function commerce(array $taxe = [], string $tenantId = 't1'): Business
    {
        return Business::updateOrCreate(['id' => $tenantId], array_merge([
            'account_id' => $tenantId, 'name' => 'Lounge Test', 'currency' => 'HTG', 'is_active' => true,
        ], $taxe));
    }

    private function makeMenu(string $tenantId = 't1'): Menu
    {
        return Menu::create([
            'vcard_id' => 77, 'tenant_id' => $tenantId, 'name' => 'Lounge Test',
            'alias' => 'lounge-test-'.$tenantId, 'currency' => 'HTG', 'is_active' => true,
            'ordering_enabled' => true,
        ]);
    }

    private function item(Menu $menu, array $attrs = []): Item
    {
        $cat = Category::create(['menu_id' => $menu->id, 'name' => 'Plats', 'sort' => 0, 'is_active' => true]);

        return Item::create(array_merge([
            'menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Plat', 'price' => 100, 'is_available' => true,
        ], $attrs));
    }

    public function test_tax_inclusive_extracts_tax_from_the_subtotal(): void
    {
        $this->commerce(['tax_enabled' => true, 'tax_rate' => 10, 'tax_inclusive' => true, 'tax_label' => 'TCA']);
        $menu = $this->makeMenu();
        $item = $this->item($menu);

        $order = app(MenuOrderService::class)->placeOrder($menu, [
            'items' => [['id' => $item->id, 'qty' => 2]],
        ]);

        // 200 TTC contient 18,18 de taxe — le total ne bouge pas, la taxe en
        // est EXTRAITE (usage haïtien : le prix affiché est ce qu'on paie).
        $this->assertSame('200.00', $order->total);
        $this->assertSame(18.18, (float) $order->tax_total);
        $this->assertTrue((bool) $order->tax_inclusive);
        $this->assertSame('TCA 10 %', $order->tax_label);
    }

    public function test_tax_exclusive_adds_tax_on_top_of_the_subtotal(): void
    {
        $this->commerce(['tax_enabled' => true, 'tax_rate' => 10, 'tax_inclusive' => false]);
        $menu = $this->makeMenu();
        $item = $this->item($menu);

        $order = app(MenuOrderService::class)->placeOrder($menu, [
            'items' => [['id' => $item->id, 'qty' => 2]],
        ]);

        // 200 hors taxe + 10 % = 220 : le client paie PLUS que le sous-total.
        $this->assertSame('200.00', $order->subtotal);
        $this->assertSame('220.00', $order->total);
        $this->assertSame(20.0, (float) $order->tax_total);
    }

    public function test_a_tip_is_never_taxed(): void
    {
        $this->commerce(['tax_enabled' => true, 'tax_rate' => 10, 'tax_inclusive' => false]);
        $menu = $this->makeMenu();
        $item = $this->item($menu);

        $order = app(MenuOrderService::class)->placeOrder($menu, [
            'items' => [['id' => $item->id, 'qty' => 1]], 'tip' => 50,
        ]);

        // 100 HT + 10 (taxe) + 50 (pourboire, jamais taxé) = 160.
        $this->assertSame('160.00', $order->total);
        $this->assertSame(10.0, (float) $order->tax_total);
    }

    public function test_an_item_can_be_exempted_even_when_the_business_taxes(): void
    {
        $this->commerce(['tax_enabled' => true, 'tax_rate' => 10, 'tax_inclusive' => true]);
        $menu = $this->makeMenu();
        $riz = $this->item($menu, ['name' => 'Riz', 'tax_rate' => 0]);

        $order = app(MenuOrderService::class)->placeOrder($menu, [
            'items' => [['id' => $riz->id, 'qty' => 1]],
        ]);

        $this->assertSame(0.0, (float) $order->tax_total);
        $this->assertSame(0.0, (float) $order->items->first()->tax_rate);
    }

    public function test_no_tax_configured_behaves_exactly_as_before(): void
    {
        // Pas de commerce déclaré du tout : régression de MenuOrderServiceTest.
        $menu = $this->makeMenu('t-sans-commerce');
        $item = $this->item($menu);

        $order = app(MenuOrderService::class)->placeOrder($menu, [
            'items' => [['id' => $item->id, 'qty' => 2]],
        ]);

        $this->assertSame('200.00', $order->total);
        $this->assertSame(0.0, (float) $order->tax_total);
        $this->assertNull($order->tax_label);
    }

    /* ------------------------------------------------------------------
       La course critique sur client_uuid — un double-tap réseau lent.
       ------------------------------------------------------------------ */

    public function test_a_racing_duplicate_client_uuid_is_recognized_and_handled_gracefully(): void
    {
        $menu = $this->makeMenu();
        $item = $this->item($menu);
        $payload = ['items' => [['id' => $item->id, 'qty' => 1]], 'client_uuid' => 'course-1'];

        $service = app(MenuOrderService::class);
        $insert = new \ReflectionMethod($service, 'insertOrder');
        $insert->setAccessible(true);

        // Le premier « thread » écrit directement, en contournant le
        // contrôle préalable — exactement ce qui se passe quand deux
        // requêtes le franchissent toutes les deux avant que l'une des deux
        // n'écrive.
        $premiere = $insert->invoke($service, $menu, $payload, 'course-1');

        // Le second « thread » tente la MÊME référence : la contrainte
        // unique en base doit refuser une deuxième ligne, jamais dupliquer
        // silencieusement.
        try {
            $insert->invoke($service, $menu, $payload, 'course-1');
            $this->fail('Une deuxième écriture avec le même client_uuid aurait dû être refusée par la base.');
        } catch (QueryException $e) {
            $reconnue = new \ReflectionMethod($service, 'isDuplicateClientUuid');
            $reconnue->setAccessible(true);
            $this->assertTrue($reconnue->invoke($service, $e));
        }

        // Le chemin PUBLIC, lui, ne doit jamais laisser passer une erreur
        // 500 : il rend la commande déjà écrite.
        $encore = $service->placeOrder($menu, $payload);
        $this->assertSame($premiere->id, $encore->id);
        $this->assertSame(1, Order::count());
    }

    /**
     * La vraie fenêtre de course : AUCUNE commande n'existe au moment où
     * placeOrder() fait son contrôle initial — c'est la tentative d'écriture
     * elle-même qui découvre qu'une autre requête vient de committer entre
     * temps. Un mock partiel simule ce commit concurrent au moment précis où
     * insertOrder() est appelé, ce qu'un test à un seul fil d'exécution ne
     * peut pas provoquer autrement.
     */
    public function test_the_public_path_recovers_from_a_genuine_concurrent_commit_without_a_500(): void
    {
        $menu = $this->makeMenu();
        $item = $this->item($menu);
        $payload = ['items' => [['id' => $item->id, 'qty' => 1]], 'client_uuid' => 'course-2'];

        $service = \Mockery::mock(
            \Modules\Tagtoa\App\Services\Menu\MenuOrderService::class,
            [
                app(\Modules\Tagtoa\App\Services\Billing\RevenueService::class),
                app(\Modules\Tagtoa\App\Services\Notifications\NotificationService::class),
                app(\Modules\Tagtoa\App\Services\Loyalty\LoyaltyCardService::class),
            ]
        )->makePartial()->shouldAllowMockingProtectedMethods();

        $service->shouldReceive('insertOrder')->once()->andReturnUsing(function () use ($menu) {
            // L'AUTRE requête vient de committer, exactement entre le
            // contrôle initial de placeOrder() et cette tentative d'écriture.
            Order::create([
                'menu_id' => $menu->id, 'tenant_id' => $menu->tenant_id,
                'reference' => Order::generateReference(),
                'subtotal' => 100, 'total' => 100, 'tip' => 0, 'currency' => 'HTG',
                'status' => 'pending', 'payment_status' => 'unpaid',
                'channel' => 'menu', 'order_type' => 'dine_in',
                'client_uuid' => 'course-2', 'placed_at' => now(),
            ]);

            throw new QueryException(
                'sqlite', 'insert into "tagtoa_menu_orders" ("client_uuid") values (?)', ['course-2'],
                new \Exception('UNIQUE constraint failed: tagtoa_menu_orders.client_uuid')
            );
        });

        $order = $service->placeOrder($menu, $payload);

        $this->assertSame('course-2', $order->client_uuid);
        // Une seule ligne en base malgré la collision : le résultat de l'AUTRE
        // requête, jamais une deuxième.
        $this->assertSame(1, Order::count());
    }
}
