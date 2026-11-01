<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA EVENT — l'écran de scan du tableau de bord n'efface plus sa file à l'aveugle (CS-14, EV-6)
|--------------------------------------------------------------------------
| flush() vidait TOUTE la file dès qu'une réponse JSON arrivait : un scan en
| échec, différé ou sans clé disparaissait sans trace. Le serveur renvoie
| maintenant `ack` par scan et l'écran ne retire que les scans acquittés.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Event\Event;
use Modules\Tagtoa\App\Services\Event\CheckinService;
use Modules\Tagtoa\Tests\TestCase;

class EventScannerSyncAckTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-1', 'name' => 'Rose']));
        $this->event = Event::create(['tenant_id' => 't-1', 'title' => 'Gala', 'alias' => 'gala', 'currency' => 'HTG', 'is_published' => true,
            'starts_at' => now()->subHour()->toDateTimeString(), 'ends_at' => now()->addDay()->toDateTimeString()]);
    }

    private function sync(array $scans)
    {
        return $this->postJson(route('tagtoa.event.dashboard.sync', $this->event->id), ['scans' => $scans]);
    }

    public function test_every_processed_scan_is_acknowledged_even_when_refused(): void
    {
        $res = $this->sync([['code' => 'INCONNU', 'direction' => 'in', 'method' => 'qr', 'client_uuid' => 'A1']])
            ->assertOk()->json('results.0');

        $this->assertSame('A1', $res['client_uuid']);
        $this->assertTrue($res['ack'], 'Un refus ferme (billet inconnu) est un verdict : rejouer ne changerait rien.');
        $this->assertSame('refused', $res['status']);
        $this->assertFalse($res['result']['valid']);
    }

    public function test_an_unexpected_failure_is_not_acknowledged_and_does_not_break_the_batch(): void
    {
        $vrai = app(CheckinService::class);
        $faux = \Mockery::mock(CheckinService::class, [app(\Modules\Tagtoa\App\Services\Notifications\NotificationService::class), app(\Modules\Tagtoa\App\Services\Event\EventNotifier::class)])->makePartial();
        $faux->shouldReceive('processScan')->andReturnUsing(function ($event, $code, ...$rest) use ($vrai) {
            if ($code === 'BOOM') {
                throw new \Illuminate\Database\QueryException('mysql', 'insert into `tagtoa_ev_checkins` (`secret_col`) values (?)', ['valeur-secrete'], new \PDOException('SQLSTATE[23000]'));
            }

            return $vrai->processScan($event, $code, ...$rest);
        });
        $this->app->instance(CheckinService::class, $faux);

        $res = $this->sync([
            ['code' => 'BOOM', 'client_uuid' => 'B1'],
            ['code' => 'INCONNU', 'client_uuid' => 'B2'],
        ])->assertOk();
        $raw = $res->getContent();
        $r = $res->json('results');

        $this->assertFalse($r[0]['ack']);
        $this->assertSame('error', $r[0]['status']);
        $this->assertNotEmpty($r[0]['ref']);
        $this->assertTrue($r[1]['ack'], 'Le reste du lot est traité.');
        foreach (['tagtoa_ev_checkins', 'SQLSTATE', 'secret_col', 'valeur-secrete'] as $leak) {
            $this->assertStringNotContainsString($leak, $raw, "Fuite : « $leak » dans la réponse.");
        }
    }

    public function test_a_scan_without_a_key_is_not_acknowledged(): void
    {
        $r = $this->sync([['code' => 'X'], 'pas-un-tableau'])->assertOk()->json('results');

        $this->assertFalse($r[0]['ack']);
        $this->assertSame('invalid', $r[0]['status']);
        $this->assertFalse($r[1]['ack']);
    }

    public function test_scans_beyond_the_batch_cap_are_deferred_not_dropped_nor_acknowledged(): void
    {
        $scans = [];
        for ($i = 1; $i <= CheckinService::SYNC_BATCH_MAX + 3; $i++) {
            $scans[] = ['code' => 'INCONNU', 'client_uuid' => 'S'.$i];
        }

        $r = $this->sync($scans)->assertOk()->json('results');

        $this->assertCount(CheckinService::SYNC_BATCH_MAX + 3, $r);
        $this->assertTrue($r[CheckinService::SYNC_BATCH_MAX - 1]['ack']);
        $this->assertFalse($r[CheckinService::SYNC_BATCH_MAX]['ack']);
        $this->assertSame('deferred', $r[CheckinService::SYNC_BATCH_MAX]['status']);
    }

    /* ---------- le code de l'écran ---------- */

    private function evq(): string
    {
        $html = $this->get(route('tagtoa.event.dashboard.scanner', $this->event->id))->assertOk()->getContent();
        $this->assertSame(1, preg_match('#/\*EVQ-START\*/(.*?)/\*EVQ-END\*/#s', $html, $m), 'Bloc EVQ introuvable.');

        return $m[1];
    }

    public function test_the_screen_no_longer_empties_its_queue_on_any_json_answer(): void
    {
        $html = $this->get(route('tagtoa.event.dashboard.scanner', $this->event->id))->assertOk()->getContent();

        $this->assertStringNotContainsString('setQ([])', $html);
        $this->assertStringContainsString('setQ(evqRemaining(q(),j&&j.results))', $html);
        $this->assertStringContainsString('if(!r.ok){throw new Error(\'http\');}', $html, 'Un statut HTTP d\'erreur ne vide jamais la file.');
        $this->assertStringContainsString('if(flushing)return;', $html, 'Un seul envoi à la fois.');
    }

    public function test_the_queue_logic_behaves_under_node(): void
    {
        if (trim((string) shell_exec('command -v node')) === '') {
            $this->markTestSkipped('node indisponible');
        }
        $js = $this->evq();
        $file = tempnam(sys_get_temp_dir(), 'evq').'.js';
        file_put_contents($file, $js.<<<'JS'

const assert = require('assert');
const Q = [{client_uuid:'a'},{client_uuid:'b'},{client_uuid:'c'},{client_uuid:'d'}];
// acquittés : a ; échec : b ; différé : c ; d absent de la réponse
let r = evqRemaining(Q, [{client_uuid:'a',ack:true},{client_uuid:'b',ack:false},{client_uuid:'c',ack:false,status:'deferred'}]);
assert.deepStrictEqual(r.map(x=>x.client_uuid), ['b','c','d']);
// réponse illisible ou sans results : RIEN n'est retiré
assert.strictEqual(evqRemaining(Q, undefined).length, 4);
assert.strictEqual(evqRemaining(Q, null).length, 4);
assert.strictEqual(evqRemaining(Q, {ok:true}).length, 4);
// seul ack === true acquitte (pas "true", pas 1)
assert.strictEqual(evqRemaining(Q, [{client_uuid:'a',ack:'true'},{client_uuid:'b',ack:1}]).length, 4);
// un scan ajouté PENDANT l'envoi reste (la file est relue au retour)
const apres = Q.concat([{client_uuid:'nouveau'}]);
assert.deepStrictEqual(evqRemaining(apres, [{client_uuid:'a',ack:true}]).map(x=>x.client_uuid), ['b','c','d','nouveau']);
// uuid inconnu du serveur ou file corrompue : sans effet, sans exception
assert.strictEqual(evqRemaining(Q, [{client_uuid:'zzz',ack:true}]).length, 4);
assert.deepStrictEqual(evqRemaining(null, []), []);
console.log('EVQ OK');
JS);
        $out = []; $code = 0;
        exec('node '.escapeshellarg($file).' 2>&1', $out, $code);
        @unlink($file);

        $this->assertSame(0, $code, implode("\n", $out));
        $this->assertStringContainsString('EVQ OK', implode("\n", $out));
    }
}
