<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA HÔTEL (H4) — le support QR/NFC d'une chambre
|--------------------------------------------------------------------------
| Activer un Smart Stand pour UNE chambre (secret gratté exigé), le révoquer, le remplacer ;
| le portail public /h/{support} revérifie tout à chaque requête et répond de façon identique
| (404 neutre) pour inconnu / révoqué / remplacé / suspendu / chambre désactivée.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Tagtoa\App\Models\Audit\AuditLog;
use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Models\Hotel\HotelProfile;
use Modules\Tagtoa\App\Models\Hotel\HotelRoom;
use Modules\Tagtoa\App\Models\Hotel\HotelService;
use Modules\Tagtoa\App\Models\Stand\Stand;
use Modules\Tagtoa\App\Models\Stand\StandEvent;
use Modules\Tagtoa\App\Services\Business\BusinessService;
use Modules\Tagtoa\App\Services\Hotel\HotelRoomSupportService as S;
use Modules\Tagtoa\App\Services\Stand\StandActivator;
use Modules\Tagtoa\App\Services\Stand\StandMinter;
use Modules\Tagtoa\App\Support\Stand\StandScratch;
use Modules\Tagtoa\App\Support\Stand\StandState;
use Modules\Tagtoa\App\Support\Tenant;
use Modules\Tagtoa\Tests\TestCase;

class HotelRoomSupportTest extends TestCase
{
    use RefreshDatabase;

    private array $secrets = [];

    private function fabriquer(int $n = 3): void
    {
        $this->secrets = app(StandMinter::class)->mint('TAGTOA-2026-001', $n)['secrets'];
    }

    private function gratte(Stand $stand): string
    {
        return StandScratch::payload($stand->public_id, $this->secrets[$stand->public_id]);
    }

    private function stands()
    {
        return Stand::orderBy('serial')->get();
    }

    private function compte(string $id = 'compte-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $id, 'name' => 'Rose']));
        Tenant::flush();
    }

    private function commerce(string $type = 'hotel', string $nom = 'Hôtel Belle Vue'): Business
    {
        return app(BusinessService::class)->create(Tenant::account(), ['name' => $nom, 'type' => $type, 'currency' => 'HTG']);
    }

    private function chambre(string $tenant, string $numero = '101', bool $active = true): HotelRoom
    {
        return HotelRoom::allTenants()->create(['tenant_id' => $tenant, 'number' => $numero, 'is_active' => $active]);
    }

    private function svc(): S
    {
        return app(S::class);
    }

    private function hotelEtChambre(): array
    {
        $this->compte();
        $b = $this->commerce();

        return [$b, $this->chambre($b->id)];
    }

    /* ---------- activation ---------- */

    public function test_a_support_is_activated_and_linked_to_the_room(): void
    {
        $this->fabriquer();
        [$b, $room] = $this->hotelEtChambre();
        $stand = $this->stands()[0];

        $r = $this->svc()->attach($b->id, $room->id, $this->gratte($stand));

        $this->assertSame(S::OK, $r['result']);
        $this->assertSame($stand->id, $room->fresh()->stand_id);
        $stand->refresh();
        $this->assertSame($b->id, $stand->tenant_id);
        $this->assertSame(StandState::ACTIVE, $stand->digital_state);
        $this->assertSame('hotel_room', $stand->target_module);
        $this->assertSame('Chambre 101', $stand->location_label);
    }

    public function test_the_scan_of_the_printed_qr_leads_to_the_room_portal(): void
    {
        $this->fabriquer();
        [$b, $room] = $this->hotelEtChambre();
        $stand = $this->stands()[0];
        $this->svc()->attach($b->id, $room->id, $this->gratte($stand));

        $this->get('/s/'.$stand->public_id)->assertRedirect(url('/h/'.$stand->public_id));
        $this->get('/h/'.$stand->public_id)->assertOk()->assertSee('Hôtel Belle Vue')->assertSee('Chambre 101');
    }

