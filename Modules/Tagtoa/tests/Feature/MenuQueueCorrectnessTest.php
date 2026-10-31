<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA MENU — CS-6 : la file de commandes hors ligne ne se bloque plus
|--------------------------------------------------------------------------
| Avant : retenterFile() avalait TOUTES les erreurs et laissait la commande en
| tête de file. Une commande refusée pour une raison définitive (commerce fermé,
| rupture, table invalide, informations client manquantes) était rejouée à
| l'infini toutes les 20 s, bloquait toutes les suivantes, et le client n'en
| savait rien. De plus, si le navigateur refusait d'écrire la file (stockage
| plein/bloqué), l'écran disait quand même « en attente de connexion ».
|
| Le JavaScript de la file est exécuté pour de vrai, sous node.
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\Tests\TestCase;

class MenuQueueCorrectnessTest extends TestCase
{
    use RefreshDatabase;

    private function html(): string
    {
        $menu = Menu::create(['tenant_id' => 't-1', 'name' => 'Chez Rose', 'alias' => 'chez-rose',
            'currency' => 'HTG', 'is_active' => true, 'ordering_enabled' => true]);
        $cat = Category::create(['menu_id' => $menu->id, 'name' => 'Plats', 'is_active' => true]);
        Item::create(['menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Griot', 'price' => 350, 'is_available' => true]);

        return $this->get('/menu/chez-rose')->assertOk()->getContent();
    }

    public function test_the_page_wires_the_tested_queue_and_no_longer_swallows_every_error(): void
    {
        $html = $this->html();

        $this->assertStringContainsString('/*MENUQ-START*/', $html);
        $this->assertStringContainsString('MenuQ.make(', $html);
        $this->assertStringNotContainsString('lireFile', $html, 'L\'ancienne file (qui avalait toutes les erreurs) a disparu.');
        $this->assertStringContainsString('if (!QUEUE.push(payload))', $html, 'Un échec d\'écriture du stockage doit être dit au client.');
        $this->assertStringContainsString('restoreCart();', $html);
        $this->assertStringContainsString('uuid:ORDER_UUID', $html, 'L\'identifiant de commande suit le panier restauré (pas de doublon après rechargement).');
        $this->assertStringContainsString('showQueued(shown)', $html, 'Le total affiché hors ligne est calculé AVANT de vider le panier.');
    }

    public function test_the_queue_logic_behaves_under_node(): void
    {
        if (trim((string) shell_exec('command -v node')) === '') {
            $this->markTestSkipped('node indisponible');
        }
        $this->assertSame(1, preg_match('#/\*MENUQ-START\*/(.*?)/\*MENUQ-END\*/#s', $this->html(), $m), 'Bloc MenuQ introuvable.');

        $file = tempnam(sys_get_temp_dir(), 'menuq').'.js';
        file_put_contents($file, $m[1].self::SCENARIOS);
        $out = [];
        exec('node '.escapeshellarg($file).' 2>&1', $out, $code);
        @unlink($file);

        $this->assertSame(0, $code, "node a échoué :\n".implode("\n", $out));
        $this->assertStringContainsString('MenuQ: all scenarios passed', implode("\n", $out));
    }

