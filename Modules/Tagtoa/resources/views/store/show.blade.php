{{-- TAGTOA STORE — boutique publique (NFC/QR). Autonome, mobile-first, panier. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $store->name }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Space+Grotesk:wght@500;600;700&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @php $accent = $store->accent_color ?: '#2cb809'; @endphp
    <style>
        :root{--acc:{{ $accent }};--ink:#0e140c;--bg:#f5f7f3;--surf:#fff;--mut:#6b7865;--bd:rgba(14,20,12,.10);--red:#d6402f;
            --fh:'Space Grotesk',sans-serif;--fb:'Nunito',sans-serif;--ft:'Anton',sans-serif}
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:var(--fb);background:var(--bg);color:var(--ink);line-height:1.55;padding-bottom:88px}
        .cover{height:150px;background:var(--ink) center/cover no-repeat;position:relative}
        .cover.has{background-image:url('{{ $store->cover_url }}')}
        .head{max-width:900px;margin:0 auto;padding:0 18px;display:flex;gap:14px;align-items:flex-end;margin-top:-38px;position:relative}
        .logo{width:76px;height:76px;border-radius:18px;background:#fff;border:3px solid #fff;box-shadow:0 6px 20px rgba(0,0,0,.12);
            object-fit:cover;display:grid;place-items:center;font-family:var(--ft);font-size:30px;color:var(--acc)}
        .head .meta{padding-bottom:6px}
        .head h1{font-family:var(--ft);font-weight:400;font-size:26px;letter-spacing:.01em;line-height:1}
        .head .tl{color:var(--mut);font-size:14px;margin-top:3px}
        .wrap{max-width:900px;margin:0 auto;padding:20px 18px}
        .info{display:flex;flex-wrap:wrap;gap:8px;margin:14px 0 6px}
        .chip{font:600 12.5px var(--fh);background:var(--surf);border:1px solid var(--bd);border-radius:999px;padding:6px 12px;display:inline-flex;gap:6px;align-items:center;color:var(--mut)}
        .desc{color:var(--mut);font-size:14.5px;margin:10px 0 4px;max-width:66ch}
        h2.cat{font-family:var(--ft);font-weight:400;font-size:19px;letter-spacing:.02em;margin:26px 0 12px}
        .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px}
        .card{background:var(--surf);border:1px solid var(--bd);border-radius:16px;overflow:hidden;display:flex;flex-direction:column}
        .card .img{aspect-ratio:1/1;background:#eef2ea center/cover no-repeat;display:grid;place-items:center;color:#c3ccbb;font-size:30px}
        .card .b{padding:12px 13px 13px;display:flex;flex-direction:column;flex:1}
        .card .nm{font-family:var(--fh);font-weight:600;font-size:15px}
        .card .ds{color:var(--mut);font-size:12.5px;margin-top:3px;flex:1;line-height:1.4}
        .card .pr{margin-top:9px;display:flex;align-items:baseline;gap:7px}
        .card .pr b{font-family:var(--ft);font-weight:400;font-size:18px;color:var(--acc)}
        .card .pr s{color:var(--mut);font-size:13px}
        .card .feat{position:relative}
        .badge-feat{position:absolute;top:8px;left:8px;background:var(--acc);color:#fff;font:700 10.5px var(--fh);padding:3px 8px;border-radius:999px;text-transform:uppercase;letter-spacing:.05em;z-index:1}
        .badge-out{position:absolute;top:8px;right:8px;background:var(--red);color:#fff;font:700 10.5px var(--fh);padding:3px 8px;border-radius:999px;z-index:1}
        .add{margin-top:10px;border:0;background:var(--acc);color:#fff;border-radius:11px;padding:10px;font:700 14px var(--fh);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px}
        .add:disabled{background:#cfd6cb;cursor:not-allowed}
        .qty{margin-top:10px;display:flex;align-items:center;justify-content:space-between;gap:8px}
        .qty button{width:34px;height:34px;border:1.5px solid var(--bd);background:#fff;border-radius:9px;font-size:18px;cursor:pointer}
        .qty span{font-family:var(--ft);font-size:17px;min-width:24px;text-align:center}
        /* Panier barre + drawer */
        .cartbar{position:fixed;left:0;right:0;bottom:0;background:var(--ink);color:#fff;padding:14px 18px;display:none;
            align-items:center;justify-content:space-between;gap:12px;z-index:60}
        .cartbar.show{display:flex}
        .cartbar .t{font:700 15px var(--fh)}
        .cartbar button{background:var(--acc);color:#fff;border:0;border-radius:12px;padding:12px 20px;font:700 15px var(--fh);cursor:pointer}
        .sheet{position:fixed;inset:0;background:rgba(0,0,0,.5);display:none;z-index:70;align-items:flex-end}
        .sheet.show{display:flex}
        .sheet .panel{background:var(--bg);width:100%;max-width:900px;margin:0 auto;border-radius:20px 20px 0 0;max-height:90vh;overflow:auto;padding:18px}
        .sheet h3{font-family:var(--ft);font-weight:400;font-size:20px;margin-bottom:12px}
        .line{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--bd)}
        .line .ln{flex:1}.line .ln b{font-family:var(--fh);font-weight:600;font-size:14px}
        .line .lp{color:var(--mut);font-size:13px}
        .lbl{display:block;font:600 12.5px var(--fh);color:#555;margin:12px 0 6px}
        .inp{width:100%;padding:12px 14px;border:1.5px solid var(--bd);border-radius:11px;font:15px var(--fb);background:#fff}
        .cta{width:100%;border:0;background:var(--acc);color:#fff;border-radius:14px;padding:15px;font:700 16px var(--fh);cursor:pointer;margin-top:14px}
        .cta.wa{background:#25d366}
        .okbox{display:none;text-align:center;padding:20px}
        .okbox.show{display:block}
        .okbox .rf{font-family:var(--ft);font-size:22px;color:var(--acc);margin:6px 0}
        .err{background:#fdecea;color:#9a2820;border:1px solid var(--red);border-radius:11px;padding:11px 14px;font-size:14px;margin-top:12px;display:none}
        .err.show{display:block}
        .empty{text-align:center;color:var(--mut);padding:50px 20px}
        .foot{max-width:900px;margin:0 auto;padding:24px 18px;color:var(--mut);font-size:12.5px;text-align:center}
    </style>
</head>
<body>
    <div class="cover {{ $store->cover_url ? 'has' : '' }}"></div>
    <div class="head">
        @if($store->logo_url)<img class="logo" src="{{ $store->logo_url }}" alt="">@else<div class="logo">{{ mb_substr($store->name,0,1) }}</div>@endif
        <div class="meta"><h1>{{ $store->name }}</h1>@if($store->tagline)<div class="tl">{{ $store->tagline }}</div>@endif</div>
    </div>

    <div class="wrap">
        <div class="info">
            @if($store->whatsapp)<span class="chip"><i class="fa-brands fa-whatsapp"></i> {{ $store->whatsapp }}</span>@endif
            @if($store->address)<span class="chip"><i class="fa-solid fa-location-dot"></i> {{ $store->address }}</span>@endif
            @if($store->delivery_note)<span class="chip"><i class="fa-solid fa-truck"></i> {{ $store->delivery_note }}</span>@endif
        </div>
        @if($store->description)<p class="desc">{{ $store->description }}</p>@endif

        @forelse($groups as $cat => $items)
            <h2 class="cat">{{ $cat }}</h2>
            <div class="grid">
                @foreach($items as $p)
                    @php $out = $p->stock !== null && $p->stock <= 0; @endphp
                    <div class="card">
                        <div class="img feat" @if($p->image_url) style="background-image:url('{{ $p->image_url }}')" @endif>
                            @if($p->is_featured)<span class="badge-feat">★ {{ __('Vedette') }}</span>@endif
                            @if($out)<span class="badge-out">{{ __('Épuisé') }}</span>@endif
                            @if(!$p->image_url)<i class="fa-solid fa-box"></i>@endif
                        </div>
                        <div class="b">
                            <div class="nm">{{ $p->name }}</div>
                            @if($p->description)<div class="ds">{{ \Illuminate\Support\Str::limit($p->description,90) }}</div>@endif
                            <div class="pr">
                                <b>{{ number_format((float)$p->price,2) }} {{ $store->currency }}</b>
                                @if($p->compare_price && $p->compare_price > $p->price)<s>{{ number_format((float)$p->compare_price,2) }}</s>@endif
                            </div>
                            @if($out)
                                <button class="add" disabled><i class="fa-solid fa-ban"></i> {{ __('Épuisé') }}</button>
                            @else
                                <button class="add" data-id="{{ $p->id }}" data-name="{{ $p->name }}" data-price="{{ (float)$p->price }}"><i class="fa-solid fa-cart-plus"></i> {{ __('Ajouter') }}</button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @empty
            <div class="empty"><i class="fa-solid fa-box-open" style="font-size:34px;color:#cfcfcf"></i><p style="margin-top:12px">{{ __('Aucun produit pour le moment.') }}</p></div>
        @endforelse
    </div>

    <div class="foot">{{ $store->name }} · {{ __('Propulsé par') }} <b>TAGTOA</b></div>

    {{-- Barre panier --}}
    <div class="cartbar" id="cartbar">
        <span class="t"><span id="cartCount">0</span> {{ __('article(s)') }} · <span id="cartTotal">0</span> {{ $store->currency }}</span>
        <button id="openCart">{{ __('Voir le panier') }} →</button>
    </div>

    {{-- Feuille panier / checkout --}}
    <div class="sheet" id="sheet">
        <div class="panel">
            <div id="cartView">
                <h3>{{ __('Votre panier') }}</h3>
                <div id="lines"></div>
                <label class="lbl">{{ __('Nom') }} *</label><input class="inp" id="cName" autocomplete="name">
                <label class="lbl">{{ __('WhatsApp') }} *</label><input class="inp" id="cPhone" inputmode="tel" placeholder="+509…">
                <label class="lbl">{{ __('Adresse de livraison') }}</label><input class="inp" id="cAddr" autocomplete="street-address">
                <label class="lbl">{{ __('Note (optionnel)') }}</label><input class="inp" id="cNote">
                <div class="err" id="cErr"></div>
                <button class="cta" id="confirm">{{ __('Confirmer la commande') }}</button>
                <button class="cta" style="background:#e9ede6;color:var(--ink);margin-top:8px" id="closeCart">{{ __('Continuer mes achats') }}</button>
            </div>
            <div class="okbox" id="okBox">
                <i class="fa-solid fa-circle-check" style="font-size:44px;color:var(--acc)"></i>
                <div class="rf" id="okRef"></div>
                <p style="color:var(--mut)">{{ __('Commande enregistrée !') }}</p>
                <a class="cta wa" id="waBtn" href="#" style="display:none;text-decoration:none;display:block"><i class="fa-brands fa-whatsapp"></i> {{ __('Envoyer sur WhatsApp') }}</a>
                <a class="cta" id="payBtn" href="#" style="display:none;text-decoration:none"><i class="fa-solid fa-credit-card"></i> {{ __('Payer maintenant') }}</a>
            </div>
        </div>
    </div>

<script>
    var CSRF=document.querySelector('meta[name=csrf-token]').content;
    var ORDER_URL=@json(route('tagtoa.store.order',$store->alias));
    var CUR=@json($store->currency);
    var T={empty:@json(__('Votre panier est vide.')),wait:@json(__('Patientez…')),confirm:@json(__('Confirmer la commande')),err:@json(__('Réessayez.')),need:@json(__('Nom et WhatsApp requis.'))};
    var cart={}; // id -> {name,price,qty}

    function el(id){return document.getElementById(id);}
    function money(n){return n.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})+' '+CUR;}
    function totals(){var c=0,t=0;for(var k in cart){c+=cart[k].qty;t+=cart[k].qty*cart[k].price;}return{c:c,t:t};}
    function refresh(){var s=totals();el('cartCount').textContent=s.c;el('cartTotal').textContent=money(s.t).replace(' '+CUR,'');
        el('cartbar').classList.toggle('show',s.c>0);
        // relabel add buttons
        document.querySelectorAll('.add[data-id]').forEach(function(b){var q=cart[b.dataset.id]?cart[b.dataset.id].qty:0;
            b.innerHTML = q>0 ? '<i class="fa-solid fa-check"></i> '+q : '<i class="fa-solid fa-cart-plus"></i> {{ __('Ajouter') }}';});}
    function addItem(b){var id=b.dataset.id;if(!cart[id])cart[id]={name:b.dataset.name,price:parseFloat(b.dataset.price),qty:0};cart[id].qty++;refresh();}
    document.querySelectorAll('.add[data-id]').forEach(function(b){b.addEventListener('click',function(){addItem(b);});});

    function renderLines(){var h='';for(var k in cart){var it=cart[k];
        h+='<div class="line"><div class="ln"><b>'+it.name+'</b><div class="lp">'+money(it.price)+'</div></div>'+
           '<div class="qty" style="margin:0"><button data-m="'+k+'">−</button><span>'+it.qty+'</span><button data-p="'+k+'">+</button></div></div>';}
        el('lines').innerHTML=h||('<p style="color:var(--mut)">'+T.empty+'</p>');
        el('lines').querySelectorAll('[data-p]').forEach(function(b){b.onclick=function(){cart[b.dataset.p].qty++;renderLines();refresh();};});
        el('lines').querySelectorAll('[data-m]').forEach(function(b){b.onclick=function(){var k=b.dataset.m;cart[k].qty--;if(cart[k].qty<=0)delete cart[k];renderLines();refresh();};});
    }
    el('openCart').onclick=function(){el('okBox').classList.remove('show');el('cartView').style.display='';renderLines();el('sheet').classList.add('show');};
    el('closeCart').onclick=function(){el('sheet').classList.remove('show');};
    el('sheet').onclick=function(e){if(e.target===el('sheet'))el('sheet').classList.remove('show');};

    function uuid(){return 'stk-'+Date.now().toString(36)+'-'+Math.random().toString(36).slice(2,9);}
    el('confirm').onclick=function(){
        var name=el('cName').value.trim(),phone=el('cPhone').value.trim();
        var errB=el('cErr');errB.classList.remove('show');
        if(!Object.keys(cart).length){errB.textContent=T.empty;errB.classList.add('show');return;}
        if(!name||!phone){errB.textContent=T.need;errB.classList.add('show');return;}
        var items=[];for(var k in cart)items.push({id:parseInt(k),qty:cart[k].qty});
        var btn=el('confirm');btn.disabled=true;btn.textContent=T.wait;
        fetch(ORDER_URL,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':CSRF},
            body:JSON.stringify({items:items,customer_name:name,customer_phone:phone,customer_address:el('cAddr').value.trim()||null,note:el('cNote').value.trim()||null,client_uuid:uuid()})})
        .then(function(r){return r.json();}).then(function(j){btn.disabled=false;btn.textContent=T.confirm;
            if(!j.ok){errB.textContent=j.message||T.err;errB.classList.add('show');return;}
            el('cartView').style.display='none';el('okBox').classList.add('show');
            el('okRef').textContent=j.reference;
            if(j.whatsapp_url){var w=el('waBtn');w.href=j.whatsapp_url;w.style.display='block';}
            var payUrl=j.checkout_url||j.pay_url; // paiement en ligne (MonCash) prioritaire
            if(payUrl){var p=el('payBtn');p.href=payUrl;p.style.display='block';}
            cart={};refresh();
        }).catch(function(){btn.disabled=false;btn.textContent=T.confirm;errB.textContent=T.err;errB.classList.add('show');});
    };
</script>
</body>
</html>