    public function test_a_wrong_code_links_nothing_and_leaves_the_stand_untouched(): void
    {
        $this->fabriquer();
        [$b, $room] = $this->hotelEtChambre();
        $stand = $this->stands()[0];

        $r = $this->svc()->attach($b->id, $room->id, StandScratch::payload($stand->public_id, 'ZZZZZZZZ'));

        $this->assertSame('stand_bad_code', $r['result']);
        $this->assertNull($room->fresh()->stand_id);
        $this->assertNull($stand->fresh()->tenant_id);
        $this->assertSame(StandState::UNCLAIMED, $stand->fresh()->digital_state);
    }

    public function test_the_qr_alone_without_the_scratched_secret_is_refused(): void
    {
        $this->fabriquer();
        [$b, $room] = $this->hotelEtChambre();
        $stand = $this->stands()[0];

        $r = $this->svc()->attach($b->id, $room->id, $stand->public_id);

        $this->assertNotSame(S::OK, $r['result']);
        $this->assertNull($room->fresh()->stand_id);
    }

    public function test_a_stand_already_claimed_by_another_hotel_is_refused(): void
    {
        $this->fabriquer();
        $this->compte('compte-a');
        $a = $this->commerce('hotel', 'Hôtel A');
        $roomA = $this->chambre($a->id);
        $stand = $this->stands()[0];
        $this->svc()->attach($a->id, $roomA->id, $this->gratte($stand));

        $this->compte('compte-b');
        $b = $this->commerce('hotel', 'Hôtel B');
        $roomB = $this->chambre($b->id);

        $r = $this->svc()->attach($b->id, $roomB->id, $this->gratte($stand));

        $this->assertNotSame(S::OK, $r['result']);
        $this->assertNull($roomB->fresh()->stand_id);
        $this->assertSame($a->id, $stand->fresh()->tenant_id);
    }

    public function test_one_support_cannot_serve_two_rooms(): void
    {
        $this->fabriquer();
        [$b, $room] = $this->hotelEtChambre();
        $room2 = $this->chambre($b->id, '102');
        $stand = $this->stands()[0];
        $this->svc()->attach($b->id, $room->id, $this->gratte($stand));

        $r = $this->svc()->attach($b->id, $room2->id, $this->gratte($stand));

        $this->assertSame(S::STAND_IN_USE, $r['result']);
        $this->assertNull($room2->fresh()->stand_id);
        $this->assertSame($stand->id, $room->fresh()->stand_id);
    }

    public function test_the_database_itself_refuses_two_rooms_on_one_support(): void
    {
        [$b, $room] = $this->hotelEtChambre();
        $room2 = $this->chambre($b->id, '102');
        $room->forceFill(['stand_id' => 7])->save();

        $this->expectException(QueryException::class);
        $room2->forceFill(['stand_id' => 7])->save();
    }

    public function test_a_table_stand_of_the_same_hotel_is_not_hijacked_for_a_room(): void
    {
        $this->fabriquer();
        [$b, $room] = $this->hotelEtChambre();
        $stand = $this->stands()[0];
        app(StandActivator::class)->activate($this->gratte($stand), $b->id);   // stand de table (menu)

        $r = $this->svc()->attach($b->id, $room->id, $this->gratte($stand));

        $this->assertSame(S::STAND_IN_USE, $r['result']);
        $this->assertSame('menu', $stand->fresh()->target_module);
        $this->assertNull($room->fresh()->stand_id);
    }

    public function test_a_room_with_a_support_cannot_get_a_second_one_by_attach(): void
    {
        $this->fabriquer();
        [$b, $room] = $this->hotelEtChambre();
        [$s1, $s2] = [$this->stands()[0], $this->stands()[1]];
        $this->svc()->attach($b->id, $room->id, $this->gratte($s1));

        $this->assertSame(S::HAS_SUPPORT, $this->svc()->attach($b->id, $room->id, $this->gratte($s2))['result']);
        $this->assertNull($s2->fresh()->tenant_id, 'Le second support n\'est pas consommé.');
    }

    public function test_an_inactive_room_gets_no_support(): void
    {
        $this->fabriquer();
        $this->compte();
        $b = $this->commerce();
        $room = $this->chambre($b->id, '101', false);

        $this->assertSame(S::ROOM_INACTIVE, $this->svc()->attach($b->id, $room->id, $this->gratte($this->stands()[0]))['result']);
        $this->assertNull($this->stands()[0]->tenant_id);
    }

