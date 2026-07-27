{{-- TAGTOA POS — Caisse tactile (offline-first, Web Audio, split, reçu WhatsApp, impression thermique).
     Variables : $terminal, $products, $methods. Standalone. --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $terminal->name }} — TAGTOA POS</title>
    {{-- PWA : installable + hors ligne --}}
    <link rel="manifest" href="{{ route('tagtoa.pos.manifest',$terminal->id) }}">
    <meta name="theme-color" content="#2cb809">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <link rel="apple-touch-icon" href="{{ route('tagtoa.pos.icon') }}">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root{--blk:#0A0A0A;--blue:#2cb809;--green:#1D9E75;--red:#E0473E;--bg:#F5F5F3;--bd:rgba(0,0,0,.08);--fh:'Space Grotesk',sans-serif;--fb:'Nunito',sans-serif}
        *{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
        body{font-family:var(--fb);background:var(--bg);color:var(--blk);height:100vh;overflow:hidden}
        .app{display:grid;grid-template-columns:1fr 320px;height:100vh}
        @media(max-width:760px){.app{grid-template-columns:1fr}.cart{position:fixed;inset:auto 0 0 0;max-height:48vh;border-radius:18px 18px 0 0;box-shadow:0 -8px 30px rgba(0,0,0,.15)}}
        .top{grid-column:1/-1;display:flex;align-items:center;gap:12px;padding:12px 18px;background:var(--blk);color:#fff}.top h1{font:600 16px var(--fh);flex:1}.top .net{font-size:12px;padding:4px 9px;border-radius:999px;background:rgba(255,255,255,.15)}.top .net.off{background:#E08A1E}.top a{color:#fff;opacity:.8;text-decoration:none}
        .grid{padding:14px;overflow-y:auto;display:grid;grid-template-columns:repeat(auto-fill,minmax(96px,1fr));gap:10px;align-content:start}
        .p{border:0;border-radius:16px;padding:14px 8px;color:#fff;cursor:pointer;font:600 13px var(--fh);display:flex;flex-direction:column;align-items:center;gap:6px;min-height:96px;justify-content:center;transition:transform .1s}.p:active{transform:scale(.94)}.p .em{font-size:26px}.p .pr{font-size:12px;opacity:.85}
        .cart{background:#fff;border-left:1px solid var(--bd);display:flex;flex-direction:column}.cart h2{font:600 15px var(--fh);padding:14px 16px;border-bottom:1px solid var(--bd)}
        .lines{flex:1;overflow-y:auto;padding:8px 12px}.ln{display:flex;align-items:center;gap:8px;padding:8px 0;border-bottom:1px solid var(--bd)}.ln .nm{flex:1;font-size:14px}.ln .nm small{display:block;color:#888}.ln .q button{width:26px;height:26px;border-radius:7px;border:1px solid var(--bd);background:#fff;cursor:pointer}
        .tot{padding:12px 16px;border-top:1px solid var(--bd)}.tot .r{display:flex;justify-content:space-between;font-size:14px;padding:3px 0}.tot .r.g{font:700 20px var(--fh)}
        .pay{display:block;width:100%;background:var(--green);color:#fff;border:0;border-radius:14px;padding:15px;font:600 16px var(--fh);cursor:pointer;margin-top:8px}.pay:disabled{background:#bcd;cursor:not-allowed}
        .pay-proforma{display:block;width:100%;background:#6C6C6C;color:#fff;border:0;border-radius:14px;padding:10px;font:600 13px var(--fh);cursor:pointer;margin-top:6px}
        .pay-proforma:disabled{opacity:.4;cursor:not-allowed}
        .modal{position:fixed;inset:0;background:rgba(0,0,0,.5);display:none;align-items:flex-end;z-index:50}.modal.show{display:flex}
        .sheet{background:#fff;width:100%;max-width:480px;margin:0 auto;border-radius:22px 22px 0 0;padding:18px;max-height:88vh;overflow-y:auto}.sheet h3{font-family:var(--fh);margin-bottom:6px}
        .methods{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin:12px 0}.m{border:1.5px solid var(--bd);border-radius:12px;padding:10px 6px;text-align:center;cursor:pointer;font-size:12px;background:#fff}.m.on{border-color:var(--blue);background:rgba(44,184,9,.08);color:var(--blue);font-weight:700}
        .split{font-size:13px;color:#666;margin:8px 0}.split input{width:90px;padding:6px;border:1px solid var(--bd);border-radius:8px;text-align:right}
        .field{width:100%;padding:11px;border:1.5px solid var(--bd);border-radius:10px;margin:6px 0;font:15px var(--fb)}
        .done{position:fixed;inset:0;background:var(--green);color:#fff;display:none;flex-direction:column;align-items:center;justify-content:center;z-index:60;text-align:center;padding:20px}.done.show{display:flex}.done i{font-size:64px}.done h2{font:700 24px var(--fh);margin:12px 0 4px}.done .acts{display:flex;gap:10px;margin-top:20px;flex-wrap:wrap;justify-content:center}.done .acts a,.done .acts button{background:rgba(255,255,255,.2);color:#fff;border:0;border-radius:12px;padding:12px 18px;font:600 14px var(--fh);text-decoration:none;cursor:pointer}
        #print-target{display:none}
        @media (prefers-reduced-motion:reduce){*{transition:none!important}}

        /* ── IMPRESSION THERMIQUE 80 mm ─────────────────────────────── */
        @media print{
            @page{size:80mm auto;margin:4mm 3mm}
            body,html{background:#fff!important;color:#000!important;height:auto!important;overflow:visible!important}
            .app,.top,.modal,.done,.grid,.cart{display:none!important}
            #print-target{display:block!important;width:74mm;font-family:'Courier New',Courier,monospace;font-size:11pt;line-height:1.4;color:#000}
            #print-target .rct-head{text-align:center;border-bottom:1px dashed #000;padding-bottom:4mm;margin-bottom:3mm}
            #print-target .rct-head .rct-logo{font-size:16pt;font-weight:700;letter-spacing:1px}
            #print-target .rct-head .rct-sub{font-size:8pt;color:#444}
            #print-target .rct-meta{font-size:9pt;margin-bottom:3mm}
            #print-target .rct-meta div{display:flex;justify-content:space-between}
            #print-target table{width:100%;border-collapse:collapse;font-size:10pt;margin-bottom:3mm}
            #print-target table th{border-bottom:1px solid #000;text-align:left;padding:1mm 0;font-size:9pt}
            #print-target table td{padding:1mm 0;vertical-align:top}
            #print-target table td.qty{width:8mm;text-align:center}
            #print-target table td.price{width:18mm;text-align:right}
            #print-target table td.line{width:20mm;text-align:right}
            #print-target .rct-totals{border-top:1px solid #000;padding-top:2mm;font-size:10pt}
            #print-target .rct-totals div{display:flex;justify-content:space-between}
            #print-target .rct-totals .big{font-size:13pt;font-weight:700;border-top:1px dashed #000;padding-top:2mm;margin-top:2mm}
            #print-target .rct-methods{font-size:9pt;margin-top:2mm;padding-top:2mm;border-top:1px dashed #000}
            #print-target .rct-foot{text-align:center;margin-top:4mm;font-size:8pt;border-top:1px dashed #000;padding-top:3mm}
            #print-target .rct-proforma-stamp{text-align:center;font-size:18pt;font-weight:700;letter-spacing:3px;border:2px solid #000;padding:2mm 0;margin-bottom:3mm}
        }
    </style>
</head>
<body data-terminal="{{ $terminal->id }}" data-currency="{{ $terminal->currency }}" data-terminal-name="{{ $terminal->name }}">
    <div class="app">
        <div class="top"><i class="fa-solid fa-cash-register" style="color:var(--blue)"></i><h1>{{ $terminal->name }}</h1><button id="installBtn" class="net" style="display:none;border:0;cursor:pointer;background:var(--blue)" title="{{ __('Installer l\'application') }}"><i class="fa-solid fa-download"></i> {{ __('Installer') }}</button><span class="net" id="net">●</span><a href="{{ route('tagtoa.pos.report',$terminal->id) }}"><i class="fa-solid fa-chart-simple"></i></a></div>
        <div class="grid" id="grid">
            @foreach($products as $p)
                <button class="p" style="background:{{ $p->color }}" onclick="add({{ $p->id }},'{{ addslashes($p->name) }}',{{ $p->price }})"><span class="em">{{ $p->emoji ?: '🛒' }}</span><span>{{ $p->name }}</span><span class="pr">{{ number_format($p->price,2) }}</span></button>
            @endforeach
        </div>
        <div class="cart">
            <h2>{{ __('Panier') }}</h2>
            <div class="lines" id="lines"><p style="color:#999;padding:14px;font-size:14px">{{ __('Touchez un produit') }}</p></div>
            <div class="tot">
                <div class="r"><span>{{ __('Sous-total') }}</span><span id="sub">0.00</span></div>
                <div class="r"><span>{{ __('Remise') }}</span><span><input id="disc" type="number" value="0" min="0" style="width:80px;text-align:right;border:1px solid var(--bd);border-radius:6px" oninput="render()"></span></div>
                <div class="r g"><span>{{ __('Total') }}</span><span id="tot">0.00</span></div>
                <button class="pay" id="paybtn" onclick="openPay()" disabled><i class="fa-solid fa-credit-card"></i> {{ __('Encaisser') }}</button>
                <button class="pay-proforma" id="profbtn" onclick="printProforma()" disabled><i class="fa-solid fa-file-invoice"></i> {{ __('Proforma') }}</button>
            </div>
        </div>
    </div>

    <div class="modal" id="modal"><div class="sheet">
        <h3>{{ __('Paiement') }} — <span id="pt">0.00</span> {{ $terminal->currency }}</h3>
        <div class="methods" id="methods">@foreach($methods as $k=>$label)<div class="m" data-m="{{ $k }}" onclick="pickM('{{ $k }}',this)">{{ $label }}</div>@endforeach</div>
        <div class="split"><label><input type="checkbox" id="splitchk" onchange="render()"> {{ __('Paiement séparé (split)') }}</label><div id="splitbox" style="display:none;margin-top:6px"></div></div>
        <input class="field" id="phone" placeholder="{{ __('Téléphone client (reçu WhatsApp)') }}">
        <button class="pay" onclick="confirmSale()"><i class="fa-solid fa-check"></i> {{ __('Confirmer') }}</button>
        <button class="pay" style="background:#888;margin-top:6px" onclick="closePay()">{{ __('Annuler') }}</button>
    </div></div>

    <div class="done" id="done"><i class="fa-solid fa-circle-check"></i><h2 id="dref"></h2><p id="dtot"></p>
        <div class="acts">
            <a id="wa" target="_blank"><i class="fa-brands fa-whatsapp"></i> {{ __('Reçu') }}</a>
            <button onclick="doPrint(buildReceiptHTML())" title="{{ __('Imprimer reçu') }}"><i class="fa-solid fa-print"></i> {{ __('Reçu') }}</button>
            <button onclick="printESCPOS()" title="{{ __('Imprimante thermique USB') }}" id="escposBtn"><i class="fa-solid fa-receipt"></i> USB</button>
            <button onclick="newSale()"><i class="fa-solid fa-plus"></i> {{ __('Nouvelle') }}</button>
        </div>
    </div>

    {{-- Zone d'impression — cachée à l'écran, visible uniquement @media print --}}
    <div id="print-target"></div>

<script>
var T=document.body.dataset.terminal,CUR=document.body.dataset.currency,TNAME=document.body.dataset.terminalName,CSRF=document.querySelector('meta[name=csrf-token]').content;
var SALE_URL="{{ route('tagtoa.pos.sale',$terminal->id) }}",SYNC_URL="{{ route('tagtoa.pos.sync',$terminal->id) }}",QKEY='tagtoa_pos_q_'+T;
var cart={},method='cash',lastSale=null;

function add(id,name,price){if(!cart[id])cart[id]={product_id:id,name:name,price:price,qty:0};cart[id].qty++;beep('add');render();}
function chg(id,d){if(cart[id]){cart[id].qty+=d;if(cart[id].qty<=0)delete cart[id];render();}}
function sub(){var s=0;for(var k in cart)s+=cart[k].price*cart[k].qty;return s;}
function total(){return Math.max(0,sub()-(parseFloat(document.getElementById('disc').value)||0));}
function render(){var L=document.getElementById('lines'),ks=Object.keys(cart);
    L.innerHTML=ks.length?ks.map(function(k){var c=cart[k];return '<div class="ln"><div class="nm">'+c.name+'<small>'+c.price.toFixed(2)+'</small></div><div class="q"><button onclick="chg('+k+',-1)">−</button> '+c.qty+' <button onclick="chg('+k+',1)">+</button></div></div>';}).join(''):'<p style="color:#999;padding:14px;font-size:14px">{{ __('Touchez un produit') }}</p>';
    var hasItems=!!ks.length;
    document.getElementById('sub').textContent=sub().toFixed(2);document.getElementById('tot').textContent=total().toFixed(2);document.getElementById('pt').textContent=total().toFixed(2);document.getElementById('paybtn').disabled=!hasItems;document.getElementById('profbtn').disabled=!hasItems;
    var sb=document.getElementById('splitbox'),on=document.getElementById('splitchk').checked;sb.style.display=on?'block':'none';if(on&&!sb.innerHTML)sb.innerHTML='{{ __('MonCash') }}: <input type="number" id="sp1" value="0"> · {{ __('Cash') }}: <input type="number" id="sp2" value="0">';}
function pickM(m,el){method=m;document.querySelectorAll('.m').forEach(function(x){x.classList.remove('on');});el.classList.add('on');}
function openPay(){document.getElementById('modal').classList.add('show');}function closePay(){document.getElementById('modal').classList.remove('show');}
function payments(){if(document.getElementById('splitchk').checked){var a=parseFloat((document.getElementById('sp1')||{}).value||0),b=parseFloat((document.getElementById('sp2')||{}).value||0);return [{method:'moncash',amount:a},{method:'cash',amount:b}];}return [{method:method,amount:total()}];}
function uuid(){return 'pxxxxxxxxyxx'.replace(/[xy]/g,function(c){var r=Math.random()*16|0;return (c==='x'?r:(r&0x3|0x8)).toString(16);})+Date.now();}
var actx;function beep(t){try{actx=actx||new (window.AudioContext||window.webkitAudioContext)();var o=actx.createOscillator(),g=actx.createGain();o.connect(g);g.connect(actx.destination);var f={add:660,success:[880,1320],error:[200,160]}[t];o.frequency.value=Array.isArray(f)?f[0]:f;o.type='sine';g.gain.value=.1;o.start();if(Array.isArray(f))setTimeout(function(){o.frequency.value=f[1];},80);setTimeout(function(){o.stop();},t==='error'?240:150);}catch(e){}}
function setNet(){var on=navigator.onLine;document.getElementById('net').textContent=on?'● online':'● offline';document.getElementById('net').classList.toggle('off',!on);if(on)flush();}
window.addEventListener('online',setNet);window.addEventListener('offline',setNet);
function q(){return JSON.parse(localStorage.getItem(QKEY)||'[]');}function setQ(a){localStorage.setItem(QKEY,JSON.stringify(a));}
function flush(){var a=q();if(!a.length)return;fetch(SYNC_URL,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},body:JSON.stringify({sales:a})}).then(function(r){return r.json();}).then(function(){setQ([]);}).catch(function(){});}
function confirmSale(){var p={items:Object.values(cart),discount:parseFloat(document.getElementById('disc').value)||0,payments:payments(),customer_phone:document.getElementById('phone').value,client_uuid:uuid()};
    if(navigator.onLine){fetch(SALE_URL,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},body:JSON.stringify(p)}).then(function(r){return r.json();}).then(function(d){ok(d.reference,p);}).catch(function(){off(p);});}else off(p);}
function off(p){var a=q();a.push(p);setQ(a);ok(p.client_uuid.substr(0,8).toUpperCase()+' ({{ __('hors-ligne') }})',p);}
function ok(ref,p){beep('success');closePay();lastSale={ref:ref,items:Object.values(cart),subtotal:sub(),discount:parseFloat(document.getElementById('disc').value)||0,total:total(),currency:CUR,payments:p.payments,customer_phone:p.customer_phone,date:new Date()};
    document.getElementById('dref').textContent=ref;document.getElementById('dtot').textContent=total().toFixed(2)+' '+CUR;
    var lines=Object.values(cart).map(function(c){return c.qty+'x '+c.name+' = '+(c.qty*c.price).toFixed(2);}).join('%0A');
    var msg='{{ __('Reçu') }} TAGTOA%0A'+ref+'%0A'+lines+'%0A{{ __('Total') }}: '+total().toFixed(2)+' '+CUR;
    document.getElementById('wa').href='https://wa.me/'+(p.customer_phone||'').replace(/[^0-9]/g,'')+'?text='+msg;document.getElementById('done').classList.add('show');}
function newSale(){cart={};document.getElementById('disc').value=0;document.getElementById('phone').value='';document.getElementById('done').classList.remove('show');render();}
window.addEventListener('load',function(){setNet();render();});

/* ── IMPRESSION ──────────────────────────────────────────────────────── */

function fmtDate(d){var dd=d||new Date();return dd.toLocaleDateString('fr-HT')+(': ')+dd.toLocaleTimeString('fr-HT',{hour:'2-digit',minute:'2-digit'});}

function itemsTableHTML(items){
    var rows=items.map(function(c){
        return '<tr><td class="qty">'+c.qty+'</td><td>'+c.name+'</td><td class="price">'+parseFloat(c.price).toFixed(2)+'</td><td class="line">'+(c.qty*parseFloat(c.price)).toFixed(2)+'</td></tr>';
    }).join('');
    return '<table><thead><tr><th class="qty">Qté</th><th>Article</th><th class="price">PU</th><th class="line">Ligne</th></tr></thead><tbody>'+rows+'</tbody></table>';}

function methodLabel(m){var MAP={cash:'Cash',moncash:'MonCash',natcash:'NatCash',zelle:'Zelle',paypal:'PayPal',card:'Carte',unibank:'Unibank',sogebank:'Sogebank',usdt:'USDT',bitcoin:'Bitcoin',loyalty:'Loyalty'};return MAP[m]||m;}

function buildReceiptHTML(){
    if(!lastSale)return '';
    var s=lastSale;
    var methods=(s.payments||[]).map(function(p){return '<div><span>'+methodLabel(p.method)+'</span><span>'+parseFloat(p.amount||0).toFixed(2)+' '+s.currency+'</span></div>';}).join('');
    return '<div class="rct-head"><div class="rct-logo">TAGTOA POS</div><div class="rct-sub">'+TNAME+'</div><div class="rct-sub">govibeht.com</div></div>'
        +'<div class="rct-meta"><div><span>{{ __('Réf.') }}</span><span>'+s.ref+'</span></div><div><span>{{ __('Date') }}</span><span>'+fmtDate(s.date)+'</span></div>'+(s.customer_phone?'<div><span>{{ __('Client') }}</span><span>'+s.customer_phone+'</span></div>':'')+'</div>'
        +itemsTableHTML(s.items)
        +'<div class="rct-totals"><div><span>{{ __('Sous-total') }}</span><span>'+s.subtotal.toFixed(2)+' '+s.currency+'</span></div>'+(s.discount>0?'<div><span>{{ __('Remise') }}</span><span>-'+s.discount.toFixed(2)+' '+s.currency+'</span></div>':'')+'<div class="big"><span>{{ __('TOTAL') }}</span><span>'+s.total.toFixed(2)+' '+s.currency+'</span></div></div>'
        +'<div class="rct-methods">'+methods+'</div>'
        +'<div class="rct-foot">{{ __('Merci pour votre achat!') }}<br>TAGTOA · govibeht.com</div>';}

function buildProformaHTML(){
    var items=Object.values(cart);if(!items.length)return '';
    var subtotal=sub(),disc=parseFloat(document.getElementById('disc').value)||0,tot=total(),cur=CUR;
    return '<div class="rct-proforma-stamp">PROFORMA</div>'
        +'<div class="rct-head"><div class="rct-logo">TAGTOA POS</div><div class="rct-sub">'+TNAME+'</div><div class="rct-sub">govibeht.com</div></div>'
        +'<div class="rct-meta"><div><span>{{ __('Date') }}</span><span>'+fmtDate()+'</span></div><div><span>{{ __('Valide') }}</span><span>{{ __('48h') }}</span></div></div>'
        +itemsTableHTML(items)
        +'<div class="rct-totals"><div><span>{{ __('Sous-total') }}</span><span>'+subtotal.toFixed(2)+' '+cur+'</span></div>'+(disc>0?'<div><span>{{ __('Remise') }}</span><span>-'+disc.toFixed(2)+' '+cur+'</span></div>':'')+'<div class="big"><span>{{ __('TOTAL') }}</span><span>'+tot.toFixed(2)+' '+cur+'</span></div></div>'
        +'<div class="rct-foot">{{ __('Document non officiel — valable sur présentation') }}<br>TAGTOA · govibeht.com</div>';}

function doPrint(html){
    if(!html)return;
    document.getElementById('print-target').innerHTML=html;
    window.print();
    /* nettoyage après délai pour laisser le navigateur rendre */
    setTimeout(function(){document.getElementById('print-target').innerHTML='';},2000);}

function printProforma(){
    var html=buildProformaHTML();
    if(!html){beep('error');return;}
    doPrint(html);}

/* ── ESC/POS via Web Serial API (Chrome 89+, USB ou RS-232) ─────────── */
function printESCPOS(){
    if(!('serial' in navigator)){alert('{{ __('Web Serial non supporté. Utilisez Chrome 89+ avec l\'imprimante en USB.') }}');return;}
    if(!lastSale){beep('error');return;}
    navigator.serial.requestPort().then(function(port){
        return port.open({baudRate:9600}).then(function(){
            var bytes=buildESCPOSBytes(lastSale);
            var writer=port.writable.getWriter();
            return writer.write(bytes).then(function(){writer.releaseLock();return port.close();});
        });
    }).catch(function(e){if(e.name!=='NotFoundError')console.warn('ESC/POS:',e);});}

function buildESCPOSBytes(s){
    /* ESC/POS minimal : init, centrer, texte, couper */
    var ESC=0x1B,GS=0x1D,LF=0x0A;
    var enc=new TextEncoder();
    var out=[];
    function push(arr){for(var i=0;i<arr.length;i++)out.push(arr[i]);}
    function txt(str){var b=enc.encode(str);for(var i=0;i<b.length;i++)out.push(b[i]);}
    function nl(n){for(var i=0;i<(n||1);i++)out.push(LF);}
    function center(){push([ESC,0x61,0x01]);}
    function left(){push([ESC,0x61,0x00]);}
    function bold(on){push([ESC,0x45,on?1:0]);}
    function dbl(on){push([GS,0x21,on?0x11:0x00]);}
    function cut(){push([GS,0x56,0x41,0x03]);}  /* corbeille après 3 mm */
    function line(n){var c='-';var r='';for(var i=0;i<(n||32);i++)r+=c;txt(r);nl();}

    push([ESC,0x40]);           /* init */
    center();dbl(true);bold(true);txt('TAGTOA POS');nl();dbl(false);bold(false);
    center();txt(TNAME);nl();txt('govibeht.com');nl(2);
    left();txt('Ref: '+s.ref);nl();txt('Date: '+fmtDate(s.date));nl();
    if(s.customer_phone)txt('Client: '+s.customer_phone),nl();
    line(32);
    /* en-tête colonnes */
    txt(pad('Qte',4)+pad('Article',16)+pad('Total',12));nl();
    line(32);
    s.items.forEach(function(it){
        txt(pad(String(it.qty),4)+pad(it.name.substring(0,15),16)+padL((it.qty*parseFloat(it.price)).toFixed(2)+' '+s.currency,12));nl();
    });
    line(32);
    txt(pad('Sous-total',20)+padL(s.subtotal.toFixed(2)+' '+s.currency,12));nl();
    if(s.discount>0){txt(pad('Remise',20)+padL('-'+s.discount.toFixed(2)+' '+s.currency,12));nl();}
    bold(true);dbl(true);
    txt(pad('TOTAL',16)+padL(s.total.toFixed(2)+' '+s.currency,16));nl();
    dbl(false);bold(false);
    line(32);
    (s.payments||[]).forEach(function(p){txt(pad(methodLabel(p.method),20)+padL(parseFloat(p.amount||0).toFixed(2)+' '+s.currency,12));nl();});
    nl(2);center();txt('Merci pour votre achat!');nl();txt('TAGTOA · govibeht.com');nl(4);
    cut();
    return new Uint8Array(out);}

function pad(s,n){s=String(s);while(s.length<n)s+=' ';return s.substring(0,n);}
function padL(s,n){s=String(s);while(s.length<n)s=' '+s;return s.substring(s.length-n);}

/* ── PWA : service worker + invite d'installation ─────────────────── */
if('serviceWorker' in navigator){
    navigator.serviceWorker.register("{{ route('tagtoa.pos.sw') }}", {scope:"{{ rtrim(url('/tagtoa/pos'),'/') }}/"}).catch(function(){});
}
var deferredPrompt=null;
window.addEventListener('beforeinstallprompt',function(e){e.preventDefault();deferredPrompt=e;var b=document.getElementById('installBtn');if(b)b.style.display='inline-block';});
(function(){var b=document.getElementById('installBtn');if(b)b.addEventListener('click',function(){if(!deferredPrompt)return;deferredPrompt.prompt();deferredPrompt.userChoice.finally(function(){deferredPrompt=null;b.style.display='none';});});})();
window.addEventListener('appinstalled',function(){var b=document.getElementById('installBtn');if(b)b.style.display='none';});
</script>
</body>
</html>
