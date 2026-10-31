<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA MENU — une livraison exige un numéro, une adresse (et accepte un repère)
|--------------------------------------------------------------------------
| Une commande en LIVRAISON sans téléphone ni adresse ne peut pas être livrée :
| personne ne joint le client, personne ne trouve la porte. Avant ce correctif,
| le serveur l'acceptait. En Haïti beaucoup de maisons n'ont ni numéro ni rue :
| le « repère » (en face du bâtiment, à côté de l'église) est ce qui retrouve
| la porte — champ facultatif placé après l'adresse.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Menu\Order;
use Modules\Tagtoa\Tests\TestCase;

class MenuDeliveryRequiredInfoTest extends TestCase
{
    use RefreshDatabase;

    private Menu $menu;
    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->menu = Menu::create(['tenant_id' => 't-1', 'name' => 'BS', 'alias' => 'bs', 'currency' => 'HTG',
            'is_active' => true, 'ordering_enabled' => true, 'service_types' => ['dine_in', 'pickup', 'delivery']]);
        $cat = Category::create(['menu_id' => $this->menu->id, 'name' => 'Plats', 'is_active' => true]);
        $this->item = Item::create(['menu_id' => $this->menu->id, 'category_id' => $cat->id, 'name' => 'Pizza', 'price' => 500, 'is_available' => true]);
    }

    private function order(array $extra = [])
    {
        return $this->postJson('/menu/bs/order', array_merge([
            'items' => [['id' => $this->item->id, 'qty' => 1]], 'order_type' => 'delivery',
        ], $extra));
    }

    public function test_delivery_without_phone_is_refused_and_nothing_is_saved(): void
    {
        $this->order(['delivery_address' => 'Delmas 33'])->assertStatus(422)
            ->assertJson(['ok' => false])->assertJsonFragment(['message' => 'Pour une livraison, indiquez votre numéro de téléphone et votre adresse.']);

        $this->assertSame(0, Order::count());
    }

    public function test_delivery_without_address_is_refused(): void
    {
        $this->order(['customer_phone' => '50933333333'])->assertStatus(422);
        $this->order(['customer_phone' => '50933333333', 'delivery_address' => '  '])->assertStatus(422);
        $this->assertSame(0, Order::count());
    }

    public function test_a_phone_that_is_not_a_real_number_is_refused(): void
    {
        $this->order(['customer_phone' => '123', 'delivery_address' => 'Delmas 33'])->assertStatus(422);
        $this->order(['customer_phone' => 'abcdefgh', 'delivery_address' => 'Delmas 33'])->assertStatus(422);
        $this->assertSame(0, Order::count());
    }

    public function test_delivery_with_phone_and_address_works_and_the_landmark_is_optional(): void
    {
        $this->order(['customer_phone' => '+509 3333-3333', 'delivery_address' => 'Delmas 33'])->assertOk()->assertJson(['ok' => true]);
        $this->assertNull(Order::first()->delivery_reference);
    }

    public function test_the_landmark_is_saved_trimmed_and_shown_on_tracking_and_dashboard(): void
    {
        $r = $this->order(['customer_phone' => '50933333333', 'delivery_address' => 'Delmas 33',
            'delivery_reference' => "  En face de l'église Saint-Jean  "])->assertOk();

        $order = Order::first();
        $this->assertSame("En face de l'église Saint-Jean", $order->delivery_reference);

        $this->get($r->json('track_url'))->assertOk()->assertSee('Repère')->assertSee("En face de l&#039;église Saint-Jean", false);

        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-1', 'name' => 'Rose']));
        $this->get(route('tagtoa.menu.dashboard.orders', $this->menu->id))->assertOk()
            ->assertSee('Repère')->assertSee("En face de l&#039;église Saint-Jean", false);
    }

    public function test_dine_in_and_pickup_do_not_need_a_phone_or_address(): void
    {
        $this->order(['order_type' => 'dine_in'])->assertOk();
        $this->order(['order_type' => 'pickup'])->assertOk();
        $this->assertSame(2, Order::count());
    }

    public function test_a_landmark_sent_with_a_non_delivery_order_is_ignored(): void
    {
        $this->order(['order_type' => 'pickup', 'delivery_reference' => 'quelque part', 'delivery_address' => 'x'])->assertOk();
        $o = Order::first();
        $this->assertNull($o->delivery_reference);
        $this->assertNull($o->delivery_address);
    }

    public function test_the_same_rule_applies_to_a_replayed_offline_order(): void
    {
        // La file hors ligne rejoue la même charge utile vers la même route :
        // une vieille commande « livraison » sans numéro est refusée (422), pas acceptée.
        $payload = ['items' => [['id' => $this->item->id, 'qty' => 1]], 'order_type' => 'delivery',
            'client_uuid' => 'old-queued-1', 'delivery_address' => 'Delmas 33'];

        $this->postJson('/menu/bs/order', $payload)->assertStatus(422);
        $this->assertSame(0, Order::count());
    }

    public function test_the_order_form_has_the_landmark_field_and_checks_delivery_info_client_side(): void
    {
        $html = $this->get('/menu/bs')->assertOk()->getContent();

        $this->assertStringContainsString('id="cRef"', $html);
        $this->assertStringContainsString("orderType === 'delivery'", $html);
        $this->assertStringContainsString('delivery_reference:val(\'cRef\')', $html);
        $this->assertGreaterThan(strpos($html, 'id="cAddress"'), strpos($html, 'id="cRef"'), 'Le repère vient APRÈS l\'adresse.');
    }

    public function test_the_migration_is_replayable_and_reversible(): void
    {
        $this->assertTrue(Schema::hasColumn('tagtoa_menu_orders', 'delivery_reference'));

        $mig = require __DIR__.'/../../Database/migrations/2026_11_01_000181_add_delivery_reference_to_menu_orders.php';
        $mig->up();                       // colonne déjà là : ne casse pas
        $mig->down();
        $this->assertFalse(Schema::hasColumn('tagtoa_menu_orders', 'delivery_reference'));
        $mig->up();
        $this->assertTrue(Schema::hasColumn('tagtoa_menu_orders', 'delivery_reference'));
    }
}
