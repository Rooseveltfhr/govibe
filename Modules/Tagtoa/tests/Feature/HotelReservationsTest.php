<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA HÔTEL (HM-3) — demandes de réservation de chambre (sans paiement en ligne)
|--------------------------------------------------------------------------
| L'invité (sans compte) demande une chambre depuis la page publique de l'hôtel, reçoit une référence + un lien de
| suivi secret, peut annuler. L'hôtel confirme ou refuse. Le PRIX et les « inclus » sont lus dans la base et GELÉS
| (jamais pris dans la requête). Plafonds, doublons, texte échappé, isolation entre hôtels, purge des données perso.
*/

use Carbon\Carbon;
use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Models\Hotel\HotelRatePlan;
use Modules\Tagtoa\App\Models\Hotel\HotelReservation;
use Modules\Tagtoa\App\Models\Hotel\HotelRoomType;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Services\Business\BusinessService;
use Modules\Tagtoa\App\Services\Hotel\HotelProfileService;
use Modules\Tagtoa\App\Support\Hotel\HotelReservationRules as R;
use Modules\Tagtoa\App\Support\Tenant;
use Modules\Tagtoa\Tests\TestCase;

class HotelReservationsTest extends TestCase
{
    use RefreshDatabase;

    private const TZ = 'America/Port-au-Prince';

