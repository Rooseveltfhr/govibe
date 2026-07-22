{{-- TAGTOA EVENT — terminal STAFF terrain (PIN, offline-first IndexedDB).
     Variables: $event, $staff (null si non connecté), $roster, $ticketTypes, $pendingConflicts --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $event->title }} — {{ __('Staff') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Space+Grotesk:wght@500;600;700&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    {{-- Scanner QR auto-hébergé (pas de CDN) : check-in par caméra même sans réseau tiers. --}}
    <script src="{{ route('tagtoa.asset', 'html5-qrcode.min.js') }}"></script>
    <style>
        :root{--green:#2cb809;--ink:#0d140c;--bg:#f5f9f2;--surf:#fff;--bd:rgba(13,20,12,.10);--mut:#5d6b5a;--red:#E0473E;--amber:#E08A1E;--fh:'Space Grotesk',sans-serif;--fb:'Nunito',sans-serif}
        .top h1,.res .msg,.okc h2{font-family:'Anton',sans-serif!important;font-weight:400!important;letter-spacing:.01em}
        *{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
        body{font-family:var(--fb);background:var(--bg);color:var(--ink);min-height:100vh}
        .top{background:var(--ink);color:#fff;padding:12px 16px;display:flex;align-items:center;gap:10px}
        .top h1{font:600 15px var(--fh);flex:1}
        .top .who{font-size:12px;opacity:.8}
        .wrap{max-width:520px;margin:0 auto;padding:16px}
        .card{background:var(--surf);border:1px solid var(--bd);border-radius:16px;padding:18px;margin-bottom:14px}
        label{display:block;font:600 12.5px var(--fh);color:var(--mut);margin:12px 0 6px}
        .inp,select{width:100%;padding:13px 14px;border:1.5px solid var(--bd);border-radius:12px;font:16px var(--fb);background:#fff;color:var(--ink)}
        .btn{width:100%;border:0;border-radius:14px;padding:15px;font:700 16px var(--fh);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:9px;margin-top:12px}
        .btn-p{background:var(--green);color:#fff}.btn-o{background:#fff;border:1.5px solid var(--bd);color:var(--ink)}
        .btn:disabled{opacity:.55}
        .tabs{display:flex;gap:8px;margin-bottom:14px}
        .tabs button{flex:1;padding:12px;border:1.5px solid var(--bd);background:#fff;border-radius:12px;font:700 14px var(--fh);cursor:pointer;color:var(--mut)}
        .tabs button.on{background:var(--ink);color:#fff;border-color:var(--ink)}
        .scr{display:none}.scr.on{display:block}
        .res{border-radius:14px;padding:18px;text-align:center;margin-bottom:14px;display:none}
        .res.show{display:block}
        .res.green{background:#eef9e8;border:1px solid var(--green)}
        .res.orange{background:#fff5e6;border:1px solid var(--amber)}
        .res.red{background:#fdecea;border:1px solid var(--red)}
        .res .msg{font:700 20px var(--fh)}
        .res .who{color:var(--mut);margin-top:4px}
        .badge{display:inline-flex;align-items:center;gap:6px;font:700 12px var(--fh);padding:5px 11px;border-radius:999px;background:#fff5e6;color:#7a5200}
        .flash{border-radius:12px;padding:12px 14px;margin-bottom:12px;font-size:14px;background:#fdecea;border:1px solid var(--red);color:#9a2820}
        .okc{background:#eef9e8;border:1px solid var(--green);border-radius:14px;padding:16px;text-align:center;margin-top:12px;display:none}
        .okc.show{display:block}
        .okc h2{font-size:20px}
        .okc .code{font:700 16px var(--fh);letter-spacing:.1em;margin-top:4px}
        .nfc{font-size:12.5px;color:var(--mut);margin-top:8px;text-align:center;min-height:16px}
        .logout{background:none;border:0;color:#fff;opacity:.7;font-size:13px;cursor:pointer}
    </style>
</head>
<body>
    <div class="top">
        <i class="fa-solid fa-id-badge" style="color:var(--green)"></i>
        <h1>{{ $event->title }}</h1>
        @if($staff)
            <span class="who">{{ $staff->name }} · {{ $staff->role }}</span>
            <form method="POST" action="{{ route('tagtoa.event.staff.logout', $event->alias) }}">@csrf<button class="logout"><i class="fa-solid fa-right-from-bracket"></i></button></form>
        @endif
    </div>
    <div class="wrap">
        @if(session('error'))<div class="flash">{{ session('error') }}</div>@endif

        @if(! $staff)
        {{-- ================= LOGIN PIN ================= --}}
        <div class="card">
            <label>{{ __('Qui êtes-vous ?') }}</label>
            <select id="who" name="staff_id" form="loginForm">
                @forelse($roster as $r)<option value="{{ $r->id }}">{{ $r->name }} ({{ $r->role }})</option>@empty<option value="">{{ __('Aucun staff — demandez à l\'organisateur') }}</option>@endforelse
            </select>
            <form id="loginForm" method="POST" action="{{ route('tagtoa.event.staff.login', $event->alias) }}">@csrf
                <input type="hidden" name="staff_id" id="staffIdMirror">
                <label>{{ __('Code PIN') }}</label>
                <input class="inp" name="pin" inputmode="numeric" pattern="[0-9]{4,6}" maxlength="6" placeholder="••••" autofocus style="text-align:center;font-size:24px;letter-spacing:.4em">
                <button class="btn btn-p"><i class="fa-solid fa-unlock"></i> {{ __('Se connecter') }}</button>
            </form>
        </div>
        <script>
            var whoSel=document.getElementById('who'),mirror=document.getElementById('staffIdMirror');
            function syncWho(){mirror.value=whoSel.value;} whoSel.addEventListener('change',syncWho); syncWho();
        </script>

        @else
        {{-- ================= PANNEAU (selon rôle) ================= --}}
        @php
            $canCheckin = \Modules\Tagtoa\App\Services\Event\StaffPinService::canAccess($staff->role, 'checkin');
            $canSell    = \Modules\Tagtoa\App\Services\Event\StaffPinService::canAccess($staff->role, 'sales');
            $isAdmin    = $staff->role === 'admin';
        @endphp

        <div class="tabs">
            @if($canCheckin)<button id="tabCk" class="on" onclick="showScr('ck')"><i class="fa-solid fa-door-open"></i> {{ __('Check-in') }}</button>@endif
            @if($canSell)<button id="tabSl" class="{{ $canCheckin ? '' : 'on' }}" onclick="showScr('sl')"><i class="fa-solid fa-id-card"></i> {{ __('Vente') }}</button>@endif
            @if($isAdmin)<button id="tabAd" onclick="showScr('ad')"><i class="fa-solid fa-gauge"></i> {{ __('Suivi') }}</button>@endif
        </div>

        @if($canCheckin)
        <div class="scr on" id="scrCk">
            <div class="res" id="ckRes"><div class="msg" id="ckMsg"></div><div class="who" id="ckWho"></div></div>
            <div class="card">
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <label style="margin:0">{{ __('Code billet / UID carte') }}</label>
                    <span class="badge" id="pendBadge" style="display:none"><i class="fa-solid fa-cloud-arrow-up"></i> <span id="pendN">0</span> {{ __('à synchroniser') }}</span>
                </div>
                <input class="inp" id="ckInput" autocomplete="off" placeholder="TCK... / 04:A2:..." style="margin-top:8px">
                <button class="btn btn-p" id="ckBtn" type="button"><i class="fa-solid fa-check"></i> {{ __('Valider l\'entrée') }}</button>
                <button class="btn btn-o" id="qrBtn" type="button"><i class="fa-solid fa-qrcode"></i> {{ __('Scanner QR (caméra)') }}</button>
                <button class="btn btn-o" id="nfcBtn" type="button"><i class="fa-solid fa-wifi"></i> {{ __('Lire le tag NFC') }}</button>
                <div id="ckReader" style="margin-top:10px;border-radius:12px;overflow:hidden;display:none"></div>
                <div class="nfc" id="nfcHint"></div>
            </div>
        </div>
        @endif

        @if($canSell)
        <div class="scr {{ $canCheckin ? '' : 'on' }}" id="scrSl">
            <div class="card">
                <label>{{ __('Nom du participant') }} *</label><input class="inp" id="slName" autocomplete="off">
                <label>{{ __('WhatsApp') }} *</label><input class="inp" id="slPhone" inputmode="tel" placeholder="+509…">
                <label>{{ __('E-mail (optionnel)') }}</label><input class="inp" id="slEmail" type="email">
                <label>{{ __('Type de billet') }}</label>
                <select id="slType"><option value="">{{ __('— Aucun —') }}</option>@foreach($ticketTypes as $tt)<option value="{{ $tt->id }}">{{ $tt->name }}</option>@endforeach</select>
                <label>{{ __('UID carte NFC (vide = e-billet QR)') }}</label><input class="inp" id="slUid" autocomplete="off" placeholder="04:A2:...">
                <label>{{ __('Crédit initial (wallet)') }}</label><input class="inp" id="slCredit" inputmode="decimal" placeholder="0">
                <button class="btn btn-p" id="slBtn" type="button"><i class="fa-solid fa-cart-shopping"></i> {{ __('Activer la carte / émettre le billet') }}</button>
                <div class="okc" id="slOk"><h2>{{ __('Billet émis') }}</h2><div class="code" id="slCode"></div><div style="color:var(--mut);font-size:13px" id="slWho"></div></div>
                <div class="flash" id="slErr" style="display:none;margin-top:12px"></div>
            </div>

            {{-- Retrait : lier un billet acheté EN LIGNE à une carte NFC physique --}}
            <div class="card">
                <label style="margin-top:0">{{ __('Retrait carte (billet acheté en ligne)') }}</label>
                <input class="inp" id="pkCode" autocomplete="off" placeholder="{{ __('Code billet (TCK…)') }}">
                <label>{{ __('UID carte NFC') }}</label>
                <input class="inp" id="pkUid" autocomplete="off" placeholder="04:A2:...">
                <button class="btn btn-o" id="pkBtn" type="button"><i class="fa-solid fa-link"></i> {{ __('Lier la carte au billet') }}</button>
                <div class="okc" id="pkOk"><h2>{{ __('Carte liée') }}</h2><div class="code" id="pkRes"></div></div>
                <div class="flash" id="pkErr" style="display:none;margin-top:12px"></div>
            </div>
        </div>
        @endif

        @if($isAdmin)
        <div class="scr" id="scrAd">
            <div class="card">
                <label style="margin-top:0">{{ __('Équipe active') }}</label>
                <div style="font:700 26px var(--fh)">{{ $roster->count() }}</div>
                <label>{{ __('Conflits non résolus') }}</label>
                <div style="font:700 26px var(--fh);color:{{ $pendingConflicts ? 'var(--amber)' : 'var(--green)' }}">{{ $pendingConflicts }}</div>
                <label>{{ __('En attente de sync (cet appareil)') }}</label>
                <div style="font:700 26px var(--fh)" id="adPend">0</div>
                <button class="btn btn-o" id="adSync" type="button"><i class="fa-solid fa-rotate"></i> {{ __('Forcer la synchronisation') }}</button>
            </div>
        </div>
        @endif

<script>
    var CSRF=document.querySelector('meta[name=csrf-token]').content;
    var URL_CK=@json(route('tagtoa.event.staff.checkin',$event->alias));
    var URL_SYNC=@json(route('tagtoa.event.staff.sync',$event->alias));
    var URL_SELL=@json(route('tagtoa.event.staff.sell',$event->alias));
    var URL_PICK=@json(route('tagtoa.event.staff.pickup',$event->alias));
    var T={off:@json(__('Hors-ligne : enregistré, sera synchronisé.')),err:@json(__('Réessayez.')),read:@json(__('Approchez le tag…')),nfcNo:@json(__('NFC non supporté sur cet appareil — saisissez l\'UID.')),need:@json(__('Nom et WhatsApp requis.')),qrNo:@json(__('Caméra/QR indisponible — saisissez le code.'))};

    function el(id){return document.getElementById(id);}
    function showScr(k){['Ck','Sl','Ad'].forEach(function(s){var scr=el('scr'+s),tab=el('tab'+s);if(scr){scr.classList.toggle('on',s.toLowerCase()===k);}if(tab){tab.classList.toggle('on',s.toLowerCase()===k);}});}
    function uuid(){return 'ck-'+Date.now().toString(36)+'-'+Math.random().toString(36).slice(2,10);}
    function post(url,payload){return fetch(url,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':CSRF},body:JSON.stringify(payload)}).then(function(r){return r.json();});}

    /* ---------- IndexedDB (file offline) ---------- */
    var DB=null,DBN='tagtoa-staff-{{ $event->id }}';
    function idb(){return new Promise(function(res,rej){if(DB)return res(DB);var q=indexedDB.open(DBN,1);
        q.onupgradeneeded=function(e){e.target.result.createObjectStore('checkins',{keyPath:'client_uuid'});};
        q.onsuccess=function(e){DB=e.target.result;res(DB);};q.onerror=rej;});}
    function qAdd(rec){return idb().then(function(db){return new Promise(function(res){db.transaction('checkins','readwrite').objectStore('checkins').put(rec).onsuccess=res;});}).then(updatePend);}
    function qAll(){return idb().then(function(db){return new Promise(function(res){var out=[];var c=db.transaction('checkins').objectStore('checkins').openCursor();
        c.onsuccess=function(e){var cur=e.target.result;if(cur){out.push(cur.value);cur.continue();}else{res(out);}};});});}
    function qDel(uuids){return idb().then(function(db){return new Promise(function(res){var tx=db.transaction('checkins','readwrite'),st=tx.objectStore('checkins');uuids.forEach(function(u){st.delete(u);});tx.oncomplete=res;});}).then(updatePend);}
    function updatePend(){qAll().then(function(all){var n=all.length;
        var b=el('pendBadge');if(b){b.style.display=n?'inline-flex':'none';var pn=el('pendN');if(pn){pn.textContent=n;}}
        var ap=el('adPend');if(ap){ap.textContent=n;}});}

    function syncNow(){qAll().then(function(all){if(!all.length)return;
        post(URL_SYNC,{records:all}).then(function(j){if(j&&j.ok){qDel(all.map(function(r){return r.client_uuid;}));}}).catch(function(){});});}
    window.addEventListener('online',syncNow);
    setInterval(syncNow,30000);
    updatePend();syncNow();

    /* ---------- Check-in ---------- */
    @if($canCheckin)
    function showRes(color,msg,who){var r=el('ckRes');r.className='res show '+color;el('ckMsg').textContent=msg;el('ckWho').textContent=who||'';}
    function doCheckin(){var v=el('ckInput').value.trim();if(!v)return;
        var isUid=v.indexOf(':')>-1||v.length>20;
        var payload={client_uuid:uuid()};payload[isUid?'uid':'code']=v;
        var btn=el('ckBtn');btn.disabled=true;
        post(URL_CK,payload).then(function(j){btn.disabled=false;
            showRes(j.color||'red',j.message||T.err,j.name||'');
            el('ckInput').value='';el('ckInput').focus();
        }).catch(function(){btn.disabled=false;
            // Hors-ligne : on stocke localement, l'entrée sera synchronisée.
            qAdd({client_uuid:payload.client_uuid,code:payload.code||null,uid:payload.uid||null,at:new Date().toISOString()});
            showRes('orange',T.off,'');el('ckInput').value='';el('ckInput').focus();});}
    el('ckBtn').addEventListener('click',doCheckin);
    el('ckInput').addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();doCheckin();}});
    el('nfcBtn').addEventListener('click',function(){
        if(!('NDEFReader' in window)){el('nfcHint').textContent=T.nfcNo;return;}
        try{var rd=new NDEFReader();el('nfcHint').textContent=T.read;
            rd.scan().then(function(){rd.onreading=function(e){var id=e.serialNumber||'';if(id){el('ckInput').value=id;el('nfcHint').textContent=id;doCheckin();}};})
            .catch(function(){el('nfcHint').textContent=T.nfcNo;});
        }catch(err){el('nfcHint').textContent=T.nfcNo;}});

    /* ---------- Scan QR par caméra (html5-qrcode auto-hébergé) ---------- */
    var _qr=null,_qrOn=false;
    function stopQr(){_qrOn=false;el('ckReader').style.display='none';
        if(_qr){try{_qr.stop().then(function(){try{_qr.clear();}catch(e){}}).catch(function(){});}catch(e){}}}
    el('qrBtn').addEventListener('click',function(){
        if(_qrOn){stopQr();return;}
        if(!window.Html5Qrcode){el('nfcHint').textContent=T.qrNo;return;}
        var rd=el('ckReader');rd.style.display='block';_qr=new Html5Qrcode('ckReader');
        _qr.start({facingMode:'environment'},{fps:10,qrbox:220},function(txt){
            el('ckInput').value=(txt||'').trim();stopQr();doCheckin();
        },function(){}).then(function(){_qrOn=true;}).catch(function(){el('nfcHint').textContent=T.qrNo;rd.style.display='none';});
    });
    @endif

    /* ---------- Vente ---------- */
    @if($canSell)
    el('slBtn').addEventListener('click',function(){
        var name=el('slName').value.trim(),phone=el('slPhone').value.trim();
        var errB=el('slErr');errB.style.display='none';
        if(!name||!phone){errB.textContent=T.need;errB.style.display='block';return;}
        var btn=el('slBtn');btn.disabled=true;
        post(URL_SELL,{name:name,phone:phone,email:el('slEmail').value.trim()||null,
            ticket_type_id:el('slType').value?Number(el('slType').value):null,
            uid:el('slUid').value.trim()||null,
            credit:el('slCredit').value.trim()?Number(el('slCredit').value):null})
        .then(function(j){btn.disabled=false;
            if(!j.ok){errB.textContent=j.message||T.err;errB.style.display='block';return;}
            el('slCode').textContent=j.code;el('slWho').textContent=j.name;el('slOk').classList.add('show');
            el('slName').value='';el('slPhone').value='';el('slEmail').value='';el('slUid').value='';el('slCredit').value='';el('slName').focus();
        }).catch(function(){btn.disabled=false;errB.textContent=T.err;errB.style.display='block';});
    });
    el('pkBtn').addEventListener('click',function(){
        var code=el('pkCode').value.trim(),uid=el('pkUid').value.trim();
        var errB=el('pkErr');errB.style.display='none';el('pkOk').classList.remove('show');
        if(!code||!uid){errB.textContent=T.err;errB.style.display='block';return;}
        var btn=el('pkBtn');btn.disabled=true;
        post(URL_PICK,{code:code,uid:uid}).then(function(j){btn.disabled=false;
            if(!j.ok){errB.textContent=j.message||T.err;errB.style.display='block';return;}
            el('pkRes').textContent=j.code+' — '+(j.name||'');el('pkOk').classList.add('show');
            el('pkCode').value='';el('pkUid').value='';
        }).catch(function(){btn.disabled=false;errB.textContent=T.err;errB.style.display='block';});
    });
    @endif

    @if($isAdmin)
    el('adSync').addEventListener('click',syncNow);
    @endif
</script>
        @endif
    </div>
</body>
</html>