    private const SCENARIOS = <<<'JS'

const assert = require('assert');
function store(){ const d={}; return { getItem:k=>(k in d?d[k]:null), setItem:(k,v)=>{d[k]=String(v);}, raw:d }; }
function resp(status, body){ return Promise.resolve({ status, json:()=> body===undefined?Promise.reject(new Error('not json')):Promise.resolve(body) }); }
function mk(st, fetchFn, extra){ let t=1000000; const q = MenuQ.make(Object.assign({ storage:st, key:'q', rejKey:'rej', fetchFn, url:'/o', csrf:()=> 'tok',
  rejectedDefault:'refusée', giveUp:'abandon', now:()=>t }, extra||{})); q.advance=ms=>{t+=ms;}; return q; }
const order = (id)=>({ items:[{id:1,qty:1}], client_uuid:id });

(async () => {
  // 1) succès : la commande sort de la file
  { const st=store(), calls=[]; const q=mk(st,(u,o)=>{calls.push(JSON.parse(o.body)); return resp(200,{ok:true,reference:'R1'});});
    assert.strictEqual(q.push(order('a')), true); assert.strictEqual(await q.flush(), 'empty'); assert.strictEqual(q.pending().length,0); assert.strictEqual(calls.length,1); }

  // 2) refus DÉFINITIF : n'est plus rejoué, ne bloque pas la suivante, et le client en est informé
  { const st=store(), calls=[]; const q=mk(st,(u,o)=>{ const b=JSON.parse(o.body); calls.push(b.client_uuid);
      return b.client_uuid==='bad' ? resp(422,{ok:false,message:'Rupture de stock : Griot'}) : resp(200,{ok:true}); });
    q.push(order('bad')); q.push(order('good'));
    assert.strictEqual(await q.flush(), 'empty');
    assert.deepStrictEqual(calls, ['bad','good'], 'la commande suivante part malgré le refus');
    assert.strictEqual(q.pending().length,0);
    assert.strictEqual(q.rejected().length,1); assert.strictEqual(q.rejected()[0].message,'Rupture de stock : Griot');
    assert.strictEqual(q.rejected()[0].payload.client_uuid,'bad'); }

  // 3) réseau coupé : la file est gardée telle quelle, sans compter d'essai
  { const st=store(); const q=mk(st,()=>Promise.reject(new TypeError('Failed to fetch')));
    q.push(order('a')); assert.strictEqual(await q.flush(), 'offline'); assert.strictEqual(q.pending().length,1); assert.strictEqual(q.pending()[0]._tries, undefined); }

  // 4) erreur serveur (500, page HTML) : reprise espacée, puis abandon VISIBLE (jamais infini, jamais muet)
  { const st=store(); let n=0; const q=mk(st,()=>{n++; return resp(500, undefined);});
    q.push(order('a'));
    assert.strictEqual(await q.flush(), 'retry'); assert.strictEqual(n,1);
    assert.strictEqual(await q.flush(), 'wait', 'pas de nouvel essai avant le délai'); assert.strictEqual(n,1);
    for (let i=0;i<20 && q.pending().length;i++){ q.advance(10*60*1000); await q.flush(); }
    assert.strictEqual(q.pending().length,0,'abandon après les essais'); assert.strictEqual(n,8);
    assert.strictEqual(q.rejected().length,1); assert.strictEqual(q.rejected()[0].message,'abandon'); assert.strictEqual(q.rejected()[0].gaveUp,true,'abandon distinct d\'un refus'); }

  // 5) jeton CSRF expiré : un jeton frais est relu, puis la commande part
  { const st=store(); let tok='old', refreshed=0; const seen=[];
    const q=mk(st,(u,o)=>{ seen.push(o.headers['X-CSRF-TOKEN']); return o.headers['X-CSRF-TOKEN']==='old' ? resp(419,{message:'CSRF token mismatch.'}) : resp(200,{ok:true}); },
      { csrf:()=>tok, refreshCsrf:()=>{ refreshed++; tok='fresh'; return Promise.resolve(); } });
    q.push(order('a')); assert.strictEqual(await q.flush(), 'empty'); assert.deepStrictEqual(seen,['old','fresh']); assert.strictEqual(refreshed,1); assert.strictEqual(q.pending().length,0); }

  // 5b) jeton toujours refusé après relecture : compté comme un échec, jamais de boucle
  { const st=store(); let n=0; const q=mk(st,()=>{n++; return resp(419,{});}, { refreshCsrf:()=>Promise.resolve() });
    q.push(order('a')); assert.strictEqual(await q.flush(), 'retry'); assert.strictEqual(n,2); assert.strictEqual(q.pending()[0]._tries,1); }

  // 6) stockage refusé : push() le dit (l'écran ne doit PAS affirmer « en attente »)
  { const bad={ getItem:()=>null, setItem:()=>{ throw new Error('QuotaExceededError'); } }; const q=mk(bad,()=>resp(200,{ok:true}));
    assert.strictEqual(q.push(order('a')), false); }
  { const lying={ getItem:()=>null, setItem:()=>{} }; const q=mk(lying,()=>resp(200,{ok:true}));  // n'écrit rien, sans erreur
    assert.strictEqual(q.push(order('a')), false); }

  // 7) un seul envoi à la fois (minuteur + évènement « online » ne doublent pas la requête)
  { const st=store(); let n=0, release; const gate=new Promise(r=>release=r);
    const q=mk(st,()=>{n++; return gate.then(()=>resp(200,{ok:true}));});
    q.push(order('a')); const p1=q.flush(), p2=q.flush();
    assert.strictEqual(await p2,'busy'); release(); await p1; assert.strictEqual(n,1); }

  // 8) l'ancien format (objet de commande nu, sans compteurs) est toujours rejoué ; les champs internes ne partent jamais au serveur
  { const st=store(); st.setItem('q', JSON.stringify([{items:[{id:2,qty:3}],client_uuid:'old-1'}])); const bodies=[];
    const q=mk(st,(u,o)=>{bodies.push(JSON.parse(o.body)); return resp(200,{ok:true});}); await q.flush();
    assert.deepStrictEqual(bodies,[{items:[{id:2,qty:3}],client_uuid:'old-1'}]);
    const st2=store(); const seen=[]; const q2=mk(st2,(u,o)=>{seen.push(JSON.parse(o.body)); return seen.length===1?resp(500,undefined):resp(200,{ok:true});});
    q2.push(order('b')); await q2.flush(); q2.advance(10*60*1000); await q2.flush();
    assert.ok(!Object.keys(seen[1]).some(k=>k.charAt(0)==='_'), 'aucun champ interne envoyé'); assert.strictEqual(seen[1].client_uuid,'b', 'même client_uuid : jamais de doublon'); }

  // 9) « Réessayer » remet la commande refusée dans la file ; « Fermer » l'oublie
  { const st=store(); let allow=false; const q=mk(st,()=> allow?resp(200,{ok:true}):resp(409,{message:'Fermé'}));
    q.push(order('a')); await q.flush(); assert.strictEqual(q.rejected().length,1);
    allow=true; q.retryRejected(0); assert.strictEqual(q.rejected().length,0); assert.strictEqual(q.pending().length,1); await q.flush(); assert.strictEqual(q.pending().length,0);
    q.push(order('z')); const q2=mk(st,()=>resp(422,{message:'x'})); await q2.flush(); q2.dismiss(0); assert.strictEqual(q2.rejected().length,0); }

  // 10) classification
  { const C=MenuQ.classify;
    assert.strictEqual(C(200,{ok:true}),'ok'); assert.strictEqual(C(200,{ok:false}),'rejected'); assert.strictEqual(C(200,null),'retry','page de portail captif / proxy : on réessaie, on ne rejette pas');
    assert.strictEqual(C(422,{}),'rejected'); assert.strictEqual(C(409,{}),'rejected'); assert.strictEqual(C(419,{}),'csrf');
    assert.strictEqual(C(500,null),'retry'); assert.strictEqual(C(429,{}),'retry'); assert.strictEqual(C(502,null),'retry'); assert.strictEqual(C(0,null),'retry'); }

  // 11) une commande ajoutée PENDANT l'envoi n'est jamais écrasée par une copie périmée de la file (relevé à la revue, sévérité 6)
  { const st=store(); let release; const gate=new Promise(r=>release=r); const sent=[];
    const q=mk(st,(u,o)=>{ const id=JSON.parse(o.body).client_uuid; sent.push(id); return id==='first' ? gate.then(()=>resp(200,{ok:true})) : resp(200,{ok:true}); });
    q.push(order('first')); const p1=q.flush();
    while(!sent.length){ await new Promise(r=>setImmediate(r)); }   // l'envoi est RÉELLEMENT en vol
    q.push(order('second'));                       // le client commande pendant que le premier envoi est en vol
    release(); await p1;
    assert.deepStrictEqual(sent,['first','second'],'la commande ajoutée pendant l\'envoi est ENVOYÉE, pas perdue');
    assert.strictEqual(q.pending().length,0); }
  { const st=store(); let release, inflight=false; const gate=new Promise(r=>release=r);
    const q=mk(st,()=>{ inflight=true; return gate.then(()=>resp(500,undefined)); });       // échec : l'écriture du compteur d'essais ne doit pas non plus écraser
    q.push(order('first')); const p1=q.flush();
    while(!inflight){ await new Promise(r=>setImmediate(r)); }
    q.push(order('second')); release(); await p1;
    assert.deepStrictEqual(q.pending().map(o=>o.client_uuid),['first','second']); assert.strictEqual(q.pending()[0]._tries,1); }

  // 12) un délai maximal est posé sur chaque envoi (signal d'annulation) quand le navigateur le permet
  { const st=store(); let sig=null; const q=mk(st,(u,o)=>{ sig=o.signal; return resp(200,{ok:true}); }); q.push(order('a')); await q.flush();
    assert.ok(sig && typeof sig.aborted==='boolean','AbortSignal fourni'); }

  console.log('MenuQ: all scenarios passed');
})().catch(e=>{ console.error(e); process.exit(1); });
JS;
}
