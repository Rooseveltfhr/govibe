<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA POS — CS-1 : une vente ne peut pas être perdue ni mal étiquetée
|--------------------------------------------------------------------------
| Quatre défauts, vérifiés dans le code avant d'être corrigés :
|
|  1. flush() vidait toute la file dès que le serveur répondait un JSON, quel
|     qu'il soit : une vente REFUSÉE (stock épuisé ailleurs…) disparaissait de
|     l'appareil, marchandise déjà remise.
|  2. confirmSale() prenait n'importe quel JSON — un refus compris — pour une
|     vente terminée.
|  3. sync() ne validait rien et renvoyait getMessage() : SQL, noms de tables,
|     valeurs liées.
|  4. Une vente faite lundi hors ligne et reçue mercredi portait l'heure de
|     mercredi. Et la course entre deux requêtes identiques finissait en erreur
|     pour une vente pourtant enregistrée.
|
| Une vraie concurrence ne se reproduit pas sous PHPUnit/SQLite : la course est
| simulée en écrivant la vente concurrente ENTRE la recherche et l'écriture.
| Le côté caisse (JavaScript) est exécuté pour de vrai, sous `node`.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Tagtoa\App\Models\Audit\AuditLog;
use Modules\Tagtoa\App\Models\Pos\Product;
use Modules\Tagtoa\App\Models\Pos\Sale;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Services\Pos\PosCatalog;
use Modules\Tagtoa\App\Services\Pos\PosService;
use Modules\Tagtoa\Tests\TestCase;

/** PosService dont la recherche d'idempotence « rate » : une vente concurrente est écrite juste après. */
class RacingPosService extends PosService
{
    public int $lookups = 0;

    public function __construct(public array $competitor)
    {
        parent::__construct(app(\Modules\Tagtoa\App\Services\Billing\RevenueService::class), app(\Modules\Tagtoa\App\Services\Pos\PosCatalog::class));
    }

    protected function existingSale(Terminal $terminal, ?string $uuid): ?Sale
    {
        $this->lookups++;
        if ($this->lookups === 1) {
            // La requête concurrente a gagné la course : sa vente est écrite
            // APRÈS notre recherche, AVANT notre écriture.
            DB::table('tagtoa_pos_sales')->insert($this->competitor);

            return null;
        }

        return parent::existingSale($terminal, $uuid);
    }
}

class PosSyncCorrectnessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-1', 'name' => 'Roosevelt']));
    }

    private function caisse(string $tenant = 't-1'): Terminal
    {
        return Terminal::firstOrCreate(['tenant_id' => $tenant, 'name' => 'Caisse'], ['currency' => 'HTG', 'is_active' => true]);
    }

    private function article(Terminal $t, float $stock = 10): Product
    {
        return app(PosCatalog::class)->save($t, ['name' => 'Prestige', 'price' => 150, 'stock' => $stock, 'is_active' => true]);
    }

    private function vente(string $uuid, array $extra = []): array
    {
        return array_merge([
            'client_uuid' => $uuid,
            'items'       => [['name' => 'Divers', 'price' => 100, 'qty' => 1]],
        ], $extra);
    }

    private function sync(Terminal $t, array $sales)
    {
        return $this->postJson(route('tagtoa.pos.sync', $t->id), ['sales' => $sales]);
    }

    /* ---------- sync : validation et contrat de réponse ---------- */

    public function test_a_valid_batch_returns_one_ok_result_per_sale_in_order(): void
    {
        $t = $this->caisse();

        $res = $this->sync($t, [$this->vente('a'), $this->vente('b')])->assertOk()->json('results');

        $this->assertCount(2, $res);
        $this->assertSame(['a', 'b'], [$res[0]['client_uuid'], $res[1]['client_uuid']]);
        $this->assertTrue($res[0]['ok'] && $res[1]['ok']);
        $this->assertSame(2, Sale::count());
    }

    public function test_a_malformed_sale_is_refused_with_a_code_and_does_not_block_the_others(): void
    {
        $t = $this->caisse();

        $res = $this->sync($t, [
            $this->vente('bad', ['items' => 'pas-un-tableau']),
            $this->vente('neg', ['items' => [['name' => 'X', 'price' => -5, 'qty' => 1]]]),
            $this->vente('good'),
        ])->assertOk()->json('results');

        $this->assertFalse($res[0]['ok']);
        $this->assertSame('invalid', $res[0]['code']);
        $this->assertFalse($res[1]['ok']);
        $this->assertSame('invalid', $res[1]['code']);
        $this->assertTrue($res[2]['ok']);
        $this->assertSame(1, Sale::count());
    }

    public function test_a_sale_without_an_idempotency_key_is_refused_not_replayed_blindly(): void
    {
        $t = $this->caisse();

        $res = $this->sync($t, [['items' => [['name' => 'X', 'price' => 1, 'qty' => 1]]]])->assertOk()->json('results');

        $this->assertFalse($res[0]['ok']);
        $this->assertSame('invalid', $res[0]['code']);
        $this->assertSame(0, Sale::count());
    }

    public function test_the_batch_itself_must_be_a_bounded_non_empty_array(): void
    {
        $t = $this->caisse();

        $this->postJson(route('tagtoa.pos.sync', $t->id), ['sales' => 'x'])->assertStatus(422);
        $this->postJson(route('tagtoa.pos.sync', $t->id), ['sales' => []])->assertStatus(422);
        $this->postJson(route('tagtoa.pos.sync', $t->id), [])->assertStatus(422);
        $this->sync($t, array_map(fn ($i) => $this->vente("u$i"), range(1, 51)))->assertStatus(422);
    }

    public function test_a_stock_shortage_is_a_refusal_with_a_code_and_the_rest_still_syncs(): void
    {
        $t = $this->caisse();
        $a = $this->article($t, 1);
        $ligne = [['product_id' => $a->id, 'qty' => 1, 'name' => 'Prestige', 'price' => 150]];

        $res = $this->sync($t, [
            $this->vente('1', ['items' => $ligne]),
            $this->vente('2', ['items' => $ligne]),
            $this->vente('3'),
        ])->assertOk()->json('results');

        $this->assertTrue($res[0]['ok']);
        $this->assertFalse($res[1]['ok']);
        $this->assertSame('stock', $res[1]['code']);
        $this->assertStringContainsString('Prestige', $res[1]['message']);
        $this->assertStringContainsString('Prestige', $res[1]['error'], 'Ancien champ gardé pour les caisses déjà installées.');
        $this->assertTrue($res[2]['ok']);
    }

    public function test_an_unexpected_failure_never_returns_the_exception_text(): void
    {
        $t = $this->caisse();
        $this->app->bind(PosService::class, fn () => new class extends PosService {
            public function __construct() {}

            public function recordSale(Terminal $terminal, array $payload, ?\Modules\Tagtoa\App\Models\Staff\Staff $staff = null): Sale
            {
                throw new QueryException('mysql', 'insert into `tagtoa_pos_sales` (`secret_col`) values (?)', ['valeur-secrete'], new \PDOException('SQLSTATE[23000]: Integrity constraint violation'));
            }
        });

        $res = $this->sync($t, [$this->vente('boom')])->assertOk();
        $raw = $res->getContent();

        $this->assertFalse($res->json('results.0.ok'));
        $this->assertSame('error', $res->json('results.0.code'));
        $this->assertNotEmpty($res->json('results.0.ref'), 'Un numéro de suivi permet au support de retrouver l\'erreur.');
        foreach (['tagtoa_pos_sales', 'SQLSTATE', 'secret_col', 'valeur-secrete', 'Integrity'] as $leak) {
            $this->assertStringNotContainsString($leak, $raw, "Fuite : « $leak » dans la réponse.");
        }
    }

    public function test_the_register_of_another_merchant_cannot_be_synced_into(): void
    {
        $autre = $this->caisse('t-2');

        $this->sync($autre, [$this->vente('x')])->assertNotFound();
        $this->assertSame(0, Sale::count());
    }

    public function test_client_supplied_ownership_fields_are_ignored(): void
    {
        $t = $this->caisse();
        $autre = $this->caisse('t-2');

        $this->sync($t, [$this->vente('own', ['terminal_id' => $autre->id, 'tenant_id' => 't-2', 'status' => 9, 'reference' => 'TGP-FORGED'])])->assertOk();

        $sale = Sale::first();
        $this->assertSame($t->id, $sale->terminal_id);
        $this->assertSame(1, $sale->status);
        $this->assertNotSame('TGP-FORGED', $sale->reference);
    }

    /* ---------- idempotence ---------- */

    public function test_replaying_a_sale_returns_ok_with_the_same_reference_even_inside_one_batch(): void
    {
        $t = $this->caisse();

        $res = $this->sync($t, [$this->vente('same'), $this->vente('same')])->assertOk()->json('results');

        $this->assertTrue($res[0]['ok'] && $res[1]['ok']);
        $this->assertSame($res[0]['reference'], $res[1]['reference']);
        $this->assertSame(1, Sale::count());
    }

    public function test_a_concurrent_identical_request_that_lost_the_race_gets_the_existing_sale(): void
    {
        $t = $this->caisse();
        $now = now()->toDateTimeString();
        $service = new RacingPosService([
            'terminal_id' => $t->id, 'reference' => 'TGP-WINNER', 'subtotal' => 100, 'discount' => 0, 'total' => 100,
            'currency' => 'HTG', 'client_uuid' => 'race-1', 'status' => 1, 'sold_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);

        $sale = $service->recordSale($t, $this->vente('race-1'));

        $this->assertSame('TGP-WINNER', $sale->reference, 'La perdante rend la vente de la gagnante.');
        $this->assertSame(1, Sale::count(), 'Une seule vente, jamais deux.');
        $this->assertSame(2, $service->lookups);
    }

    public function test_the_race_loser_touches_no_stock(): void
    {
        $t = $this->caisse();
        $a = $this->article($t, 5);
        $now = now()->toDateTimeString();
        $service = new RacingPosService([
            'terminal_id' => $t->id, 'reference' => 'TGP-WINNER', 'subtotal' => 150, 'discount' => 0, 'total' => 150,
            'currency' => 'HTG', 'client_uuid' => 'race-2', 'status' => 1, 'sold_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);

        $service->recordSale($t, $this->vente('race-2', ['items' => [['product_id' => $a->id, 'qty' => 2, 'name' => 'Prestige', 'price' => 150]]]));

        $this->assertSame(5.0, (float) $a->fresh()->stock, 'La transaction perdante annule tout, stock compris.');
    }

    /* ---------- heure de la vente : originale, reçue, signalée ---------- */

    public function test_an_offline_sale_keeps_its_original_time_and_records_the_receipt_time_separately(): void
    {
        $t = $this->caisse();
        $lundi = now()->subHours(30)->startOfSecond();

        $this->sync($t, [$this->vente('old', ['client_created_at' => $lundi->toIso8601String()])])->assertOk();

        $sale = Sale::first();
        $this->assertSame($lundi->timestamp, $sale->sold_at->timestamp, 'L\'heure de la vente est celle de la caisse.');
        $this->assertGreaterThan($lundi->timestamp + 3600, $sale->created_at->timestamp, 'L\'heure de réception est séparée (created_at).');
        $this->assertSame(0, AuditLog::where('action', 'pos.sale_time_flagged')->count(), 'Dans la fenêtre : aucun drapeau.');
    }

    /**
     * `$claimed` est un DÉCALAGE en secondes (calculé AU MOMENT du test, jamais
     * à la collecte des données : une suite longue ferait glisser l'heure) ou
     * un texte tel quel.
     *
     * @dataProvider outsideTheWindow
     */
    public function test_a_time_outside_the_window_is_not_trusted_but_is_flagged_with_the_claimed_value(int|string $claimed, string $reason): void
    {
        $t = $this->caisse();
        $iso = is_int($claimed) ? now()->addSeconds($claimed)->toIso8601String() : $claimed;

        $this->sync($t, [$this->vente('flag', ['client_created_at' => $iso])])->assertOk();

        $sale = Sale::first();
        $this->assertLessThan(5, abs($sale->sold_at->timestamp - now()->timestamp), 'Hors fenêtre : heure du serveur.');
        $log = AuditLog::where('action', 'pos.sale_time_flagged')->first();
        $this->assertNotNull($log, 'L\'heure annoncée n\'est pas réécrite en silence : elle est journalisée.');
        $this->assertStringContainsString($reason, $log->description);
        $this->assertStringContainsString($sale->reference, $log->description);
        $this->assertStringContainsString(substr($iso, 0, 19), $log->description, 'La valeur annoncée est conservée dans le journal.');
    }

    public static function outsideTheWindow(): array
    {
        return [
            'older than 72 h'     => [-(72 * 3600 + 120), 'too_old'],
            'five minutes ahead+' => [5 * 60 + 60, 'future'],
            'next year'           => ['2099-01-01T00:00:00+00:00', 'future'],
            'unreadable'          => ['pas-une-date', 'invalid'],
        ];
    }

    public function test_the_window_edges_are_accepted(): void
    {
        $t = $this->caisse();
        $limite = now()->subHours(71)->subMinutes(55)->startOfSecond();
        $avance = now()->addMinutes(4)->startOfSecond();

        $this->sync($t, [
            $this->vente('edge-old', ['client_created_at' => $limite->toIso8601String()]),
            $this->vente('edge-new', ['client_created_at' => $avance->toIso8601String()]),
        ])->assertOk();

        $this->assertSame($limite->timestamp, Sale::where('client_uuid', 'edge-old')->first()->sold_at->timestamp);
        $this->assertSame($avance->timestamp, Sale::where('client_uuid', 'edge-new')->first()->sold_at->timestamp);
        $this->assertSame(0, AuditLog::where('action', 'pos.sale_time_flagged')->count());
    }

    public function test_an_old_register_that_sends_no_time_still_works_with_server_time_and_no_flag(): void
    {
        $t = $this->caisse();

        $this->sync($t, [$this->vente('legacy')])->assertOk();

        $this->assertLessThan(5, abs(Sale::first()->sold_at->timestamp - now()->timestamp));
        $this->assertSame(0, AuditLog::where('action', 'pos.sale_time_flagged')->count());
    }

    public function test_a_live_sale_ignores_any_client_time(): void
    {
        $t = $this->caisse();

        $this->postJson(route('tagtoa.pos.sale', $t->id), [
            'items' => [['name' => 'X', 'price' => 10, 'qty' => 1]], 'client_uuid' => 'live',
            'client_created_at' => '2001-01-01T00:00:00+00:00',
        ])->assertOk();

        $this->assertLessThan(5, abs(Sale::first()->sold_at->timestamp - now()->timestamp), 'En ligne, le serveur est la référence.');
        $this->assertSame(0, AuditLog::where('action', 'pos.sale_time_flagged')->count());
    }

    public function test_a_replayed_sale_is_not_flagged_twice(): void
    {
        $t = $this->caisse();
        $iso = now()->subDays(10)->toIso8601String();

        $this->sync($t, [$this->vente('dup', ['client_created_at' => $iso])]);
        $this->sync($t, [$this->vente('dup', ['client_created_at' => $iso])]);

        $this->assertSame(1, Sale::count());
        $this->assertSame(1, AuditLog::where('action', 'pos.sale_time_flagged')->count());
    }

    /* ---------- vente en ligne : refus lisibles ---------- */

    public function test_a_live_sale_with_a_validation_error_answers_422_json_when_json_is_requested(): void
    {
        $t = $this->caisse();

        $this->postJson(route('tagtoa.pos.sale', $t->id), ['items' => []])->assertStatus(422);
    }

    /* ---------- côté caisse : le vrai JavaScript, exécuté sous node ---------- */

    private function pageScript(): string
    {
        $t = $this->caisse();
        $html = $this->get(route('tagtoa.pos.register', $t->id))->assertOk()->getContent();
        $this->assertSame(1, preg_match('#/\*POSSYNC-START\*/(.*?)/\*POSSYNC-END\*/#s', $html, $m), 'Bloc PosSync introuvable dans la caisse.');

        return $m[1];
    }

    private function runNode(string $js): string
    {
        $file = tempnam(sys_get_temp_dir(), 'possync').'.js';
        file_put_contents($file, $js);
        exec('node '.escapeshellarg($file).' 2>&1', $out, $code);
        @unlink($file);
        $this->assertSame(0, $code, "node a échoué :\n".implode("\n", $out));

        return implode("\n", $out);
    }

    public function test_the_register_queue_never_removes_an_unacknowledged_sale(): void
    {
        if (trim((string) shell_exec('command -v node')) === '') {
            $this->markTestSkipped('node indisponible');
        }
        $lib = $this->pageScript();
        $scenarios = <<<'JS'
const assert = require('assert');
function storage(initial){ const m = {}; if (initial !== undefined) m.q = initial; return { m, getItem:k=>(k in m?m[k]:null), setItem:(k,v)=>{ if (storage.fail) throw new Error('quota'); m[k]=String(v);} }; }
function resp(status, body, bad){ return { ok: status>=200&&status<300, status, json: ()=> bad ? Promise.reject(new Error('not json')) : Promise.resolve(body) }; }
function q(st){ return JSON.parse(st.m.q||'[]'); }
function mk(st, fetchFn){ return PosSync.make({ storage:st, key:'q', fetchFn, url:'/sync', csrf:()=> 'tok', batch:50 }); }
const S = (u)=>({ client_uuid:u, items:[{name:'X',qty:1,price:1}] });
(async () => {
  // 1. only acknowledged sales leave the queue; refused ones stay, flagged, with the reason
  let st = storage(JSON.stringify([S('A'),S('B'),S('C')]));
  let calls = 0;
  let w = mk(st, async ()=>{ calls++; return resp(200,{results:[{client_uuid:'A',ok:true,reference:'R1'},{client_uuid:'B',ok:false,code:'stock',message:'plus de stock'},{client_uuid:'C',ok:true,reference:'R3'}]}); });
  assert.strictEqual(await w.flush(), 'synced');
  assert.deepStrictEqual(q(st).map(e=>e.client_uuid), ['B']);
  assert.strictEqual(q(st)[0].rejected.code, 'stock');
  assert.strictEqual(q(st)[0].rejected.message, 'plus de stock');
  // 2. a refused sale is NOT re-sent automatically, and nothing else is pending
  assert.strictEqual(await w.flush(), 'empty'); assert.strictEqual(calls, 1);
  assert.strictEqual(w.attention().length, 1); assert.strictEqual(w.pending().length, 0);
  // 3. retry puts it back in the normal queue
  w.retryRejected(); assert.strictEqual(w.pending().length, 1);
  // 4. every kind of non-acknowledgement leaves the queue untouched
  const base = JSON.stringify([S('A'),S('B')]);
  const cases = {
    'http 500':        async ()=> resp(500,{message:'boom'}),
    'http 419':        async ()=> resp(419,{message:'expired'}),
    'http 422':        async ()=> resp(422,{message:'bad'}),
    'not json':        async ()=> resp(200,null,true),
    'network error':   async ()=> { throw new Error('offline'); },
    'no results':      async ()=> resp(200,{}),
    'results not array': async ()=> resp(200,{results:'x'}),
    'ok for unknown uuid': async ()=> resp(200,{results:[{client_uuid:'ZZZ',ok:true}]}),
    'result without uuid': async ()=> resp(200,{results:[{ok:true}]}),
  };
  for (const [name, f] of Object.entries(cases)) {
    st = storage(base); w = mk(st, f);
    assert.strictEqual(await w.flush(), name==='ok for unknown uuid'||name==='result without uuid' ? 'synced' : 'failed', name);
    assert.deepStrictEqual(q(st).map(e=>e.client_uuid), ['A','B'], 'kept: '+name);
    assert.ok(q(st).every(e=>!e.rejected), 'not flagged: '+name);
  }
  // 5. a sale added while the request is in flight survives the acknowledgement
  st = storage(JSON.stringify([S('A')]));
  w = mk(st, async ()=>{ const a = q(st); a.push(S('NEW')); st.m.q = JSON.stringify(a); return resp(200,{results:[{client_uuid:'A',ok:true,reference:'R'}]}); });
  await w.flush(); assert.deepStrictEqual(q(st).map(e=>e.client_uuid), ['NEW']);
  // 6. single flight: a second flush while one is running does not send
  st = storage(JSON.stringify([S('A')])); calls = 0;
  let release; const gate = new Promise(r=>release=r);
  w = mk(st, async ()=>{ calls++; await gate; return resp(200,{results:[{client_uuid:'A',ok:true,reference:'R'}]}); });
  const f1 = w.flush(); const f2 = w.flush();
  assert.strictEqual(await f2, 'busy'); release(); assert.strictEqual(await f1, 'synced'); assert.strictEqual(calls, 1);
  // 7. a write failure is reported (never "saved"), and the queue is untouched
  st = storage(JSON.stringify([S('A')])); w = mk(st, async()=>resp(200,{results:[]}));
  storage.fail = true; assert.strictEqual(w.add(S('B')), false); storage.fail = false;
  assert.deepStrictEqual(q(st).map(e=>e.client_uuid), ['A']);
  assert.strictEqual(w.add(S('B')), true);
  // 8. an unreadable queue is set aside, not silently overwritten
  st = storage('{pas du json'); w = mk(st, async()=>resp(200,{results:[]}));
  assert.strictEqual(w.add(S('N')), true);
  assert.strictEqual(st.m['q.corrompue'], '{pas du json');
  assert.deepStrictEqual(q(st).map(e=>e.client_uuid), ['N']);
  // 9. batches are bounded
  st = storage(JSON.stringify(Array.from({length:120},(_,i)=>S('u'+i)))); let sent = 0;
  w = mk(st, async (u,o)=>{ sent = JSON.parse(o.body).sales.length; return resp(200,{results:[]}); });
  await w.flush(); assert.strictEqual(sent, 50);
  // 10. the request asks for JSON and carries the token
  let hdr; st = storage(JSON.stringify([S('A')])); w = mk(st, async (u,o)=>{ hdr = o.headers; return resp(200,{results:[]}); });
  await w.flush(); assert.strictEqual(hdr.Accept, 'application/json'); assert.strictEqual(hdr['X-CSRF-TOKEN'], 'tok');
  // 11. how a live answer is understood
  const C = PosSync.classify;
  assert.strictEqual(C(200,{ok:true}), 'ok');
  assert.strictEqual(C(409,{ok:false,error:'stock'}), 'refused');
  assert.strictEqual(C(422,{message:'x',errors:{}}), 'refused');
  for (const s of [500,502,503,419,401,403,404,302]) assert.strictEqual(C(s,{ok:false}), 'unknown', String(s));
  assert.strictEqual(C(200,{ok:false}), 'unknown'); assert.strictEqual(C(200,null), 'unknown'); assert.strictEqual(C(200,{}), 'unknown');
  const M = PosSync.refusalMessage;
  assert.strictEqual(M({error:'Stock insuffisant'},'f'), 'Stock insuffisant');
  assert.strictEqual(M({message:'The given data was invalid.',errors:{'items.0.qty':['qty requis']}},'f'), 'qty requis');
  assert.strictEqual(M({message:'seul message'},'f'), 'seul message');
  assert.strictEqual(M(null,'repli'), 'repli');
  console.log('PosSync: all scenarios passed');
})().catch(e => { console.error(e && e.stack || e); process.exit(1); });
JS;

        $out = $this->runNode($lib."\n".$scenarios);

        $this->assertStringContainsString('all scenarios passed', $out);
    }

    public function test_the_register_never_reports_a_refused_sale_as_done(): void
    {
        $t = $this->caisse();
        $html = $this->get(route('tagtoa.pos.register', $t->id))->assertOk()->getContent();

        // confirmSale() passe par classify() et n'appelle ok() que pour 'ok'.
        $this->assertMatchesRegularExpression("/var c=PosSync\.classify\(status,d\);\s*if\(c==='ok'\)\{ok\(d\.reference,p,d\);\}/", $html);
        $this->assertStringNotContainsString('.then(function(d){ok(d.reference,p,d);})', $html, 'L\'ancien chemin « tout JSON = vente faite » est de retour.');
        $this->assertStringNotContainsString('.then(function(){setQ([]);})', $html, 'L\'ancien « vider toute la file » est de retour.');
        $this->assertStringContainsString("'Accept':'application/json'", $html);
        $this->assertStringContainsString('client_created_at:new Date().toISOString()', $html);
    }

    public function test_the_register_scripts_parse_and_use_no_html_escaped_strings(): void
    {
        $t = $this->caisse();
        $html = $this->get(route('tagtoa.pos.register', $t->id))->assertOk()->getContent();
        preg_match_all('#<script(?![^>]*\bsrc=)[^>]*>(.*?)</script>#s', $html, $m);
        $this->assertNotEmpty($m[1]);

        foreach ($m[1] as $js) {
            $this->assertSame(0, preg_match('/[(,+=]\s*(&quot;|&#039;)/', $js), 'Chaîne de script échappée pour le HTML.');
        }
        if (trim((string) shell_exec('command -v node')) !== '') {
            foreach ($m[1] as $i => $js) {
                $f = tempnam(sys_get_temp_dir(), 'regjs').'.js';
                file_put_contents($f, $js);
                exec('node --check '.escapeshellarg($f).' 2>&1', $out, $code);
                @unlink($f);
                $this->assertSame(0, $code, "Script n°$i : ".implode("\n", $out));
                $out = [];
            }
        }
    }
}
