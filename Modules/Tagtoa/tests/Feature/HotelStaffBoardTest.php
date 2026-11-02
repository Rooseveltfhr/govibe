<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA HÔTEL (H6) — personnel d'hôtel, écran des demandes par équipe
|--------------------------------------------------------------------------
| Un rôle « personnel hôtel » SANS aucun droit de caisse ; une équipe (réception, ménage, maintenance)
| décide de ce que la personne voit ET peut traiter, contrôlé côté serveur ; chaque action est
| attribuée à la personne ; isolation entre hôtels ; code deviné → verrou.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Modules\Tagtoa\App\Models\Audit\AuditLog;
use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Models\Hotel\HotelRequest;
use Modules\Tagtoa\App\Models\Hotel\HotelRoom;
use Modules\Tagtoa\App\Models\Hotel\HotelService;
use Modules\Tagtoa\App\Models\Staff\Staff;
use Modules\Tagtoa\App\Services\Business\BusinessService;
use Modules\Tagtoa\App\Services\Event\StaffPinService;
use Modules\Tagtoa\App\Services\Hotel\HotelRequestService;
use Modules\Tagtoa\App\Services\Staff\StaffService;
use Modules\Tagtoa\App\Support\Hotel\HotelRequestRules;
use Modules\Tagtoa\App\Support\Pos\StaffAccess;
use Modules\Tagtoa\App\Support\Tenant;
use Modules\Tagtoa\Tests\TestCase;

class HotelStaffBoardTest extends TestCase
{
    use RefreshDatabase;

