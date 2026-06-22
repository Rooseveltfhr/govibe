{{-- TAGTOA MENU — page publique (NFC/QR). Variables : $menu, $categories --}}
@php
    $dark = $menu->theme === 'dark';
    $accent = preg_match('/^#[0-9A-Fa-f]{3,8}$/', (string) $menu->accent_color) ? $menu->accent_color : '#0055FF';
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
    <title>{{ $menu->name }} — TAGTOA Menu</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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
        @media (prefers-reduced-motion:reduce){*{transition:none!important}}
    </style>
</head>
<body>
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
                    <div class="item">
                        @if($it->image_url)<img class="ph" src="{{ $it->image_url }}" alt="">
                        @else<div class="ph">{{ $it->emoji ?: '🍽️' }}</div>@endif
                        <div class="body">
                            <div class="nm">{{ $it->name }} @if($it->badge)<span class="pillb">{{ $it->badge }}</span>@endif</div>
                            @if($it->description)<div class="ds">{{ $it->description }}</div>@endif
                            <div class="ft">
                                @if($menu->show_prices)<span class="price">{{ number_format((float) $it->price, 2) }} {{ $cur }}</span>@else<span></span>@endif
                                @if($canOrder)
                                    <button class="add" aria-label="{{ __('Ajouter') }}" data-id="{{ $it->id }}" data-name="{{ $it->name }}" data-price="{{ (float) $it->price }}" onclick="add(this)"><i class="fa-solid fa-plus"></i></button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </section>
        @endforeach
    @endif

    <div class="foot">{{ __('Propulsé par') }} <b>TAGTOA</b> · tagtoa.com</div>
</div>

@if($canOrder)
    <div class="cartbar" id="cartbar">
        <button onclick="openCart()"><span><i class="fa-solid fa-bag-shopping"></i> {{ __('Voir la commande') }} <span class="cnt" id="cnt">0</span></span><span id="bartot">0 {{ $cur }}</span></button>
    </div>

    <div class="sheet" id="sheet">
        <div class="ov" onclick="closeCart()"></div>
        <div class="pan">
            <h3>{{ __('Votre commande') }} <button class="x" onclick="closeCart()">&times;</button></h3>
            <div id="clist"></div>
            <div class="tot"><span>{{ __('Total') }}</span><span id="total">0 {{ $cur }}</span></div>
            <div class="cta">
                <a class="wa" id="waBtn" href="#" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> {{ __('Commander sur WhatsApp') }}</a>
                @if($menu->payPage)<a class="pay" href="{{ url('/pay/'.$menu->payPage->alias) }}"><i class="fa-solid fa-credit-card"></i> {{ __('Payer maintenant') }}</a>@endif
                <button class="clr" onclick="clearCart()">{{ __('Vider la commande') }}</button>
            </div>
        </div>
    </div>

    <script>
        var CUR = @json($cur);
        var WA  = @json($menu->whatsapp_digits);
        var NAME = @json($menu->name);
        var cart = {};
        function fmt(n){ return n.toLocaleString('fr-FR',{minimumFractionDigits:2,maximumFractionDigits:2}) + ' ' + CUR; }
        function add(el){
            var id = el.getAttribute('data-id');
            if(!cart[id]) cart[id] = {name:el.getAttribute('data-name'), price:parseFloat(el.getAttribute('data-price'))||0, qty:0};
            cart[id].qty++; render();
        }
        function chg(id,d){ if(!cart[id])return; cart[id].qty+=d; if(cart[id].qty<=0) delete cart[id]; render(); }
        function clearCart(){ cart={}; render(); closeCart(); }
        function totals(){ var n=0,t=0; for(var k in cart){ n+=cart[k].qty; t+=cart[k].qty*cart[k].price; } return {n:n,t:t}; }
        function render(){
            var s = totals();
            document.getElementById('cnt').textContent = s.n;
            document.getElementById('bartot').textContent = fmt(s.t);
            document.getElementById('total').textContent = fmt(s.t);
            document.getElementById('cartbar').classList.toggle('show', s.n>0);
            var list = document.getElementById('clist'), html='';
            if(s.n===0){ html = '<div class="empty">'+@json(__('Votre commande est vide.'))+'</div>'; }
            else { for(var k in cart){ var c=cart[k];
                html += '<div class="crow"><div class="cn">'+esc(c.name)+'<div class="cp">'+fmt(c.price)+'</div></div>'+
                        '<div class="qty"><button onclick="chg(\''+k+'\',-1)">−</button><span>'+c.qty+'</span><button onclick="chg(\''+k+'\',1)">+</button></div></div>';
            }}
            list.innerHTML = html;
            updateWa(s);
        }
        function updateWa(s){
            var lines = [@json(__('Bonjour')) + ' ' + NAME + ', ' + @json(__('je voudrais commander :'))];
            for(var k in cart){ var c=cart[k]; lines.push('• '+c.qty+'x '+c.name+' — '+fmt(c.qty*c.price)); }
            lines.push(''); lines.push(@json(__('Total')) + ': ' + fmt(s.t));
            document.getElementById('waBtn').href = 'https://wa.me/'+WA+'?text='+encodeURIComponent(lines.join('\n'));
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
