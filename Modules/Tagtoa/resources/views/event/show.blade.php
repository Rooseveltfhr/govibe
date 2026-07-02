{{-- TAGTOA Event — vitrine publique + achat. Variables : $event --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $event->title }} — TAGTOA</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root{--blk:#0A0A0A;--blue:#2cb809;--bg:#F5F5F3;--bd:rgba(0,0,0,.08);--fh:'Space Grotesk',sans-serif;--fb:'Nunito',sans-serif}
        *{box-sizing:border-box;margin:0;padding:0}body{font-family:var(--fb);background:var(--bg);color:var(--blk);line-height:1.5}
        .wrap{max-width:480px;margin:0 auto;min-height:100vh;background:var(--bg);padding-bottom:90px}
        .cover{height:200px;background:linear-gradient(135deg,#0A0A0A,#239406);position:relative;overflow:hidden}.cover img{width:100%;height:100%;object-fit:cover}
        .tb{position:absolute;top:14px;left:14px;background:var(--blue);color:#fff;font:600 11px var(--fh);letter-spacing:.1em;text-transform:uppercase;padding:5px 11px;border-radius:999px}
        .body{padding:20px 18px}h1{font:700 23px var(--fh)}
        .meta{display:flex;flex-direction:column;gap:8px;margin:14px 0;color:#555;font-size:14px}.meta i{color:var(--blue);width:18px}
        .desc{font-size:14.5px;color:#444;margin:14px 0}
        .sec{font:600 13px var(--fh);letter-spacing:.05em;text-transform:uppercase;color:#777;margin:22px 0 12px}
        .tt{display:flex;align-items:center;gap:12px;background:#fff;border:1px solid var(--bd);border-radius:14px;padding:14px;margin-bottom:10px}
        .tt .info{flex:1}.tt .info b{font:600 15px var(--fh)}.tt .info span{display:block;font-size:12.5px;color:#888}
        .qty{display:flex;align-items:center;gap:8px}.qty button{width:30px;height:30px;border-radius:8px;border:1px solid var(--bd);background:#fff;font-size:16px;cursor:pointer}.qty input{width:38px;text-align:center;border:1px solid var(--bd);border-radius:8px;height:30px;font:600 14px var(--fh)}
        .form input{width:100%;padding:12px 14px;border:1.5px solid var(--bd);border-radius:12px;font:15px var(--fb);margin-bottom:10px}.form input:focus{outline:0;border-color:var(--blue)}
        .err{color:#E0473E;font-size:13px;margin-bottom:10px}
        .bar{position:fixed;bottom:0;left:50%;transform:translateX(-50%);width:100%;max-width:480px;background:#fff;border-top:1px solid var(--bd);padding:12px 16px;display:flex;align-items:center;gap:12px}
        .bar .tot{flex:1}.bar .tot small{font-size:11px;color:#888;display:block}.bar .tot b{font:700 19px var(--fh)}
        .btn{background:var(--blue);color:#fff;border:0;border-radius:12px;padding:14px 22px;font:600 15px var(--fh);cursor:pointer}
        .foot{text-align:center;padding:20px;color:#aaa;font-size:12px}.foot b{font-family:var(--fh);color:#777}
    </style>
</head>
<body>
<div style="position:fixed;top:12px;right:12px;z-index:50">@include('tagtoa::partials.lang')</div>
<form class="wrap" method="POST" action="{{ route('tagtoa.event.buy', $event->alias) }}">
    @csrf
    <div class="cover"><span class="tb">{{ $event->type }}</span>@if($event->cover_url)<img src="{{ $event->cover_url }}" alt="">@endif</div>
    <div class="body">
        <h1>{{ $event->title }}</h1>
        <div class="meta">
            @if($event->starts_at)<div><i class="fa-regular fa-calendar"></i> {{ $event->starts_at->format('d M Y · H:i') }}</div>@endif
            @if($event->venue)<div><i class="fa-solid fa-location-dot"></i> {{ $event->venue }}{{ $event->address ? ', '.$event->address : '' }}</div>@endif
            <div><i class="fa-solid fa-tag"></i> {{ $event->is_free ? __('Gratuit') : __('Billets payants') }}</div>
        </div>
        @if($event->description)<p class="desc">{{ $event->description }}</p>@endif
        @if($errors->any())<div class="err">{{ $errors->first() }}</div>@endif

        <p class="sec">{{ __('Billets') }}</p>
        @forelse($event->activeTicketTypes as $tt)
            <div class="tt">
                <div class="info"><b>{{ $tt->name }}</b><span>{{ $tt->price > 0 ? number_format($tt->price,2).' '.$event->currency : __('Gratuit') }}@if($tt->remaining !== null) · {{ $tt->remaining }} {{ __('restants') }}@endif</span></div>
                @if($tt->isOnSale())
                    <div class="qty"><button type="button" onclick="ev(-1,{{ $tt->id }})">−</button><input type="number" name="qty[{{ $tt->id }}]" id="q{{ $tt->id }}" value="0" min="0" max="{{ $tt->remaining ?? 50 }}" data-price="{{ $tt->price }}" readonly><button type="button" onclick="ev(1,{{ $tt->id }})">+</button></div>
                @else<span style="color:#E0473E;font-size:12px;font-weight:600">{{ __('Épuisé') }}</span>@endif
            </div>
        @empty<p style="color:#888;font-size:14px">{{ __('Aucun billet disponible.') }}</p>@endforelse

        <p class="sec">{{ __('Vos informations') }}</p>
        <div class="form">
            <input name="buyer_name" placeholder="{{ __('Nom complet') }}" required value="{{ old('buyer_name') }}">
            <input name="buyer_phone" placeholder="{{ __('Téléphone (WhatsApp)') }}" value="{{ old('buyer_phone') }}">
            <input name="buyer_email" type="email" placeholder="Email" value="{{ old('buyer_email') }}">
        </div>
    </div>
    <div class="foot">{{ __('Propulsé par') }} <b>TAGTOA</b></div>
    <div class="bar"><div class="tot"><small>{{ __('Total') }}</small><b><span id="tot">0.00</span> {{ $event->currency }}</b></div><button class="btn" type="submit"><i class="fa-solid fa-ticket"></i> {{ __('Obtenir') }}</button></div>
</form>
<script>
function ev(d,id){var i=document.getElementById('q'+id),v=Math.max(0,parseInt(i.value||0)+d),mx=parseInt(i.max||50);i.value=Math.min(v,mx);tot();}
function tot(){var s=0;document.querySelectorAll('[id^=q]').forEach(function(i){s+=parseInt(i.value||0)*parseFloat(i.dataset.price||0);});document.getElementById('tot').textContent=s.toFixed(2);}
</script>
</body>
</html>
