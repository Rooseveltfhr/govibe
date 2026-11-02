<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA HÔTEL (H5) — demandes d'un invité, suivi, annulation, traitement par l'hôtel
|--------------------------------------------------------------------------
| L'invité n'a pas de compte : il ouvre la page de sa chambre (QR), demande un service et suit sa
| demande avec référence + secret de 128 bits. Création : jeton récent + plafonds ; suivi : mêmes
| 404 neutres que le portail ; isolation entre hôtels ; aucune donnée personnelle exigée.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Tagtoa\App\Models\Audit\AuditLog;
use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Models\Hotel\HotelRequest;
use Modules\Tagtoa\App\Models\Hotel\HotelRoom;
use Modules\Tagtoa\App\Models\Hotel\HotelService;
use Modules\Tagtoa\App\Models\Stand\Stand;
use Modules\Tagtoa\App\Services\Business\BusinessService;
use Modules\Tagtoa\App\Services\Hotel\HotelRequestService;
use Modules\Tagtoa\App\Services\Hotel\HotelRoomSupportService;
use Modules\Tagtoa\App\Services\Stand\StandMinter;
use Modules\Tagtoa\App\Support\Hotel\HotelPortalToken;
use Modules\Tagtoa\App\Support\Hotel\HotelRequestRules;
use Modules\Tagtoa\App\Support\Stand\StandScratch;
use Modules\Tagtoa\App\Support\Tenant;
use Modules\Tagtoa\Tests\TestCase;

class HotelRequestsTest extends TestCase
{
    use RefreshDatabase;

    private array $secrets = [];

    private function fabriquer(int $n = 4): void
    {
        $this->secrets = app(StandMinter::class)->mint('TAGTOA-2026-001', $n)['secrets'];
    }

    private function stand(int $i): Stand
    {
        return Stand::orderBy('serial')->get()[$i];
    }