    protected function setUp(): void
    {
        parent::setUp();
        // Temps gelé: « aujourd'hui » et « maintenant » ne dépendent pas de l'heure du test.
        Carbon::setTestNow(Carbon::parse('2026-11-10 15:00:00', self::TZ));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function commerce(string $compte, string $nom, string $type = 'hotel'): Business
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $compte, 'name' => 'Rose']));
        Tenant::flush();

        return app(BusinessService::class)->create(Tenant::account(), ['name' => $nom, 'type' => $type, 'currency' => 'HTG']);
    }

    /** Un hôtel prêt à recevoir: menu public, un type de chambre, une nuit à 2500,50 et un moment de 2 h à 800. */
    private function hotel(string $compte = 'c-a', string $nom = 'Hôtel Alpha'): array
    {
        $b = $this->commerce($compte, $nom);
        $menu = Menu::allTenants()->create(['tenant_id' => $b->id, 'name' => 'Hôtel', 'alias' => 'hotel-'.uniqid(), 'type' => 'restaurant', 'currency' => 'HTG', 'is_active' => true]);
        $type = HotelRoomType::allTenants()->create(['tenant_id' => $b->id, 'name' => 'Chambre climatisée', 'is_active' => true, 'sort' => 1]);
        $nuit = HotelRatePlan::allTenants()->create(['tenant_id' => $b->id, 'room_type_id' => $type->id, 'kind' => 'night', 'slot' => 0, 'price' => 2500.50, 'currency' => 'HTG',
            'inclusions' => ['keys' => ['breakfast', 'housekeeping'], 'custom' => ['Jacuzzi']], 'sort' => 1, 'is_active' => true]);
        $moment = HotelRatePlan::allTenants()->create(['tenant_id' => $b->id, 'room_type_id' => $type->id, 'kind' => 'block', 'hours' => 2, 'slot' => 2, 'price' => 800, 'currency' => 'HTG',
            'inclusions' => ['keys' => ['fan'], 'custom' => []], 'sort' => 2, 'is_active' => true]);

        return [$b, $menu, $type, $nuit, $moment];
    }

    private function visiteur(): void
    {
        Auth::guard()->forgetUser();
        Tenant::flush();
    }

    private function demander(Menu $menu, HotelRatePlan $plan, array $extra = [])
    {
        $this->visiteur();
        $base = ['plan_id' => $plan->id, 'date' => '2026-11-15', 'name' => 'Jean Pierre', 'phone' => '+509 3712 4455'];
        if ($plan->kind === 'night') {
            $base['nights'] = 3;
        } else {
            $base['time'] = '18:00';
        }

        return $this->post(route('tagtoa.menu.reserve', $menu->alias), array_merge($base, $extra));
    }

    /** @return array{0:string,1:string} référence et secret lus dans le lien de suivi */
    private function lien($response): array
    {
        $response->assertRedirect();
        $this->assertMatchesRegularExpression('#/h/r/(HV-[A-Z0-9]{8})/([a-f0-9]{32})$#', $response->headers->get('Location'));
        preg_match('#/h/r/(HV-[A-Z0-9]{8})/([a-f0-9]{32})$#', $response->headers->get('Location'), $m);

        return [$m[1], $m[2]];
    }

    /* ---------- création ---------- */

    public function test_a_night_request_freezes_the_price_total_and_inclusions_and_redirects_to_a_secret_tracking_page(): void
    {
        [$b, $menu, $type, $nuit] = $this->hotel();

        [$ref, $secret] = $this->lien($this->demander($menu, $nuit, ['guests' => 2, 'note' => 'Arrivée tardive']));

        $r = HotelReservation::allTenants()->sole();
        $this->assertSame($b->id, $r->tenant_id);
        $this->assertSame($ref, $r->reference);
        $this->assertSame('new', $r->status);
        $this->assertSame(['Chambre climatisée', 'night', null, 3], [$r->room_type_name, $r->plan_kind, $r->plan_hours, $r->nights]);
        $this->assertEquals(2500.50, $r->price);
        $this->assertEquals(7501.50, $r->total, '3 nuits × 2500,50, calculé côté serveur.');
        $this->assertSame('HTG', $r->currency);
        $this->assertSame(['keys' => ['breakfast', 'housekeeping'], 'custom' => ['Jacuzzi']], $r->inclusions);
        $this->assertSame('2026-11-15', $r->starts_on->format('Y-m-d'));
        $this->assertNull($r->start_time);
        $this->assertSame(2, $r->guests);
        $this->assertSame('Jean Pierre', $r->guest_name);
        $this->assertSame('50937124455', $r->guest_phone);
        $this->assertSame('37124455', $r->phone_key);
        $this->assertSame('Arrivée tardive', $r->note);

        // Le secret n'est JAMAIS stocké en clair: seul son sha256.
        $this->assertSame(hash('sha256', $secret), $r->getAttributes()['track_hash']);
        $this->assertStringNotContainsString($secret, json_encode($r->getAttributes()));
        $this->assertArrayNotHasKey('track_hash', $r->toArray());

        $page = $this->get(route('tagtoa.hotel.reservation.track', [$ref, $secret]))->assertOk()->getContent();
        foreach ([$ref, 'Chambre climatisée', 'Nuit', '3 nuits', '15/11/2026', 'Petit déjeuner', 'Ménage', 'Jacuzzi', 'à régler sur place', 'Nouvelle'] as $txt) {
            $this->assertStringContainsString($txt, $page);
        }
    }

    public function test_a_moment_request_needs_a_time_and_ignores_nights(): void
    {
        [, $menu, , , $moment] = $this->hotel();

        [$ref] = $this->lien($this->demander($menu, $moment, ['nights' => 9]));
        $r = HotelReservation::allTenants()->where('reference', $ref)->sole();
        $this->assertSame(['block', 2, 1], [$r->plan_kind, $r->plan_hours, $r->nights]);
        $this->assertEquals(800, $r->total, 'Un moment = son prix, jamais multiplié.');
        $this->assertSame('18:00:00', (string) $r->start_time);
    }

    public function test_the_price_never_comes_from_the_request(): void
    {
        [, $menu, , $nuit] = $this->hotel();

        $this->lien($this->demander($menu, $nuit, ['price' => 1, 'total' => 1, 'currency' => 'USD', 'inclusions' => ['pool'], 'room_type_name' => 'Gratuit']));
        $r = HotelReservation::allTenants()->sole();
        $this->assertEquals(2500.50, $r->price);
        $this->assertEquals(7501.50, $r->total);
        $this->assertSame('HTG', $r->currency);
        $this->assertSame('Chambre climatisée', $r->room_type_name);
        $this->assertSame(['breakfast', 'housekeeping'], $r->inclusions['keys']);
    }

    public function test_changing_the_rate_later_never_changes_a_request_already_made(): void
    {
        [, $menu, , $nuit] = $this->hotel();
        [$ref, $secret] = $this->lien($this->demander($menu, $nuit));

        $nuit->update(['price' => 9999, 'inclusions' => ['keys' => ['pool'], 'custom' => []]]);
        HotelRoomType::allTenants()->update(['name' => 'Renommée']);

        $r = HotelReservation::allTenants()->sole();
        $this->assertEquals(2500.50, $r->price);
        $this->assertSame('Chambre climatisée', $r->room_type_name);
        $page = $this->get(route('tagtoa.hotel.reservation.track', [$ref, $secret]))->getContent();
        $this->assertStringContainsString('Petit déjeuner', $page);
        $this->assertStringNotContainsString('Accès à la piscine', $page);
        $this->assertStringNotContainsString('Renommée', $page);
    }

    /* ---------- validation ---------- */

    /** @dataProvider badInputs */
    public function test_invalid_requests_create_nothing(array $extra, string $kind = 'night'): void
    {
        [, $menu, , $nuit, $moment] = $this->hotel();

        $this->demander($menu, $kind === 'night' ? $nuit : $moment, $extra)->assertRedirect(route('tagtoa.menu.show', $menu->alias).'#rooms');
        $this->assertSame(0, HotelReservation::allTenants()->count());
    }

    public static function badInputs(): array
    {
        return [
            'sans nom'            => [['name' => '']],
            'nom trop court'      => [['name' => 'J']],
            'nom trop long'       => [['name' => str_repeat('a', 121)]],
            'sans téléphone'      => [['phone' => '']],
            'téléphone trop court' => [['phone' => '1234567']],
            'téléphone sans chiffres' => [['phone' => 'abcdefghij']],
            'téléphone trop long' => [['phone' => '1234567890123456']],
            'sans date'           => [['date' => '']],
            'date mal formée'     => [['date' => '15/11/2026']],
            'date impossible'     => [['date' => '2026-02-31']],
            'date passée'         => [['date' => '2026-11-09']],
            'date trop lointaine' => [['date' => '2027-12-31']],
            'nuits à zéro'        => [['nights' => 0]],
            'trop de nuits'       => [['nights' => R::NIGHTS_MAX + 1]],
            'personnes à zéro'    => [['guests' => 0]],
            'trop de personnes'   => [['guests' => R::GUESTS_MAX + 1]],
            'note trop longue'    => [['note' => str_repeat('n', R::NOTE_MAX + 1)]],
            'plan en tableau'     => [['plan_id' => [1]]],
            'nom en tableau'      => [['name' => ['x']]],
            'moment sans heure'   => [['time' => ''], 'block'],
            'heure mal formée'    => [['time' => '25:99'], 'block'],
            'moment dans le passé' => [['date' => '2026-11-10', 'time' => '14:00'], 'block'],
        ];
    }

    public function test_today_is_allowed_and_a_moment_slightly_in_the_past_is_tolerated_but_not_an_hour_ago(): void
    {
        [, $menu, , $nuit, $moment] = $this->hotel();

        $this->lien($this->demander($menu, $nuit, ['date' => '2026-11-10']));
        $this->lien($this->demander($menu, $moment, ['date' => '2026-11-10', 'time' => '14:57', 'phone' => '3700 0001']));
        $this->assertSame(2, HotelReservation::allTenants()->count());
        $this->demander($menu, $moment, ['date' => '2026-11-10', 'time' => '14:00', 'phone' => '3700 0002']);
        $this->assertSame(2, HotelReservation::allTenants()->count());
    }

    public function test_an_unavailable_plan_is_refused_whatever_the_reason(): void
    {
        [$b, $menu, $type, $nuit, $moment] = $this->hotel();
        $autre = $this->hotel('c-b', 'Hôtel Beta')[3];

        $this->demander($menu, $autre)->assertSessionHas('reserve_error');                         // tarif d'un AUTRE hôtel
        $nuit->update(['is_active' => false]);
        $this->demander($menu, $nuit)->assertSessionHas('reserve_error');                          // tarif désactivé
        $type->update(['is_active' => false]);
        $this->demander($menu, $moment)->assertSessionHas('reserve_error');                        // type désactivé
        $this->assertSame(0, HotelReservation::allTenants()->count());
    }

    public function test_a_non_hotel_or_an_inactive_hotel_or_an_inactive_menu_receives_nothing(): void
    {
        [$b, $menu, , $nuit] = $this->hotel();

        Business::whereKey($b->id)->update(['type' => 'restaurant']);
        $this->demander($menu, $nuit)->assertSessionHas('reserve_error');
        Business::whereKey($b->id)->update(['type' => 'hotel', 'is_active' => false]);
        $this->demander($menu, $nuit)->assertSessionHas('reserve_error');
        Business::whereKey($b->id)->update(['is_active' => true]);
        Menu::allTenants()->whereKey($menu->id)->update(['is_active' => false]);
        $this->demander($menu, $nuit)->assertNotFound();
        $this->assertSame(0, HotelReservation::allTenants()->count());
    }

    /* ---------- doublons et plafonds ---------- */

    public function test_the_same_open_request_is_not_created_twice_but_another_date_is(): void
    {
        [, $menu, , $nuit] = $this->hotel();

        $this->lien($this->demander($menu, $nuit));
        $this->demander($menu, $nuit)->assertSessionHas('reserve_error');                          // double envoi / deux onglets
        $this->demander($menu, $nuit, ['phone' => '37124455'])->assertSessionHas('reserve_error'); // même numéro écrit autrement
        $this->assertSame(1, HotelReservation::allTenants()->count());

        $this->lien($this->demander($menu, $nuit, ['date' => '2026-11-20']));
        $this->assertSame(2, HotelReservation::allTenants()->count());
    }

    public function test_a_phone_cannot_stack_more_than_three_new_requests_and_a_confirmation_frees_a_slot(): void
    {
        [$b, $menu, , $nuit] = $this->hotel();
        foreach (['2026-11-15', '2026-11-16', '2026-11-17'] as $d) {
            $this->lien($this->demander($menu, $nuit, ['date' => $d]));
        }
        $this->demander($menu, $nuit, ['date' => '2026-11-18', 'phone' => '+50937124455'])->assertSessionHas('reserve_error');
        $this->assertSame(3, HotelReservation::allTenants()->count());

        // Un autre numéro n'est pas gêné.
        $this->lien($this->demander($menu, $nuit, ['date' => '2026-11-18', 'phone' => '3799 9999']));

        // Une demande confirmée ne compte plus comme « nouvelle ».
        HotelReservation::allTenants()->whereDate('starts_on', '2026-11-15')->update(['status' => 'confirmed']);
        $this->lien($this->demander($menu, $nuit, ['date' => '2026-11-19']));
    }

    public function test_an_old_forgotten_new_request_stops_counting_toward_the_phone_cap(): void
    {
        [, $menu, , $nuit] = $this->hotel();
        foreach (['2026-11-15', '2026-11-16', '2026-11-17'] as $d) {
            $this->lien($this->demander($menu, $nuit, ['date' => $d]));
        }
        HotelReservation::allTenants()->update(['created_at' => now()->subDays(R::NEW_FRESH_DAYS + 1)]);

        $this->lien($this->demander($menu, $nuit, ['date' => '2026-11-18']));
    }

    public function test_the_hotel_wide_hourly_cap_stops_a_flood(): void
    {
        [$b, $menu, , $nuit] = $this->hotel();
        for ($i = 0; $i < R::PER_HOTEL_HOURLY; $i++) {
            RateLimiter::hit('hotel-reserve-all:'.$b->id, 3600);
        }

        $this->demander($menu, $nuit)->assertSessionHas('reserve_error');
        $this->assertSame(0, HotelReservation::allTenants()->count());
    }

    public function test_a_successful_request_counts_toward_the_hotel_hourly_cap(): void
    {
        [$b, $menu, , $nuit] = $this->hotel();
        $this->assertSame(0, RateLimiter::attempts('hotel-reserve-all:'.$b->id));

        $this->lien($this->demander($menu, $nuit));
        $this->assertSame(1, RateLimiter::attempts('hotel-reserve-all:'.$b->id));

        $this->demander($menu, $nuit)->assertSessionHas('reserve_error');   // un échec ne compte pas
        $this->assertSame(1, RateLimiter::attempts('hotel-reserve-all:'.$b->id));
    }

    public function test_the_service_itself_clamps_nights_and_ignores_any_price_or_inclusions_it_is_handed(): void
    {
        [$b, , , $nuit] = $this->hotel();
        $svc = app(\Modules\Tagtoa\App\Services\Hotel\HotelReservationService::class);
        $base = ['plan_id' => $nuit->id, 'date' => '2026-11-15', 'name' => 'Direct', 'phone' => '37120000'];

        // Appel direct (hors contrôleur): même garde que la validation HTTP.
        $r = $svc->create($b->id, $base + ['nights' => 999, 'price' => 1, 'inclusions' => ['keys' => ['pool'], 'custom' => []], 'total' => 1], now(self::TZ));
        $this->assertSame('ok', $r['result']);
        $this->assertSame(R::NIGHTS_MAX, $r['reservation']->nights);
        $this->assertEquals(round(2500.50 * R::NIGHTS_MAX, 2), $r['reservation']->total);
        $this->assertEquals(2500.50, $r['reservation']->price);
        $this->assertSame(['breakfast', 'housekeeping'], $r['reservation']->inclusions['keys']);

        $r2 = $svc->create($b->id, array_merge($base, ['date' => '2026-11-16', 'nights' => -5]), now(self::TZ));
        $this->assertSame(1, $r2['reservation']->nights);
    }

    public function test_a_plan_of_another_hotel_is_refused_even_when_its_type_id_points_to_this_hotels_type(): void
    {
        [$a, $menuA, $typeA] = $this->hotel('c-a', 'Hôtel Alpha');
        [, , , , $momentB] = $this->hotel('c-b', 'Hôtel Beta');
        // Donnée incohérente: le tarif de B accroché au type de A (créneau libre: 3 h). Le filtre tenant du TARIF doit suffire.
        $momentB->update(['room_type_id' => $typeA->id, 'hours' => 3, 'slot' => 3]);

        $this->demander($menuA, $momentB->fresh())->assertSessionHas('reserve_error');
        $this->assertSame(0, HotelReservation::allTenants()->count());
    }

    public function test_the_public_reserve_route_has_its_own_throttle_counter(): void
    {
        $this->assertContains('throttle:10,1,hotel-reserve', Route::getRoutes()->getByName('tagtoa.menu.reserve')->gatherMiddleware());
    }

    /* ---------- suivi et annulation par l'invité ---------- */

    public function test_tracking_answers_the_same_neutral_404_for_every_wrong_reference_or_secret(): void
    {
        [, $menu, , $nuit] = $this->hotel();
        [$ref, $secret] = $this->lien($this->demander($menu, $nuit));

        $mauvais = [
            [$ref, str_repeat('0', 32)], ['HV-AAAAAAAA', $secret], [$ref, 'x'], [$ref, str_repeat('a', 65)], ['HV-'.str_repeat('Z', 30), $secret],
        ];
        $corps = [];
        foreach ($mauvais as [$r, $s]) {
            $rep = $this->get(route('tagtoa.hotel.reservation.track', [$r, $s]));
            $rep->assertNotFound();
            $corps[] = $rep->getContent();
            $this->post(route('tagtoa.hotel.reservation.cancel', [$r, $s]))->assertNotFound();
        }
        $this->assertCount(1, array_unique($corps), 'Même corps pour toute erreur: rien à deviner.');
        $this->assertSame('new', HotelReservation::allTenants()->sole()->status);
    }

    public function test_the_tracking_page_is_never_cached_indexed_or_leaking_a_referrer(): void
    {
        [, $menu, , $nuit] = $this->hotel();
        [$ref, $secret] = $this->lien($this->demander($menu, $nuit));

        $r = $this->get(route('tagtoa.hotel.reservation.track', [$ref, $secret]))->assertOk();
        $this->assertStringContainsString('no-store', (string) $r->headers->get('Cache-Control'));
        $this->assertSame('noindex, nofollow', $r->headers->get('X-Robots-Tag'));
        $this->assertSame('no-referrer', $r->headers->get('Referrer-Policy'));
    }

    public function test_a_tracking_link_expires(): void
    {
        [, $menu, , $nuit] = $this->hotel();
        [$ref, $secret] = $this->lien($this->demander($menu, $nuit));

        HotelReservation::allTenants()->update(['created_at' => now()->subDays(R::TRACK_DAYS + 1), 'starts_on' => now()->subDays(R::TRACK_DAYS + 1)->toDateString()]);
        $this->get(route('tagtoa.hotel.reservation.track', [$ref, $secret]))->assertNotFound();
    }

    public function test_the_guest_can_cancel_while_the_request_is_open_but_not_after(): void
    {
        [, $menu, , $nuit] = $this->hotel();
        [$ref, $secret] = $this->lien($this->demander($menu, $nuit));

        $this->post(route('tagtoa.hotel.reservation.cancel', [$ref, $secret]))->assertRedirect(route('tagtoa.hotel.reservation.track', [$ref, $secret]))->assertSessionHas('guest_ok');
        $r = HotelReservation::allTenants()->sole();
        $this->assertSame('cancelled', $r->status);
        $this->assertNotNull($r->closed_at);

        $this->post(route('tagtoa.hotel.reservation.cancel', [$ref, $secret]))->assertSessionHas('guest_error');   // déjà clôturée

        // Une demande CONFIRMÉE peut aussi être annulée par l'invité; une refusée non.
        $r->update(['status' => 'confirmed', 'closed_at' => null]);
        $this->post(route('tagtoa.hotel.reservation.cancel', [$ref, $secret]))->assertSessionHas('guest_ok');
        $r->update(['status' => 'refused']);
        $this->post(route('tagtoa.hotel.reservation.cancel', [$ref, $secret]))->assertSessionHas('guest_error');
        $this->assertSame('refused', $r->fresh()->status);
    }

    /* ---------- côté hôtel ---------- */

    private function ouvrirHotel(Business $b): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $b->account_id ?? 'c-a', 'name' => 'Rose']));
        Tenant::flush();
        Tenant::switchTo($b->id);
    }

    public function test_the_hotel_lists_confirms_and_refuses_with_a_reason_the_guest_can_read(): void
    {
        [$b, $menu, , $nuit, $moment] = $this->hotel();
        [$ref1, $secret1] = $this->lien($this->demander($menu, $nuit, ['name' => 'Marie Claire']));
        [$ref2, $secret2] = $this->lien($this->demander($menu, $moment, ['name' => 'Paul Jean', 'phone' => '3799 1111']));
        $this->ouvrirHotel($b);

        $this->get(route('tagtoa.hotel.reservations'))->assertOk()->assertSee('Marie Claire')->assertSee('Paul Jean')->assertSee($ref1)
            ->assertSee(\Modules\Tagtoa\App\Support\Money::format(7501.50, 'HTG'))->assertSee('/tagtoa/hotel/reservations', false);

        $id1 = HotelReservation::where('reference', $ref1)->value('id');
        $id2 = HotelReservation::where('reference', $ref2)->value('id');
        $this->post(route('tagtoa.hotel.reservations.status', $id1), ['to' => 'confirmed'])->assertSessionHas('success');
        $this->post(route('tagtoa.hotel.reservations.status', $id2), ['to' => 'refused', 'reason' => 'Complet ce soir'])->assertSessionHas('success');

        $this->assertSame(['confirmed', 'refused'], [HotelReservation::find($id1)->status, HotelReservation::find($id2)->status]);
        $this->assertNotNull(HotelReservation::find($id2)->closed_at);
        $this->assertNull(HotelReservation::find($id1)->closed_at, 'Une confirmée reste ouverte.');

        $this->visiteur();
        $this->get(route('tagtoa.hotel.reservation.track', [$ref1, $secret1]))->assertOk()->assertSee('Confirmée');
        $this->get(route('tagtoa.hotel.reservation.track', [$ref2, $secret2]))->assertOk()->assertSee('Refusée')->assertSee('Complet ce soir');
    }

    public function test_hotel_side_transitions_are_enforced(): void
    {
        [$b, $menu, , $nuit] = $this->hotel();
        $this->lien($this->demander($menu, $nuit));
        $this->ouvrirHotel($b);
        $id = HotelReservation::allTenants()->value('id');

        $this->post(route('tagtoa.hotel.reservations.status', $id), ['to' => 'cancelled'])->assertSessionHasErrors('to');   // l'annulation est le fait de l'invité
        $this->post(route('tagtoa.hotel.reservations.status', $id), ['to' => 'new'])->assertSessionHasErrors('to');
        $this->post(route('tagtoa.hotel.reservations.status', $id), ['to' => 'confirmed'])->assertSessionHas('success');
        $this->post(route('tagtoa.hotel.reservations.status', $id), ['to' => 'confirmed'])->assertSessionHas('success');       // déjà confirmée : succès neutre
        $this->post(route('tagtoa.hotel.reservations.status', $id), ['to' => 'refused', 'reason' => 'Retirée'])->assertSessionHas('success');
        $this->post(route('tagtoa.hotel.reservations.status', $id), ['to' => 'confirmed'])->assertSessionHas('error');         // une refusée ne revient pas
        $this->assertSame('refused', HotelReservation::allTenants()->value('status'));
    }

    public function test_the_section_is_reserved_to_hotels(): void
    {
        $this->commerce('c-r', 'Resto', 'restaurant');

        $this->get(route('tagtoa.hotel.reservations'))->assertRedirect(route('tagtoa.hub'));
        $this->post(route('tagtoa.hotel.reservations.status', 1), ['to' => 'confirmed'])->assertRedirect(route('tagtoa.hub'));
    }

    public function test_another_hotel_cannot_read_or_touch_these_requests(): void
    {
        [$a, $menuA, , $nuitA] = $this->hotel('c-a', 'Hôtel Alpha');
        $this->lien($this->demander($menuA, $nuitA, ['name' => 'Cliente Alpha']));
        $idA = HotelReservation::allTenants()->value('id');

        [$b] = $this->hotel('c-b', 'Hôtel Beta');
        $this->ouvrirHotel($b);

        $this->get(route('tagtoa.hotel.reservations'))->assertOk()->assertDontSee('Cliente Alpha');
        $this->post(route('tagtoa.hotel.reservations.status', $idA), ['to' => 'confirmed'])->assertNotFound();
        $this->assertSame('new', HotelReservation::allTenants()->find($idA)->status);
    }

    public function test_the_filter_accepts_a_status_and_ignores_garbage_including_arrays(): void
    {
        [$b, $menu, , $nuit] = $this->hotel();
        $this->lien($this->demander($menu, $nuit, ['name' => 'Nouvelle Une']));
        $this->ouvrirHotel($b);

        $this->get(route('tagtoa.hotel.reservations', ['status' => 'confirmed']))->assertOk()->assertDontSee('Nouvelle Une');
        $this->get(route('tagtoa.hotel.reservations', ['status' => 'new']))->assertOk()->assertSee('Nouvelle Une');
        $this->get('/tagtoa/hotel/reservations?status[]=new')->assertOk()->assertSee('Nouvelle Une');
        $this->get(route('tagtoa.hotel.reservations', ['status' => 'zzz']))->assertOk()->assertSee('Nouvelle Une');
    }

    /* ---------- texte échappé ---------- */

    public function test_names_and_notes_are_cleaned_and_escaped_everywhere(): void
    {
        [$b, $menu, , $nuit] = $this->hotel();
        [$ref, $secret] = $this->lien($this->demander($menu, $nuit, ['name' => '<b>Marc</b> <script>alert(1)</script>', 'note' => "<img src=x onerror=alert(2)>Calme\x07 svp"]));

        $r = HotelReservation::allTenants()->sole();
        $this->assertStringNotContainsString('<', (string) $r->guest_name);
        $this->assertStringNotContainsString('<', (string) $r->note);
        $this->assertStringNotContainsString("\x07", (string) $r->note);

        $this->assertStringNotContainsString('<script>alert', $this->get(route('tagtoa.hotel.reservation.track', [$ref, $secret]))->getContent());
        $this->ouvrirHotel($b);
        $html = $this->get(route('tagtoa.hotel.reservations'))->getContent();
        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringNotContainsString('<img src=x', $html);

        // Motif de refus: balises neutralisées aussi.
        $this->post(route('tagtoa.hotel.reservations.status', $r->id), ['to' => 'refused', 'reason' => '<script>x</script>Complet']);
        $this->assertSame('xComplet', $r->fresh()->reason);
    }

    /* ---------- page publique ---------- */

    public function test_the_public_page_offers_a_form_per_plan_with_the_right_fields(): void
    {
        [, $menu, , $nuit, $moment] = $this->hotel();
        $this->visiteur();

        $html = $this->get(route('tagtoa.menu.show', $menu->alias))->assertOk()->getContent();
        $this->assertStringContainsString('action="'.route('tagtoa.menu.reserve', $menu->alias).'"', $html);
        $this->assertStringContainsString('name="plan_id" value="'.$nuit->id.'"', $html);
        $this->assertStringContainsString('name="plan_id" value="'.$moment->id.'"', $html);
        $this->assertStringContainsString('name="nights"', $html, 'La nuit demande un nombre de nuits.');
        $this->assertStringContainsString('name="time"', $html, 'Le moment demande une heure.');
        $this->assertStringContainsString('Aucun paiement en ligne', $html);
        // Le prix n'est PAS un champ du formulaire.
        $this->assertStringNotContainsString('name="price"', $html);
    }

    public function test_a_failed_form_reopens_and_refills_only_itself(): void
    {
        [, $menu, , $nuit, $moment] = $this->hotel();

        $this->demander($menu, $nuit, ['phone' => '123', 'name' => 'Rempli Nuit']);
        $html = $this->get(route('tagtoa.menu.show', $menu->alias))->getContent();

        $this->assertSame(1, substr_count($html, 'Rempli Nuit'), 'Les valeurs reviennent dans le seul formulaire fautif.');
        $this->assertSame(1, preg_match_all('/<details class="rform"\s+open\s*>/', $html), 'Seul le formulaire fautif s\'ouvre; celui du moment reste fermé.');
        $this->assertSame(2, preg_match_all('/<details class="rform"/', $html));
    }

    /* ---------- sécurité des données ---------- */

    public function test_a_hotel_with_upcoming_requests_cannot_change_type_but_a_closed_or_past_one_does_not_block(): void
    {
        [$b, $menu, , $nuit] = $this->hotel();
        $service = app(HotelProfileService::class);
        $this->assertNull($service->blocksTypeChange(Business::find($b->id)));

        $this->lien($this->demander($menu, $nuit));
        $this->assertStringContainsString('réservations', (string) $service->blocksTypeChange(Business::find($b->id)));

        HotelReservation::allTenants()->update(['status' => 'refused']);
        $this->assertNull($service->blocksTypeChange(Business::find($b->id)));

        HotelReservation::allTenants()->update(['status' => 'confirmed', 'starts_on' => '2026-11-01']);
        $this->assertNull($service->blocksTypeChange(Business::find($b->id)), 'Un séjour passé ne bloque pas.');
    }

    public function test_the_purge_erases_guest_data_of_old_closed_requests_only_and_keeps_the_money_trail(): void
    {
        [, $menu, , $nuit] = $this->hotel();
        $refs = [];
        foreach (['2026-11-15', '2026-11-16', '2026-11-17', '2026-11-18', '2026-11-19'] as $i => $d) {
            [$refs[$i]] = $this->lien($this->demander($menu, $nuit, ['date' => $d, 'phone' => '3711 000'.$i, 'note' => 'secret'.$i]));
        }
        $get = fn ($i) => HotelReservation::allTenants()->where('reference', $refs[$i])->first();

        // 0: refusée il y a 120 j (effacée) · 1: refusée hier (gardée) · 2: confirmée, séjour il y a 120 j (effacée)
        // 3: confirmée, séjour à venir (gardée) · 4: « nouvelle » vieille de 200 j (effacée)
        $get(0)->forceFill(['status' => 'refused', 'reason' => 'Complet', 'updated_at' => now()->subDays(120)])->saveQuietly();
        $get(1)->forceFill(['status' => 'refused', 'updated_at' => now()->subDay()])->saveQuietly();
        $get(2)->forceFill(['status' => 'confirmed', 'starts_on' => now()->subDays(120)])->saveQuietly();
        $get(3)->forceFill(['status' => 'confirmed'])->saveQuietly();
        $get(4)->forceFill(['status' => 'new', 'created_at' => now()->subDays(200)])->saveQuietly();

        $this->artisan('tagtoa:hotel:purge-requests')->assertSuccessful();

        foreach ([0, 2, 4] as $i) {
            $r = $get($i);
            $this->assertSame([null, null, null, null, null], [$r->guest_name, $r->guest_phone, $r->phone_key, $r->note, $r->reason], "ligne $i");
            $this->assertEquals(7501.50, $r->total, 'Le montant et la ligne restent.');
        }
        foreach ([1, 3] as $i) {
            $this->assertNotNull($get($i)->guest_name, "ligne $i intacte");
            $this->assertNotNull($get($i)->guest_phone);
        }
    }

    public function test_the_hotel_sidebar_links_to_the_reservations(): void
    {
        [$b] = $this->hotel();
        $this->ouvrirHotel($b);

        $this->assertContains('/tagtoa/hotel/reservations', array_column(\Modules\Tagtoa\App\Support\DashboardModules::enabled('module')['hotel']['children'], 'url'));
    }

    public function test_the_migration_is_replayable_and_reversible(): void
    {
        $m = require __DIR__.'/../../Database/migrations/2026_11_02_000197_create_tagtoa_hotel_reservations_table.php';
        $m->up();
        $m->up();
        $this->assertTrue(Schema::hasTable('tagtoa_hotel_reservations'));
        $m->down();
        $this->assertFalse(Schema::hasTable('tagtoa_hotel_reservations'));
        $m->up();
        $this->assertTrue(Schema::hasColumns('tagtoa_hotel_reservations', ['track_hash', 'total', 'inclusions', 'phone_key']));
    }

    /* ---------- revue HM-3 : durcissements ---------- */

    public function test_one_visitor_cannot_exhaust_the_hotel_wide_cap_alone(): void
    {
        [$b, $menu, , $nuit] = $this->hotel();
        for ($i = 0; $i < R::PER_IP_HOURLY; $i++) {
            RateLimiter::hit('hotel-reserve-ip:'.$b->id.':127.0.0.1', 3600);
        }

        $this->demander($menu, $nuit)->assertSessionHas('reserve_error');
        $this->assertSame(0, HotelReservation::allTenants()->count());
        $this->assertSame(0, RateLimiter::attempts('hotel-reserve-all:'.$b->id), 'Le plafond de l\'hôtel reste intact.');
    }

    public function test_each_successful_request_counts_toward_the_per_visitor_cap_and_the_next_one_is_refused(): void
    {
        // Le throttle de la route (10/min) est testé ailleurs ; ici on isole le plafond horaire par visiteur.
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        [$b, $menu, , $nuit] = $this->hotel();
        for ($i = 0; $i < R::PER_IP_HOURLY; $i++) {
            $this->lien($this->demander($menu, $nuit, ['date' => '2026-11-'.(11 + $i), 'phone' => '3700 00'.str_pad((string) $i, 2, '0', STR_PAD_LEFT)]));
        }
        $this->assertSame(R::PER_IP_HOURLY, RateLimiter::attempts('hotel-reserve-ip:'.$b->id.':127.0.0.1'));

        $this->demander($menu, $nuit, ['date' => '2026-12-20', 'phone' => '3799 9999'])->assertSessionHas('reserve_error');
        $this->assertSame(R::PER_IP_HOURLY, HotelReservation::allTenants()->count());
    }

    public function test_duplicate_and_too_many_give_the_same_message_so_a_phone_cannot_be_probed(): void
    {
        [, $menu, , $nuit] = $this->hotel();
        $this->lien($this->demander($menu, $nuit));
        $dup = $this->demander($menu, $nuit)->assertSessionHas('reserve_error')->getSession()->get('reserve_error');
        foreach (['2026-11-16', '2026-11-17'] as $d) {
            $this->lien($this->demander($menu, $nuit, ['date' => $d]));
        }
        $many = $this->demander($menu, $nuit, ['date' => '2026-11-18'])->assertSessionHas('reserve_error')->getSession()->get('reserve_error');

        $this->assertSame($dup, $many);
    }

    public function test_the_tracking_link_of_a_far_future_stay_outlives_sixty_days_from_creation(): void
    {
        [, $menu, , $nuit] = $this->hotel();
        [$ref, $secret] = $this->lien($this->demander($menu, $nuit, ['date' => '2027-06-01']));
        HotelReservation::allTenants()->update(['created_at' => now()->subDays(R::TRACK_DAYS + 1)]);

        $this->get(route('tagtoa.hotel.reservation.track', [$ref, $secret]))->assertOk();

        HotelReservation::allTenants()->update(['starts_on' => now()->subDays(R::TRACK_DAYS + 1)->toDateString()]);
        $this->get(route('tagtoa.hotel.reservation.track', [$ref, $secret]))->assertNotFound();
    }

    public function test_confirming_twice_is_a_neutral_success_not_an_error(): void
    {
        [$b, $menu, , $nuit] = $this->hotel();
        $this->lien($this->demander($menu, $nuit));
        $this->ouvrirHotel($b);
        $id = HotelReservation::allTenants()->value('id');

        $this->post(route('tagtoa.hotel.reservations.status', $id), ['to' => 'confirmed'])->assertSessionHas('success');
        $this->post(route('tagtoa.hotel.reservations.status', $id), ['to' => 'confirmed'])->assertSessionHas('success')->assertSessionMissing('error');
        $this->post(route('tagtoa.hotel.reservations.status', $id), ['to' => 'refused'])->assertSessionHas('success');
        // Une refusée ne revient jamais en confirmée.
        $this->post(route('tagtoa.hotel.reservations.status', $id), ['to' => 'confirmed'])->assertSessionHas('error');
    }

    public function test_whatsapp_link_only_for_numbers_with_a_country_code(): void
    {
        [$b, $menu, , $nuit, $moment] = $this->hotel();
        $this->lien($this->demander($menu, $nuit, ['phone' => '37124455']));
        $this->ouvrirHotel($b);
        $this->get(route('tagtoa.hotel.reservations'))->assertOk()->assertDontSee('wa.me/', false);

        $this->lien($this->demander($menu, $moment, ['phone' => '+50937124455']));
        $this->ouvrirHotel($b);
        $this->get(route('tagtoa.hotel.reservations'))->assertSee('wa.me/50937124455', false);
    }
}
