<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA MENU — zones de livraison, chacune avec son propre frais
|--------------------------------------------------------------------------
| `delivery_fee` (voir MenuDeliveryFeeTest) est un frais UNIQUE pour tout
| le monde. Un commerce qui livre à la fois le quartier d'à côté et l'autre
| bout de la ville facture pourtant différemment les deux : ces zones sont
| un raccourci de tarification — le client choisit la sienne, son frais
| remplace le frais unique — jamais l'inverse, jamais les deux additionnés.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\DeliveryZone;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Services\Menu\MenuOrderService;
use Modules\Tagtoa\Tests\TestCase;

class MenuDeliveryZonesTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));
    }

    private function menu(array $attrs = []): Menu
    {
        return Menu::create(array_merge([
            'tenant_id' => 't-1', 'name' => 'Lounge', 'alias' => 'lounge-'.uniqid(),
            'currency' => 'HTG', 'is_active' => true, 'delivery_fee' => 50,
            // La commande publique n'apparaît que si le commerce l'a activée
            // ET renseigné un WhatsApp — voir menu/show.blade.php ($canOrder).
            'ordering_enabled' => true, 'whatsapp' => '+509 0000 0000',
        ], $attrs));
    }

    private function item(Menu $menu): Item
    {
        $cat = Category::create(['menu_id' => $menu->id, 'name' => 'Plats', 'is_active' => true]);

        return Item::create(['menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Plat', 'price' => 100, 'is_available' => true]);
    }

    public function test_saving_the_menu_form_creates_the_submitted_zones(): void
    {
        $this->patron();
        $menu = $this->menu();

        $this->put(route('tagtoa.menu.dashboard.update', $menu->id), [
            'name' => 'Lounge', 'currency' => 'HTG', 'form_end' => '1',
            'delivery_zones' => [
                ['name' => 'Centre-ville', 'fee' => '100'],
                ['name' => 'Périphérie', 'fee' => '250'],
            ],
        ])->assertRedirect();

        $zones = $menu->deliveryZones()->orderBy('sort')->get();
        $this->assertCount(2, $zones);
        $this->assertSame('Centre-ville', $zones[0]->name);
        $this->assertSame('250.00', $zones[1]->fee);
    }

    public function test_a_zone_removed_from_the_form_is_deleted(): void
    {
        $this->patron();
        $menu = $this->menu();
        $zone = DeliveryZone::create(['menu_id' => $menu->id, 'name' => 'Centre-ville', 'fee' => 100, 'sort' => 0, 'is_active' => true]);

        $this->put(route('tagtoa.menu.dashboard.update', $menu->id), [
            'name' => 'Lounge', 'currency' => 'HTG', 'form_end' => '1', 'delivery_zones' => [],
        ])->assertRedirect();

        $this->assertNull(DeliveryZone::find($zone->id));
    }

    public function test_resubmitting_an_existing_zone_by_id_updates_it_in_place(): void
    {
        $this->patron();
        $menu = $this->menu();
        $zone = DeliveryZone::create(['menu_id' => $menu->id, 'name' => 'Centre-ville', 'fee' => 100, 'sort' => 0, 'is_active' => true]);

        $this->put(route('tagtoa.menu.dashboard.update', $menu->id), [
            'name' => 'Lounge', 'currency' => 'HTG', 'form_end' => '1',
            'delivery_zones' => [['id' => $zone->id, 'name' => 'Centre-ville', 'fee' => '150']],
        ])->assertRedirect();

        $this->assertSame(1, $menu->deliveryZones()->count());
        $this->assertSame('150.00', $zone->fresh()->fee);
    }

    public function test_a_delivery_order_with_a_chosen_zone_is_charged_that_zone_s_fee_instead_of_the_flat_fee(): void
    {
        $menu = $this->menu();
        $item = $this->item($menu);
        $zone = DeliveryZone::create(['menu_id' => $menu->id, 'name' => 'Périphérie', 'fee' => 250, 'sort' => 0, 'is_active' => true]);

        $order = app(MenuOrderService::class)->placeOrder($menu, [
            'items' => [['id' => $item->id, 'qty' => 1]], 'order_type' => 'delivery',
            'delivery_zone_id' => $zone->id,
        ]);

        $this->assertSame('250.00', $order->delivery_fee);
        $this->assertSame('Périphérie', $order->delivery_zone_label);
        $this->assertSame('350.00', $order->total);
    }

    public function test_an_inactive_or_foreign_zone_id_falls_back_to_the_flat_fee(): void
    {
        $menu = $this->menu();
        $item = $this->item($menu);
        $autreMenu = Menu::create(['tenant_id' => 't-2', 'name' => 'Autre', 'alias' => 'autre-'.uniqid(), 'currency' => 'HTG']);
        $zoneEtrangere = DeliveryZone::create(['menu_id' => $autreMenu->id, 'name' => 'Zone X', 'fee' => 999, 'sort' => 0, 'is_active' => true]);

        $order = app(MenuOrderService::class)->placeOrder($menu, [
            'items' => [['id' => $item->id, 'qty' => 1]], 'order_type' => 'delivery',
            'delivery_zone_id' => $zoneEtrangere->id,
        ]);

        // Retombe sur le frais unique du menu (50), jamais sur celui de la
        // zone d'un autre commerce, et la commande n'est pas refusée pour
        // autant : un identifiant périmé ne doit jamais bloquer une livraison.
        $this->assertSame('50.00', $order->delivery_fee);
        $this->assertNull($order->delivery_zone_label);
    }

    public function test_a_menu_without_any_zone_behaves_exactly_as_before(): void
    {
        $menu = $this->menu();
        $item = $this->item($menu);

        $order = app(MenuOrderService::class)->placeOrder($menu, [
            'items' => [['id' => $item->id, 'qty' => 1]], 'order_type' => 'delivery',
        ]);

        $this->assertSame('50.00', $order->delivery_fee);
        $this->assertNull($order->delivery_zone_label);
    }

    public function test_the_public_page_offers_a_zone_picker_only_when_the_menu_has_zones(): void
    {
        $menu = $this->menu();
        DeliveryZone::create(['menu_id' => $menu->id, 'name' => 'Centre-ville', 'fee' => 100, 'sort' => 0, 'is_active' => true]);

        $html = $this->get('/menu/'.$menu->alias)->assertOk()->getContent();

        $this->assertStringContainsString('id="cZone"', $html);
        $this->assertStringContainsString('Centre-ville', $html);
    }

    public function test_the_public_page_never_shows_a_zone_picker_for_a_menu_without_zones(): void
    {
        $menu = $this->menu();

        $html = $this->get('/menu/'.$menu->alias)->assertOk()->getContent();

        $this->assertStringNotContainsString('id="cZone"', $html);
    }
}
