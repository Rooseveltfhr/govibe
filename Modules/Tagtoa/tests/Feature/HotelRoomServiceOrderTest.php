<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA HÔTEL (H5b) — room service : une commande Menu passée depuis une chambre
|--------------------------------------------------------------------------
| Le portail de la chambre mène au Menu avec un jeton lié à la chambre ET au menu. Le serveur revérifie
| tout à la commande (jeton → support → chambre → hôtel → même commerce → service room service actif
| pointant ce menu) ; un jeton invalide est REFUSÉ. La commande reste « sur place », au nom de la chambre,
| non payée (règlement à la livraison / sur la note de l'hôtel : aucun paiement nouveau).
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Modules\Tagtoa\App\Exceptions\OrderRuleException;
use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Models\Hotel\HotelRoom;
use Modules\Tagtoa\App\Models\Hotel\HotelService;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Menu\Order;
use Modules\Tagtoa\App\Models\Stand\Stand;
use Modules\Tagtoa\App\Services\Business\BusinessService;
use Modules\Tagtoa\App\Services\Hotel\HotelRoomOrderGate;
use Modules\Tagtoa\App\Services\Hotel\HotelRoomSupportService;
use Modules\Tagtoa\App\Services\Menu\MenuOrderService;
use Modules\Tagtoa\App\Services\Stand\StandMinter;
use Modules\Tagtoa\App\Support\Hotel\HotelRoomOrderToken;
use Modules\Tagtoa\App\Support\Stand\StandScratch;
use Modules\Tagtoa\App\Support\Tenant;
use Modules\Tagtoa\Tests\TestCase;

class HotelRoomServiceOrderTest extends TestCase
{
    use RefreshDatabase;

    private array $secrets = [];

    private function stand(int $i): Stand
    {
        return Stand::orderBy('serial')->get()[$i];
    }

    /** Un hôtel : chambre 101 équipée d'un support, un Menu avec un plat, un service room service vers ce menu. */
    private function hotel(string $compte = 'c-a', string $nom = 'Hôtel Alpha', int $standIndex = 0): array
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $compte, 'name' => 'Rose']));
        Tenant::flush();
        $b = app(BusinessService::class)->create(Tenant::account(), ['name' => $nom, 'type' => 'hotel', 'currency' => 'HTG']);
        $room = HotelRoom::allTenants()->create(['tenant_id' => $b->id, 'number' => '101', 'is_active' => true]);
        $stand = $this->stand($standIndex);
        $r = app(HotelRoomSupportService::class)->attach($b->id, $room->id, StandScratch::payload($stand->public_id, $this->secrets[$stand->public_id]));
        $this->assertSame('ok', $r['result']);

        $menu = Menu::allTenants()->create([
            'tenant_id' => $b->id, 'name' => 'Restaurant '.$nom, 'alias' => 'resto-'.$compte, 'type' => 'restaurant', 'currency' => 'HTG',
            'is_active' => true, 'ordering_enabled' => true, 'show_prices' => true,
        ]);
        $cat = Category::create(['menu_id' => $menu->id, 'name' => 'Plats', 'is_active' => true]);
        $item = Item::create(['menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Griot', 'price' => 400, 'is_available' => true]);
        $svc = HotelService::allTenants()->create(['tenant_id' => $b->id, 'kind' => 'room_service', 'name' => 'Room service', 'menu_id' => $menu->id, 'is_active' => true]);

        return [$b, $room, $stand->fresh(), $menu, $item, $svc];
    }

    private function commander(Menu $menu, Item $item, ?string $token, array $extra = [])
    {
        return app(MenuOrderService::class)->placeOrder($menu, array_merge([
            'items' => [['id' => $item->id, 'qty' => 2]], 'room_token' => $token, 'order_type' => 'pickup',
        ], $extra));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->secrets = app(StandMinter::class)->mint('TAGTOA-2026-001', 4)['secrets'];
    }

    /* ---------- jeton ---------- */

    public function test_the_token_is_bound_to_its_support_and_its_menu_and_expires(): void
    {
        $t = HotelRoomOrderToken::issue('TG-000001', 7, 1_800_000_000);

        $this->assertSame('TG-000001', HotelRoomOrderToken::verify($t, 7, 1_800_000_000 + 60));
        $this->assertNull(HotelRoomOrderToken::verify($t, 8, 1_800_000_000 + 60), 'Autre menu.');
        $this->assertNull(HotelRoomOrderToken::verify($t, 7, 1_800_000_000 + HotelRoomOrderToken::TTL + 1), 'Expiré.');
        $this->assertNull(HotelRoomOrderToken::verify($t, 7, 1_800_000_000 - 3600), 'Daté du futur.');
        $this->assertNull(HotelRoomOrderToken::verify(str_replace('TG-000001', 'TG-000002', $t), 7, 1_800_000_000 + 60), 'Autre support.');
        $this->assertNull(HotelRoomOrderToken::verify(substr($t, 0, -1).(substr($t, -1) === '0' ? '1' : '0'), 7, 1_800_000_000 + 60), 'Altéré.');
        $this->assertNull(HotelRoomOrderToken::verify(null, 7));
        $this->assertNull(HotelRoomOrderToken::verify('n-importe-quoi', 7));
    }

    /* ---------- portail et page Menu ---------- */

    public function test_the_room_page_links_room_service_to_the_menu_with_a_token(): void
    {
        [, , $stand, $menu, , ] = $this->hotel();

        $html = $this->get('/h/'.$stand->public_id)->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#href="[^"]*/menu/resto-c-a\?r=TG-[A-Z0-9\-]+\.\d+\.[a-f0-9]{16}\.[a-f0-9]{64}"#', $html);
        $this->assertStringContainsString('Commander', $html);
    }

    public function test_no_order_link_when_the_menu_is_inactive_or_orders_are_off(): void
    {
        [, , $stand, $menu, , ] = $this->hotel();
        $menu->update(['ordering_enabled' => false]);
        $this->get('/h/'.$stand->public_id)->assertOk()->assertDontSee('Commander');

        $menu->update(['ordering_enabled' => true, 'is_active' => false]);
        $this->get('/h/'.$stand->public_id)->assertOk()->assertDontSee('Commander');
    }

    public function test_the_menu_page_shows_the_fixed_room_with_a_valid_token_and_an_ordinary_page_otherwise(): void
    {
        [, , $stand, $menu, , ] = $this->hotel();
        $token = HotelRoomOrderToken::issue($stand->public_id, $menu->id);

        $ok = $this->get(route('tagtoa.menu.show', $menu->alias).'?r='.urlencode($token))->assertOk()->getContent();
        $this->assertStringContainsString('Chambre 101', $ok);
        // Le jeton part avec la commande (charge utile JSON de la page), pas seulement dans l'adresse.
        $this->assertStringContainsString('room_token:'.json_encode($token), $ok);

        $ko = $this->get(route('tagtoa.menu.show', $menu->alias).'?r='.urlencode('faux.jeton'))->assertOk()->getContent();
        $this->assertStringNotContainsString('Chambre 101', $ko);
        $this->assertStringNotContainsString('TG-', $ko);
    }

    /* ---------- la commande ---------- */

    public function test_a_valid_token_makes_a_dine_in_order_in_the_rooms_name_priced_by_the_server(): void
    {
        [$b, $room, $stand, $menu, $item, ] = $this->hotel();

        $o = $this->commander($menu, $item, HotelRoomOrderToken::issue($stand->public_id, $menu->id), ['order_type' => 'delivery']);

        $this->assertSame('dine_in', $o->order_type, 'Jamais une livraison : le client est déjà dans la chambre.');
        $this->assertSame('Chambre 101', $o->table_label);
        $this->assertSame($room->id, $o->hotel_room_id);
        $this->assertSame($b->id, $o->tenant_id);
        $this->assertEquals(800, $o->total);
        $this->assertSame('unpaid', $o->payment_status, 'Aucun paiement nouveau : règlement à la livraison / sur la note.');
    }

    public function test_an_ordinary_order_is_untouched(): void
    {
        [, , , $menu, $item, ] = $this->hotel();

        $o = $this->commander($menu, $item, null);

        $this->assertNull($o->hotel_room_id);
        $this->assertSame('pickup', $o->order_type);
        $this->assertNull($o->table_label);
    }

    public function test_the_room_wins_over_a_table_code(): void
    {
        [, $room, $stand, $menu, $item, ] = $this->hotel();
        \Modules\Tagtoa\App\Models\Menu\Table::create(['menu_id' => $menu->id, 'label' => 'Table 9', 'code' => 'ABCD1234', 'is_active' => true]);

        $o = $this->commander($menu, $item, HotelRoomOrderToken::issue($stand->public_id, $menu->id), ['table_code' => 'ABCD1234']);

        $this->assertSame('Chambre 101', $o->table_label);
        $this->assertSame($room->id, $o->hotel_room_id);
    }

    public function test_replaying_the_same_order_returns_the_same_order(): void
    {
        [, , $stand, $menu, $item, ] = $this->hotel();
        $tk = HotelRoomOrderToken::issue($stand->public_id, $menu->id);

        $a = $this->commander($menu, $item, $tk, ['client_uuid' => 'room-uuid-1']);
        $b = $this->commander($menu, $item, $tk, ['client_uuid' => 'room-uuid-1']);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, Order::count());
    }

    /* ---------- refus : jamais ignoré en silence ---------- */

    private function refuse(Menu $menu, Item $item, ?string $token): void
    {
        try {
            $this->commander($menu, $item, $token);
            $this->fail('Commande acceptée avec un jeton invalide.');
        } catch (OrderRuleException $e) {
            $this->assertSame('invalid_room', $e->getMessage());
        }
        $this->assertSame(0, Order::count());
    }

    public function test_garbage_expired_and_foreign_menu_tokens_are_refused(): void
    {
        [, , $stand, $menu, $item, ] = $this->hotel();

        $this->refuse($menu, $item, 'garbage');
        $this->refuse($menu, $item, HotelRoomOrderToken::issue($stand->public_id, $menu->id, time() - HotelRoomOrderToken::TTL - 100));
        $this->refuse($menu, $item, HotelRoomOrderToken::issue($stand->public_id, $menu->id + 99));
    }

    public function test_a_revoked_support_an_inactive_room_or_a_non_hotel_refuse_the_order(): void
    {
        [$b, $room, $stand, $menu, $item, ] = $this->hotel();
        $tk = HotelRoomOrderToken::issue($stand->public_id, $menu->id);

        $room->update(['is_active' => false]);
        $this->refuse($menu, $item, $tk);
        $room->update(['is_active' => true]);

        \Illuminate\Support\Facades\DB::table('tagtoa_businesses')->where('id', $b->id)->update(['type' => 'restaurant']);
        $this->refuse($menu, $item, $tk);
        \Illuminate\Support\Facades\DB::table('tagtoa_businesses')->where('id', $b->id)->update(['type' => 'hotel']);

        app(HotelRoomSupportService::class)->revoke($b->id, $room->id);
        $this->refuse($menu, $item, $tk);
    }

    public function test_a_service_that_is_off_or_points_elsewhere_refuses_the_order(): void
    {
        [$b, , $stand, $menu, $item, $svc] = $this->hotel();
        $tk = HotelRoomOrderToken::issue($stand->public_id, $menu->id);

        $svc->update(['is_active' => false]);
        $this->refuse($menu, $item, $tk);
        $svc->update(['is_active' => true, 'menu_id' => $menu->id + 50]);
        $this->refuse($menu, $item, $tk);
    }

    public function test_a_token_of_one_hotel_cannot_order_from_another_hotels_menu(): void
    {
        [, , $standA, , , ] = $this->hotel('c-a', 'Hôtel Alpha', 0);
        [, , , $menuB, $itemB, ] = $this->hotel('c-b', 'Hôtel Bravo', 1);

        // Jeton signé pour le menu de Bravo mais avec le support d'Alpha (forgé par un tiers qui connaît la clé : pire cas).
        $this->refuse($menuB, $itemB, HotelRoomOrderToken::issue($standA->public_id, $menuB->id));
    }

    public function test_the_menu_of_a_hotel_without_a_matching_service_cannot_be_ordered_from_a_room(): void
    {
        [$b, , $stand, , , ] = $this->hotel();
        $autre = Menu::allTenants()->create(['tenant_id' => $b->id, 'name' => 'Bar', 'alias' => 'bar-a', 'type' => 'bar', 'currency' => 'HTG', 'is_active' => true, 'ordering_enabled' => true]);
        $cat = Category::create(['menu_id' => $autre->id, 'name' => 'Boissons', 'is_active' => true]);
        $it = Item::create(['menu_id' => $autre->id, 'category_id' => $cat->id, 'name' => 'Rhum', 'price' => 100, 'is_available' => true]);

        $this->refuse($autre, $it, HotelRoomOrderToken::issue($stand->public_id, $autre->id));
    }

    /* ---------- plafonds ---------- */

    public function test_a_room_cannot_have_more_open_orders_than_the_cap_and_a_finished_one_frees_a_slot(): void
    {
        [, $room, $stand, $menu, $item, ] = $this->hotel();
        for ($i = 0; $i < HotelRoomOrderGate::OPEN_MAX; $i++) {
            $this->commander($menu, $item, HotelRoomOrderToken::issue($stand->public_id, $menu->id), ['client_uuid' => "u$i"]);
        }

        try {
            $this->commander($menu, $item, HotelRoomOrderToken::issue($stand->public_id, $menu->id));
            $this->fail('Plafond dépassé.');
        } catch (OrderRuleException $e) {
            $this->assertSame('room_busy', $e->getMessage());
        }

        Order::where('hotel_room_id', $room->id)->first()->update(['status' => 'completed']);
        $this->commander($menu, $item, HotelRoomOrderToken::issue($stand->public_id, $menu->id));
        $this->assertSame(HotelRoomOrderGate::OPEN_MAX + 1, Order::count());
    }

    public function test_orders_per_hour_are_capped_per_room(): void
    {
        [, $room, $stand, $menu, $item, ] = $this->hotel();
        for ($i = 0; $i < HotelRoomOrderGate::HOURLY_MAX; $i++) {
            RateLimiter::hit('hotel-room-order:'.$room->id, 3600);
        }

        try {
            $this->commander($menu, $item, HotelRoomOrderToken::issue($stand->public_id, $menu->id));
            $this->fail('Plafond horaire dépassé.');
        } catch (OrderRuleException $e) {
            $this->assertSame('room_busy', $e->getMessage());
        }
    }

    public function test_every_recorded_order_counts_toward_the_hourly_cap(): void
    {
        [, $room, $stand, $menu, $item, ] = $this->hotel();
        RateLimiter::clear('hotel-room-order:'.$room->id);

        for ($i = 0; $i < HotelRoomOrderGate::HOURLY_MAX; $i++) {
            $o = $this->commander($menu, $item, HotelRoomOrderToken::issue($stand->public_id, $menu->id));
            $o->update(['status' => 'completed']);   // le plafond des commandes ouvertes ne joue pas ici
        }

        try {
            $this->commander($menu, $item, HotelRoomOrderToken::issue($stand->public_id, $menu->id));
            $this->fail('La 11e commande de l\'heure est passée.');
        } catch (OrderRuleException $e) {
            $this->assertSame('room_busy', $e->getMessage());
        }
    }

    /* ---------- HTTP public ---------- */

    public function test_the_public_endpoint_explains_a_dead_room_link(): void
    {
        [, , , $menu, $item, ] = $this->hotel();

        $this->postJson(route('tagtoa.menu.order', $menu->alias), [
            'items' => [['id' => $item->id, 'qty' => 1]], 'room_token' => 'faux.jeton',
        ])->assertStatus(422)->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'Ce lien de chambre n\'est plus valide. Rescannez le QR de votre chambre.');
        $this->assertSame(0, Order::count());
    }

    public function test_the_public_endpoint_creates_the_room_order(): void
    {
        [, $room, $stand, $menu, $item, ] = $this->hotel();

        $this->postJson(route('tagtoa.menu.order', $menu->alias), [
            'items' => [['id' => $item->id, 'qty' => 1]], 'room_token' => HotelRoomOrderToken::issue($stand->public_id, $menu->id),
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertSame($room->id, Order::sole()->hotel_room_id);
    }

    public function test_the_migration_is_replayable_and_reversible(): void
    {
        $mig = require __DIR__.'/../../Database/migrations/2026_11_02_000193_add_hotel_room_to_menu_orders.php';
        $mig->up();
        $mig->up();
        $mig->down();
        $this->assertFalse(Schema::hasColumn('tagtoa_menu_orders', 'hotel_room_id'));
        $mig->up();
        $this->assertTrue(Schema::hasColumn('tagtoa_menu_orders', 'hotel_room_id'));
    }
}
