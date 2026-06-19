{{-- TAGTOA EVENT — Scanner de check-in (PWA).
     Offline-first (IndexedDB-lite via localStorage), sons Web Audio, vibration.
     Variables : $event, $stats. Standalone HTML (pas de @extends). --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Scanner') }} — {{ $event->title }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        :root{--blk:#0A0A0A;--blue:#0055FF;--green:#1D9E75;--red:#E0473E;--orange:#E08A1E;--fh:'Space Grotesk',sans-serif;--fb:'Nunito',sans-serif}
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:var(--fb);background:var(--blk);color:#fff;min-height:100vh}
        .top{padding:16px 18px;display:flex;align-items:center;gap:12px;border-bottom:1px solid rgba(255,255,255,.1)}
        .top h1{font-family:var(--fh);font-size:16px;flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .top .net{font-size:12px;padding:4px 9px;border-radius:999px;background:rgba(255,255,255,.12)}
        .top .net.off{background:var(--orange)}
        .stats{display:flex;gap:10px;padding:14px 18px}
        .stat{flex:1;background:rgba(255,255,255,.07);border-radius:12px;padding:12px;text-align:center}
        .stat b{font-family:var(--fh);font-size:22px;display:block}.stat span{font-size:11px;opacity:.6}
        #reader{margin:8px 18px;border-radius:16px;overflow:hidden}
        .toggle{display:flex;gap:8px;padding:0 18px 10px}
        .toggle button{flex:1;background:rgba(255,255,255,.08);color:#fff;border:0;border-radius:10px;padding:11px;font:600 13px var(--fh);cursor:pointer}
        .toggle button.on{background:var(--blue)}
        .manual{padding:0 18px 14px;display:flex;gap:8px}
        .manual input{flex:1;background:rgba(255,255,255,.08);border:0;border-radius:10px;padding:12px;color:#fff;font:15px var(--fb)}
        .manual button{background:#fff;color:var(--blk);border:0;border-radius:10px;padding:0 16px;font:600 14px var(--fh)}
        .result{position:fixed;left:0;right:0;bottom:0;padding:26px 20px 34px;border-radius:24px 24px 0 0;transform:translateY(110%);transition:transform .25s cubic-bezier(.4,0,.2,1);text-align:center}
        .result.show{transform:translateY(0)}
        .result.green{background:var(--green)}.result.red{background:var(--red)}.result.orange{background:var(--orange)}
        .result i{font-size:46px}.result h2{font-family:var(--fh);font-size:22px;margin:8px 0 2px}.result p{opacity:.9;font-size:14px}
        .pending{padding:0 18px;font-size:12px;opacity:.6}
        @media (prefers-reduced-motion:reduce){*{transition:none!important}}
    </style>
</head>
<body data-event="{{ $event->id }}">
    <div class="top">
        <i class="fa-solid fa-qrcode" style="color:var(--blue)"></i>
        <h1>{{ $event->title }}</h1>
        <span class="net" id="net">●</span>
    </div>
    <div class="stats">
        <div class="stat"><b id="s-in">{{ $stats['checked_in'] }}</b><span>{{ __('Entrés') }}</span></div>
        <div class="stat"><b>{{ $stats['tickets'] }}</b><span>{{ __('Billets') }}</span></div>
        <div class="stat"><b id="s-pending">0</b><span>{{ __('À sync') }}</span></div>
    </div>
    <div class="toggle">
        <button id="dir-in" class="on" onclick="setDir('in')"><i class="fa-solid fa-right-to-bracket"></i> {{ __('Entrée') }}</button>
        <button id="dir-out" onclick="setDir('out')"><i class="fa-solid fa-right-from-bracket"></i> {{ __('Sortie') }}</button>
    </div>
    <div id="reader"></div>
    <div class="manual">
        <input id="m-code" placeholder="{{ __('Code billet (manuel)') }}">
        <button onclick="manual()">{{ __('OK') }}</button>
    </div>
    <p class="pending" id="pending-txt"></p>

    <div class="result" id="result"><i id="r-ic"></i><h2 id="r-msg"></h2><p id="r-sub"></p></div>

<script>
var EVENT = document.body.dataset.event;
var DIR = 'in';
var SCAN_URL = "{{ route('tagtoa.event.dashboard.scan', $event->id) }}";
var SYNC_URL = "{{ route('tagtoa.event.dashboard.sync', $event->id) }}";
var CSRF = document.querySelector('meta[name=csrf-token]').content;
var QUEUE_KEY = 'tagtoa_ev_queue_' + EVENT;
var lastScan = 0;

function setDir(d){ DIR=d; document.getElementById('dir-in').classList.toggle('on',d==='in'); document.getElementById('dir-out').classList.toggle('on',d==='out'); }
function uuid(){ return 'xxxxxxxxyxxxx'.replace(/[xy]/g,function(c){var r=Math.random()*16|0;return (c==='x'?r:(r&0x3|0x8)).toString(16);})+Date.now(); }

/* ---- Web Audio sons (pas de fichiers) ---- */
var actx;
function beep(type){
    try{ actx=actx||new (window.AudioContext||window.webkitAudioContext)();
        var o=actx.createOscillator(),g=actx.createGain();o.connect(g);g.connect(actx.destination);
        var f={success:[880,1320],error:[200,160],warning:[440,440]}[type]||[600];
        o.frequency.value=f[0];o.type='sine';g.gain.value=.12;o.start();
        if(f[1]){setTimeout(function(){o.frequency.value=f[1];},90);}
        setTimeout(function(){o.stop();},type==='error'?260:170);
    }catch(e){}
}
function vibrate(type){ if(navigator.vibrate){navigator.vibrate(type==='success'?80:type==='error'?[60,40,60]:[40]);} }

function showResult(r){
    var el=document.getElementById('result');
    el.className='result show '+(r.color||'red');
    document.getElementById('r-ic').className='fa-solid '+(r.valid?'fa-circle-check':(r.color==='orange'?'fa-triangle-exclamation':'fa-circle-xmark'));
    document.getElementById('r-msg').textContent=r.message||'';
    document.getElementById('r-sub').textContent=r.ticket?((r.ticket.holder||'')+' · '+(r.ticket.type||'')):'';
    beep(r.sound); vibrate(r.sound);
    if(r.valid && DIR==='in'){ var s=document.getElementById('s-in'); s.textContent=parseInt(s.textContent||0)+1; }
    setTimeout(function(){el.classList.remove('show');}, 1800);
}

function setNet(){ var on=navigator.onLine; document.getElementById('net').textContent=on?'● online':'● offline'; document.getElementById('net').classList.toggle('off',!on); if(on) flush(); }
window.addEventListener('online',setNet); window.addEventListener('offline',setNet);

function queue(){ return JSON.parse(localStorage.getItem(QUEUE_KEY)||'[]'); }
function setQueue(q){ localStorage.setItem(QUEUE_KEY,JSON.stringify(q)); var n=q.length; document.getElementById('s-pending').textContent=n; document.getElementById('pending-txt').textContent=n?(n+' {{ __('scan(s) en attente de synchronisation') }}'):''; }

function handle(code){
    var now=Date.now(); if(now-lastScan<1200) return; lastScan=now;
    var payload={code:code,direction:DIR,method:'qr',client_uuid:uuid()};
    if(navigator.onLine){
        fetch(SCAN_URL,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},body:JSON.stringify(payload)})
            .then(function(r){return r.json();}).then(showResult)
            .catch(function(){ enqueue(payload); showResult({valid:true,color:'orange',sound:'warning',message:'{{ __('Hors-ligne — mis en file') }}'}); });
    } else { enqueue(payload); showResult({valid:true,color:'orange',sound:'warning',message:'{{ __('Hors-ligne — mis en file') }}'}); }
}
function enqueue(p){ var q=queue(); q.push(p); setQueue(q); }
function flush(){
    var q=queue(); if(!q.length) return;
    fetch(SYNC_URL,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},body:JSON.stringify({scans:q})})
        .then(function(r){return r.json();}).then(function(){ setQueue([]); }).catch(function(){});
}
function manual(){ var c=document.getElementById('m-code').value.trim(); if(c){handle(c);document.getElementById('m-code').value='';} }

/* ---- QR camera ---- */
window.addEventListener('load',function(){
    setNet(); setQueue(queue());
    if(window.Html5Qrcode){
        var q=new Html5Qrcode('reader');
        q.start({facingMode:'environment'},{fps:10,qrbox:230},handle,function(){}).catch(function(){
            document.getElementById('reader').innerHTML='<p style="padding:20px;opacity:.6;text-align:center">{{ __('Caméra indisponible — utilisez la saisie manuelle.') }}</p>';
        });
    }
});
</script>
</body>
</html>