    public function test_a_room_of_another_hotel_is_not_found(): void
    {
        $this->fabriquer();
        $this->compte('compte-a');
        $a = $this->commerce('hotel', 'Hôtel A');
        $roomA = $this->chambre($a->id);
        $this->compte('compte-b');
        $b = $this->commerce('hotel', 'Hôtel B');

        $r = $this->svc()->attach($b->id, $roomA->id, $this->gratte($this->stands()[0]));

        $this->assertSame(S::NOT_FOUND, $r['result']);
        $this->assertNull($this->stands()[0]->tenant_id);
    }

    /* ---------- révocation / remplacement ---------- */

    public function test_revoking_kills_the_qr_for_good(): void
    {
        $this->fabriquer();
        [$b, $room] = $this->hotelEtChambre();
        $stand = $this->stands()[0];
        $this->svc()->attach($b->id, $room->id, $this->gratte($stand));
        $this->get('/h/'.$stand->public_id)->assertOk();

        $this->assertSame(S::OK, $this->svc()->revoke($b->id, $room->id)['result']);

        $this->get('/h/'.$stand->public_id)->assertNotFound();
        $this->assertNull($room->fresh()->stand_id);
        $this->assertSame(StandState::REVOKED, $stand->fresh()->digital_state);
        $this->assertTrue(StandEvent::where('stand_id', $stand->id)->where('event', StandEvent::REVOKED)->exists());
        // Le secret d'un objet révoqué ne le rouvre pas.
        $this->assertNotSame(StandActivator::OK, app(StandActivator::class)->activate($this->gratte($stand), $b->id)['result']);
        $this->assertSame(StandState::REVOKED, $stand->fresh()->digital_state);
    }

    public function test_a_revoked_support_scan_no_longer_redirects_to_the_portal(): void
    {
        $this->fabriquer();
        [$b, $room] = $this->hotelEtChambre();
        $stand = $this->stands()[0];
        $this->svc()->attach($b->id, $room->id, $this->gratte($stand));
        $this->get('/s/'.$stand->public_id)->assertRedirect();   // met la destination en cache

        $this->svc()->revoke($b->id, $room->id);

        $this->get('/s/'.$stand->public_id)->assertOk()->assertDontSee('/h/'.$stand->public_id);
    }

    public function test_replacing_kills_the_old_support_and_activates_the_new_one(): void
    {
        $this->fabriquer();
        [$b, $room] = $this->hotelEtChambre();
        [$old, $new] = [$this->stands()[0], $this->stands()[1]];
        $this->svc()->attach($b->id, $room->id, $this->gratte($old));

        $r = $this->svc()->replace($b->id, $room->id, $this->gratte($new));

        $this->assertSame(S::OK, $r['result']);
        $this->assertSame($new->id, $room->fresh()->stand_id);
        $this->get('/h/'.$old->public_id)->assertNotFound();
        $this->get('/h/'.$new->public_id)->assertOk()->assertSee('Chambre 101');
        $this->assertSame(StandState::REVOKED, $old->fresh()->digital_state);
        $this->assertTrue(StandEvent::where('stand_id', $old->id)->where('event', StandEvent::REPLACED)->exists());
    }

    public function test_a_failed_replacement_keeps_the_old_support_working(): void
    {
        $this->fabriquer();
        [$b, $room] = $this->hotelEtChambre();
        [$old, $new] = [$this->stands()[0], $this->stands()[1]];
        $this->svc()->attach($b->id, $room->id, $this->gratte($old));

        $r = $this->svc()->replace($b->id, $room->id, StandScratch::payload($new->public_id, 'ZZZZZZZZ'));

        $this->assertNotSame(S::OK, $r['result']);
        $this->assertSame($old->id, $room->fresh()->stand_id);
        $this->assertSame(StandState::ACTIVE, $old->fresh()->digital_state);
        $this->get('/h/'.$old->public_id)->assertOk();
    }

