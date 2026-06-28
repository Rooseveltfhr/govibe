{{-- TAGTOA SITE — site web public (vitrine). Variables : $site. Sans emoji. --}}
@php
    $dark = $site->theme === 'dark';
    $acc = preg_match('/^#[0-9A-Fa-f]{3,8}$/', (string) $site->accent_color) ? $site->accent_color : '#16A34A';
    $bg   = $dark ? '#0A0F0B' : '#FFFFFF';
    $bg2  = $dark ? '#0E1A12' : '#F5F7F5';
    $fg   = $dark ? '#FFFFFF' : '#0A0A0A';
    $mut  = $dark ? 'rgba(255,255,255,.6)' : '#667';
    $bd   = $dark ? 'rgba(255,255,255,.1)' : 'rgba(0,0,0,.08)';
    $sf   = $dark ? '#13241A' : '#FFFFFF';
    $services = $site->services ?? [];
    $hours = $site->hours ?? [];
    $socials = $site->socials ?? [];
    $gallery = $site->gallery ?? [];
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $site->name }}@if($site->tagline) — {{ $site->tagline }}@endif</title>
    <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($site->about ?: $site->tagline ?: $site->name), 150) }}">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root{--acc:{{ $acc }};--bg:{{ $bg }};--bg2:{{ $bg2 }};--fg:{{ $fg }};--mut:{{ $mut }};--bd:{{ $bd }};--sf:{{ $sf }};--fh:'Space Grotesk',sans-serif;--fb:'Nunito',sans-serif}
        *{box-sizing:border-box;margin:0;padding:0}
        html{scroll-behavior:smooth}
        body{font-family:var(--fb);background:var(--bg);color:var(--fg);line-height:1.65;-webkit-font-smoothing:antialiased}
        a{text-decoration:none;color:inherit}
        .wrap{max-width:1040px;margin:0 auto;padding:0 22px}
        .btn{display:inline-flex;align-items:center;gap:8px;border:0;border-radius:11px;padding:12px 22px;font:700 14px var(--fh);cursor:pointer;transition:transform .12s,filter .18s}
        .btn:active{transform:scale(.97)}
        .btn-p{background:var(--acc);color:#fff}.btn-p:hover{filter:brightness(1.08)}
        .btn-o{background:transparent;border:1.5px solid var(--bd);color:var(--fg)}
        /* Nav */
        .nav{position:sticky;top:0;z-index:40;background:color-mix(in srgb,var(--bg) 88%,transparent);backdrop-filter:blur(12px);border-bottom:1px solid var(--bd)}
        .nav .in{display:flex;align-items:center;gap:12px;height:62px}
        .nav .brand{display:flex;align-items:center;gap:10px;font:700 17px var(--fh)}
        .nav .brand img{width:34px;height:34px;border-radius:9px;object-fit:cover}
        .nav .brand .ph{width:34px;height:34px;border-radius:9px;background:var(--acc);color:#fff;display:flex;align-items:center;justify-content:center;font:700 16px var(--fh)}
        .nav .sp{flex:1}
        .nav .lk{color:var(--mut);font:600 14px var(--fh);padding:8px 10px}.nav .lk:hover{color:var(--acc)}
        @media(max-width:740px){.nav .lk{display:none}}
        /* Hero */
        .hero{position:relative;padding:72px 0;text-align:center;overflow:hidden}
        .hero.has-cover{padding:0}
        .cover{height:340px;position:relative;background:linear-gradient(150deg,var(--acc),#0A0A0A);background-size:cover;background-position:center}
        .cover::after{content:"";position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,.6),rgba(0,0,0,.15))}
        .hero-c{position:relative;z-index:2}
        .hero.has-cover .hero-c{margin-top:-150px;padding-bottom:40px;color:#fff}
        .logo{width:96px;height:96px;border-radius:24px;object-fit:cover;border:4px solid var(--bg);margin:0 auto 16px;display:block;box-shadow:0 14px 40px rgba(0,0,0,.25)}
        .logo.ph{background:var(--acc);color:#fff;display:flex;align-items:center;justify-content:center;font:700 38px var(--fh)}
        .hero h1{font:700 clamp(28px,5vw,46px) var(--fh);letter-spacing:-.02em}
        .hero .tag{color:var(--mut);font-size:clamp(15px,2vw,19px);margin-top:8px}
        .hero.has-cover .tag{color:rgba(255,255,255,.85)}
        .hero .cta{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:24px}
        /* Sections */
        section{padding:60px 0}
        .sec-h{text-align:center;margin-bottom:38px}
        .sec-h .ey{font:700 12px var(--fh);letter-spacing:.12em;text-transform:uppercase;color:var(--acc)}
        .sec-h h2{font:700 clamp(22px,3.4vw,32px) var(--fh);margin-top:8px}
        .about{max-width:720px;margin:0 auto;text-align:center;font-size:16.5px;color:var(--mut)}
        /* Services */
        .sgrid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
        .scard{background:var(--sf);border:1px solid var(--bd);border-radius:18px;padding:26px;transition:transform .15s,box-shadow .2s}
        .scard:hover{transform:translateY(-4px);box-shadow:0 16px 40px rgba(0,0,0,.1)}
        .scard .si{width:48px;height:48px;border-radius:13px;background:color-mix(in srgb,var(--acc) 14%,transparent);color:var(--acc);display:flex;align-items:center;justify-content:center;font-size:20px;margin-bottom:14px}
        .scard h3{font:700 17px var(--fh)}.scard p{color:var(--mut);font-size:14px;margin-top:6px}
        /* Gallery */
        .ggrid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
        .ggrid img{width:100%;aspect-ratio:1;object-fit:cover;border-radius:14px;border:1px solid var(--bd)}
        /* Hours + Contact */
        .two{display:grid;grid-template-columns:1fr 1fr;gap:24px}
        .panel{background:var(--sf);border:1px solid var(--bd);border-radius:18px;padding:26px}
        .panel h3{font:700 17px var(--fh);margin-bottom:16px;display:flex;align-items:center;gap:9px}.panel h3 i{color:var(--acc)}
        .hrow{display:flex;justify-content:space-between;padding:9px 0;border-bottom:1px dashed var(--bd);font-size:14.5px}.hrow:last-child{border:0}
        .hrow .d{color:var(--mut)}.hrow .v{font:600 14px var(--fh)}
        .crow{display:flex;align-items:center;gap:12px;padding:10px 0;font-size:14.5px}
        .crow i{width:34px;height:34px;border-radius:10px;background:color-mix(in srgb,var(--acc) 12%,transparent);color:var(--acc);display:flex;align-items:center;justify-content:center;flex-shrink:0}
        .crow a{word-break:break-word}
        .map{margin-top:16px;border-radius:14px;overflow:hidden;border:1px solid var(--bd)}.map iframe{width:100%;height:200px;border:0;display:block}
        /* Socials */
        .socials{display:flex;justify-content:center;gap:14px;flex-wrap:wrap}
        .socials a{width:46px;height:46px;border-radius:12px;background:var(--sf);border:1px solid var(--bd);display:flex;align-items:center;justify-content:center;font-size:19px;color:var(--fg);transition:all .18s}
        .socials a:hover{color:#fff;background:var(--acc);border-color:transparent;transform:translateY(-3px)}
        /* Footer */
        footer{background:var(--bg2);border-top:1px solid var(--bd);padding:36px 0;text-align:center;color:var(--mut);font-size:13px}
        footer b{font-family:var(--fh);color:var(--fg)}
        .lang-fixed{position:fixed;top:12px;right:12px;z-index:60}
        @media(max-width:760px){.sgrid{grid-template-columns:1fr}.ggrid{grid-template-columns:repeat(2,1fr)}.two{grid-template-columns:1fr}}
        @media (prefers-reduced-motion:reduce){*{transition:none!important}}
    </style>
</head>
<body>
<div class="lang-fixed">@include('tagtoa::partials.lang')</div>

<nav class="nav"><div class="wrap in">
    <span class="brand">
        @if($site->logo_url)<img src="{{ $site->logo_url }}" alt="">@else<span class="ph">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($site->name,0,1)) }}</span>@endif
        {{ $site->name }}
    </span>
    <span class="sp"></span>
    @if(!empty($services) && $site->show_services)<a class="lk" href="#services">{{ __('Services') }}</a>@endif
    @if($site->show_contact)<a class="lk" href="#contact">{{ __('Contact') }}</a>@endif
    @if($site->menu)<a class="btn btn-p" href="{{ url('/menu/'.$site->menu->alias) }}"><i class="fa-solid fa-utensils"></i> {{ __('Menu') }}</a>
    @elseif($site->payPage)<a class="btn btn-p" href="{{ url('/pay/'.$site->payPage->alias) }}"><i class="fa-solid fa-credit-card"></i> {{ __('Payer') }}</a>@endif
</div></nav>

<header class="hero {{ $site->cover_url ? 'has-cover' : '' }}">
    @if($site->cover_url)<div class="cover" style="background-image:url('{{ $site->cover_url }}')"></div>@endif
    <div class="hero-c"><div class="wrap">
        @if($site->logo_url)<img class="logo" src="{{ $site->logo_url }}" alt="">
        @else<div class="logo ph">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($site->name,0,1)) }}</div>@endif
        <h1>{{ $site->name }}</h1>
        @if($site->tagline)<p class="tag">{{ $site->tagline }}</p>@endif
        <div class="cta">
            @if($site->menu)<a class="btn btn-p" href="{{ url('/menu/'.$site->menu->alias) }}"><i class="fa-solid fa-utensils"></i> {{ __('Voir le menu') }}</a>@endif
            @if($site->payPage)<a class="btn {{ $site->menu ? 'btn-o' : 'btn-p' }}" href="{{ url('/pay/'.$site->payPage->alias) }}"><i class="fa-solid fa-credit-card"></i> {{ __('Payer') }}</a>@endif
            @if($site->whatsapp_digits)<a class="btn btn-o" href="https://wa.me/{{ $site->whatsapp_digits }}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>@endif
        </div>
    </div></div>
