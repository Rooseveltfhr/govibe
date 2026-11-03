<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA HÔTEL (HM-3b) — les réservations sur l'écran du personnel
|--------------------------------------------------------------------------
| Patron, gérant et réception voient les NOUVELLES réservations et les confirment / refusent ; ménage et maintenance ne
| voient rien (nom et téléphone d'un client) et ne peuvent rien faire, même en appelant la route. Contrôle côté serveur,
| isolation entre hôtels, action attribuée à la personne, alertes limitées à ceux qui ont le droit.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Audit\AuditLog;
use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Models\Hotel\HotelReservation;
use Modules\Tagtoa\App\Models\Staff\Staff;
use Modules\Tagtoa\App\Services\Business\BusinessService;
use Modules\Tagtoa\App\Services\Event\StaffPinService;
use Modules\Tagtoa\App\Support\Hotel\HotelReservationRules as R;
use Modules\Tagtoa\App\Support\Pos\StaffAccess;
use Modules\Tagtoa\App\Support\Tenant;
use Modules\Tagtoa\Tests\TestCase;

class HotelBoardReservationsTest extends TestCase
{
    use RefreshDatabase;

    private function hotel(string $compte = 'c-a', string $nom = 'Hôtel Alpha'): Business
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $compte, 'name' => 'Rose']));
        Tenant::flush();

        return app(BusinessService::class)->create(Tenant::account(), ['name' => $nom, 'type' => 'hotel', 'currency' => 'HTG']);
    }

    private function employe(Business $b, string $nom, string $role, ?string $equipe, string $pin): Staff
    {
        return Staff::create([
            'tenant_id' => $b->id, 'name' => $nom, 'role' => $role, 'hotel_team' => $equipe,
            'pin_hash' => StaffPinService::hashPin($pin), 'is_active' => true,
        ]);
    }

    private function resa(Business $b, string $client = 'Jean Pierre', string $statut = 'new'): HotelReservation
    {
        return HotelReservation::allTenants()->create([
            'tenant_id' => $b->id, 'reference' => 'HV-'.strtoupper(substr(md5($client.$b->id.$statut), 0, 8)),
            'track_hash' => hash('sha256', $client.$statut), 'room_type_name' => 'Chambre climatisée', 'plan_kind' => 'night',
            'price' => 2500, 'currency' => 'HTG', 'nights' => 2, 'total' => 5000, 'starts_on' => now()->addDays(3)->toDateString(),
            'guest_name' => $client, 'guest_phone' => '50937124455', 'status' => $statut,
        ]);
    }

    private function entrer(string $pin)
    {
        return $this->post(route('tagtoa.hotel.board.login'), ['pin' => $pin]);
    }

    private function voit(string $pin): string
    {
        $this->entrer($pin);
        $html = $this->get(route('tagtoa.hotel.board'))->assertOk()->getContent();
        $this->post(route('tagtoa.hotel.board.logout'));

        return $html;
    }

    public function test_reception_manager_and_owner_see_new_reservations_but_housekeeping_and_maintenance_see_nothing(): void
    {
        $b = $this->hotel();
        $this->employe($b, 'Rose', StaffAccess::ROLE_HOTEL, 'reception', '1111');
        $this->employe($b, 'Chef', StaffAccess::ROLE_MANAGER, null, '2222');
        $this->employe($b, 'Marie', StaffAccess::ROLE_HOTEL, 'housekeeping', '3333');
        $this->employe($b, 'Paul', StaffAccess::ROLE_HOTEL, 'maintenance', '4444');
        $this->resa($b, 'Client-Secret');

        foreach (['1111', '2222'] as $pin) {
            $html = $this->voit($pin);
            $this->assertStringContainsString('Client-Secret', $html, $pin);
            $this->assertStringContainsString('50937124455', $html);
        }
        foreach (['3333', '4444'] as $pin) {
            $html = $this->voit($pin);
            $this->assertStringNotContainsString('Client-Secret', $html, $pin);
            $this->assertStringNotContainsString('50937124455', $html);
            $this->assertStringNotContainsString('Réservations à traiter', $html);
        }
    }

    public function test_only_new_reservations_appear_on_the_board(): void
    {
        $b = $this->hotel();
        $this->employe($b, 'Rose', StaffAccess::ROLE_HOTEL, 'reception', '1111');
        $this->resa($b, 'Nouvelle-A', 'new');
        $this->resa($b, 'Confirmee-B', 'confirmed');
        $this->resa($b, 'Refusee-C', 'refused');

        $html = $this->voit('1111');

        $this->assertStringContainsString('Nouvelle-A', $html);
        $this->assertStringNotContainsString('Confirmee-B', $html);
        $this->assertStringNotContainsString('Refusee-C', $html);
    }

    public function test_reception_confirms_and_the_action_is_attributed_and_audited(): void
    {
        $b = $this->hotel();
        $this->employe($b, 'Rose', StaffAccess::ROLE_HOTEL, 'reception', '1111');
        $r = $this->resa($b);
        $this->entrer('1111');

        $this->post(route('tagtoa.hotel.board.reservation.status', $r->id), ['to' => 'confirmed'])->assertSessionHas('success');

        $this->assertSame('confirmed', $r->fresh()->status);
        $this->assertTrue(AuditLog::where('action', 'hotel.reservation.confirmed')->where('description', 'like', '%Rose%')->exists());
    }

    public function test_reception_refuses_with_a_reason(): void
    {
        $b = $this->hotel();
        $this->employe($b, 'Rose', StaffAccess::ROLE_HOTEL, 'reception', '1111');
        $r = $this->resa($b);
        $this->entrer('1111');

        $this->post(route('tagtoa.hotel.board.reservation.status', $r->id), ['to' => 'refused', 'reason' => 'Complet'])->assertSessionHas('success');

        $this->assertSame(['refused', 'Complet'], [$r->fresh()->status, $r->fresh()->reason]);
    }

    public function test_housekeeping_and_maintenance_cannot_act_even_by_calling_the_route(): void
    {
        $b = $this->hotel();
        $this->employe($b, 'Marie', StaffAccess::ROLE_HOTEL, 'housekeeping', '3333');
        $this->employe($b, 'Paul', StaffAccess::ROLE_HOTEL, 'maintenance', '4444');
        $r = $this->resa($b);

        foreach (['3333', '4444'] as $pin) {
            $this->entrer($pin);
            $this->post(route('tagtoa.hotel.board.reservation.status', $r->id), ['to' => 'confirmed'])->assertNotFound();
            $this->post(route('tagtoa.hotel.board.reservation.status', $r->id), ['to' => 'refused'])->assertNotFound();
            $this->post(route('tagtoa.hotel.board.logout'));
        }
        $this->assertSame('new', $r->fresh()->status);
    }

    public function test_without_a_staff_session_the_route_changes_nothing(): void
    {
        $b = $this->hotel();
        $r = $this->resa($b);

        $this->post(route('tagtoa.hotel.board.reservation.status', $r->id), ['to' => 'confirmed'])->assertRedirect(route('tagtoa.hotel.board'));

        $this->assertSame('new', $r->fresh()->status);
    }

    public function test_an_employee_of_another_hotel_cannot_touch_my_reservation(): void
    {
        $a = $this->hotel('c-a', 'Hôtel Alpha');
        $r = $this->resa($a, 'Client-Alpha');
        $b = $this->hotel('c-b', 'Hôtel Beta');
        $this->employe($b, 'Rose-B', StaffAccess::ROLE_HOTEL, 'reception', '9999');
        $this->entrer('9999');

        $this->post(route('tagtoa.hotel.board.reservation.status', $r->id), ['to' => 'confirmed'])->assertNotFound();
        $this->assertSame('new', $r->fresh()->status);
        $this->assertStringNotContainsString('Client-Alpha', $this->get(route('tagtoa.hotel.board'))->getContent());
    }

    public function test_confirming_twice_is_neutral_and_a_refused_one_never_comes_back(): void
    {
        $b = $this->hotel();
        $this->employe($b, 'Rose', StaffAccess::ROLE_HOTEL, 'reception', '1111');
        $r = $this->resa($b);
        $this->entrer('1111');
        $url = route('tagtoa.hotel.board.reservation.status', $r->id);

        $this->post($url, ['to' => 'confirmed'])->assertSessionHas('success');
        $this->post($url, ['to' => 'confirmed'])->assertSessionHas('success')->assertSessionMissing('error');
        $this->post($url, ['to' => 'refused'])->assertSessionHas('success');
        $this->post($url, ['to' => 'confirmed'])->assertSessionHas('error');
        $this->assertSame('refused', $r->fresh()->status);
    }

    public function test_an_invalid_target_is_rejected(): void
    {
        $b = $this->hotel();
        $this->employe($b, 'Rose', StaffAccess::ROLE_HOTEL, 'reception', '1111');
        $r = $this->resa($b);
        $this->entrer('1111');

        foreach (['cancelled', 'new', 'xx'] as $to) {
            $this->post(route('tagtoa.hotel.board.reservation.status', $r->id), ['to' => $to])->assertSessionHasErrors('to');
        }
        $this->assertSame('new', $r->fresh()->status);
    }

    public function test_alerts_count_new_reservations_only_for_those_allowed_to_see_them(): void
    {
        $b = $this->hotel();
        $this->employe($b, 'Rose', StaffAccess::ROLE_HOTEL, 'reception', '1111');
        $this->employe($b, 'Marie', StaffAccess::ROLE_HOTEL, 'housekeeping', '3333');
        $x = $this->resa($b, 'A');
        $this->resa($b, 'B');
        $this->resa($b, 'C', 'confirmed');

        $this->entrer('1111');
        $this->getJson(route('tagtoa.hotel.board.alerts'))->assertOk()->assertJson(['resa_pending' => 2, 'resa_latest_id' => HotelReservation::allTenants()->where('status', 'new')->max('id')]);
        $this->post(route('tagtoa.hotel.board.logout'));

        $this->entrer('3333');
        $this->getJson(route('tagtoa.hotel.board.alerts'))->assertOk()->assertJson(['resa_pending' => 0, 'resa_latest_id' => 0]);
        $this->assertNotNull($x);
    }

    public function test_the_staff_rule_is_pure_and_unknown_roles_see_nothing(): void
    {
        $this->assertTrue(R::staffMaySee('owner', null));
        $this->assertTrue(R::staffMaySee('manager', null));
        $this->assertTrue(R::staffMaySee('hotel_staff', 'reception'));
        $this->assertFalse(R::staffMaySee('hotel_staff', 'housekeeping'));
        $this->assertFalse(R::staffMaySee('hotel_staff', 'maintenance'));
        $this->assertFalse(R::staffMaySee('hotel_staff', null));
        $this->assertFalse(R::staffMaySee('cashier', null));
        $this->assertFalse(R::staffMaySee(null, null));
    }
}
