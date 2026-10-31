<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA EVENT — CS-2 : la reprise des scans hors ligne ne casse plus, ne perd plus
|--------------------------------------------------------------------------
| Défauts vérifiés dans le code avant d'être corrigés :
|
|  1. StaffTerminalController::sync() lisait `->checked_in` sur un TABLEAU (le
|     résultat de CheckinService::r()). En PHP 8 c'est un avertissement, que
|     Laravel transforme en exception : HTTP 500 pour TOUT le lot dès qu'un scan
|     était un doublon ou un billet annulé. Le terminal rejouait ce lot toutes
|     les 30 s. Et la journalisation du conflit n'était jamais atteinte.
|  2. L'heure du scan envoyée par l'appareil était ignorée (scanned_at = now()).
|  3. Un code ou une carte NFC non reconnu était sauté en silence (`continue`).
|  4. Le terminal vidait toute sa file dès que `ok` était vrai.
|  5. check() lisait le nom du participant sur un tableau : toujours null.
|
| Le côté terminal (JavaScript) est exécuté pour de vrai sous `node`.
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Tagtoa\App\Models\Event\Checkin;
use Modules\Tagtoa\App\Models\Event\Event;
use Modules\Tagtoa\App\Models\Event\Staff;
use Modules\Tagtoa\App\Models\Event\SyncConflict;
use Modules\Tagtoa\App\Models\Event\Ticket;
use Modules\Tagtoa\App\Services\Event\CheckinService;
use Modules\Tagtoa\Tests\TestCase;

/** Compte les « entrée confirmée » (WhatsApp/email), sans rien envoyer. */
class NotifySpyCheckin extends CheckinService
{
    public int $notified = 0;

    protected function notifyEntry(Event $event, Ticket $ticket): void
    {
        $this->notified++;
    }
}

class EventStaffSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function event(string $alias = 'gala', array $extra = []): Event
    {
        return Event::create(array_merge([
            'tenant_id' => 't-1', 'title' => 'Gala', 'alias' => $alias, 'currency' => 'HTG', 'is_published' => true,
            'starts_at' => now()->subDay()->toDateTimeString(), 'ends_at' => now()->addDay()->toDateTimeString(),
        ], $extra));
    }

    private function ticket(Event $e, string $code, int $status = Ticket::STATUS_VALID): Ticket
    {
        return Ticket::create(['event_id' => $e->id, 'code' => $code, 'holder_name' => 'Jean '.$code, 'status' => $status]);
    }

    private function staff(Event $e, string $role = 'checkin'): Staff
    {
        return Staff::create(['event_id' => $e->id, 'name' => 'Agent', 'pin_hash' => bcrypt('1234'), 'role' => $role, 'active' => true]);
    }

    private function sync(Event $e, ?Staff $staff, array $records)
    {
        $t = $this->withSession($staff ? ['tagtoa_ev_staff.'.$e->id => ['id' => $staff->id, 'name' => $staff->name, 'role' => $staff->role]] : []);

        return $t->postJson(route('tagtoa.event.staff.sync', $e->alias), ['records' => $records]);
    }

    private function rec(string $uuid, array $extra = []): array
    {
        return array_merge(['client_uuid' => $uuid, 'at' => now()->toIso8601String()], $extra);
    }

    /* ---------- le crash : un doublon ne casse plus le lot ---------- */

    public function test_a_batch_with_a_duplicate_a_void_ticket_and_a_valid_one_answers_200_with_one_result_each(): void
    {
        $e = $this->event();
        $staff = $this->staff($e);
        $this->ticket($e, 'TOK');
        $this->ticket($e, 'TVOID', Ticket::STATUS_VOID);
        $dup = $this->ticket($e, 'TDUP');
        app(CheckinService::class)->processScan($e, 'TDUP'); // déjà entré aujourd'hui

        $res = $this->sync($e, $staff, [
            $this->rec('u1', ['code' => 'TOK']),
            $this->rec('u2', ['code' => 'TDUP']),
            $this->rec('u3', ['code' => 'TVOID']),
        ])->assertOk()->json();

        $by = collect($res['results'])->keyBy('client_uuid');
        $this->assertSame('admitted', $by['u1']['status']);
        $this->assertSame('refused', $by['u2']['status']);
        $this->assertSame('duplicate_today', $by['u2']['reason']);
        $this->assertSame('refused', $by['u3']['status']);
        $this->assertSame('void', $by['u3']['reason']);
        $this->assertTrue($by['u1']['ack'] && $by['u2']['ack'] && $by['u3']['ack']);
        $this->assertTrue($res['ok']);
        $this->assertSame(1, $res['synced']);
    }

    public function test_the_conflict_logic_is_finally_reached_first_wins_second_is_logged(): void
    {
        $e = $this->event();
        $staff = $this->staff($e);
        $t = $this->ticket($e, 'TWIN');

        $this->sync($e, $staff, [$this->rec('a', ['code' => 'TWIN'])])->assertOk();
        // Un 2e appareil, resté hors ligne, rejoue SA copie du même scan.
        $this->sync($e, $staff, [$this->rec('b', ['code' => 'TWIN'])])->assertOk();

        $this->assertSame(1, Checkin::where('ticket_id', $t->id)->count(), 'Le premier gagne.');
        $c = SyncConflict::where('event_id', $e->id)->where('kind', 'duplicate_checkin')->first();
        $this->assertNotNull($c, 'Le second est journalisé.');
        $this->assertSame($t->id, $c->ticket_id);
        $this->assertSame('b', $c->client_uuid);
    }

    public function test_a_refused_scan_is_logged_once_even_if_replayed(): void
    {
        $e = $this->event();
        $staff = $this->staff($e);
        $this->ticket($e, 'TVOID', Ticket::STATUS_VOID);

        $this->sync($e, $staff, [$this->rec('v1', ['code' => 'TVOID'])])->assertOk();
        $this->sync($e, $staff, [$this->rec('v1', ['code' => 'TVOID'])])->assertOk();

        $this->assertSame(1, SyncConflict::where('kind', 'refused_scan')->count());
    }

    /* ---------- plus de scan perdu sans trace ---------- */

    public function test_an_unknown_code_or_nfc_card_is_logged_not_silently_skipped(): void
    {
        $e = $this->event();
        $staff = $this->staff($e);

        $res = $this->sync($e, $staff, [
            $this->rec('x1', ['code' => 'CODE-QUI-N-EXISTE-PAS']),
            $this->rec('x2', ['uid' => '04:AA:BB:CC:DD:EE:FF']),
        ])->assertOk()->json();

        $by = collect($res['results'])->keyBy('client_uuid');
        $this->assertSame('refused', $by['x1']['status']);
        $this->assertSame('not_found', $by['x1']['reason']);
        $this->assertSame('unresolved', $by['x2']['status']);
        $this->assertTrue($by['x2']['ack'], 'Consigné durablement : le terminal peut le retirer de sa file.');
        $this->assertSame(1, SyncConflict::where('kind', 'unresolved_scan')->where('client_uuid', 'x2')->count());
        $this->assertSame(1, SyncConflict::where('kind', 'refused_scan')->where('client_uuid', 'x1')->count());
    }

    public function test_an_unexpected_failure_keeps_the_scan_unacknowledged_and_leaks_nothing(): void
    {
        $e = $this->event();
        $staff = $this->staff($e);
        $this->ticket($e, 'TBOOM');
        $this->app->bind(CheckinService::class, fn () => new class extends CheckinService {
            public function __construct() {}

            public function processScan(Event $event, string $code, string $direction = 'in', string $method = 'qr', ?string $gate = null, ?string $clientUuid = null, ?int $staffId = null, ?\Carbon\Carbon $scannedAt = null): array
            {
                throw new \Illuminate\Database\QueryException('mysql', 'insert into `tagtoa_ev_checkins` (`secret`) values (?)', ['valeur-secrete'], new \PDOException('SQLSTATE[23000]'));
            }
        });

        $res = $this->sync($e, $staff, [$this->rec('boom', ['code' => 'TBOOM'])])->assertOk();
        $raw = $res->getContent();

        $this->assertSame('error', $res->json('results.0.status'));
        $this->assertFalse($res->json('results.0.ack'));
        $this->assertFalse($res->json('ok'), 'Un terminal déjà installé, qui vide sa file sur ok, ne doit rien perdre.');
        $this->assertNotEmpty($res->json('results.0.ref'));
        foreach (['tagtoa_ev_checkins', 'SQLSTATE', 'valeur-secrete', 'secret'] as $leak) {
            $this->assertStringNotContainsString($leak, $raw, "Fuite : « $leak ».");
        }
    }

    public function test_a_replayed_uuid_is_acknowledged_without_a_second_entry(): void
    {
        $e = $this->event();
        $staff = $this->staff($e);
        $t = $this->ticket($e, 'TREP');

        $this->sync($e, $staff, [$this->rec('same', ['code' => 'TREP'])])->assertOk();
        $res = $this->sync($e, $staff, [$this->rec('same', ['code' => 'TREP'])])->assertOk();

        $this->assertSame('duplicate_replay', $res->json('results.0.status'));
        $this->assertTrue($res->json('results.0.ack'));
        $this->assertSame(1, Checkin::where('ticket_id', $t->id)->count());
    }

    public function test_a_batch_beyond_the_cap_is_never_reported_fully_ok(): void
    {
        $e = $this->event();
        $staff = $this->staff($e);
        $records = [];
        for ($i = 1; $i <= 201; $i++) {
            $records[] = $this->rec("c$i", ['code' => "NOPE$i"]);
        }

        $res = $this->sync($e, $staff, $records)->assertOk();

        $this->assertCount(200, $res->json('results'), 'Le lot est borné à 200.');
        $this->assertFalse($res->json('ok'), 'ok=false : un terminal ancien qui vide sa file sur ok garderait le 201e.');
    }

    /* ---------- l'heure du scan ---------- */

    public function test_the_device_time_is_kept_as_scanned_at_inside_the_window(): void
    {
        $e = $this->event();
        $staff = $this->staff($e);
        $this->ticket($e, 'TTIME');
        $hier = now()->subHours(20)->startOfSecond();

        $this->sync($e, $staff, [$this->rec('t1', ['code' => 'TTIME', 'at' => $hier->toIso8601String()])])->assertOk();

        $c = Checkin::first();
        $this->assertSame($hier->timestamp, $c->scanned_at->timestamp, 'L\'heure du scan est celle de l\'appareil.');
        $this->assertGreaterThan($hier->timestamp + 3600, $c->created_at->timestamp, 'L\'heure de réception reste created_at.');
        $this->assertSame(0, SyncConflict::where('kind', 'time_flagged')->count());
    }

    /** @dataProvider outsideTheWindow */
    public function test_a_time_outside_the_window_uses_server_time_and_is_logged_with_the_claimed_value(int|string $claimed, string $reason): void
    {
        $e = $this->event();
        $staff = $this->staff($e);
        $this->ticket($e, 'TFLAG');
        $iso = is_int($claimed) ? now()->addSeconds($claimed)->toIso8601String() : $claimed;

        $this->sync($e, $staff, [$this->rec('f1', ['code' => 'TFLAG', 'at' => $iso])])->assertOk();

        $this->assertLessThan(5, abs(Checkin::first()->scanned_at->timestamp - now()->timestamp), 'Hors fenêtre : heure du serveur.');
        $c = SyncConflict::where('kind', 'time_flagged')->first();
        $this->assertNotNull($c, 'L\'heure annoncée n\'est pas réécrite en silence : elle est journalisée.');
        $this->assertSame($reason, $c->payload['detail']['reason']);
        $this->assertSame(substr($iso, 0, 40), $c->payload['detail']['claimed']);
    }

    public static function outsideTheWindow(): array
    {
        return [
            'older than 72 h' => [-(72 * 3600 + 120), 'too_old'],
            'future'          => [5 * 60 + 60, 'future'],
            'unreadable'      => ['pas-une-date', 'invalid'],
        ];
    }

    public function test_the_scan_day_decides_already_entered_for_a_multi_day_pass(): void
    {
        $e = $this->event('festival', ['starts_at' => '2026-08-14 18:00:00', 'ends_at' => '2026-08-15 23:00:00']);
        $staff = $this->staff($e);
        $t = $this->ticket($e, 'TMULTI');
        Carbon::setTestNow('2026-08-15 10:00:00');

        // Deux scans faits hors ligne le jour 1 (l'appareil dit samedi 14), rejoués le jour 2.
        $res = $this->sync($e, $staff, [
            $this->rec('m1', ['code' => 'TMULTI', 'at' => '2026-08-14 19:00:00']),
            $this->rec('m2', ['code' => 'TMULTI', 'at' => '2026-08-14 19:05:00']),
        ])->assertOk()->json('results');

        $this->assertSame('admitted', $res[0]['status']);
        $this->assertSame('refused', $res[1]['status'], 'Même jour (14) : double entrée, même si on est le 15 au serveur.');
        $this->assertSame('duplicate_today', $res[1]['reason']);
        $this->assertSame('2026-08-14 19:00:00', Checkin::first()->scanned_at->format('Y-m-d H:i:s'));
    }

    public function test_a_live_scan_still_uses_now(): void
    {
        $e = $this->event();
        $this->ticket($e, 'TLIVE');
        Carbon::setTestNow('2026-03-01 12:00:00');

        app(CheckinService::class)->processScan($e, 'TLIVE');

        $this->assertSame('2026-03-01 12:00:00', Checkin::first()->scanned_at->format('Y-m-d H:i:s'));
    }


    /* ---------- relevé à la revue : l'horloge de l'appareil ne choisit pas le jour ---------- */

    public function test_a_ticket_already_admitted_today_cannot_be_admitted_again_with_a_backdated_time(): void
    {
        $e = $this->event(); // jours d'événement : hier, aujourd'hui, demain
        $staff = $this->staff($e);
        $t = $this->ticket($e, 'TREUSE');
        app(CheckinService::class)->processScan($e, 'TREUSE'); // entrée réelle, maintenant

        // Même personne, ou complice : nouvelle clé, heure d'HIER.
        $res = $this->sync($e, $staff, [
            $this->rec('bd1', ['code' => 'TREUSE', 'at' => now()->subDay()->toIso8601String()]),
            // Toujours HIER, à toute heure du jour (subHours(30) tombait avant-hier, hors des jours de l'événement, avant 6 h).
            $this->rec('bd2', ['code' => 'TREUSE', 'at' => now()->subDay()->startOfDay()->addMinutes(30)->toIso8601String()]),
        ])->assertOk()->json('results');

        $this->assertSame('refused', $res[0]['status']);
        $this->assertSame('backdated_reentry', $res[0]['reason']);
        $this->assertSame('refused', $res[1]['status']);
        $this->assertSame(1, Checkin::where('ticket_id', $t->id)->count(), 'Une seule entrée : celle d\'aujourd\'hui.');
        $this->assertSame(2, SyncConflict::where('kind', 'refused_scan')->count(), 'Les tentatives sont consignées.');
    }

    public function test_a_genuine_offline_scan_from_earlier_today_or_yesterday_is_still_admitted(): void
    {
        $e = $this->event();
        $staff = $this->staff($e);
        $this->ticket($e, 'TREEL');

        $res = $this->sync($e, $staff, [$this->rec('g1', ['code' => 'TREEL', 'at' => now()->subHours(3)->toIso8601String()])])->assertOk()->json('results');

        $this->assertSame('admitted', $res[0]['status'], 'Le correctif ne doit pas casser la reprise légitime.');
    }

    public function test_a_scan_time_on_a_day_the_event_does_not_run_is_not_trusted_and_is_flagged(): void
    {
        $e = $this->event('futur', ['starts_at' => now()->addDays(10)->toDateTimeString(), 'ends_at' => now()->addDays(11)->toDateTimeString()]);
        $staff = $this->staff($e);
        $this->ticket($e, 'TOUT');
        $iso = now()->subHour()->toIso8601String();

        $this->sync($e, $staff, [$this->rec('o1', ['code' => 'TOUT', 'at' => $iso])])->assertOk();

        $this->assertLessThan(5, abs(Checkin::first()->scanned_at->timestamp - now()->timestamp), 'Heure du serveur.');
        $c = SyncConflict::where('kind', 'time_flagged')->first();
        $this->assertNotNull($c);
        $this->assertSame('outside_event', $c->payload['detail']['reason']);
    }

    public function test_a_late_replay_is_recorded_but_does_not_notify_the_holder(): void
    {
        $e = $this->event();
        $this->ticket($e, 'TLATE');
        $this->ticket($e, 'TFRESH');
        $this->ticket($e, 'TLIVE2');
        $svc = app()->make(NotifySpyCheckin::class);

        $svc->processScan($e, 'TLATE', 'in', 'qr', null, 'l1', null, now()->subHours(3));
        $this->assertSame(0, $svc->notified, 'Scan vieux de 3 h : consigné, pas notifié.');

        $svc->processScan($e, 'TFRESH', 'in', 'qr', null, 'l2', null, now()->subMinutes(2));
        $this->assertSame(1, $svc->notified, 'Scan de moins de 15 min : notifié.');

        $svc->processScan($e, 'TLIVE2');
        $this->assertSame(2, $svc->notified, 'Scan en direct : notifié, comme avant.');
    }

    public function test_client_strings_stored_in_conflicts_are_capped_and_nested_values_dropped(): void
    {
        $e = $this->event();
        $staff = $this->staff($e);

        $res = $this->sync($e, $staff, [
            $this->rec('big1', ['code' => str_repeat('A', 5000)]),
            $this->rec('big2', ['uid' => str_repeat('B', 5000)]),
            $this->rec('big3', ['code' => ['nested' => 'x'], 'at' => str_repeat('9', 5000)]),
        ])->assertOk()->json('results');

        $this->assertCount(3, $res);
        foreach (SyncConflict::all() as $c) {
            $r = $c->payload['record'];
            $this->assertLessThanOrEqual(64, mb_strlen((string) ($r['code'] ?? '')));
            $this->assertLessThanOrEqual(120, mb_strlen((string) ($r['uid'] ?? '')));
            $this->assertLessThanOrEqual(40, mb_strlen((string) ($r['at'] ?? '')));
        }
        $this->assertLessThan(2000, strlen(json_encode(SyncConflict::all()->pluck('payload'))), 'Aucune chaîne de plusieurs Ko ne se retrouve en base.');
    }

    public function test_a_device_time_with_a_utc_offset_is_stored_as_the_same_instant(): void
    {
        $e = $this->event('festival2', ['starts_at' => '2026-08-14 18:00:00', 'ends_at' => '2026-08-15 23:00:00']);
        $staff = $this->staff($e);
        $this->ticket($e, 'TOFF');
        Carbon::setTestNow('2026-08-15 02:00:00'); // UTC

        // 22:00 à Port-au-Prince (UTC−4) le 14 = 02:00 UTC le 15 : le MÊME instant.
        $this->sync($e, $staff, [$this->rec('z1', ['code' => 'TOFF', 'at' => '2026-08-14T22:00:00-04:00'])])->assertOk();

        $this->assertSame('2026-08-15 02:00:00', Checkin::first()->scanned_at->format('Y-m-d H:i:s'), 'Pas de décalage de 4 h.');
        $this->assertSame(0, SyncConflict::where('kind', 'time_flagged')->count());
    }

    /* ---------- accès et périmètre ---------- */

    public function test_without_a_staff_session_the_sync_is_refused(): void
    {
        $e = $this->event();
        $this->ticket($e, 'TX');

        $this->sync($e, null, [$this->rec('a', ['code' => 'TX'])])->assertStatus(401);
        $this->assertSame(0, Checkin::count());
    }

    public function test_a_staff_role_without_the_checkin_screen_is_refused(): void
    {
        $e = $this->event();
        $staff = $this->staff($e, 'vente');
        $this->ticket($e, 'TX');

        $this->sync($e, $staff, [$this->rec('a', ['code' => 'TX'])])->assertStatus(401);
    }

    public function test_a_ticket_of_another_event_is_not_admitted_through_this_terminal(): void
    {
        $mine = $this->event('mine');
        $other = $this->event('other');
        $staff = $this->staff($mine);
        $this->ticket($other, 'TOTHER');

        $res = $this->sync($mine, $staff, [$this->rec('o1', ['code' => 'TOTHER'])])->assertOk();

        $this->assertSame('refused', $res->json('results.0.status'));
        $this->assertSame('not_found', $res->json('results.0.reason'));
        $this->assertSame(0, Checkin::count());
    }

    public function test_the_single_checkin_endpoint_now_returns_the_attendee_name(): void
    {
        $e = $this->event();
        $staff = $this->staff($e);
        $this->ticket($e, 'TNAME');

        $res = $this->withSession(['tagtoa_ev_staff.'.$e->id => ['id' => $staff->id, 'name' => 'Agent', 'role' => 'checkin']])
            ->postJson(route('tagtoa.event.staff.checkin', $e->alias), ['code' => 'TNAME', 'client_uuid' => 'c1'])->assertOk();

        $this->assertSame('Jean TNAME', $res->json('name'));
        $this->assertTrue($res->json('valid'));
    }

    /* ---------- côté terminal : le vrai JavaScript ---------- */

    public function test_the_terminal_only_removes_acknowledged_scans_from_its_queue(): void
    {
        $e = $this->event();
        $staff = $this->staff($e);
        $html = $this->withSession(['tagtoa_ev_staff.'.$e->id => ['id' => $staff->id, 'name' => 'Agent', 'role' => 'checkin']])
            ->get(route('tagtoa.event.staff.terminal', $e->alias))->assertOk()->getContent();

        // L'ancien « tout vider sur ok » ne doit pas revenir.
        $this->assertStringNotContainsString('if(j&&j.ok){qDel(all.map(', $html);
        $this->assertStringContainsString('r.ack===true', $html);

        if (trim((string) shell_exec('command -v node')) === '') {
            $this->markTestSkipped('node indisponible (le contrôle de texte ci-dessus a tourné)');
        }
        // On extrait la décision d'acquittement et on l'exécute sur des réponses réelles.
        $this->assertSame(1, preg_match('#post\(URL_SYNC,\{records:all\}\)\.then\(function\(j\)\{(.*?)\}\)\.catch#s', $html, $m));
        $body = $m[1];
        $js = <<<JS
const assert = require('assert');
let deleted = null;
function qDel(a){ deleted = a; }
function run(j){ deleted = null; (function(j){ $body })(j); return deleted; }
assert.deepStrictEqual(run({ok:true,results:[{client_uuid:'a',ack:true},{client_uuid:'b',ack:false},{client_uuid:'c',ack:true}]}), ['a','c']);
assert.strictEqual(run({ok:true}), null, 'sans results : rien ne part');
assert.strictEqual(run({ok:true,results:'x'}), null);
assert.strictEqual(run(null), null);
assert.deepStrictEqual(run({results:[{client_uuid:'a',ack:'yes'},{ack:true},{client_uuid:'z',ack:true}]}), ['z'], 'seul ack===true avec un uuid compte');
console.log('terminal ack ok');
JS;
        $f = tempnam(sys_get_temp_dir(), 'evack').'.js';
        file_put_contents($f, $js);
        exec('node '.escapeshellarg($f).' 2>&1', $out, $code);
        @unlink($f);
        $this->assertSame(0, $code, implode("\n", $out));
        $this->assertStringContainsString('terminal ack ok', implode("\n", $out));
    }

    public function test_the_conflict_list_shows_the_new_kinds_with_readable_labels(): void
    {
        // Le tableau de bord organisateur : les nouveaux types ne s'affichent plus bruts.
        $e = $this->event();
        $staff = $this->staff($e);
        $this->ticket($e, 'TV', Ticket::STATUS_VOID);
        $this->sync($e, $staff, [$this->rec('z1', ['code' => 'TV']), $this->rec('z2', ['uid' => '04:11:22'])])->assertOk();

        $this->be(new \Illuminate\Auth\GenericUser(['id' => 1, 'tenant_id' => 't-1', 'name' => 'Patron']));
        $html = $this->get(route('tagtoa.event.dashboard.staff', $e->id))->assertOk()->getContent();

        $this->assertStringContainsString('Scan refusé', $html);
        $this->assertStringContainsString('Code ou carte non reconnu', $html);
        $this->assertStringNotContainsString('unresolved_scan</td>', $html);
    }
}