</header>

@if($site->about)
<section style="background:var(--bg2)"><div class="wrap">
    <div class="sec-h"><div class="ey">{{ __('À propos') }}</div><h2>{{ __('Qui sommes-nous') }}</h2></div>
    <p class="about">{!! nl2br(e($site->about)) !!}</p>
</div></section>
@endif

@if($site->show_services && !empty($services))
<section id="services"><div class="wrap">
    <div class="sec-h"><div class="ey">{{ __('Services') }}</div><h2>{{ __('Ce que nous offrons') }}</h2></div>
    <div class="sgrid">
        @foreach($services as $s)
            <div class="scard">
                <div class="si"><i class="{{ $s['icon'] ?? 'fa-solid fa-star' }}"></i></div>
                <h3>{{ $s['title'] ?? '' }}</h3>
                @if(!empty($s['desc']))<p>{{ $s['desc'] }}</p>@endif
            </div>
        @endforeach
    </div>
</div></section>
@endif

@if($site->show_gallery && !empty($gallery))
<section style="background:var(--bg2)"><div class="wrap">
    <div class="sec-h"><div class="ey">{{ __('Galerie') }}</div><h2>{{ __('En images') }}</h2></div>
    <div class="ggrid">
        @foreach($gallery as $img)<img src="{{ \Illuminate\Support\Facades\Storage::url($img) }}" alt="" loading="lazy">@endforeach
    </div>
