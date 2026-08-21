<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA MENU — placement de commande avec options, pourboire, mode de
| service + encaissement (commission hors pourboire + points fidélité).
|--------------------------------------------------------------------------
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Loyalty\Card;
use Modules\Tagtoa\App\Models\Loyalty\Program;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\ItemOption;
use Modules\Tagtoa\App\Models\Menu\ItemOptionChoice;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Services\Menu\MenuOrderService;
use Modules\Tagtoa\Tests\TestCase;

class MenuOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeMenu(): Menu
    {
        return Menu::create([
            'vcard_id' => 77, 'tenant_id' => 't1', 'name' => 'Lounge Test',
            'alias' => 'lounge-test', 'currency' => 'HTG', 'is_active' => true,
            'ordering_enabled' => true,
        ]);
    }

    public function test_places_order_with_options_and_tip_and_computes_server_side_price(): void
    {
        $menu = $this->makeMenu();
        $cat = Category::create(['menu_id' => $menu->id, 'name' => 'Pizzas', 'sort' => 0, 'is_active' => true]);
        $item = Item::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Pizza',
            'price' => 500, 'is_available' => true,
        ]);
        $size = ItemOption::create(['item_id' => $item->id, 'name' => 'Taille', 'required' => true, 'multiple' => false]);
        $large = ItemOptionChoice::create(['option_id' => $size->id, 'label' => 'Grande', 'price_delta' => 100]);
        ItemOptionChoice::create(['option_id' => $size->id, 'label' => 'Petite', 'price_delta' => 0]);

        $order = app(MenuOrderService::class)->placeOrder($menu, [
            'items'          => [['id' => $item->id, 'qty' => 2, 'options' => [$large->id]]],
            'order_type'     => 'delivery',
            'delivery_address' => 'Rue Test 1',
            'tip'            => 60,
            'customer_name'  => 'Jean',
            'customer_phone' => '+509 3456 7890',
        ]);

        // (500 + 100) * 2 = 1200 sous-total, + 60 pourboire = 1260 total.
        $this->assertSame('1200.00', $order->subtotal);
        $this->assertSame('1260.00', $order->total);
        $this->assertSame('60.00', $order->tip);
        $this->assertSame('delivery', $order->order_type);
        $this->assertSame('Rue Test 1', $order->delivery_address);
        $this->assertNull($order->table_label);

        $line = $order->items()->first();
        $this->assertSame('600.00', $line->price);
        // json_encode(100.0) round-trips as l'entier 100 (perte du .0, sans impact
        // sur la facturation qui repose sur price/line_total, pas ce snapshot).
        $this->assertEquals([['group' => 'Taille', 'label' => 'Grande', 'price_delta' => 100]], $line->selected_options);
    }

    public function test_missing_required_option_rejects_the_order(): void
    {
        $menu = $this->makeMenu();
        $cat = Category::create(['menu_id' => $menu->id, 'name' => 'Pizzas', 'sort' => 0, 'is_active' => true]);
        $item = Item::create(['menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Pizza', 'price' => 500, 'is_available' => true]);
        ItemOption::create(['item_id' => $item->id, 'name' => 'Taille', 'required' => true, 'multiple' => false]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('missing_required_option');

        app(MenuOrderService::class)->placeOrder($menu, [
            'items' => [['id' => $item->id, 'qty' => 1]],
        ]);
    }

    public function test_mark_paid_charges_commission_on_subtotal_only_and_awards_loyalty_points(): void
    {
        $menu = $this->makeMenu();
        $cat = Category::create(['menu_id' => $menu->id, 'name' => 'Boissons', 'sort' => 0, 'is_active' => true]);
        $item = Item::create(['menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Jus', 'price' => 200, 'is_available' => true]);

        $program = Program::create([
            'vcard_id' => $menu->vcard_id, 'name' => 'Fidélité Test', 'alias' => 'fidelite-test',
            'points_per_dollar' => 2, 'currency' => 'HTG', 'is_active' => true,
        ]);
        $card = Card::create([
            'program_id' => $program->id, 'public_token' => 'tok123', 'card_number' => '4297000000000001',
            'cvc' => bcrypt('1234'), 'expiry_date' => now()->addYear(), 'cardholder_name' => 'Jean',
            'cardholder_phone' => '34567890', 'balance' => 0, 'points' => 0, 'status' => Card::STATUS_ACTIVE,
        ]);

        $order = app(MenuOrderService::class)->placeOrder($menu, [
            'items'          => [['id' => $item->id, 'qty' => 3]],
            'tip'            => 30,
            'customer_phone' => '+509 3456 7890', // même numéro que la carte, format différent
        ]);
        $this->assertSame('600.00', $order->subtotal);
        $this->assertSame('630.00', $order->total);

        app(MenuOrderService::class)->markPaid($order->fresh());

        $card->refresh();
        $this->assertSame(1200, $card->points); // 600 (sous-total, hors pourboire) * 2 pts/HTG
    }
}
