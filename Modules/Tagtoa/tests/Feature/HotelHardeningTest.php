<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA HÔTEL (H7) — durcissement transversal (audit de l'ensemble du module)
|--------------------------------------------------------------------------
| Alerte de la tablette qui ne boucle plus, demandes récentes jamais cachées, compteurs de limite
| séparés par surface, un hôtel qui le reste, plafonds de commandes de chambre qui vieillissent,
| rétention des données d'invité, support « à remplacer » visible, index.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Modules\Tagtoa\App\Exceptions\OrderRuleException;
use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Models\Hotel\HotelRequest;
use Modules\Tagtoa\App\Models\Hotel\HotelRoom;
use Modules\Tagtoa\App\Models\Hotel\HotelService;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Menu\Order;
use Modules\Tagtoa\App\Models\Staff\Staff;
use Modules\Tagtoa\App\Models\Stand\Stand;
use Modules\Tagtoa\App\Services\Business\BusinessService;
use Modules\Tagtoa\App\Services\Event\StaffPinService;
use Modules\Tagtoa\App\Services\Hotel\HotelRoomOrderGate;
use Modules\Tagtoa\App\Services\Hotel\HotelRoomSupportService;
use Modules\Tagtoa\App\Services\Menu\MenuOrderService;
use Modules\Tagtoa\App\Services\Stand\StandMinter;
use Modules\Tagtoa\App\Support\Hotel\HotelRoomOrderToken;
use Modules\Tagtoa\App\Support\Pos\StaffAccess;
use Modules\Tagtoa\App\Support\Stand\StandScratch;
use Modules\Tagtoa\App\Support\Stand\StandState;
use Modules\Tagtoa\App\Support\Tenant;
use Modules\Tagtoa\Tests\TestCase;

class HotelHardeningTest extends TestCase
{
    use RefreshDatabase;

    private array $secrets = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->secrets = app(StandMinter::class)->mint('TAGTOA-2026-001', 3)['secrets'];
    }

    private function stand(int $i): Stand
    {
        return Stand::orderBy('serial')->get()[$i];
    }

    /** @return array{0:Business,1:HotelRoom,2:Stand} */
    private function hotel(string $compte = 'c-a', string $nom = 'Hôtel Alpha', int $si = 0): array
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $compte, 'name' => 'Rose']));
        Tenant::flush();
        $b = app(BusinessService::class)->create(Tenant::account(), ['name' => $nom, 'type' => 'hotel', 'currency' => 'HTG']);
        $room = HotelRoom::allTenants()->create(['tenant_id' => $b->id, 'number' => '101', 'is_active' => true]);
        $stand = $this->stand($si);
        $r = app(HotelRoomSupportService::class)->attach($b->id, $room->id, StandScratch::payload($stand->public_id, $this->secrets[$stand->public_id]));
        $this->assertSame('ok', $r['result']);

        return [$b, $room, $stand->fresh()];
    }

    private function demande(Business $b, HotelRoom $room, string $team, string $status = 'new', string $nom = null): HotelRequest
    {
        static $n = 0;
        $n++;

        return HotelRequest::allTenants()->create([
            'tenant_id' => $b->id, 'room_id' => $room->id, 'reference' => 'HR-'.str_pad((string) $n, 8, 'X'), 'track_hash' => hash('sha256', (string) $n),
            'kind' => 'housekeeping', 'service_name' => $nom ?? "Demande $n", 'team' => $team, 'status' => $status,
        ]);
    }

    private function employe(Business $b, string $role, ?string $team, string $pin): Staff
    {
        return Staff::create(['tenant_id' => $b->id, 'name' => 'Marie', 'role' => $role, 'hotel_team' => $team, 'pin_hash' => StaffPinService::hashPin($pin), 'is_active' => true]);
    }

    /* ---------- C1/C2 : écran du personnel ---------- */

    public function test_the_alert_never_beeps_on_an_empty_board_or_when_the_newest_request_is_closed(): void
    {
        [$b, $room] = $this->hotel();
        $this->employe($b, StaffAccess::ROLE_HOTEL, 'housekeeping', '1111');
        $ancienne = $this->demande($b, $room, 'housekeeping');
        $recente = $this->demande($b, $room, 'housekeeping', 'cancelled');   // la plus récente est annulée
        $this->post(route('tagtoa.hotel.board.login'), ['pin' => '1111']);

        $a = $this->getJson(route('tagtoa.hotel.board.alerts'))->assertOk()->json();
        $this->assertSame($ancienne->id, $a['latest_id'], 'Seules les demandes OUVERTES comptent.');

        // Tout est clos : l'écran vide et l'alerte disent la même chose (0), donc aucun rechargement en boucle.
        $ancienne->update(['status' => 'done']);
        $this->assertSame(0, $this->getJson(route('tagtoa.hotel.board.alerts'))->json('latest_id'));
        $this->assertStringContainsString('var dernier = 0;', $this->get(route('tagtoa.hotel.board'))->getContent());
    }

    public function test_a_new_request_is_never_hidden_behind_a_hundred_old_ones(): void
    {
        [$b, $room] = $this->hotel();
        $this->employe($b, StaffAccess::ROLE_HOTEL, 'housekeeping', '1111');
        for ($i = 0; $i < 100; $i++) {
            $this->demande($b, $room, 'housekeeping', 'new', 'Ancienne');
        }
        $this->demande($b, $room, 'housekeeping', 'new', 'Serviettes-NOUVELLE');
        $this->post(route('tagtoa.hotel.board.login'), ['pin' => '1111']);

        $html = $this->get(route('tagtoa.hotel.board'))->assertOk()->getContent();

        $this->assertStringContainsString('Serviettes-NOUVELLE', $html);
        $this->assertStringContainsString('1 demandes plus anciennes ne sont pas affichées', $html);
    }

    /* ---------- C3 : compteurs séparés ---------- */

    public function test_room_page_scans_do_not_use_up_the_menu_order_limit_of_the_same_address(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->get('/h/TG-999999')->assertNotFound();
        }

        // Avant : 20 requêtes sur /h/ suffisaient à faire répondre 429 aux commandes du MÊME réseau.
        $this->postJson('/menu/n-existe-pas/order', ['items' => [['id' => 1, 'qty' => 1]]])->assertNotFound();
    }

    public function test_each_public_hotel_route_has_its_own_throttle_counter(): void
    {
        $prefixes = [];
        foreach (\Illuminate\Support\Facades\Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'h/')) {
                continue;
            }
            foreach ($route->gatherMiddleware() as $m) {
                if (is_string($m) && str_starts_with($m, 'throttle:')) {
                    $parts = explode(',', substr($m, 9));
                    $this->assertCount(3, $parts, $route->getName().' : compteur partagé (pas de préfixe).');
                    $prefixes[$route->getName()] = $parts[2];
                }
            }
        }
        $this->assertGreaterThanOrEqual(4, count($prefixes));
        $this->assertSame(count($prefixes), count(array_unique($prefixes)), 'Deux routes publiques partagent un même compteur.');
    }

    /* ---------- C4 : un hôtel le reste ---------- */

    public function test_a_menu_of_type_restaurant_never_turns_the_hotel_into_a_restaurant(): void
    {
        [$b] = $this->hotel();
        $menu = Menu::allTenants()->create(['tenant_id' => $b->id, 'name' => 'Resto', 'alias' => 'resto-h', 'type' => 'restaurant', 'currency' => 'HTG', 'is_active' => true]);

        $this->put(route('tagtoa.menu.dashboard.update', $menu->id), ['name' => 'Resto', 'currency' => 'HTG', 'type' => 'bar']);

        $this->assertSame('hotel', Business::find($b->id)->type);
    }

    public function test_a_hotel_with_open_requests_orders_or_supports_cannot_change_type(): void
    {
        [$b, $room] = $this->hotel();
        $service = app(\Modules\Tagtoa\App\Services\Hotel\HotelProfileService::class);

        $this->assertStringContainsString('support', (string) $service->blocksTypeChange($b));   // support actif

        app(HotelRoomSupportService::class)->revoke($b->id, $room->id);
        $this->assertNull($service->blocksTypeChange($b));

        $r = $this->demande($b, $room, 'reception');
        $this->assertStringContainsString('demandes', (string) $service->blocksTypeChange($b));
        $r->update(['status' => 'done']);
        $this->assertNull($service->blocksTypeChange($b));

        $menu = Menu::allTenants()->create(['tenant_id' => $b->id, 'name' => 'Resto', 'alias' => 'resto-z', 'type' => 'restaurant', 'currency' => 'HTG', 'is_active' => true]);
        $o = Order::allTenants()->create(['menu_id' => $menu->id, 'tenant_id' => $b->id, 'reference' => 'ORD-ZZZ', 'subtotal' => 1, 'total' => 1, 'currency' => 'HTG', 'status' => 'pending', 'payment_status' => 'unpaid', 'order_type' => 'dine_in', 'hotel_room_id' => $room->id, 'placed_at' => now()]);
        $this->assertStringContainsString('commandes de chambre', (string) $service->blocksTypeChange($b));
        $o->update(['status' => 'completed']);
        $this->assertNull($service->blocksTypeChange($b));
    }

    public function test_the_pos_shortcut_cannot_turn_a_hotel_with_live_supports_into_another_type(): void
    {
        [$b] = $this->hotel();   // support actif sur la chambre

        $this->put(route('tagtoa.pos.settings.business-type'), ['type' => 'restaurant'])->assertSessionHas('error');
        $this->assertSame('hotel', \Modules\Tagtoa\App\Models\Business\Business::find($b->id)->type);
    }

    public function test_the_business_form_refuses_to_leave_the_hotel_type_while_something_is_open(): void
    {
        [$b] = $this->hotel();   // support actif => refus

        $this->put(route('tagtoa.business.update', $b->id), ['name' => 'Hôtel Alpha', 'type' => 'restaurant', 'currency' => 'HTG'])
            ->assertSessionHasErrors('type');

        $this->assertSame('hotel', Business::find($b->id)->type);
    }

    /* ---------- C5 : commandes de chambre ---------- */

    private function menuEtPlat(Business $b): array
    {
        $menu = Menu::allTenants()->create(['tenant_id' => $b->id, 'name' => 'Resto', 'alias' => 'resto-'.uniqid(), 'type' => 'restaurant', 'currency' => 'HTG', 'is_active' => true, 'ordering_enabled' => true]);
        $cat = Category::create(['menu_id' => $menu->id, 'name' => 'Plats', 'is_active' => true]);
        $item = Item::create(['menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Griot', 'price' => 400, 'is_available' => true]);
        HotelService::allTenants()->create(['tenant_id' => $b->id, 'kind' => 'room_service', 'name' => 'Room service', 'menu_id' => $menu->id, 'is_active' => true]);

        return [$menu, $item];
    }

    private function commander(Menu $m, Item $i, Stand $s)
    {
        return app(MenuOrderService::class)->placeOrder($m, ['items' => [['id' => $i->id, 'qty' => 1]], 'room_token' => HotelRoomOrderToken::issue($s->public_id, $m->id)]);
    }

    public function test_stale_pending_room_orders_stop_blocking_the_room(): void
    {
        [$b, $room, $stand] = $this->hotel();
        [$menu, $item] = $this->menuEtPlat($b);
        for ($i = 0; $i < HotelRoomOrderGate::OPEN_MAX; $i++) {
            $this->commander($menu, $item, $stand);
        }
        Order::where('hotel_room_id', $room->id)->update(['placed_at' => now()->subHours(HotelRoomOrderGate::OPEN_FRESH_HOURS + 1)]);
        RateLimiter::clear('hotel-room-order:'.$room->id);

        $this->commander($menu, $item, $stand);   // les vieilles commandes oubliées ne comptent plus

        $this->assertSame(HotelRoomOrderGate::OPEN_MAX + 1, Order::count());
    }

    public function test_room_orders_are_capped_for_the_whole_hotel_too(): void
    {
        [$b, , $stand] = $this->hotel();
        [$menu, $item] = $this->menuEtPlat($b);
        for ($i = 0; $i < HotelRoomOrderGate::HOTEL_HOURLY_MAX; $i++) {
            RateLimiter::hit('hotel-room-order-all:'.$b->id, 3600);
        }

        try {
            $this->commander($menu, $item, $stand);
            $this->fail('Plafond de l\'hôtel dépassé.');
        } catch (OrderRuleException $e) {
            $this->assertSame('room_busy', $e->getMessage());
        }
    }

    public function test_every_recorded_room_order_counts_toward_the_hotel_cap(): void
    {
        [$b, $room, $stand] = $this->hotel();
        [$menu, $item] = $this->menuEtPlat($b);
        RateLimiter::clear('hotel-room-order-all:'.$b->id);

        $this->commander($menu, $item, $stand);

        $this->assertSame(1, RateLimiter::attempts('hotel-room-order-all:'.$b->id));
    }

    /* ---------- C10 : « Chambre », pas « Table » ---------- */

    public function test_room_orders_are_labelled_as_rooms_on_the_kitchen_feed_and_the_tracking_page(): void
    {
        [$b, $room, $stand] = $this->hotel();
        [$menu, $item] = $this->menuEtPlat($b);
        $o = $this->commander($menu, $item, $stand);

        $feed = $this->getJson(route('tagtoa.menu.dashboard.kitchen.feed', $menu->id))->assertOk()->json();
        $ligne = collect($feed['orders'] ?? $feed)->firstWhere('reference', $o->reference);
        $this->assertTrue((bool) ($ligne['is_room'] ?? false));

        $track = $this->get(route('tagtoa.menu.track', $o->reference))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<span>Chambre</span><span>Chambre 101</span>#', $track);
    }

    /* ---------- C12 : rétention des données d'invité ---------- */

    public function test_the_purge_erases_the_guest_data_of_old_finished_room_orders_only(): void
    {
        [$b, $room, $stand] = $this->hotel();
        [$menu, $item] = $this->menuEtPlat($b);
        $vieille = $this->commander($menu, $item, $stand);
        $recente = $this->commander($menu, $item, $stand);
        Order::whereKey([$vieille->id, $recente->id])->update(['status' => 'completed']);   // libère le plafond de la chambre
        $ouverte = $this->commander($menu, $item, $stand);
        $abandonnee = $this->commander($menu, $item, $stand);
        $ordinaire = Order::allTenants()->create(['menu_id' => $menu->id, 'tenant_id' => $b->id, 'reference' => 'ORD-PLAIN', 'subtotal' => 1, 'total' => 1, 'currency' => 'HTG', 'status' => 'completed', 'payment_status' => 'paid', 'order_type' => 'pickup', 'customer_name' => 'Jean', 'placed_at' => now()->subYear()]);
        foreach ([$vieille, $recente, $ouverte, $abandonnee] as $o) {
            $o->forceFill(['customer_name' => 'Invité', 'customer_phone' => '+50937000000', 'note' => 'sans piment'])->save();
        }
        Order::whereKey($vieille->id)->update(['status' => 'completed', 'updated_at' => now()->subDays(120)]);
        Order::whereKey($recente->id)->update(['status' => 'completed']);
        Order::whereKey($abandonnee->id)->update(['placed_at' => now()->subDays(400)]);   // jamais terminée
        \Modules\Tagtoa\App\Models\Order\Order::allTenants()->whereIn('source_id', [$vieille->id, $recente->id, $abandonnee->id])->where('source_type', 'menu_order')->update(['customer_name' => 'Invité', 'customer_phone' => '+50937000000']);
        // Ouverte ET ancienne : seul le statut doit la protéger (sinon une commande en cours perdrait ses coordonnées).
        Order::whereKey($ouverte->id)->update(['updated_at' => now()->subDays(120)]);
        Order::whereKey($ordinaire->id)->update(['updated_at' => now()->subDays(400)]);

        $this->artisan('tagtoa:hotel:purge-requests')->assertSuccessful();

        $v = Order::find($vieille->id);
        $this->assertNull($v->customer_name);
        $this->assertNull($v->customer_phone);
        $this->assertNull($v->note);
        $this->assertEquals(400, $v->total, 'La ligne et les montants restent.');
        $spine = fn ($o) => \Modules\Tagtoa\App\Models\Order\Order::allTenants()->where('source_type', 'menu_order')->where('source_id', $o->id)->first();
        $this->assertNull($spine($vieille)->customer_name, 'La colonne vertébrale est purgée aussi.');
        $this->assertNull($spine($vieille)->customer_phone);
        $this->assertSame('Invité', $spine($recente)->customer_name, 'Récente : colonne vertébrale intacte.');
        $this->assertNull(Order::find($abandonnee->id)->customer_name, 'Restée ouverte au-delà du double délai : effacée.');
        $this->assertNull($spine($abandonnee)->customer_phone);
        $this->assertSame('Invité', Order::find($recente->id)->customer_name, 'Récente : intacte.');
        $this->assertSame('Invité', Order::find($ouverte->id)->customer_name, 'Encore ouverte : intacte.');
        $this->assertSame('Jean', Order::find($ordinaire->id)->customer_name, 'Une commande ordinaire n\'est jamais touchée.');
    }

    /* ---------- C14 : l'état réel du support ---------- */

    public function test_the_rooms_page_flags_a_support_that_no_longer_works(): void
    {
        [$b, $room, $stand] = $this->hotel();
        $this->get(route('tagtoa.hotel.rooms'))->assertOk()->assertDontSee('à remplacer');

        $stand->forceFill(['physical_state' => StandState::LOST])->save();

        $this->get(route('tagtoa.hotel.rooms'))->assertOk()->assertSee('à remplacer');
    }

    /* ---------- divers ---------- */

    public function test_the_hotel_info_field_warns_against_passwords(): void
    {
        [$b] = $this->hotel();

        $this->get(route('tagtoa.business.edit', $b->id))->assertOk()->assertSee('JAMAIS de mot de passe');
    }

    public function test_the_hot_path_indexes_migration_is_replayable_and_reversible(): void
    {
        $mig = require __DIR__.'/../../Database/migrations/2026_11_02_000194_add_hotel_hot_path_indexes.php';
        $mig->up();
        $mig->up();
        $this->assertTrue(Schema::hasIndex('tagtoa_hotel_requests', 'hotel_requests_team_idx'));
        $this->assertTrue(Schema::hasIndex('tagtoa_hotel_requests', 'hotel_requests_created_idx'));
        $mig->down();
        $this->assertFalse(Schema::hasIndex('tagtoa_hotel_requests', 'hotel_requests_team_idx'));
        $mig->up();
        $this->assertTrue(Schema::hasIndex('tagtoa_hotel_requests', 'hotel_requests_team_idx'));
    }
}