    private function compte(string $id): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $id, 'name' => 'Rose']));
        Tenant::flush();
    }

    /** Un hôtel avec une chambre 101 équipée d'un support ; renvoie [hôtel, chambre, support, service ménage]. */
    private function hotel(string $compte, string $nom, int $standIndex): array
    {
        $this->compte($compte);
        $b = app(BusinessService::class)->create(Tenant::account(), ['name' => $nom, 'type' => 'hotel', 'currency' => 'HTG']);
        $room = HotelRoom::allTenants()->create(['tenant_id' => $b->id, 'number' => '101', 'is_active' => true]);
        $stand = $this->stand($standIndex);
        $r = app(HotelRoomSupportService::class)->attach($b->id, $room->id, StandScratch::payload($stand->public_id, $this->secrets[$stand->public_id]));
        $this->assertSame('ok', $r['result']);
        $svc = $this->service($b->id, 'housekeeping', 'Nettoyage de la chambre');

        return [$b, $room, $stand->fresh(), $svc];
    }

    private function service(string $tenant, string $kind, string $name, bool $active = true): HotelService
    {
        return HotelService::allTenants()->create(['tenant_id' => $tenant, 'kind' => $kind, 'name' => $name, 'is_active' => $active]);
    }

    private function demander(Stand $stand, HotelService $svc, array $extra = [])
    {
        return $this->post(route('tagtoa.hotel.request', $stand->public_id), array_merge([
            'service_id' => $svc->id, 'tk' => HotelPortalToken::issue($stand->public_id),
        ], $extra));
    }

    /** Crée une demande par le chemin public et renvoie [requête, secret]. */
    private function creer(Stand $stand, HotelService $svc, array $extra = []): array
    {
        $loc = $this->demander($stand, $svc, $extra)->assertRedirect()->headers->get('Location');
        preg_match('#/h/track/(HR-[A-Z0-9]{8})/([a-f0-9]{32})$#', $loc, $m);
        $this->assertNotEmpty($m, "Redirection inattendue : $loc");

        return [HotelRequest::allTenants()->where('reference', $m[1])->firstOrFail(), $m[2]];
    }

    /* ---------- jeton ---------- */

    public function test_the_page_token_is_bound_to_its_support_and_expires(): void
    {
        $t = HotelPortalToken::issue('TG-000001', 1_800_000_000);

        $this->assertTrue(HotelPortalToken::verify('TG-000001', $t, 1_800_000_000 + 60));
        $this->assertTrue(HotelPortalToken::verify('tg-000001', $t, 1_800_000_000 + 60), 'Casse normalisée.');
        $this->assertFalse(HotelPortalToken::verify('TG-000002', $t, 1_800_000_000 + 60), 'Autre support.');
        $this->assertFalse(HotelPortalToken::verify('TG-000001', $t, 1_800_000_000 + HotelRequestRules::TOKEN_TTL + 1), 'Expiré.');
        $this->assertFalse(HotelPortalToken::verify('TG-000001', $t, 1_800_000_000 - 3600), 'Daté du futur.');
        $this->assertFalse(HotelPortalToken::verify('TG-000001', substr($t, 0, -1).(substr($t, -1) === '0' ? '1' : '0'), 1_800_000_000 + 60), 'Altéré.');
        $this->assertFalse(HotelPortalToken::verify('TG-000001', null));
        $this->assertFalse(HotelPortalToken::verify('TG-000001', 'n-importe-quoi'));
    }

    /* ---------- la page ---------- */

    public function test_the_page_offers_a_request_form_only_for_requestable_services(): void
    {
        $this->fabriquer();
        [$b, , $stand, $menage] = $this->hotel('c-a', 'Hôtel Alpha', 0);
        $this->service($b->id, 'room_service', 'Carte du soir');
        $this->service($b->id, 'information', 'Wi-Fi');
        $this->service($b->id, 'maintenance', 'Climatisation', false);

        $html = $this->get('/h/'.$stand->public_id)->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'name="service_id"'), 'Un seul service se demande ici.');
        $this->assertStringContainsString('value="'.$menage->id.'"', $html);
        $this->assertMatchesRegularExpression('/name="tk" value="\d+\.[a-f0-9]{16}\.[a-f0-9]{64}"/', $html);
        $this->assertStringNotContainsString('Climatisation', $html);
    }

    /* ---------- création ---------- */

    public function test_a_guest_creates_a_request_and_gets_a_private_tracking_link(): void
    {
        $this->fabriquer();
        [$b, $room, $stand, $svc] = $this->hotel('c-a', 'Hôtel Alpha', 0);

        [$req, $secret] = $this->creer($stand, $svc, ['note' => 'Après 14 h svp']);

        $this->assertSame('new', $req->status);
        $this->assertSame($b->id, $req->tenant_id);
        $this->assertSame($room->id, $req->room_id);
        $this->assertSame('Nettoyage de la chambre', $req->service_name);
        $this->assertSame('Après 14 h svp', $req->note);
        $this->assertSame(hash('sha256', $secret), $req->track_hash, 'Le secret n\'est stocké que haché.');
        $this->assertStringNotContainsString($secret, json_encode($req->getAttributes()));
        $this->assertArrayNotHasKey('track_hash', $req->toArray());
    }

    public function test_no_personal_data_is_required(): void
    {
        $this->fabriquer();
        [, , $stand, $svc] = $this->hotel('c-a', 'Hôtel Alpha', 0);

        $this->demander($stand, $svc)->assertRedirect();   // ni nom, ni téléphone, ni note
        $this->assertSame(1, HotelRequest::allTenants()->count());
    }

    public function test_a_missing_expired_or_foreign_token_creates_nothing(): void
    {
        $this->fabriquer();
        [, , $stand, $svc] = $this->hotel('c-a', 'Hôtel Alpha', 0);
        $url = route('tagtoa.hotel.request', $stand->public_id);

        $this->post($url, ['service_id' => $svc->id])->assertSessionHasErrors('tk');
        $this->post($url, ['service_id' => $svc->id, 'tk' => HotelPortalToken::issue($stand->public_id, time() - 20000)])->assertSessionHas('guest_error');
        $this->post($url, ['service_id' => $svc->id, 'tk' => HotelPortalToken::issue('TG-999999')])->assertSessionHas('guest_error');

        $this->assertSame(0, HotelRequest::allTenants()->count());
    }

    public function test_the_same_service_cannot_be_open_twice_for_one_room(): void
    {
        $this->fabriquer();
        [, , $stand, $svc] = $this->hotel('c-a', 'Hôtel Alpha', 0);
        $this->creer($stand, $svc);

        $this->demander($stand, $svc)->assertSessionHas('guest_error');

        $this->assertSame(1, HotelRequest::allTenants()->count());
    }

    public function test_a_room_cannot_have_more_than_the_open_cap_and_a_closed_one_frees_a_slot(): void
    {
        $this->fabriquer();
        [$b, , $stand, ] = $this->hotel('c-a', 'Hôtel Alpha', 0);
        $services = [];
        for ($i = 0; $i < HotelRequestRules::OPEN_MAX_PER_ROOM + 1; $i++) {
            $services[] = $this->service($b->id, 'linen', "Article $i");
        }
        foreach (array_slice($services, 0, HotelRequestRules::OPEN_MAX_PER_ROOM) as $s) {
            $this->creer($stand, $s);
        }

        $this->demander($stand, $services[HotelRequestRules::OPEN_MAX_PER_ROOM])->assertSessionHas('guest_error');
        $this->assertSame(HotelRequestRules::OPEN_MAX_PER_ROOM, HotelRequest::allTenants()->count());

        app(HotelRequestService::class)->setStatus($b->id, HotelRequest::allTenants()->first()->id, 'done');
        $this->creer($stand, $services[HotelRequestRules::OPEN_MAX_PER_ROOM]);
        $this->assertSame(HotelRequestRules::OPEN_MAX_PER_ROOM + 1, HotelRequest::allTenants()->count());
    }

    public function test_room_service_information_inactive_and_unknown_services_are_refused(): void
    {
        $this->fabriquer();
        [$b, , $stand, ] = $this->hotel('c-a', 'Hôtel Alpha', 0);
        foreach ([
            $this->service($b->id, 'room_service', 'Carte'),
            $this->service($b->id, 'information', 'Wi-Fi'),
            $this->service($b->id, 'maintenance', 'Clim', false),
        ] as $s) {
            $this->demander($stand, $s)->assertSessionHas('guest_error');
        }
        $this->post(route('tagtoa.hotel.request', $stand->public_id), ['service_id' => 999999, 'tk' => HotelPortalToken::issue($stand->public_id)])->assertSessionHas('guest_error');

        $this->assertSame(0, HotelRequest::allTenants()->count());
    }

    public function test_another_hotels_service_cannot_be_requested_from_this_room(): void
    {
        $this->fabriquer();
        [, , $standA, ] = $this->hotel('c-a', 'Hôtel Alpha', 0);
        [, , , $svcB] = $this->hotel('c-b', 'Hôtel Bravo', 1);

        $this->demander($standA, $svcB)->assertSessionHas('guest_error');

        $this->assertSame(0, HotelRequest::allTenants()->count());
    }

    public function test_an_unavailable_support_cannot_create_requests(): void
    {
        $this->fabriquer();
        [$b, $room, $stand, $svc] = $this->hotel('c-a', 'Hôtel Alpha', 0);
        $token = HotelPortalToken::issue($stand->public_id);
        app(HotelRoomSupportService::class)->revoke($b->id, $room->id);

        $this->post(route('tagtoa.hotel.request', $stand->public_id), ['service_id' => $svc->id, 'tk' => $token])->assertNotFound();

        $this->assertSame(0, HotelRequest::allTenants()->count());
    }

    public function test_a_note_is_cleaned_bounded_and_validated(): void
    {
        $this->fabriquer();
        [, , $stand, $svc] = $this->hotel('c-a', 'Hôtel Alpha', 0);

        $this->demander($stand, $svc, ['note' => str_repeat('a', 301)])->assertSessionHasErrors('note');
        [$req] = $this->creer($stand, $svc, ['note' => "  Bonjour\x00\x07 \n merci  "]);

        $this->assertSame("Bonjour \n merci", $req->note);
    }

    public function test_a_token_works_only_once(): void
    {
        $this->fabriquer();
        [$b, , $stand, $svc] = $this->hotel('c-a', 'Hôtel Alpha', 0);
        $autre = $this->service($b->id, 'linen', 'Serviettes');
        $tk = HotelPortalToken::issue($stand->public_id);
        $url = route('tagtoa.hotel.request', $stand->public_id);

        $this->post($url, ['service_id' => $svc->id, 'tk' => $tk])->assertRedirect();
        $this->post($url, ['service_id' => $autre->id, 'tk' => $tk])->assertSessionHas('guest_error');

        $this->assertSame(1, HotelRequest::allTenants()->count());
    }

    public function test_two_pages_loaded_in_the_same_second_get_different_tokens(): void
    {
        $this->assertNotSame(HotelPortalToken::issue('TG-000001', 1_800_000_000), HotelPortalToken::issue('TG-000001', 1_800_000_000));
    }

    public function test_creation_is_limited_per_support_and_per_hotel(): void
    {
        $this->fabriquer();
        [$b, , $stand, ] = $this->hotel('c-a', 'Hôtel Alpha', 0);
        $services = [];
        for ($i = 0; $i < HotelRequestRules::PER_STAND_HOURLY + 2; $i++) {
            $services[] = $this->service($b->id, 'linen', "Article $i");
        }
        // Les demandes sont clôturées au fur et à mesure : seul le plafond HORAIRE du support peut refuser.
        foreach (array_slice($services, 0, HotelRequestRules::PER_STAND_HOURLY) as $s) {
            [$r] = $this->creer($stand, $s);
            app(HotelRequestService::class)->setStatus($b->id, $r->id, 'done');
        }

        $this->demander($stand, $services[HotelRequestRules::PER_STAND_HOURLY])->assertSessionHas('guest_error');
        $this->assertSame(HotelRequestRules::PER_STAND_HOURLY, HotelRequest::allTenants()->count());
    }

    public function test_stale_unhandled_requests_stop_blocking_the_room(): void
    {
        $this->fabriquer();
        [, , $stand, $svc] = $this->hotel('c-a', 'Hôtel Alpha', 0);
        [$vieille] = $this->creer($stand, $svc);
        HotelRequest::allTenants()->whereKey($vieille->id)->update(['created_at' => now()->subHours(HotelRequestRules::OPEN_FRESH_HOURS + 1)]);

        $this->creer($stand, $svc);   // ni doublon ni plafond : la vieille ne compte plus

        $this->assertSame(2, HotelRequest::allTenants()->count());
    }

    public function test_the_hotel_closes_a_selection_in_one_go_but_only_its_own(): void
    {
        $this->fabriquer();
        [$a, , $standA, $svcA] = $this->hotel('c-a', 'Hôtel Alpha', 0);
        [$reqA] = $this->creer($standA, $svcA);
        [$b, , $standB, $svcB] = $this->hotel('c-b', 'Hôtel Bravo', 1);   // courant : Bravo
        [$reqB] = $this->creer($standB, $svcB);

        $this->post(route('tagtoa.hotel.requests.bulk'), ['ids' => [$reqA->id, $reqB->id]])->assertSessionHas('success');

        $this->assertSame('new', $reqA->fresh()->status, 'Une demande d\'un autre hôtel n\'est jamais touchée.');
        $this->assertSame('rejected', $reqB->fresh()->status);
        $this->post(route('tagtoa.hotel.requests.bulk'), ['ids' => []])->assertSessionHasErrors('ids');
    }

    public function test_a_cancel_on_a_closed_request_says_it_is_closed(): void
    {
        $this->fabriquer();
        [$b, , $stand, $svc] = $this->hotel('c-a', 'Hôtel Alpha', 0);
        [$req, $secret] = $this->creer($stand, $svc);
        app(HotelRequestService::class)->setStatus($b->id, $req->id, 'done');

        $this->post("/h/track/{$req->reference}/{$secret}/cancel")->assertSessionHas('guest_error', 'Cette demande est déjà clôturée.');
    }

    public function test_validation_errors_are_shown_to_the_guest(): void
    {
        $this->fabriquer();
        [, , $stand, ] = $this->hotel('c-a', 'Hôtel Alpha', 0);

        $this->from('/h/'.$stand->public_id)->post(route('tagtoa.hotel.request', $stand->public_id), ['service_id' => 'abc', 'tk' => 'x'])
            ->assertSessionHasErrors('service_id');
        $this->get('/h/'.$stand->public_id)->assertOk()->assertSee('class="err"', false);
    }

    public function test_the_guest_pages_say_no_referrer_themselves(): void
    {
        $this->fabriquer();
        [, , $stand, $svc] = $this->hotel('c-a', 'Hôtel Alpha', 0);
        [$req, $secret] = $this->creer($stand, $svc);

        foreach (['/h/'.$stand->public_id, "/h/track/{$req->reference}/{$secret}", '/h/TG-999999'] as $path) {
            $this->assertStringContainsString('<meta name="referrer" content="no-referrer">', $this->get($path)->getContent(), $path);
        }
    }

    public function test_the_purge_command_erases_guest_text_but_keeps_the_rows(): void
    {
        $this->fabriquer();
        [$b, , $stand, $svc] = $this->hotel('c-a', 'Hôtel Alpha', 0);
        $autre = $this->service($b->id, 'linen', 'Serviettes');
        [$close] = $this->creer($stand, $svc, ['note' => 'texte ancien']);
        [$recente] = $this->creer($stand, $autre, ['note' => 'texte récent']);
        app(HotelRequestService::class)->setStatus($b->id, $close->id, 'rejected', 'motif ancien');
        HotelRequest::allTenants()->whereKey($close->id)->update(['updated_at' => now()->subDays(HotelRequestRules::PURGE_DAYS + 1)]);

        $this->artisan('tagtoa:hotel:purge-requests')->assertSuccessful();

        $this->assertNull($close->fresh()->note);
        $this->assertNull($close->fresh()->reason);
        $this->assertSame('rejected', $close->fresh()->status);
        $this->assertSame('texte récent', $recente->fresh()->note, 'Une demande récente n\'est pas touchée.');

        // Une demande restée ouverte trop longtemps est aussi effacée.
        HotelRequest::allTenants()->whereKey($recente->id)->update(['created_at' => now()->subDays(HotelRequestRules::PURGE_DAYS * 2 + 1)]);
        $this->artisan('tagtoa:hotel:purge-requests')->assertSuccessful();
        $this->assertNull($recente->fresh()->note);
    }

    /* ---------- suivi ---------- */

    private function neutre(string $path): string
    {
        return $this->get($path)->assertNotFound()->getContent();
    }

    public function test_tracking_needs_the_reference_and_the_secret(): void
    {
        $this->fabriquer();
        [, , $stand, $svc] = $this->hotel('c-a', 'Hôtel Alpha', 0);
        [$req, $secret] = $this->creer($stand, $svc);

        $ok = $this->get("/h/track/{$req->reference}/{$secret}")->assertOk()->getContent();
        $this->assertStringContainsString('Nettoyage de la chambre', $ok);
        $this->assertStringContainsString($req->reference, $ok);
        $this->assertStringContainsString('Hôtel Alpha', $ok);

        $reference = $this->neutre('/h/track/HR-ZZZZZZZZ/'.str_repeat('0', 32));
        foreach (["/h/track/{$req->reference}/".str_repeat('0', 32), "/h/track/{$req->reference}/x", '/h/track/'.$req->reference.'/'.strtoupper($secret),
                  '/h/track/bad/bad', "/h/track/{$req->reference}/".str_repeat('a', 200)] as $path) {
            $this->assertSame($reference, $this->neutre($path), "Réponse différente pour $path");
        }
    }

    public function test_tracking_leaks_nothing_internal(): void
    {
        $this->fabriquer();
        [$b, , $stand, $svc] = $this->hotel('c-a', 'Hôtel Alpha', 0);
        [$req, $secret] = $this->creer($stand, $svc);

        $r = $this->get("/h/track/{$req->reference}/{$secret}")->assertOk();
        $html = $r->getContent();

        $this->assertStringNotContainsString($b->id, $html);
        $this->assertStringNotContainsString($req->track_hash, $html);
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $this->assertSame('no-referrer', $r->headers->get('Referrer-Policy'));
        $this->assertStringContainsString('noindex', $r->headers->get('X-Robots-Tag'));
    }

    public function test_a_guests_tracking_survives_a_support_replacement_but_expires_after_the_delay(): void
    {
        $this->fabriquer();
        [$b, $room, $stand, $svc] = $this->hotel('c-a', 'Hôtel Alpha', 0);
        [$req, $secret] = $this->creer($stand, $svc);

        app(HotelRoomSupportService::class)->revoke($b->id, $room->id);
        $this->get("/h/track/{$req->reference}/{$secret}")->assertOk();

        HotelRequest::allTenants()->whereKey($req->id)->update(['created_at' => now()->subDays(HotelRequestRules::TRACK_DAYS + 1)]);
        $this->get("/h/track/{$req->reference}/{$secret}")->assertNotFound();
    }

    public function test_the_guest_cancels_only_while_nothing_was_taken_into_account(): void
    {
        $this->fabriquer();
        [$b, , $stand, $svc] = $this->hotel('c-a', 'Hôtel Alpha', 0);
        [$req, $secret] = $this->creer($stand, $svc);
        $cancel = "/h/track/{$req->reference}/{$secret}/cancel";

        $this->post("/h/track/{$req->reference}/".str_repeat('0', 32).'/cancel')->assertNotFound();
        $this->assertSame('new', $req->fresh()->status);

        $this->post($cancel)->assertRedirect()->assertSessionHas('guest_ok');
        $this->assertSame('cancelled', $req->fresh()->status);

        // Une autre demande, prise en compte : plus annulable.
        $autre = $this->service($b->id, 'linen', 'Serviettes');
        [$req2, $secret2] = $this->creer($stand, $autre);
        app(HotelRequestService::class)->setStatus($b->id, $req2->id, 'acknowledged');

        $this->post("/h/track/{$req2->reference}/{$secret2}/cancel")->assertSessionHas('guest_error');
        $this->assertSame('acknowledged', $req2->fresh()->status);
    }

    public function test_a_cancelled_request_frees_the_service_for_a_new_one(): void
    {
        $this->fabriquer();
        [, , $stand, $svc] = $this->hotel('c-a', 'Hôtel Alpha', 0);
        [$req, $secret] = $this->creer($stand, $svc);
        $this->post("/h/track/{$req->reference}/{$secret}/cancel");

        $this->creer($stand, $svc);

        $this->assertSame(2, HotelRequest::allTenants()->count());
    }

    public function test_guest_text_is_escaped_everywhere(): void
    {
        $this->fabriquer();
        [$b, , $stand, $svc] = $this->hotel('c-a', 'Hôtel Alpha', 0);
        [$req, $secret] = $this->creer($stand, $svc, ['note' => '<script>alert(1)</script>']);

        $html = $this->get("/h/track/{$req->reference}/{$secret}")->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);

        $this->compte('c-a');   // retour sur l'hôtel A (le commerce courant reste le sien)
        Tenant::flush();
        $page = $this->get(route('tagtoa.hotel.requests'))->assertOk();
        $this->assertStringNotContainsString('<script>alert(1)', $page->getContent());
        $this->assertStringContainsString('&lt;script&gt;', $page->getContent());
    }

    /* ---------- côté hôtel ---------- */

    public function test_the_hotel_sees_only_its_own_requests_and_moves_them_along(): void
    {
        $this->fabriquer();
        [$a, , $standA, $svcA] = $this->hotel('c-a', 'Hôtel Alpha', 0);
        $this->creer($standA, $svcA, ['note' => 'Pour Alpha']);
        [$b, , $standB, $svcB] = $this->hotel('c-b', 'Hôtel Bravo', 1);   // l'hôtel courant est maintenant Bravo
        [$reqB] = $this->creer($standB, $svcB, ['note' => 'Pour Bravo']);

        $html = $this->get(route('tagtoa.hotel.requests'))->assertOk()->getContent();
        $this->assertStringContainsString('Pour Bravo', $html);
        $this->assertStringNotContainsString('Pour Alpha', $html);

        $this->post(route('tagtoa.hotel.requests.status', $reqB->id), ['status' => 'in_progress'])->assertSessionHas('success');
        $this->assertSame('in_progress', $reqB->fresh()->status);
        $this->assertNotNull($reqB->fresh()->handled_at);

        $this->post(route('tagtoa.hotel.requests.status', $reqB->id), ['status' => 'done'])->assertSessionHas('success');
        $this->assertNotNull($reqB->fresh()->closed_at);
        $this->assertTrue(AuditLog::where('action', 'hotel.request.done')->exists());
    }

    public function test_invalid_transitions_are_refused(): void
    {
        $this->fabriquer();
        [$b, , $stand, $svc] = $this->hotel('c-a', 'Hôtel Alpha', 0);
        [$req] = $this->creer($stand, $svc);

        $this->post(route('tagtoa.hotel.requests.status', $req->id), ['status' => 'done'])->assertSessionHas('success');
        $this->post(route('tagtoa.hotel.requests.status', $req->id), ['status' => 'new'])->assertSessionHas('error');
        $this->post(route('tagtoa.hotel.requests.status', $req->id), ['status' => 'in_progress'])->assertSessionHas('error');
        $this->post(route('tagtoa.hotel.requests.status', $req->id), ['status' => 'cancelled'])->assertSessionHas('error');
        $this->post(route('tagtoa.hotel.requests.status', $req->id), ['status' => 'bogus'])->assertSessionHasErrors('status');
        $this->assertSame('done', $req->fresh()->status);
    }

    public function test_a_rejection_reason_reaches_the_guest(): void
    {
        $this->fabriquer();
        [, , $stand, $svc] = $this->hotel('c-a', 'Hôtel Alpha', 0);
        [$req, $secret] = $this->creer($stand, $svc);

        $this->post(route('tagtoa.hotel.requests.status', $req->id), ['status' => 'rejected', 'reason' => 'Service indisponible ce soir']);

        $this->get("/h/track/{$req->reference}/{$secret}")->assertOk()->assertSee('Service indisponible ce soir');
    }

    public function test_another_hotel_cannot_touch_my_requests(): void
    {
        $this->fabriquer();
        [, , $standA, $svcA] = $this->hotel('c-a', 'Hôtel Alpha', 0);
        [$reqA] = $this->creer($standA, $svcA);
        $this->hotel('c-b', 'Hôtel Bravo', 1);   // courant : Bravo

        $this->post(route('tagtoa.hotel.requests.status', $reqA->id), ['status' => 'done'])->assertNotFound();

        $this->assertSame('new', $reqA->fresh()->status);
        $this->assertSame('not_found', app(HotelRequestService::class)->setStatus('autre-commerce', $reqA->id, 'done')['result']);
    }

    public function test_a_non_hotel_business_has_no_requests_screen(): void
    {
        $this->compte('c-r');
        app(BusinessService::class)->create(Tenant::account(), ['name' => 'Chez Rose', 'type' => 'restaurant', 'currency' => 'HTG']);

        $this->get(route('tagtoa.hotel.requests'))->assertRedirect(route('tagtoa.hub'));
    }

    public function test_the_migration_is_replayable_and_reversible(): void
    {
        $mig = require __DIR__.'/../../Database/migrations/2026_11_02_000191_create_tagtoa_hotel_requests_table.php';
        $mig->up();
        $mig->up();
        $this->assertTrue(Schema::hasTable('tagtoa_hotel_requests'));
        $mig->down();
        $this->assertFalse(Schema::hasTable('tagtoa_hotel_requests'));
        $mig->up();
        $this->assertTrue(Schema::hasTable('tagtoa_hotel_requests'));
    }
}