    public function test_replacing_by_the_same_support_is_refused_and_changes_nothing(): void
    {
        $this->fabriquer();
        [$b, $room] = $this->hotelEtChambre();
        $old = $this->stands()[0];
        $this->svc()->attach($b->id, $room->id, $this->gratte($old));

        $this->assertSame(S::SAME_STAND, $this->svc()->replace($b->id, $room->id, $this->gratte($old))['result']);
        $this->assertSame($old->id, $room->fresh()->stand_id);
        $this->assertSame(StandState::ACTIVE, $old->fresh()->digital_state);
    }

    public function test_revoke_and_replace_need_an_existing_support(): void
    {
        $this->fabriquer();
        [$b, $room] = $this->hotelEtChambre();

        $this->assertSame(S::NO_SUPPORT, $this->svc()->revoke($b->id, $room->id)['result']);
        $this->assertSame(S::NO_SUPPORT, $this->svc()->replace($b->id, $room->id, $this->gratte($this->stands()[0]))['result']);
    }

    /* ---------- portail public ---------- */

    private function neutre(string $path): string
    {
        return $this->get($path)->assertNotFound()->getContent();
    }

    public function test_every_unavailable_case_gives_the_same_neutral_answer(): void
    {
        $this->fabriquer(6);
        [$b, $room] = $this->hotelEtChambre();
        $room2 = $this->chambre($b->id, '102');
        $room3 = $this->chambre($b->id, '103');
        [$actif, $revoque, $suspendu, $desactive, $libre] = [$this->stands()[0], $this->stands()[1], $this->stands()[2], $this->stands()[3], $this->stands()[5]];

        $this->svc()->attach($b->id, $room->id, $this->gratte($actif));
        $this->svc()->attach($b->id, $room2->id, $this->gratte($revoque));
        $this->svc()->revoke($b->id, $room2->id);
        $room3->forceFill(['is_active' => true])->save();
        $this->svc()->attach($b->id, $room3->id, $this->gratte($suspendu));
        $suspendu->refresh()->forceFill(['digital_state' => StandState::SUSPENDED])->save();
        $room4 = $this->chambre($b->id, '104');
        $this->svc()->attach($b->id, $room4->id, $this->gratte($desactive));
        $room4->update(['is_active' => false]);

        $this->get('/h/'.$actif->public_id)->assertOk();

        $reference = $this->neutre('/h/TG-999999');
        foreach (['/h/'.$revoque->public_id, '/h/'.$suspendu->public_id, '/h/'.$desactive->public_id,
                  '/h/'.$libre->public_id, '/h/not-an-id', '/h/TG-000001-EXTRA'] as $path) {
            $this->assertSame($reference, $this->neutre($path), "Réponse différente pour $path");
        }
    }

    public function test_a_recalled_batch_closes_the_portal(): void
    {
        $this->fabriquer();
        [$b, $room] = $this->hotelEtChambre();
        $stand = $this->stands()[0];
        $this->svc()->attach($b->id, $room->id, $this->gratte($stand));
        $stand->batch->forceFill(['recalled_at' => now()])->save();

        $this->get('/h/'.$stand->public_id)->assertNotFound();
    }

    public function test_a_stand_of_another_usage_never_opens_the_portal(): void
    {
        $this->fabriquer();
        [$b, $room] = $this->hotelEtChambre();
        $stand = $this->stands()[0];
        app(StandActivator::class)->activate($this->gratte($stand), $b->id);   // table / menu

        $this->get('/h/'.$stand->public_id)->assertNotFound();
    }