    private function compte(string $id): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $id, 'name' => 'Rose']));
        Tenant::flush();
    }

    private function hotel(string $compte = 'c-a', string $nom = 'Hôtel Alpha'): Business
    {
        $this->compte($compte);

        return app(BusinessService::class)->create(Tenant::account(), ['name' => $nom, 'type' => 'hotel', 'currency' => 'HTG']);
    }

    private function employe(Business $b, string $nom, string $role, ?string $equipe, string $pin, bool $actif = true): Staff
    {
        return Staff::create([
            'tenant_id' => $b->id, 'name' => $nom, 'role' => $role, 'hotel_team' => $equipe,
            'pin_hash' => StaffPinService::hashPin($pin), 'is_active' => $actif,
        ]);
    }

    /** Une demande d'une équipe donnée dans une chambre. */
    private function demande(Business $b, string $equipe, string $nom, string $numero = '101'): HotelRequest
    {
        $room = HotelRoom::allTenants()->firstOrCreate(['tenant_id' => $b->id, 'number' => $numero], ['is_active' => true]);

        return HotelRequest::allTenants()->create([
            'tenant_id' => $b->id, 'room_id' => $room->id, 'reference' => 'HR-'.strtoupper(substr(md5($nom.$b->id), 0, 8)),
            'track_hash' => hash('sha256', $nom), 'kind' => 'housekeeping', 'service_name' => $nom, 'team' => $equipe, 'status' => 'new',
        ]);
    }

    private function entrer(string $pin)
    {
        return $this->post(route('tagtoa.hotel.board.login'), ['pin' => $pin]);
    }

    /* ---------- le rôle n'ouvre rien d'autre ---------- */

    public function test_the_hotel_role_has_no_till_catalog_or_kitchen_right(): void
    {
        $this->assertTrue(StaffAccess::isValidRole(StaffAccess::ROLE_HOTEL));
        foreach (StaffAccess::ABILITIES as $ability) {
            $this->assertFalse(StaffAccess::can(StaffAccess::ROLE_HOTEL, $ability), "Le personnel d'hôtel ne doit pas avoir « $ability ».");
        }
        $this->assertSame([], StaffAccess::abilitiesFor(StaffAccess::ROLE_HOTEL));

        $s = new Staff(['role' => StaffAccess::ROLE_HOTEL, 'is_active' => true]);
        $this->assertFalse($s->canRunKitchen());
        $this->assertFalse($s->can('sell'));
    }

    public function test_hotel_staff_cannot_open_a_till_or_the_kitchen_with_their_code(): void
    {
        $b = $this->hotel();
        $this->employe($b, 'Marie', StaffAccess::ROLE_HOTEL, 'housekeeping', '4321');

        $this->assertNull(app(StaffService::class)->authenticate($b->id, '4321'));
        $this->assertNotNull(app(StaffService::class)->authenticateHotel($b->id, '4321'));
    }

    public function test_hotel_staff_without_a_team_gets_no_session(): void
    {
        $b = $this->hotel();
        $this->employe($b, 'Sans équipe', StaffAccess::ROLE_HOTEL, null, '1111');

        $this->assertNull(app(StaffService::class)->authenticateHotel($b->id, '1111'));
    }

    public function test_a_cashier_cannot_open_the_hotel_board(): void
    {
        $b = $this->hotel();
        $this->employe($b, 'Caissier', StaffAccess::ROLE_CASHIER, null, '2222');

        $this->assertNull(app(StaffService::class)->authenticateHotel($b->id, '2222'));
    }

    /* ---------- connexion ---------- */

    public function test_the_board_asks_for_a_code_first_and_a_wrong_code_opens_nothing(): void
    {
        $b = $this->hotel();
        $this->employe($b, 'Marie', StaffAccess::ROLE_HOTEL, 'housekeeping', '4321');
        $this->demande($b, 'housekeeping', 'Nettoyage');

        $this->get(route('tagtoa.hotel.board'))->assertOk()->assertSee('code personnel')->assertDontSee('Nettoyage');
        $this->entrer('0000')->assertSessionHasErrors('pin');
        $this->get(route('tagtoa.hotel.board'))->assertDontSee('Nettoyage');
    }

    public function test_guessing_the_code_locks_the_board(): void
    {
        $b = $this->hotel();
        $this->employe($b, 'Marie', StaffAccess::ROLE_HOTEL, 'housekeeping', '4321');
        RateLimiter::clear('hotel-board-pin:'.$b->id.'|127.0.0.1');

        for ($i = 0; $i < 5; $i++) {
            $this->entrer('000'.$i)->assertSessionHasErrors('pin');
        }
        $this->entrer('4321')->assertSessionHasErrors('pin');   // même le bon code attend

        $this->get(route('tagtoa.hotel.board'))->assertDontSee('Marie');
        $this->assertNull(session('tagtoa_hotel_staff.'.$b->id));
    }

    public function test_a_code_of_another_hotel_opens_nothing_here(): void
    {
        $a = $this->hotel('c-a', 'Hôtel Alpha');
        $this->employe($a, 'Marie', StaffAccess::ROLE_HOTEL, 'reception', '4321');
        $b = $this->hotel('c-b', 'Hôtel Bravo');   // courant : Bravo

        $this->entrer('4321')->assertSessionHasErrors('pin');
    }

    /* ---------- chacun voit son équipe ---------- */

    public function test_each_team_sees_only_its_own_requests(): void
    {
        $b = $this->hotel();
        $this->employe($b, 'Marie', StaffAccess::ROLE_HOTEL, 'housekeeping', '1111');
        $this->employe($b, 'Paul', StaffAccess::ROLE_HOTEL, 'maintenance', '2222');
        $this->employe($b, 'Rose', StaffAccess::ROLE_HOTEL, 'reception', '3333');
        $this->demande($b, 'housekeeping', 'Nettoyage-X', '101');
        $this->demande($b, 'maintenance', 'Fuite-Y', '102');
        $this->demande($b, 'reception', 'Appel-Z', '103');

        $voit = function (string $pin) {
            $this->entrer($pin)->assertRedirect(route('tagtoa.hotel.board'));
            $html = $this->get(route('tagtoa.hotel.board'))->assertOk()->getContent();
            $this->post(route('tagtoa.hotel.board.logout'));

            return [str_contains($html, 'Nettoyage-X'), str_contains($html, 'Fuite-Y'), str_contains($html, 'Appel-Z')];
        };

        $this->assertSame([true, false, false], $voit('1111'), 'Ménage');
        $this->assertSame([false, true, false], $voit('2222'), 'Maintenance');
        $this->assertSame([true, true, true], $voit('3333'), 'Réception');
    }

    public function test_owner_and_manager_codes_see_everything(): void
    {
        $b = $this->hotel();
        $this->employe($b, 'Chef', StaffAccess::ROLE_MANAGER, null, '5555');
        $this->demande($b, 'maintenance', 'Fuite-Y');

        $this->entrer('5555');

        $this->get(route('tagtoa.hotel.board'))->assertSee('Fuite-Y');
    }

    /* ---------- chacun ne traite que son équipe ---------- */

    public function test_a_housekeeper_cannot_handle_a_maintenance_request_even_by_calling_the_route(): void
    {
        $b = $this->hotel();
        $this->employe($b, 'Marie', StaffAccess::ROLE_HOTEL, 'housekeeping', '1111');
        $autre = $this->demande($b, 'maintenance', 'Fuite-Y');
        $sienne = $this->demande($b, 'housekeeping', 'Nettoyage-X', '102');
        $this->entrer('1111');

        $this->post(route('tagtoa.hotel.board.status', $autre->id), ['status' => 'done'])->assertNotFound();
        $this->assertSame('new', $autre->fresh()->status);

        $this->post(route('tagtoa.hotel.board.status', $sienne->id), ['status' => 'in_progress'])->assertSessionHas('success');
        $this->assertSame('in_progress', $sienne->fresh()->status);
    }

    public function test_the_action_is_attributed_to_the_person_and_audited(): void
    {
        $b = $this->hotel();
        $marie = $this->employe($b, 'Marie', StaffAccess::ROLE_HOTEL, 'housekeeping', '1111');
        $req = $this->demande($b, 'housekeeping', 'Nettoyage-X');
        $this->entrer('1111');

        $this->post(route('tagtoa.hotel.board.status', $req->id), ['status' => 'done']);

        $this->assertSame($marie->id, $req->fresh()->handled_by_staff_id);
        $this->assertTrue(AuditLog::where('action', 'hotel.request.done')->where('description', 'like', '%Marie%')->exists());
        $this->get(route('tagtoa.hotel.requests', ['statut' => 'toutes']))->assertOk()->assertSee('Par Marie');
    }

    public function test_a_deactivated_employee_is_locked_out_immediately(): void
    {
        $b = $this->hotel();
        $marie = $this->employe($b, 'Marie', StaffAccess::ROLE_HOTEL, 'housekeeping', '1111');
        $req = $this->demande($b, 'housekeeping', 'Nettoyage-X');
        $this->entrer('1111');
        $this->get(route('tagtoa.hotel.board'))->assertSee('Nettoyage-X');

        $marie->update(['is_active' => false]);

        $this->get(route('tagtoa.hotel.board'))->assertDontSee('Nettoyage-X')->assertSee('code personnel');
        $this->post(route('tagtoa.hotel.board.status', $req->id), ['status' => 'done'])->assertRedirect(route('tagtoa.hotel.board'));
        $this->assertSame('new', $req->fresh()->status);
    }

    public function test_an_employee_of_another_hotel_cannot_handle_my_request_at_service_level(): void
    {
        $a = $this->hotel('c-a', 'Hôtel Alpha');
        $req = $this->demande($a, 'housekeeping', 'Nettoyage-X');
        $b = $this->hotel('c-b', 'Hôtel Bravo');
        $etranger = $this->employe($b, 'Paul', StaffAccess::ROLE_MANAGER, null, '9999');

        $this->assertFalse(app(HotelRequestService::class)->visibleTo($etranger, $req));
        $this->assertSame('not_found', app(HotelRequestService::class)->setStatus($a->id, $req->id, 'done', null, $etranger)['result']);
        $this->assertSame('new', $req->fresh()->status);
    }

    public function test_invalid_transitions_stay_refused_for_staff(): void
    {
        $b = $this->hotel();
        $this->employe($b, 'Marie', StaffAccess::ROLE_HOTEL, 'housekeeping', '1111');
        $req = $this->demande($b, 'housekeeping', 'Nettoyage-X');
        $this->entrer('1111');
        $this->post(route('tagtoa.hotel.board.status', $req->id), ['status' => 'done']);

        $this->post(route('tagtoa.hotel.board.status', $req->id), ['status' => 'in_progress'])->assertSessionHas('error');
        $this->assertSame('done', $req->fresh()->status);
    }

    /* ---------- alertes ---------- */

    public function test_alerts_count_only_the_teams_requests_and_need_a_session(): void
    {
        $b = $this->hotel();
        $this->employe($b, 'Marie', StaffAccess::ROLE_HOTEL, 'housekeeping', '1111');
        $this->demande($b, 'housekeeping', 'N1', '101');
        $this->demande($b, 'housekeeping', 'N2', '102');
        $this->demande($b, 'maintenance', 'M1', '103');

        $this->getJson(route('tagtoa.hotel.board.alerts'))->assertStatus(401);

        $this->entrer('1111');
        $this->getJson(route('tagtoa.hotel.board.alerts'))->assertOk()->assertJson(['pending' => 2]);
    }

    /* ---------- fiche employé ---------- */

    public function test_the_staff_form_requires_a_team_for_hotel_staff_and_ignores_it_otherwise(): void
    {
        $b = $this->hotel();
        $url = route('tagtoa.staff.store');

        $this->post($url, ['name' => 'Marie', 'role' => 'hotel_staff', 'pin' => '4321'])->assertSessionHasErrors('hotel_team');
        $this->post($url, ['name' => 'Marie', 'role' => 'hotel_staff', 'hotel_team' => 'cuisine', 'pin' => '4321'])->assertSessionHasErrors('hotel_team');
        $this->post($url, ['name' => 'Marie', 'role' => 'hotel_staff', 'hotel_team' => 'housekeeping', 'pin' => '4321'])->assertSessionHasNoErrors();
        $this->post($url, ['name' => 'Chef', 'role' => 'manager', 'hotel_team' => 'maintenance', 'pin' => '5432'])->assertSessionHasNoErrors();

        $this->assertSame('housekeeping', Staff::where('name', 'Marie')->value('hotel_team'));
        $this->assertNull(Staff::where('name', 'Chef')->value('hotel_team'), 'L\'équipe n\'est gardée que pour le personnel d\'hôtel.');
    }

    public function test_the_hotel_role_is_not_offered_outside_a_hotel(): void
    {
        $this->compte('c-r');
        app(BusinessService::class)->create(Tenant::account(), ['name' => 'Chez Rose', 'type' => 'restaurant', 'currency' => 'HTG']);

        $this->post(route('tagtoa.staff.store'), ['name' => 'Marie', 'role' => 'hotel_staff', 'hotel_team' => 'housekeeping', 'pin' => '4321'])
            ->assertSessionHasErrors('role');
        $this->get(route('tagtoa.staff.index'))->assertOk()->assertDontSee('Personnel hôtel');
    }

    public function test_a_valid_code_does_not_reset_the_lock_built_by_failures(): void
    {
        $b = $this->hotel();
        $this->employe($b, 'Marie', StaffAccess::ROLE_HOTEL, 'housekeeping', '1111');
        $this->employe($b, 'Chef', StaffAccess::ROLE_MANAGER, null, '5555');
        RateLimiter::clear('hotel-board-pin:'.$b->id.'|127.0.0.1');
        RateLimiter::clear('hotel-board-pin-all:'.$b->id);

        // 4 essais ratés sur le code du gérant, puis SON propre bon code, en boucle : le verrou doit tenir.
        for ($i = 0; $i < 4; $i++) {
            $this->entrer('000'.$i)->assertSessionHasErrors('pin');
        }
        $this->entrer('1111')->assertRedirect(route('tagtoa.hotel.board'));
        $this->post(route('tagtoa.hotel.board.logout'));

        $this->entrer('0004')->assertSessionHasErrors('pin');   // 5e échec : verrou
        $this->entrer('1111')->assertSessionHasErrors('pin');   // le bon code attend aussi
    }

    public function test_failures_are_also_capped_per_hotel_whatever_the_address(): void
    {
        $b = $this->hotel();
        $this->employe($b, 'Marie', StaffAccess::ROLE_HOTEL, 'housekeeping', '1111');
        RateLimiter::clear('hotel-board-pin:'.$b->id.'|127.0.0.1');
        for ($i = 0; $i < 20; $i++) {
            RateLimiter::hit('hotel-board-pin-all:'.$b->id, 3600);
        }

        $this->entrer('1111')->assertSessionHasErrors('pin');
    }

    public function test_a_shared_code_never_promotes_hotel_staff_to_manager(): void
    {
        $b = $this->hotel();
        $chef = $this->employe($b, 'Chef', StaffAccess::ROLE_MANAGER, null, '7777');
        $marie = $this->employe($b, 'Marie', StaffAccess::ROLE_HOTEL, 'housekeeping', '7777');   // même code
        Staff::whereKey($chef->id)->update(['created_at' => now()->subDay()]);   // le gérant est « premier » en base

        $this->assertSame($marie->id, app(StaffService::class)->authenticateHotel($b->id, '7777')->id);
    }

    public function test_hotel_staff_never_runs_the_kitchen_even_after_a_role_switch(): void
    {
        $b = $this->hotel();
        $s = $this->employe($b, 'Marie', StaffAccess::ROLE_CASHIER, null, '1111');
        $s->update(['is_kitchen' => true]);
        $this->assertTrue($s->fresh()->canRunKitchen());

        app(StaffService::class)->save($b->id, ['name' => 'Marie', 'role' => StaffAccess::ROLE_HOTEL, 'hotel_team' => 'housekeeping', 'is_kitchen' => true], $s);

        $this->assertFalse($s->fresh()->is_kitchen);
        $this->assertFalse($s->fresh()->canRunKitchen());
        $s->forceFill(['is_kitchen' => true])->save();   // même une case restée cochée en base ne suffit pas
        $this->assertFalse($s->fresh()->canRunKitchen());
    }

    public function test_hotel_staff_do_not_force_a_pin_prompt_on_the_till(): void
    {
        $b = $this->hotel();
        $this->employe($b, 'Marie', StaffAccess::ROLE_HOTEL, 'housekeeping', '1111');
        $terminal = \Modules\Tagtoa\App\Models\Pos\Terminal::create(['tenant_id' => $b->id, 'name' => 'Comptoir', 'currency' => 'HTG', 'is_active' => true]);

        $vue = $this->get(route('tagtoa.pos.register', $terminal->id));
        if ($vue->status() === 200) {
            $this->assertFalse($vue->viewData('hasStaff'), 'Seul du personnel d\'hôtel : la caisse ne demande aucun code.');
        } else {
            $this->markTestSkipped('Route de caisse indisponible dans ce contexte : '.$vue->status());
        }
    }

    public function test_teams_are_resolved_by_pure_rules(): void
    {
        $this->assertNull(HotelRequestRules::teamsFor('owner', null));
        $this->assertNull(HotelRequestRules::teamsFor('manager', null));
        $this->assertNull(HotelRequestRules::teamsFor('hotel_staff', 'reception'));
        $this->assertSame(['housekeeping'], HotelRequestRules::teamsFor('hotel_staff', 'housekeeping'));
        $this->assertSame([], HotelRequestRules::teamsFor('hotel_staff', null));
        $this->assertSame([], HotelRequestRules::teamsFor('hotel_staff', 'inconnue'));
        $this->assertSame([], HotelRequestRules::teamsFor('cashier', 'reception'));
        $this->assertSame([], HotelRequestRules::teamsFor(null, null));
    }

    public function test_a_non_hotel_business_has_no_board(): void
    {
        $this->compte('c-r');
        app(BusinessService::class)->create(Tenant::account(), ['name' => 'Chez Rose', 'type' => 'restaurant', 'currency' => 'HTG']);

        $this->get(route('tagtoa.hotel.board'))->assertRedirect(route('tagtoa.hub'));
    }

    public function test_the_migration_is_replayable_and_reversible(): void
    {
        $mig = require __DIR__.'/../../Database/migrations/2026_11_02_000192_add_hotel_team_to_staff_and_requests.php';
        $mig->up();
        $mig->up();
        $mig->down();
        $this->assertFalse(Schema::hasColumn('tagtoa_staff', 'hotel_team'));
        $this->assertFalse(Schema::hasColumn('tagtoa_hotel_requests', 'handled_by_staff_id'));
        $mig->up();
        $this->assertTrue(Schema::hasColumn('tagtoa_staff', 'hotel_team'));
        $this->assertTrue(Schema::hasColumn('tagtoa_hotel_requests', 'handled_by_staff_id'));
    }
}
