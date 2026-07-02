{{-- TAGTOA EVENT — terminal vendeur (tap NFC / QR → débit wallet). Standalone. --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $event->title }} — {{ __('Terminal') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Space+Grotesk:wght@500;600;700&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root{--green:#2cb809;--ink:#0d140c;--bg:#f5f9f2;--surf:#fff;--bd:rgba(13,20,12,.10);--mut:#5d6b5a;--red:#E0473E;--fh:'Space Grotesk',sans-serif;--fb:'Nunito',sans-serif}
        .top h1,.bal .amt,.done h2{font-family:'Anton',sans-serif!important;font-weight:400!important;letter-spacing:.01em}
        *{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
        body{font-family:var(--fb);background:var(--bg);color:var(--ink);min-height:100vh}
        .top{background:var(--ink);color:#fff;padding:12px 18px;display:flex;align-items:center;gap:10px}
        .top h1{font:600 15px var(--fh);flex:1}
        .wrap{max-width:520px;margin:0 auto;padding:16px}
        .card{background:var(--surf);border:1px solid var(--bd);border-radius:16px;padding:18px;margin-bottom:14px}
        label{display:block;font:600 12.5px var(--fh);color:var(--mut);margin:0 0 6px}
        .inp,select{width:100%;padding:13px 14px;border:1.5px solid var(--bd);border-radius:12px;font:16px var(--fb);background:#fff;color:var(--ink)}
        .btn{width:100%;border:0;border-radius:14px;padding:15px;font:700 16px var(--fh);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:9px;margin-top:12px}
        .btn-p{background:var(--green);color:#fff}.btn-o{background:#fff;border:1.5px solid var(--bd);color:var(--ink)}
        .btn:disabled{opacity:.55}
        .bal{text-align:center;padding:18px;border-radius:14px;background:#eef9e8;border:1px solid #d5efc9;margin-bottom:14px;display:none}
        .bal.show{display:block}
        .bal .who{font:700 16px var(--fh)}
        .bal .amt{font:700 30px var(--fh);color:var(--green);margin-top:4px}
        .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:8px}
        .grid button{padding:12px;border:1.5px solid var(--bd);background:#fff;border-radius:12px;font:700 15px var(--fh);cursor:pointer}
        .done{position:fixed;inset:0;background:var(--green);color:#fff;display:none;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:20px;z-index:60}
        .done.show{display:flex}.done i{font-size:64px}.done h2{font:700 24px var(--fh);margin:12px 0 4px}
        .done .sub{opacity:.9}.done button{margin-top:22px;background:rgba(255,255,255,.2);color:#fff;border:0;border-radius:12px;padding:13px 22px;font:700 15px var(--fh);cursor:pointer}
        .err{background:#fdecea;color:#9a2820;border:1px solid var(--red);border-radius:10px;padding:10px 12px;font-size:14px;margin-top:10px;display:none}
        .err.show{display:block}
        .nfc{font-size:12.5px;color:var(--mut);margin-top:8px;text-align:center}
    </style>
</head>
<body>
    <div class="top"><i class="fa-solid fa-cash-register" style="color:var(--green)"></i><h1>{{ $event->title }}</h1><span style="font-size:12px;opacity:.7">{{ __('Terminal') }}</span></div>
    <div class="wrap">
        <div class="card">
            <label>{{ __('Stand') }}</label>
            <select id="vendor">
                @forelse($vendors as $v)<option value="{{ $v->id }}">{{ $v->owner_label }}</option>@empty<option value="">{{ __('Aucun stand — créez-en un') }}</option>@endforelse
            </select>

            <label style="margin-top:14px">{{ __('Tag NFC / UID') }}</label>
            <input class="inp" id="uid" placeholder="04:A2:..." autocomplete="off">
            <button class="btn btn-o" id="nfcBtn" type="button"><i class="fa-solid fa-wifi"></i> {{ __('Lire le tag NFC') }}</button>
            <div class="nfc" id="nfcHint"></div>
            <button class="btn btn-o" id="lookupBtn" type="button"><i class="fa-solid fa-magnifying-glass"></i> {{ __('Vérifier le solde') }}</button>
            <div class="err" id="err"></div>
        </div>

        <div class="bal" id="bal">
            <div class="who" id="who"></div>
            <div class="amt" id="amt"></div>
        </div>

        <div class="card">
            <label>{{ __('Montant') }} ({{ $event->currency }})</label>
            <input class="inp" id="amount" type="number" step="0.01" min="0.01" inputmode="decimal" placeholder="0">
            <div class="grid">
                @foreach([50,100,250,500,1000,2000] as $q)<button type="button" onclick="addAmt({{ $q }})">+{{ $q }}</button>@endforeach
            </div>
            <button class="btn btn-p" id="chargeBtn" type="button"><i class="fa-solid fa-bolt"></i> {{ __('Encaisser') }}</button>
        </div>
    </div>

    <div class="done" id="doneScr">
        <i class="fa-solid fa-circle-check"></i>
        <h2 id="doneAmt"></h2>
        <div class="sub">{{ __('Nouveau solde') }} : <b id="doneBal"></b></div>
        <div class="sub" id="doneRef" style="opacity:.7;margin-top:4px"></div>
        <a id="doneReceipt" href="#" target="_blank" rel="noopener" style="display:none;margin-top:14px;background:rgba(255,255,255,.2);color:#fff;border-radius:12px;padding:11px 18px;font:700 14px var(--fh);text-decoration:none"><i class="fa-solid fa-receipt"></i> {{ __('Reçu') }}</a>
        <button onclick="reset()">{{ __('Nouvelle vente') }}</button>
    </div>

<script>
    var CSRF=document.querySelector('meta[name=csrf-token]').content;
    var RESOLVE=@json(route('tagtoa.event.dashboard.wallet.resolve',$event->id));
    var CHARGE=@json(route('tagtoa.event.dashboard.wallet.charge',$event->id));
    var T={read:@json(__('Approchez le tag…')),nfcNo:@json(__('NFC non supporté sur cet appareil — saisissez l\'UID.')),err:@json(__('Réessayez.')),pick:@json(__('Choisissez un stand et un tag.'))};

    function el(id){return document.getElementById(id);}
    function showErr(m){var e=el('err');e.textContent=m;e.classList.add('show');}
    function clearErr(){el('err').classList.remove('show');}
    function addAmt(n){var a=el('amount');a.value=(parseFloat(a.value||0)+n).toString();}

    function lookup(){
        clearErr(); var uid=el('uid').value.trim(); if(!uid){showErr(T.pick);return;}
        fetch(RESOLVE,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':CSRF},body:JSON.stringify({uid:uid})})
        .then(function(r){return r.json().then(function(j){return{ok:r.ok,j:j};});})
        .then(function(res){
            if(!res.ok||!res.j.ok){showErr(res.j.message||T.err);el('bal').classList.remove('show');return;}
            el('who').textContent=res.j.holder||'—';el('amt').textContent=res.j.balance;el('bal').classList.add('show');
        }).catch(function(){showErr(T.err);});
    }

    function charge(){
        clearErr(); var uid=el('uid').value.trim(),vendor=el('vendor').value,amount=parseFloat(el('amount').value||0);
        if(!uid||!vendor){showErr(T.pick);return;}
        if(!(amount>0)){showErr(T.err);return;}
        var btn=el('chargeBtn');btn.disabled=true;
        fetch(CHARGE,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':CSRF},
            body:JSON.stringify({uid:uid,vendor_id:Number(vendor),amount:amount,client_uuid:'ch-'+Date.now().toString(36)+Math.random().toString(36).slice(2,8)})})
        .then(function(r){return r.json().then(function(j){return{ok:r.ok,j:j};});})
        .then(function(res){
            btn.disabled=false;
            if(!res.ok||!res.j.ok){showErr(res.j.message||T.err);if(res.j.balance){el('amt').textContent=res.j.balance;}return;}
            el('doneAmt').textContent=res.j.charged;el('doneBal').textContent=res.j.balance;el('doneRef').textContent=res.j.reference;
            var rc=el('doneReceipt');if(res.j.receipt_url){rc.href=res.j.receipt_url;rc.style.display='inline-block';}else{rc.style.display='none';}
            el('doneScr').classList.add('show');
        }).catch(function(){btn.disabled=false;showErr(T.err);});
    }

    function reset(){el('doneScr').classList.remove('show');el('amount').value='';el('uid').value='';el('bal').classList.remove('show');}

    el('lookupBtn').addEventListener('click',lookup);
    el('chargeBtn').addEventListener('click',charge);

    // Web NFC (Chrome Android). Sinon, saisie manuelle de l'UID.
    el('nfcBtn').addEventListener('click',function(){
        if(!('NDEFReader' in window)){el('nfcHint').textContent=T.nfcNo;return;}
        try{
            var reader=new NDEFReader();
            el('nfcHint').textContent=T.read;
            reader.scan().then(function(){
                reader.onreading=function(e){
                    var id=e.serialNumber||'';
                    if(id){el('uid').value=id;el('nfcHint').textContent=id;lookup();}
                };
            }).catch(function(){el('nfcHint').textContent=T.nfcNo;});
        }catch(err){el('nfcHint').textContent=T.nfcNo;}
    });
</script>
</body>
</html>