    public function test_the_portal_shows_only_its_own_hotel_data(): void
    {
        $this->fabriquer(2);
        $this->compte('compte-a');
        $a = $this->commerce('hotel', 'Hôtel Alpha');
        $roomA = $this->chambre($a->id, '101');
        HotelProfile::allTenants()->create(['tenant_id' => $a->id, 'reception_phone' => '+509 1111 1111']);
        HotelService::allTenants()->create(['tenant_id' => $a->id, 'kind' => 'housekeeping', 'name' => 'Ménage Alpha', 'is_active' => true]);

        $this->compte('compte-b');
        $b = $this->commerce('hotel', 'Hôtel Bravo');
        $roomB = $this->chambre($b->id, '101');
        HotelProfile::allTenants()->create(['tenant_id' => $b->id, 'reception_phone' => '+509 2222 2222']);
        HotelService::allTenants()->create(['tenant_id' => $b->id, 'kind' => 'housekeeping', 'name' => 'Ménage Bravo', 'is_active' => true]);
        HotelService::allTenants()->create(['tenant_id' => $b->id, 'kind' => 'maintenance', 'name' => 'Inactif Bravo', 'is_active' => false]);

        [$sa, $sb] = [$this->stands()[0], $this->stands()[1]];
        $this->svc()->attach($a->id, $roomA->id, $this->gratte($sa));
        $this->svc()->attach($b->id, $roomB->id, $this->gratte($sb));

        $pa = $this->get('/h/'.$sa->public_id)->assertOk()->getContent();
        $this->assertStringContainsString('Hôtel Alpha', $pa);
        $this->assertStringContainsString('Ménage Alpha', $pa);
        $this->assertStringContainsString('+509 1111 1111', $pa);
        $this->assertStringNotContainsString('Bravo', $pa);
        $this->assertStringNotContainsString('2222', $pa);

        $pb = $this->get('/h/'.$sb->public_id)->assertOk()->getContent();
        $this->assertStringContainsString('Ménage Bravo', $pb);
        $this->assertStringNotContainsString('Inactif Bravo', $pb);
        $this->assertStringNotContainsString('Alpha', $pb);
    }

    public function test_the_portal_is_private_noindex_and_leaks_no_internal_id(): void
    {
        $this->fabriquer();
        [$b, $room] = $this->hotelEtChambre();
        $stand = $this->stands()[0];
        $this->svc()->attach($b->id, $room->id, $this->gratte($stand));

        $r = $this->get('/h/'.$stand->public_id)->assertOk();

        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $this->assertStringContainsString('noindex', $r->headers->get('X-Robots-Tag'));
        $this->assertSame('no-referrer', $r->headers->get('Referrer-Policy'));
        $this->assertStringNotContainsString($b->id, $r->getContent(), 'L\'identifiant du commerce ne doit pas apparaître.');
        $this->assertStringNotContainsString('secret', strtolower($r->getContent()));
    }

    public function test_hotel_text_is_escaped_on_the_portal(): void
    {
        $this->fabriquer();
        [$b, $room] = $this->hotelEtChambre();
        HotelProfile::allTenants()->create(['tenant_id' => $b->id, 'description' => '<script>alert(1)</script>', 'guest_info' => '<img src=x onerror=alert(2)>']);
        $stand = $this->stands()[0];
        $this->svc()->attach($b->id, $room->id, $this->gratte($stand));

        $html = $this->get('/h/'.$stand->public_id)->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
    }

    /* ---------- écrans du marchand ---------- */

    public function test_the_merchant_activates_a_support_from_the_rooms_screen(): void
    {
        $this->fabriquer();
        [$b, $room] = $this->hotelEtChambre();
        $stand = $this->stands()[0];

        $this->post(route('tagtoa.hotel.rooms.support.attach', $room->id), ['code' => $this->gratte($stand)])
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame($stand->id, $room->fresh()->stand_id);
        $this->get(route('tagtoa.hotel.rooms'))->assertOk()->assertSee($stand->public_id);
        $this->assertTrue(AuditLog::where('action', 'hotel.room.support_attach')->exists());
    }

    public function test_a_wrong_code_shows_a_clear_error_and_never_echoes_the_code(): void
    {
        $this->fabriquer();
        [$b, $room] = $this->hotelEtChambre();
        $stand = $this->stands()[0];
        $mauvais = StandScratch::payload($stand->public_id, 'ZZZZZZZZ');

        $r = $this->from(route('tagtoa.hotel.rooms'))->post(route('tagtoa.hotel.rooms.support.attach', $room->id), ['code' => $mauvais]);

        $r->assertSessionHasErrors('code');
        $this->get(route('tagtoa.hotel.rooms'))->assertDontSee('ZZZZZZZZ', false);
    }