</div></section>
@endif

@if($site->show_contact || ($site->show_hours && !empty($hours)))
<section id="contact"><div class="wrap">
    <div class="sec-h"><div class="ey">{{ __('Contact') }}</div><h2>{{ __('Nous trouver') }}</h2></div>
    <div class="two">
        @if($site->show_contact)
        <div class="panel">
            <h3><i class="fa-solid fa-location-dot"></i> {{ __('Coordonnées') }}</h3>
            @if($site->address)<div class="crow"><i class="fa-solid fa-location-dot"></i><span>{{ $site->address }}</span></div>@endif
            @if($site->phone)<div class="crow"><i class="fa-solid fa-phone"></i><a href="tel:{{ $site->phone }}">{{ $site->phone }}</a></div>@endif
            @if($site->whatsapp_digits)<div class="crow"><i class="fa-brands fa-whatsapp"></i><a href="https://wa.me/{{ $site->whatsapp_digits }}" target="_blank" rel="noopener">WhatsApp</a></div>@endif
            @if($site->email)<div class="crow"><i class="fa-solid fa-envelope"></i><a href="mailto:{{ $site->email }}">{{ $site->email }}</a></div>@endif
            @if($site->map_url)<div class="map"><iframe src="{{ $site->map_url }}" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe></div>@endif
        </div>
        @endif
        @if($site->show_hours && !empty($hours))
        <div class="panel">
            <h3><i class="fa-solid fa-clock"></i> {{ __('Heures d\'ouverture') }}</h3>
            @foreach($hours as $h)
                <div class="hrow"><span class="d">{{ $h['day'] ?? '' }}</span><span class="v">{{ $h['value'] ?? '' }}</span></div>
            @endforeach
        </div>
        @endif
    </div>
</div></section>
@endif

@if(!empty($socials) || $site->linkPage)
<section style="background:var(--bg2)"><div class="wrap">
    <div class="sec-h"><div class="ey">{{ __('Suivez-nous') }}</div><h2>{{ __('Restons connectés') }}</h2></div>
    <div class="socials">
        @foreach($socials as $s)
            <a href="{{ $s['url'] }}" target="_blank" rel="noopener" aria-label="{{ $s['platform'] ?? 'lien' }}"><i class="{{ $site->socialIcon($s['platform'] ?? 'website') }}"></i></a>
        @endforeach
        @if($site->linkPage)<a href="{{ url('/links/'.$site->linkPage->alias) }}" aria-label="Linktree"><i class="fa-solid fa-link"></i></a>@endif
    </div>
</div></section>
@endif

<footer><div class="wrap">
    <div style="margin-bottom:8px">{{ $site->name }}</div>
    {{ __('Propulsé par') }} <b>TAGTOA</b> · tagtoa.com
</div></footer>
</body>
</html>
