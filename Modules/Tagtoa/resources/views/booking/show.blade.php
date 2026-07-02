{{-- TAGTOA BOOKING — page publique de réservation (NFC/QR). Variables : $page, $services --}}
@php
    $dark = $page->theme === 'dark';
    $accent = preg_match('/^#[0-9A-Fa-f]{3,8}$/', (string) $page->accent_color) ? $page->accent_color : '#2cb809';
    $bg   = $dark ? '#0A0A0A' : '#F5F5F3';
    $fg   = $dark ? '#FFFFFF' : '#0A0A0A';
    $surf = $dark ? '#161616' : '#FFFFFF';
    $mut  = $dark ? 'rgba(255,255,255,.6)' : '#888888';
    $bd   = $dark ? 'rgba(255,255,255,.10)' : 'rgba(0,0,0,.08)';
    $cur  = $page->currency ?: 'HTG';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $page->name }} — TAGTOA</title>
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
        .wrap{max-width:560px;margin:0 auto;padding-bottom:40px}
        .cover{height:170px;background:linear-gradient(150deg,var(--acc),#0A0A0A);position:relative;background-size:cover;background-position:center}
        .cover::after{content:"";position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,.45),transparent 60%)}
        .head{padding:0 20px;margin-top:-44px;position:relative;z-index:2}
        .logo{width:84px;height:84px;border-radius:20px;border:3px solid var(--surf);background:var(--surf);object-fit:cover;display:flex;align-items:center;justify-content:center;font:700 30px var(--fh);color:var(--acc);box-shadow:0 8px 26px rgba(0,0,0,.18)}
        .title{font:700 24px var(--fh);margin-top:12px}
        .tag{color:var(--mut);font-size:14.5px;margin-top:4px}
        .meta{display:flex;flex-wrap:wrap;gap:14px;margin-top:12px;color:var(--mut);font-size:13.5px}
        .meta a{display:inline-flex;align-items:center;gap:6px}.meta i{color:var(--acc)}
        .sec{padding:22px 16px 4px}
        .sec h2{font:700 18px var(--fh);margin-bottom:14px}
        .svc{display:flex;gap:12px;align-items:center;background:var(--surf);border:1.5px solid var(--bd);border-radius:14px;padding:14px;margin-bottom:10px;cursor:pointer;transition:border-color .15s}
        .svc.on{border-color:var(--acc)}
        .svc .rd{width:22px;height:22px;border-radius:50%;border:2px solid var(--bd);flex:0 0 22px;position:relative}
        .svc.on .rd{border-color:var(--acc)}
        .svc.on .rd::after{content:"";position:absolute;inset:4px;border-radius:50%;background:var(--acc)}
        .svc .body{flex:1;min-width:0}
        .svc .nm{font:700 15.5px var(--fh)}
        .svc .ds{color:var(--mut);font-size:13px;margin-top:2px}
        .svc .dur{color:var(--mut);font-size:12.5px;margin-top:4px}
        .svc .price{font:700 15px var(--fh);color:var(--acc);white-space:nowrap}
        .field{margin:12px 16px}
        .lbl{display:block;font:600 12.5px var(--fh);color:var(--mut);margin:0 0 6px}
        .cin{width:100%;padding:13px 14px;border:1.5px solid var(--bd);border-radius:12px;font:15px var(--fb);background:var(--surf);color:var(--fg)}
        .cin:focus{outline:0;border-color:var(--acc)}
        .submit{margin:18px 16px}
        .submit button{width:100%;border:0;background:var(--acc);color:#fff;border-radius:15px;padding:16px;font:700 16px var(--fh);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:9px}
        .submit button:disabled{opacity:.6;cursor:default}
        .foot{text-align:center;margin:30px 0 10px;color:var(--mut);font-size:12px}.foot b{font-family:var(--fh);color:var(--fg)}
        .done{margin:0 16px;background:var(--surf);border:1.5px solid var(--bd);border-radius:16px;padding:24px;text-align:center;display:none}
        .done .ic{width:64px;height:64px;border-radius:50%;background:#25D366;color:#fff;display:flex;align-items:center;justify-content:center;font-size:30px;margin:0 auto 12px}
        .cta{display:flex;flex-direction:column;gap:10px;margin-top:16px}
        .cta a{width:100%;border:0;border-radius:14px;padding:15px;font:700 15px var(--fh);display:flex;align-items:center;justify-content:center;gap:9px}
        .cta .wa{background:#25D366;color:#fff}
        .cta .pay{background:var(--acc);color:#fff}
        @media (prefers-reduced-motion:reduce){*{transition:none!important}}
    </style>
</head>
<body>
<div style="position:fixed;top:12px;right:12px;z-index:50">@include('tagtoa::partials.lang')</div>
<div class="wrap">
    <div class="cover" @if($page->cover_url) style="background-image:url('{{ $page->cover_url }}')" @endif></div>
    <div class="head">
        @if($page->logo_url)<img class="logo" src="{{ $page->logo_url }}" alt="">
        @else<div class="logo">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($page->name,0,1)) }}</div>@endif
        <div class="title">{{ $page->name }}</div>
        @if($page->tagline)<div class="tag">{{ $page->tagline }}</div>@endif
        <div class="meta">
            @if($page->address)<span><i class="fa-solid fa-location-dot"></i> {{ $page->address }}</span>@endif
            @if($page->phone)<a href="tel:{{ $page->phone }}"><i class="fa-solid fa-phone"></i> {{ $page->phone }}</a>@endif
            @if($page->whatsapp_digits)<a href="https://wa.me/{{ $page->whatsapp_digits }}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>@endif
        </div>
        @if($page->about)<p class="tag" style="margin-top:12px">{{ $page->about }}</p>@endif
    </div>

    <form id="bookForm" onsubmit="submitBooking(event)">
        @if($services->isNotEmpty())
            <section class="sec">
                <h2>{{ __('Choisissez une prestation') }}</h2>
                @foreach($services as $s)
                    <label class="svc" data-id="{{ $s->id }}">
                        <input type="radio" name="service_id" value="{{ $s->id }}" style="display:none" onchange="selSvc(this)" @if($loop->first) checked @endif>
                        <span class="rd"></span>
                        <span class="body">
                            <span class="nm">{{ $s->name }}</span>
                            @if($s->description)<span class="ds" style="display:block">{{ $s->description }}</span>@endif
                            <span class="dur"><i class="fa-solid fa-clock"></i> {{ $s->duration_min }} {{ __('min') }}</span>
                        </span>
                        @if((float) $s->price > 0)<span class="price">{{ \Modules\Tagtoa\App\Support\Money::format($s->price, $cur) }}</span>@endif
                    </label>
                @endforeach
            </section>
        @endif

        <section class="sec"><h2>{{ __('Vos coordonnées') }}</h2></section>
        <div class="field"><label class="lbl">{{ __('Date & heure souhaitées') }}</label><input class="cin" type="datetime-local" id="starts_at" required></div>
        <div class="field"><label class="lbl">{{ __('Votre nom') }}</label><input class="cin" id="cName" maxlength="120" required></div>
        <div class="field"><label class="lbl">{{ __('Téléphone (WhatsApp)') }}</label><input class="cin" id="cPhone" type="tel" maxlength="40"></div>
        <div class="field"><label class="lbl">{{ __('E-mail (optionnel)') }}</label><input class="cin" id="cEmail" type="email" maxlength="160"></div>
        <div class="field"><label class="lbl">{{ __('Note (optionnel)') }}</label><textarea class="cin" id="cNote" rows="2" maxlength="500"></textarea></div>

        <div class="submit"><button type="submit" id="bookBtn"><i class="fa-solid fa-calendar-check"></i> {{ __('Réserver') }}</button></div>
    </form>

    <div class="done" id="done">
        <div class="ic"><i class="fa-solid fa-check"></i></div>
        <div style="font:700 18px var(--fh)">{{ __('Rendez-vous enregistré') }}</div>
        <div style="color:var(--mut);margin-top:4px">{{ __('Référence') }} : <b id="okRef"></b></div>
        <div style="color:var(--mut)" id="okWhen"></div>
        <div style="color:var(--mut)" id="okPrice"></div>
        <div class="cta">
            <a class="wa" id="okWa" href="#" target="_blank" rel="noopener" style="display:none"><i class="fa-brands fa-whatsapp"></i> {{ __('Confirmer sur WhatsApp') }}</a>
            <a class="pay" id="okPay" href="#" style="display:none"><i class="fa-solid fa-credit-card"></i> {{ __('Payer maintenant') }}</a>
        </div>
    </div>

    @include('tagtoa::partials.reviews', ['subjectType' => 'booking', 'subjectId' => $page->id, 'subjectAlias' => $page->alias, 'reviews' => $reviews, 'summary' => $summary])

    <div class="foot">{{ __('Propulsé par') }} <b>TAGTOA</b> · tagtoa.com</div>
</div>

<script>
    var RESERVE_URL = @json(route('tagtoa.booking.reserve', $page->alias));
    var CSRF = document.querySelector('meta[name=csrf-token]').getAttribute('content');
    var T = { wait:@json(__('Patientez…')), book:@json(__('Réserver')), err:@json(__('Réessayez.')) };
    var BOOK_UUID = 'bk-' + Date.now().toString(36) + Math.random().toString(36).slice(2,10);

    function selSvc(input){
        document.querySelectorAll('.svc').forEach(function(el){ el.classList.remove('on'); });
        var lbl = input.closest('.svc'); if(lbl) lbl.classList.add('on');
    }
    (function(){ var c = document.querySelector('input[name=service_id]:checked'); if(c) selSvc(c); })();

    function val(id){ var e=document.getElementById(id); return e?e.value.trim():''; }

    function submitBooking(e){
        e.preventDefault();
        var btn = document.getElementById('bookBtn'); btn.disabled=true; var old=btn.innerHTML; btn.textContent=T.wait;
        var svc = document.querySelector('input[name=service_id]:checked');
        var payload = {
            service_id: svc ? Number(svc.value) : null,
            starts_at: val('starts_at'),
            customer_name: val('cName'),
            customer_phone: val('cPhone'),
            customer_email: val('cEmail'),
            note: val('cNote'),
            client_uuid: BOOK_UUID
        };
        fetch(RESERVE_URL,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':CSRF},body:JSON.stringify(payload)})
        .then(function(r){return r.json().then(function(j){return {ok:r.ok,j:j};});})
        .then(function(res){
            if(!res.ok||!res.j.ok){ throw new Error(res.j && res.j.message ? res.j.message : ''); }
            showDone(res.j);
        })
        .catch(function(err){ btn.disabled=false; btn.innerHTML=old; alert(err.message || T.err); });
    }

    function showDone(j){
        document.getElementById('bookForm').style.display='none';
        document.getElementById('okRef').textContent = j.reference;
        document.getElementById('okWhen').textContent = j.starts_at ? (@json(__('Date')) + ' : ' + j.starts_at) : '';
        document.getElementById('okPrice').textContent = j.price ? (@json(__('Total')) + ' : ' + j.price) : '';
        var wa=document.getElementById('okWa'); if(j.whatsapp_url){ wa.href=j.whatsapp_url; wa.style.display=''; }
        var pay=document.getElementById('okPay'); if(j.pay_url){ pay.href=j.pay_url; pay.style.display=''; }
        document.getElementById('done').style.display='block';
        window.scrollTo({top:0,behavior:'smooth'});
    }
</script>
</body>
</html>