    public function test_another_hotel_cannot_touch_my_room_support(): void
    {
        $this->fabriquer();
        $this->compte('compte-a');
        $a = $this->commerce('hotel', 'Hôtel A');
        $roomA = $this->chambre($a->id);
        $this->svc()->attach($a->id, $roomA->id, $this->gratte($this->stands()[0]));
        $this->compte('compte-b');
        $this->commerce('hotel', 'Hôtel B');

        $this->post(route('tagtoa.hotel.rooms.support.revoke', $roomA->id))->assertNotFound();
        $this->post(route('tagtoa.hotel.rooms.support.replace', $roomA->id), ['code' => $this->gratte($this->stands()[1])])->assertNotFound();
        $this->post(route('tagtoa.hotel.rooms.support.attach', $roomA->id), ['code' => $this->gratte($this->stands()[1])])->assertNotFound();

        $this->assertSame($this->stands()[0]->id, $roomA->fresh()->stand_id);
        $this->assertSame(StandState::ACTIVE, $this->stands()[0]->digital_state);
    }

    public function test_a_non_hotel_business_has_no_room_support_screens(): void
    {
        $this->compte();
        $this->commerce('restaurant', 'Chez Rose');

        $this->post(route('tagtoa.hotel.rooms.support.attach', 1), ['code' => 'x'])->assertRedirect(route('tagtoa.hub'));
    }

    public function test_a_room_support_cannot_be_retargeted_from_the_stands_screen(): void
    {
        $this->fabriquer();
        [$b, $room] = $this->hotelEtChambre();
        $stand = $this->stands()[0];
        $this->svc()->attach($b->id, $room->id, $this->gratte($stand));

        $this->put(route('tagtoa.stand.update', $stand->id), ['location_label' => 'Chambre 101', 'target_module' => 'menu']);

        $this->assertSame('hotel_room', $stand->fresh()->target_module);
        $this->get('/h/'.$stand->public_id)->assertOk();
    }

    public function test_deactivating_the_room_closes_its_portal(): void
    {
        $this->fabriquer();
        [$b, $room] = $this->hotelEtChambre();
        $stand = $this->stands()[0];
        $this->svc()->attach($b->id, $room->id, $this->gratte($stand));

        $this->post(route('tagtoa.hotel.rooms.toggle', $room->id));

        $this->get('/h/'.$stand->public_id)->assertNotFound();
    }

    public function test_a_business_that_is_no_longer_a_hotel_closes_its_portal(): void
    {
        $this->fabriquer();
        [$b, $room] = $this->hotelEtChambre();
        $stand = $this->stands()[0];
        $this->svc()->attach($b->id, $room->id, $this->gratte($stand));
        \Illuminate\Support\Facades\DB::table('tagtoa_businesses')->where('id', $b->id)->update(['type' => 'restaurant']);

        $this->get('/h/'.$stand->public_id)->assertNotFound();
    }

    public function test_a_room_linked_stand_that_is_not_a_room_support_never_opens_the_portal(): void
    {
        $this->fabriquer();
        [$b, $room] = $this->hotelEtChambre();
        $stand = $this->stands()[0];
        $this->svc()->attach($b->id, $room->id, $this->gratte($stand));
        $stand->forceFill(['target_module' => 'menu'])->save();

        $this->get('/h/'.$stand->public_id)->assertNotFound();
    }

    public function test_deactivating_the_room_stops_the_scan_redirect_even_when_cached(): void
    {
        $this->fabriquer();
        [$b, $room] = $this->hotelEtChambre();
        $stand = $this->stands()[0];
        $this->svc()->attach($b->id, $room->id, $this->gratte($stand));
        $this->get('/s/'.$stand->public_id)->assertRedirect();   // met la destination en cache

        $this->post(route('tagtoa.hotel.rooms.toggle', $room->id));

        $this->get('/s/'.$stand->public_id)->assertOk();   // page sobre, plus de redirection vers le portail
    }

    public function test_the_migration_is_replayable_and_reversible(): void
    {
        $mig = require __DIR__.'/../../Database/migrations/2026_11_01_000190_add_stand_id_to_hotel_rooms.php';
        $mig->up();
        $mig->up();
        $mig->down();
        $this->assertFalse(Schema::hasColumn('tagtoa_hotel_rooms', 'stand_id'));
        $mig->up();
        $this->assertTrue(Schema::hasColumn('tagtoa_hotel_rooms', 'stand_id'));
    }
}
