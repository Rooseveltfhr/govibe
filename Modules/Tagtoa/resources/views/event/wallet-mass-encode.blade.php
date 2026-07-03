{{-- TAGTOA EVENT — encodage en masse (jour de vente). Tap NFC successif rapide → billet + wallet.
     Variables: $event, $ticketTypes. Standalone (hors layout dashboard). --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $event->title }} — {{ __('Encodage en masse') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Space+Grotesk:wght@500;600;700&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root{--green:#2cb809;--ink:#0d140c;--bg:#f5f9f2;--surf:#fff;--bd:rgba(13,20,12,.10);--mut:#5d6b5a;--red:#E0473E;--fh:'Space Grotesk',sans-serif;--fb:'Nunito',sans-serif}
        .top h1,.count b,.list .nm{font-family:'Anton',sans-serif!important;font-weight:400!important;letter-spacing:.01em}
        *{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
        body{font-family:var(--fb);background:var(--bg);color:var(--ink);min-height:100vh}
        .top{background:var(--ink);color:#fff;padding:12px 18px;display:flex;align-items:center;gap:10px}
        .top h1{font:600 15px var(--fh);flex:1}
        .top a{color:#fff;opacity:.75;font-size:12.5px;text-decoration:none}
        .wrap{max-width:560px;margin:0 auto;padding:16px}
        .card{background:var(--surf);border:1px solid var(--bd);border-radius:16px;padding:18px;margin-bottom:14px}
        .card h2{font:600 13px var(--fh);color:var(--mut);text-transform:uppercase;letter-spacing:.05em;margin-bottom:12px}
        label{display:block;font:600 12.5px var(--fh);color:var(--mut);margin:0 0 6px}
        .inp,select{width:100%;padding:13px 14px;border:1.5px solid var(--bd);border-radius:12px;font:16px var(--fb);background:#fff;color:var(--ink)}
        .row{display:flex;gap:10px}.row>*{flex:1}
        .mb{margin-bottom:12px}
        .btn{width:100%;border:0;border-radius:14px;padding:15px;font:700 16px var(--fh);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:9px;margin-top:12px}
        .btn-p{background:var(--green);color:#fff}.btn-o{background:#fff;border:1.5px solid var(--bd);color:var(--ink)}
        .btn:disabled{opacity:.55}
        .nfc{font-size:12.5px;color:var(--mut);margin-top:8px;text-align:center;min-height:16px}
        .err{background:#fdecea;color:#9a2820;border:1px solid var(--red);border-radius:10px;padding:10px 12px;font-size:14px;margin-top:10px;display:none}
        .err.show{display:block}
        .count{text-align:center;padding:14px;border-radius:14px;background:#eef9e8;border:1px solid #d5efc9;margin-bottom:14px}
        .count b{font:700 34px var(--fh);color:var(--green);display:block;line-height:1}
        .count span{font-size:12.5px;color:var(--mut)}
        .list{list-style:none}
        .list li{display:flex;align-items:center;gap:10px;padding:11px 12px;background:var(--surf);border:1px solid var(--bd);border-radius:12px;margin-bottom:8px;animation:pop .25s ease}
        @keyframes pop{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:none}}
        .list li i{color:var(--green);font-size:18px}
        .list .nm{font:600 15px var(--fh);flex:1}
        .list .code{font:600 12px var(--fh);letter-spacing:.06em;color:#888}
        .empty{text-align:center;color:#888;padding:18px;font-size:13.5px}
    </style>
</head>
<body>
    <div class="top">
        <i class="fa-solid fa-id-card" style="color:var(--green)"></i>
        <h1>{{ $event->title }}</h1>
        <a href="{{ route('tagtoa.event.dashboard.wallet', $event->id) }}"><i class="fa-solid fa-arrow-left"></i> {{ __('Wallet') }}</a>
    </div>
    <div class="wrap">

        {{-- Réglages par défaut (fixés une fois pour la session d'encodage) --}}
        <div class="card">
            <h2>{{ __('Réglages par défaut') }}</h2>
            <div class="mb">
                <label>{{ __('Type de billet') }}</label>
                <select id="ticketType">
                    <option value="">{{ __('Aucun') }}</option>
                    @foreach($ticketTypes as $tt)
                        <option value="{{ $tt->id }}">{{ $tt->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label>{{ __('Recharge initiale') }} ({{ $event->currency }})</label>
                <input class="inp" id="defAmount" type="number" step="0.01" min="0" inputmode="decimal" placeholder="0">
            </div>
        </div>

        {{-- Compteur --}}
        <div class="count"><b id="counter">0</b><span>{{ __('cartes encodées') }}</span></div>

        {{-- Carte courante --}}
        <div class="card">
            <h2>{{ __('Nouvelle carte') }}</h2>
            <div class="mb">
                <label>{{ __('Nom du participant') }}</label>
                <input class="inp" id="name" autocomplete="off" autofocus placeholder="{{ __('Nom complet') }}">
            </div>
            <div class="row mb">
                <div>
                    <label>{{ __('Téléphone') }}</label>
                    <input class="inp" id="phone" inputmode="tel" autocomplete="off" placeholder="+509…">
                </div>
                <div>
                    <label>{{ __('E-mail') }}</label>
                    <input class="inp" id="email" type="email" autocomplete="off" placeholder="—">
                </div>
            </div>
            <div>
                <label>{{ __('Tag NFC / UID') }}</label>
                <input class="inp" id="uid" autocomplete="off" placeholder="04:A2:…">
            </div>
            <button class="btn btn-o" id="nfcBtn" type="button"><i class="fa-solid fa-wifi"></i> {{ __('Lire le tag NFC') }}</button>
            <div class="nfc" id="nfcHint"></div>
            <button class="btn btn-p" id="saveBtn" type="button"><i class="fa-solid fa-floppy-disk"></i> {{ __('Encoder la carte') }}</button>
            <div class="err" id="err"></div>
        </div>

        {{-- Liste des cartes encodées --}}
        <div class="card">
            <h2>{{ __('Cartes encodées') }}</h2>
            <ul class="list" id="list"></ul>
            <div class="empty" id="empty">{{ __('Aucune carte encodée pour l\'instant.') }}</div>
        </div>
    </div>

<script>
    var CSRF=document.querySelector('meta[name=csrf-token]').content;
    var ENCODE=@json(route('tagtoa.event.dashboard.wallet.encode-json',$event->id));
    var T={read:@json(__('Approchez le tag…')),nfcNo:@json(__('NFC non supporté sur cet appareil — saisissez l\'UID.')),err:@json(__('Réessayez.')),need:@json(__('Nom et tag NFC requis.'))};
    var count=0;

    function el(id){return document.getElementById(id);}
    function showErr(m){var e=el('err');e.textContent=m;e.classList.add('show');}
    function clearErr(){el('err').classList.remove('show');}

    function addRow(name,code){
        el('empty').style.display='none';
        var li=document.createElement('li');
        li.innerHTML='<i class="fa-solid fa-circle-check"></i><span class="nm"></span><span class="code"></span>';
        li.querySelector('.nm').textContent=name||'—';
        li.querySelector('.code').textContent=code||'';
        var list=el('list');list.insertBefore(li,list.firstChild);
        count++;el('counter').textContent=count;
    }

    function save(){
        clearErr();
        var name=el('name').value.trim(),uid=el('uid').value.trim();
        if(!name||!uid){showErr(T.need);return;}
        var payload={
            uid:uid,name:name,
            phone:el('phone').value.trim()||null,
            email:el('email').value.trim()||null,
            ticket_type_id:el('ticketType').value?Number(el('ticketType').value):null,
            amount:parseFloat(el('defAmount').value||0)||null
        };
        var btn=el('saveBtn');btn.disabled=true;
        fetch(ENCODE,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':CSRF},body:JSON.stringify(payload)})
        .then(function(r){return r.json().then(function(j){return{ok:r.ok,j:j};});})
        .then(function(res){
            btn.disabled=false;
            if(!res.ok||!res.j.ok){showErr((res.j&&res.j.message)||T.err);return;}
            addRow(res.j.name,res.j.code);
            // Reset carte pour le tap suivant (on garde les réglages par défaut).
            el('name').value='';el('phone').value='';el('email').value='';el('uid').value='';
            el('nfcHint').textContent='';el('name').focus();
        }).catch(function(){btn.disabled=false;showErr(T.err);});
    }

    el('saveBtn').addEventListener('click',save);
    // Entrée dans le champ UID (ou clavier NFC USB) déclenche l'encodage.
    el('uid').addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();save();}});
    el('name').addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();el('uid').focus();}});

    // Web NFC (Chrome Android) — tap successif : remplit l'UID en continu.
    el('nfcBtn').addEventListener('click',function(){
        if(!('NDEFReader' in window)){el('nfcHint').textContent=T.nfcNo;return;}
        try{
            var reader=new NDEFReader();
            el('nfcHint').textContent=T.read;
            reader.scan().then(function(){
                reader.onreading=function(e){
                    var id=e.serialNumber||'';
                    if(id){el('uid').value=id;el('nfcHint').textContent=id;}
                };
            }).catch(function(){el('nfcHint').textContent=T.nfcNo;});
        }catch(err){el('nfcHint').textContent=T.nfcNo;}
    });
</script>
</body>
</html>
