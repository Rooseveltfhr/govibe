{{-- TAGTOA MENU — page publique (NFC/QR). Variables : $menu, $categories --}}
@php
    $dark = $menu->theme === 'dark';
    $accent = preg_match('/^#[0-9A-Fa-f]{3,8}$/', (string) $menu->accent_color) ? $menu->accent_color : '#2cb809';
    $bg   = $dark ? '#0A0A0A' : '#F5F5F3';
    $fg   = $dark ? '#FFFFFF' : '#0A0A0A';
    $surf = $dark ? '#161616' : '#FFFFFF';
    $mut  = $dark ? 'rgba(255,255,255,.6)' : '#888888';
    $bd   = $dark ? 'rgba(255,255,255,.10)' : 'rgba(0,0,0,.08)';
    $tm   = $menu->type_meta;
    $canOrder = $menu->ordering_enabled && $menu->whatsapp_digits;
    $cur = $menu->currency ?: 'HTG';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $menu->name }} — TAGTOA Menu</title>
    <link rel="stylesheet" href="{{ route('tagtoa.asset', 'tagtoa-fonts.css') }}">
    <link rel="stylesheet" href="/tagtoa-asset/fontawesome-6.5.1.css">
    <style>
        :root{
            --acc:{{ $accent }};--bg:{{ $bg }};--fg:{{ $fg }};--surf:{{ $surf }};--mut:{{ $mut }};--bd:{{ $bd }};
            --fh:'Space Grotesk',sans-serif;--fb:'Nunito',sans-serif;
        }
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:var(--fb);background:var(--bg);color:var(--fg);min-height:100vh;-webkit-font-smoothing:antialiased}
        a{text-decoration:none;color:inherit}
        .wrap{max-width:560px;margin:0 auto;padding-bottom:120px}
        /* Header */
        .cover{height:180px;background:linear-gradient(150deg,var(--acc),#0A0A0A);position:relative;background-size:cover;background-position:center}
        .cover::after{content:"";position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,.45),transparent 60%)}
        .head{padding:0 20px;margin-top:-44px;position:relative;z-index:2}
        .logo{width:84px;height:84px;border-radius:20px;border:3px solid var(--surf);background:var(--surf);object-fit:cover;display:flex;align-items:center;justify-content:center;font:700 30px var(--fh);color:var(--acc);box-shadow:0 8px 26px rgba(0,0,0,.18)}
        .title{font:700 24px var(--fh);margin-top:12px;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
        .badge-type{display:inline-flex;align-items:center;gap:6px;font:600 12px var(--fh);background:color-mix(in srgb,var(--acc) 16%,transparent);color:var(--acc);padding:4px 10px;border-radius:999px}
        .tag{color:var(--mut);font-size:14.5px;margin-top:4px}
        .meta{display:flex;flex-wrap:wrap;gap:14px;margin-top:12px;color:var(--mut);font-size:13.5px}
        .meta a{display:inline-flex;align-items:center;gap:6px}.meta i{color:var(--acc)}
        /* Category nav */
        .catnav{position:sticky;top:0;z-index:30;background:color-mix(in srgb,var(--bg) 85%,transparent);backdrop-filter:blur(10px);border-bottom:1px solid var(--bd);padding:12px 16px;display:flex;gap:8px;overflow-x:auto;margin-top:18px;-ms-overflow-style:none;scrollbar-width:none}
        .catnav::-webkit-scrollbar{display:none}
        .chip{white-space:nowrap;font:600 13.5px var(--fh);color:var(--fg);background:var(--surf);border:1px solid var(--bd);padding:8px 14px;border-radius:999px;cursor:pointer}
        .chip.on{background:var(--acc);color:#fff;border-color:transparent}
        /* Sections */
        .sec{padding:22px 16px 4px}
        .sec h2{font:700 18px var(--fh);display:flex;align-items:center;gap:9px;margin-bottom:14px}
        .item{display:flex;gap:14px;background:var(--surf);border:1px solid var(--bd);border-radius:16px;padding:14px;margin-bottom:12px}
        .item .ph{width:64px;height:64px;border-radius:13px;flex:0 0 64px;background:color-mix(in srgb,var(--acc) 10%,var(--surf));display:flex;align-items:center;justify-content:center;font-size:30px;object-fit:cover}
        .item .body{flex:1;min-width:0}
        .item .nm{font:700 15.5px var(--fh);display:flex;align-items:center;gap:8px;flex-wrap:wrap}
        .pillb{font:700 10.5px var(--fh);background:var(--acc);color:#fff;padding:2px 8px;border-radius:999px;text-transform:uppercase;letter-spacing:.04em}
        .item .ds{color:var(--mut);font-size:13.5px;margin-top:3px}
        .item .ft{display:flex;align-items:center;justify-content:space-between;margin-top:10px;gap:10px}
        .price{font:700 15px var(--fh);color:var(--acc)}
        .add{border:0;background:var(--acc);color:#fff;width:36px;height:36px;border-radius:11px;font-size:16px;cursor:pointer;flex:0 0 auto;transition:transform .12s}
        .add:active{transform:scale(.9)}
        .foot{text-align:center;margin:34px 0 10px;color:var(--mut);font-size:12px}.foot b{font-family:var(--fh);color:var(--fg)}
        /* Cart bar + drawer */
        .cartbar{position:fixed;left:0;right:0;bottom:0;z-index:40;display:none;justify-content:center;padding:14px 16px calc(14px + env(safe-area-inset-bottom))}
        .cartbar.show{display:flex}
        .cartbar button{width:100%;max-width:528px;border:0;background:var(--acc);color:#fff;border-radius:15px;padding:15px 20px;font:700 15.5px var(--fh);display:flex;align-items:center;justify-content:space-between;cursor:pointer;box-shadow:0 10px 30px rgba(0,0,0,.25)}
        .cartbar .cnt{background:rgba(255,255,255,.22);border-radius:999px;padding:2px 10px;font-size:13px}
        .sheet{position:fixed;inset:0;z-index:60;display:none}
        .sheet.show{display:block}
        .sheet .ov{position:absolute;inset:0;background:rgba(0,0,0,.5)}
        .sheet .pan{position:absolute;left:0;right:0;bottom:0;max-width:560px;margin:0 auto;background:var(--bg);border-radius:22px 22px 0 0;padding:18px 18px calc(18px + env(safe-area-inset-bottom));max-height:84vh;overflow:auto}
        .sheet h3{font:700 18px var(--fh);margin-bottom:6px;display:flex;justify-content:space-between;align-items:center}
        .sheet .x{background:none;border:0;color:var(--mut);font-size:22px;cursor:pointer}
        .crow{display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--bd)}
        .crow .cn{flex:1;font:600 14.5px var(--fh)}
        .crow .cp{color:var(--mut);font-size:13px}
        .qty{display:flex;align-items:center;gap:10px}
        .qty button{width:30px;height:30px;border-radius:9px;border:1px solid var(--bd);background:var(--surf);color:var(--fg);font-size:15px;cursor:pointer}
        .qty span{min-width:18px;text-align:center;font:700 14px var(--fh)}
        .tot{display:flex;justify-content:space-between;font:700 17px var(--fh);margin:16px 0}
        .cta{display:flex;flex-direction:column;gap:10px}
        .cta a,.cta button{width:100%;border:0;border-radius:14px;padding:15px;font:700 15px var(--fh);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:9px}
        .cta .wa{background:#25D366;color:#fff}
        .cta .pay{background:var(--acc);color:#fff}
        .cta .clr{background:transparent;color:var(--mut)}
        .empty{color:var(--mut);text-align:center;padding:30px 0}
        .custf{display:flex;flex-direction:column;gap:8px;margin:6px 0 14px}
        .cin{width:100%;padding:12px 14px;border:1px solid var(--bd);border-radius:11px;font:15px var(--fb);background:var(--surf);color:var(--fg)}
        .cin:focus{outline:0;border-color:var(--acc)}
        .cta button:disabled{opacity:.6;cursor:default}
        /* Type de commande + pourboire */
        .otype{display:flex;gap:8px;margin:6px 0 14px}
        .otbtn{flex:1;border:1px solid var(--bd);background:var(--surf);color:var(--fg);border-radius:11px;padding:10px 6px;font:600 12.5px var(--fh);cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:4px}
        .otbtn.on{background:var(--acc);color:#fff;border-color:transparent}
        .tiprow{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px;flex-wrap:wrap}
        .tiplbl{font:600 13.5px var(--fh);color:var(--mut)}
        .tipbtns{display:flex;gap:6px}
        .tipbtn{border:1px solid var(--bd);background:var(--surf);color:var(--fg);border-radius:9px;padding:7px 12px;font:700 12.5px var(--fh);cursor:pointer}
        .tipbtn.on{background:var(--acc);color:#fff;border-color:transparent}
        /* Options de plat (modificateurs) */
        .optgrp{margin-bottom:14px}
        .optgrp .oh{font:700 14px var(--fh);margin-bottom:8px;display:flex;align-items:center;gap:6px}
        .optgrp .req{font-size:10.5px;color:#fff;background:var(--acc);border-radius:999px;padding:1px 8px;text-transform:uppercase}
        .optchoice{display:flex;align-items:center;justify-content:space-between;padding:9px 0;border-bottom:1px solid var(--bd);font-size:14.5px;cursor:pointer}
        .optchoice:last-child{border-bottom:0}
        .optchoice input{margin-right:10px}
        .optchoice .od{color:var(--mut);font-size:13px}
        @media (prefers-reduced-motion:reduce){*{transition:none!important}}
    </style>
</head>
<body>
<div style="position:fixed;top:12px;right:12px;z-index:50">@include('tagtoa::partials.lang')</div>
<div class="wrap">
    <div class="cover" @if($menu->cover_url) style="background-image:url('{{ $menu->cover_url }}')" @endif></div>
    <div class="head">
        @if($menu->logo_url)<img class="logo" src="{{ $menu->logo_url }}" alt="">
        @else<div class="logo">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($menu->name,0,1)) }}</div>@endif
        <div class="title">{{ $menu->name }} <span class="badge-type"><i class="{{ $tm['icon'] }}"></i> {{ __($tm['label']) }}</span></div>
        @if($menu->tagline)<div class="tag">{{ $menu->tagline }}</div>@endif
        <div class="meta">
            @if($menu->address)<span><i class="fa-solid fa-location-dot"></i> {{ $menu->address }}</span>@endif
            @if($menu->phone)<a href="tel:{{ $menu->phone }}"><i class="fa-solid fa-phone"></i> {{ $menu->phone }}</a>@endif
            @if($menu->whatsapp_digits)<a href="https://wa.me/{{ $menu->whatsapp_digits }}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>@endif
        </div>
        @if($menu->description)<p class="tag" style="margin-top:12px">{{ $menu->description }}</p>@endif
    </div>

    @if($categories->isEmpty())
        <div class="sec"><div class="empty"><i class="fa-solid fa-utensils" style="font-size:30px;display:block;margin-bottom:10px;opacity:.4"></i>{{ __('Le menu arrive bientôt.') }}</div></div>
    @else
        <nav class="catnav" id="catnav">
            @foreach($categories as $c)
                <div class="chip" data-target="cat{{ $c->id }}">{{ $c->icon ? $c->icon.' ' : '' }}{{ $c->name }}</div>
            @endforeach
        </nav>

        @foreach($categories as $c)
            <section class="sec" id="cat{{ $c->id }}">
                <h2>{{ $c->icon ? $c->icon.' ' : '' }}{{ $c->name }}</h2>
                @foreach($c->availableItems as $it)
                    @php $out = ! $it->in_stock; @endphp
                    <div class="item" @if($out) style="opacity:.55" @endif>
                        @if($it->image_url)<img class="ph" src="{{ $it->image_url }}" alt="" loading="lazy">
                        @else<div class="ph">{{ $it->emoji ?: '🍽️' }}</div>@endif
                        <div class="body">
                            <div class="nm">{{ $it->name }} @if($it->badge)<span class="pillb">{{ $it->badge }}</span>@endif @if($out)<span class="pillb" style="background:var(--mut)">{{ __('Épuisé') }}</span>@endif</div>
                            @if($it->description)<div class="ds">{{ $it->description }}</div>@endif
                            <div class="ft">
                                @if($menu->show_prices)<span class="price">{{ \Modules\Tagtoa\App\Support\Money::format($it->price, $cur) }}</span>@else<span></span>@endif
                                @if($canOrder && ! $out)
                                    @php
                                        $opts = $it->options->map(fn ($o) => [
                                            'id' => $o->id, 'name' => $o->name, 'required' => $o->required, 'multiple' => $o->multiple,
                                            'choices' => $o->choices->map(fn ($c) => ['id' => $c->id, 'label' => $c->label, 'price_delta' => (float) $c->price_delta])->values(),
                                        ])->values();
                                    @endphp
                                    <button class="add" aria-label="{{ __('Ajouter') }}" data-id="{{ $it->id }}" data-name="{{ $it->name }}" data-price="{{ (float) $it->price }}" data-options='@json($opts)' onclick="add(this)"><i class="fa-solid fa-plus"></i></button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </section>
        @endforeach
    @endif

    @include('tagtoa::partials.reviews', ['subjectType' => 'menu', 'subjectId' => $menu->id, 'subjectAlias' => $menu->alias, 'reviews' => $reviews, 'summary' => $summary])

    <div class="foot">{{ __('Propulsé par') }} <b>TAGTOA</b> · tagtoa.com</div>
</div>

@if($canOrder)
    <div class="cartbar" id="cartbar">
        <button onclick="openCart()"><span><i class="fa-solid fa-bag-shopping"></i> {{ __('Voir la commande') }} <span class="cnt" id="cnt">0</span></span><span id="bartot">{{ \Modules\Tagtoa\App\Support\Money::format(0, $cur) }}</span></button>
    </div>

    <div class="sheet" id="sheet">
        <div class="ov" onclick="closeCart()"></div>
        <div class="pan">
            <h3>{{ __('Votre commande') }} <button class="x" onclick="closeCart()">&times;</button></h3>

            {{-- État 1 : panier + infos client --}}
            <div id="orderForm">
                <div id="clist"></div>

                <div class="otype" id="otype">
                    <button type="button" class="otbtn on" data-type="dine_in" onclick="setOrderType('dine_in')"><i class="fa-solid fa-utensils"></i> {{ __('Sur place') }}</button>
                    <button type="button" class="otbtn" data-type="pickup" onclick="setOrderType('pickup')"><i class="fa-solid fa-bag-shopping"></i> {{ __('À emporter') }}</button>
                    <button type="button" class="otbtn" data-type="delivery" onclick="setOrderType('delivery')"><i class="fa-solid fa-motorcycle"></i> {{ __('Livraison') }}</button>
                </div>

                <div class="tiprow">
                    <span class="tiplbl">{{ __('Pourboire') }}</span>
                    <div class="tipbtns" id="tipbtns">
                        <button type="button" class="tipbtn on" data-pct="0" onclick="setTipPct(0)">0%</button>
                        <button type="button" class="tipbtn" data-pct="5" onclick="setTipPct(5)">5%</button>
                        <button type="button" class="tipbtn" data-pct="10" onclick="setTipPct(10)">10%</button>
                        <button type="button" class="tipbtn" data-pct="15" onclick="setTipPct(15)">15%</button>
                    </div>
                </div>

                <div class="tot" style="font-weight:500;font-size:14px;color:var(--mut)"><span>{{ __('Sous-total') }}</span><span id="subtotal">{{ \Modules\Tagtoa\App\Support\Money::format(0, $cur) }}</span></div>
                <div class="tot" id="tiprowtot" style="display:none;font-weight:500;font-size:14px;color:var(--mut)"><span>{{ __('Pourboire') }}</span><span id="tipamt">{{ \Modules\Tagtoa\App\Support\Money::format(0, $cur) }}</span></div>
                <div class="tot"><span>{{ __('Total') }}</span><span id="total">{{ \Modules\Tagtoa\App\Support\Money::format(0, $cur) }}</span></div>

                <div class="custf">
                    <input id="cName" class="cin" placeholder="{{ __('Votre nom') }}" maxlength="120">
                    <input id="cPhone" class="cin" type="tel" placeholder="{{ __('Téléphone (WhatsApp)') }}" maxlength="40">
                    <input id="cTable" class="cin" placeholder="{{ __('N° table (optionnel)') }}" maxlength="40">
                    <input id="cAddress" class="cin" placeholder="{{ __('Adresse de livraison') }}" maxlength="200" style="display:none">
                </div>
                <div class="cta">
                    <button class="wa" id="confirmBtn" onclick="submitOrder()"><i class="fa-solid fa-bag-shopping"></i> {{ __('Confirmer la commande') }}</button>
                    <button class="clr" onclick="clearCart()">{{ __('Vider la commande') }}</button>
                </div>
            </div>

            {{-- État 2 : commande confirmée --}}
            <div id="orderDone" style="display:none">
                <div style="text-align:center;padding:14px 0 4px">
                    <div style="width:64px;height:64px;border-radius:50%;background:#25D366;color:#fff;display:flex;align-items:center;justify-content:center;font-size:30px;margin:0 auto 12px"><i class="fa-solid fa-check"></i></div>
                    <div style="font:700 18px var(--fh)">{{ __('Commande créée') }}</div>
                    <div style="color:var(--mut);margin-top:4px">{{ __('Référence') }} : <b id="okRef"></b></div>
                    <div style="color:var(--mut)">{{ __('Total') }} : <b id="okTotal"></b></div>
                </div>
                <div class="cta" style="margin-top:8px">
                    <a class="pay" id="okTrack" href="#"><i class="fa-solid fa-location-crosshairs"></i> {{ __('Suivre ma commande') }}</a>
                    <a class="wa" id="okWa" href="#" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> {{ __('Commander sur WhatsApp') }}</a>
                    <a class="pay" id="okPay" href="#" style="display:none"><i class="fa-solid fa-credit-card"></i> {{ __('Payer maintenant') }}</a>
                    <button class="clr" onclick="newOrder()">{{ __('Nouvelle') }}</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Sélecteur d'options d'un plat (taille, extras…) avant ajout au panier --}}
    <div class="sheet" id="modsheet">
        <div class="ov" onclick="closeMod()"></div>
        <div class="pan">
            <h3 id="modTitle">{{ __('Options') }} <button class="x" onclick="closeMod()">&times;</button></h3>
            <div id="modBody"></div>
            <div class="cta" style="margin-top:12px">
                <button class="wa" id="modAdd" onclick="confirmModAdd()"><i class="fa-solid fa-plus"></i> {{ __('Ajouter au panier') }}</button>
            </div>
        </div>
    </div>

    <script>
        var CURMETA = @json(\Modules\Tagtoa\App\Support\Money::meta($cur));
        var ORDER_URL = @json(route('tagtoa.menu.order', $menu->alias));
        var CSRF = document.querySelector('meta[name=csrf-token]').getAttribute('content');
        var T = { empty:@json(__('Votre commande est vide.')), confirm:@json(__('Confirmer la commande')), wait:@json(__('Patientez…')), err:@json(__('Réessayez.')), required:@json(__('Choisissez une option obligatoire.')) };
        var cart = {};
        var orderType = 'dine_in';
        var tipPct = 0;
        var modItem = null, modChosen = {};
        var ORDER_UUID = 'mo-' + Date.now().toString(36) + Math.random().toString(36).slice(2,10);
        function fmt(n){
            var d = (CURMETA.decimals==null) ? 2 : CURMETA.decimals;
            var s = Number(n).toLocaleString('en-US',{minimumFractionDigits:d,maximumFractionDigits:d});
            return CURMETA.position==='before' ? CURMETA.symbol+s : s+' '+CURMETA.symbol;
        }
        function val(id){ var e=document.getElementById(id); return e?e.value.trim():''; }
        function add(el){
            var opts = [];
            try { opts = JSON.parse(el.getAttribute('data-options') || '[]'); } catch(e){}
            var id = el.getAttribute('data-id'), name = el.getAttribute('data-name'), price = parseFloat(el.getAttribute('data-price'))||0;
            if (opts.length){ openMod(id, name, price, opts); return; }
            addToCart(id, name, price, [], '');
        }
        function addToCart(id, name, price, optionIds, optionLabel){
            var key = String(id) + (optionIds.length ? '|'+optionIds.slice().sort(function(a,b){return a-b;}).join(',') : '');
            if(!cart[key]) cart[key] = {id:Number(id), name:name, price:price, qty:0, options:optionIds, optionLabel:optionLabel};
            cart[key].qty++; render();
        }
        function chg(id,d){ if(!cart[id])return; cart[id].qty+=d; if(cart[id].qty<=0) delete cart[id]; render(); }
        function clearCart(){ cart={}; render(); closeCart(); }
        function totals(){ var n=0,t=0; for(var k in cart){ n+=cart[k].qty; t+=cart[k].qty*cart[k].price; } return {n:n,t:t}; }
        function tipAmount(subtotal){ return Math.round(subtotal*tipPct)/100; }
        function setOrderType(t){
            orderType = t;
            document.querySelectorAll('#otype .otbtn').forEach(function(b){ b.classList.toggle('on', b.getAttribute('data-type')===t); });
            document.getElementById('cTable').style.display = (t==='dine_in') ? '' : 'none';
            document.getElementById('cAddress').style.display = (t==='delivery') ? '' : 'none';
        }
        function setTipPct(p){
            tipPct = p;
            document.querySelectorAll('#tipbtns .tipbtn').forEach(function(b){ b.classList.toggle('on', Number(b.getAttribute('data-pct'))===p); });
            render();
        }
        function render(){
            var s = totals();
            var tip = tipAmount(s.t);
            document.getElementById('cnt').textContent = s.n;
            document.getElementById('bartot').textContent = fmt(s.t+tip);
            document.getElementById('subtotal').textContent = fmt(s.t);
            document.getElementById('tipamt').textContent = fmt(tip);
            document.getElementById('tiprowtot').style.display = tip>0 ? '' : 'none';
            document.getElementById('total').textContent = fmt(s.t+tip);
            document.getElementById('cartbar').classList.toggle('show', s.n>0);
            var list = document.getElementById('clist'), html='';
            if(s.n===0){ html = '<div class="empty">'+T.empty+'</div>'; }
            else { for(var k in cart){ var c=cart[k];
                html += '<div class="crow"><div class="cn">'+esc(c.name)+(c.optionLabel?'<div class="od" style="font-weight:400">'+esc(c.optionLabel)+'</div>':'')+'<div class="cp">'+fmt(c.price)+'</div></div>'+
                        '<div class="qty"><button onclick="chg(\''+k+'\',-1)">−</button><span>'+c.qty+'</span><button onclick="chg(\''+k+'\',1)">+</button></div></div>';
            }}
            list.innerHTML = html;
        }
        function openMod(id, name, price, opts){
            modItem = {id:id, name:name, price:price, opts:opts}; modChosen = {};
            document.getElementById('modTitle').innerHTML = esc(name)+' <button class="x" onclick="closeMod()">&times;</button>';
            var html = '';
            opts.forEach(function(g){
                modChosen[g.id] = [];
                html += '<div class="optgrp" data-gid="'+g.id+'"><div class="oh">'+esc(g.name)+(g.required?' <span class="req">'+@json(__('Requis'))+'</span>':'')+'</div>';
                g.choices.forEach(function(c){
                    var inputType = g.multiple ? 'checkbox' : 'radio';
                    html += '<label class="optchoice"><span><input type="'+inputType+'" name="grp'+g.id+'" onchange="toggleChoice('+g.id+','+c.id+',this,'+(g.multiple?'true':'false')+')"> '+esc(c.label)+'</span><span class="od">'+(c.price_delta ? (c.price_delta>0?'+':'')+fmt(c.price_delta) : '')+'</span></label>';
                });
                html += '</div>';
            });
            document.getElementById('modBody').innerHTML = html;
            document.getElementById('modsheet').classList.add('show');
        }
        function toggleChoice(gid, cid, el, multiple){
            if (!modChosen[gid]) modChosen[gid] = [];
            if (multiple){
                if (el.checked){ modChosen[gid].push(cid); } else { modChosen[gid] = modChosen[gid].filter(function(x){return x!==cid;}); }
            } else {
                modChosen[gid] = el.checked ? [cid] : [];
            }
        }
        function closeMod(){ document.getElementById('modsheet').classList.remove('show'); modItem=null; }
        function confirmModAdd(){
            if (!modItem) return;
            var ids = [], labels = [], extra = 0;
            for (var i=0;i<modItem.opts.length;i++){
                var g = modItem.opts[i]; var chosen = modChosen[g.id] || [];
                if (g.required && chosen.length===0){ alert(T.required); return; }
                chosen.forEach(function(cid){
                    var c = g.choices.find(function(x){return x.id===cid;});
                    if (c){ ids.push(cid); labels.push(c.label); extra += Number(c.price_delta)||0; }
                });
            }
            addToCart(modItem.id, modItem.name, modItem.price+extra, ids, labels.join(', '));
            closeMod(); openCart();
        }
        function submitOrder(){
            var s = totals(); if(s.n===0) return;
            var items=[]; for(var k in cart){ items.push({id:cart[k].id, qty:cart[k].qty, options:cart[k].options}); }
            var btn=document.getElementById('confirmBtn'); btn.disabled=true; var old=btn.innerHTML; btn.textContent=T.wait;
            fetch(ORDER_URL,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':CSRF},
                body:JSON.stringify({items:items,client_uuid:ORDER_UUID,channel:'menu',order_type:orderType,tip:tipAmount(s.t),
                    customer_name:val('cName'),customer_phone:val('cPhone'),table_label:val('cTable'),delivery_address:val('cAddress')})})
            .then(function(r){return r.json().then(function(j){return {ok:r.ok,j:j};});})
            .then(function(res){
                if(!res.ok||!res.j.ok){ throw new Error(res.j && res.j.message); }
                showConfirmed(res.j);
            })
            .catch(function(e){ btn.disabled=false; btn.innerHTML=old; alert((e && e.message) || T.err); });
        }
        function showConfirmed(j){
            document.getElementById('okRef').textContent = j.reference;
            document.getElementById('okTotal').textContent = j.total;
            var track=document.getElementById('okTrack');
            if(j.track_url){ track.href=j.track_url; track.style.display=''; } else { track.style.display='none'; }
            var wa=document.getElementById('okWa');
            if(j.whatsapp_url){ wa.href=j.whatsapp_url; wa.style.display=''; } else { wa.style.display='none'; }
            var pay=document.getElementById('okPay');
            var payUrl=j.checkout_url||j.pay_url; if(payUrl){ pay.href=payUrl; pay.style.display=''; } else { pay.style.display='none'; }
            document.getElementById('orderForm').style.display='none';
            document.getElementById('orderDone').style.display='';
        }
        function newOrder(){
            cart={}; ORDER_UUID='mo-'+Date.now().toString(36)+Math.random().toString(36).slice(2,10);
            document.getElementById('orderDone').style.display='none';
            document.getElementById('orderForm').style.display='';
            var b=document.getElementById('confirmBtn'); b.disabled=false; b.innerHTML='<i class="fa-solid fa-bag-shopping"></i> '+T.confirm;
            render(); closeCart();
        }
        function esc(x){ return String(x).replace(/[&<>"]/g,function(m){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m];}); }
        function openCart(){ document.getElementById('sheet').classList.add('show'); }
        function closeCart(){ document.getElementById('sheet').classList.remove('show'); }
        render();
    </script>
@endif

<script>
    // Surlignage de la catégorie active dans la barre de navigation.
    (function(){
        var chips = [].slice.call(document.querySelectorAll('.catnav .chip'));
        chips.forEach(function(ch){ ch.addEventListener('click', function(){
            var el = document.getElementById(ch.getAttribute('data-target'));
            if(el){ var y = el.getBoundingClientRect().top + window.pageYOffset - 64; window.scrollTo({top:y,behavior:'smooth'}); }
        }); });
        var secs = chips.map(function(ch){ return document.getElementById(ch.getAttribute('data-target')); });
        function onScroll(){
            var i = secs.length-1;
            for(var j=0;j<secs.length;j++){ if(secs[j] && secs[j].getBoundingClientRect().top<=90){ i=j; } }
            chips.forEach(function(c,k){ c.classList.toggle('on', k===i); });
        }
        window.addEventListener('scroll', onScroll, {passive:true}); onScroll();
    })();
</script>
</body>
</html>
