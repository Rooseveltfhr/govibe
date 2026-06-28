{{-- TAGTOA — page d'accueil publique (landing), multilingue, mobile-first. --}}
@php
    $modules = [
        ['menu',    'fa-utensils',           'Menu',        'Menu digital NFC/QR : restaurant, club, lounge, hôtel.', url('/menu/demo-menu')],
        ['pay',     'fa-money-bill-transfer','Paiements',   'MonCash, NatCash, PayPal, crypto, virements…', url('/pay/demo')],
        ['loyalty', 'fa-id-card',            'Fidélité',    'Cartes NFC de fidélité, points et récompenses.', url('/tagtoa/loyalty')],
        ['links',   'fa-link',               'Liens',       'Page de liens style Linktree + don.', url('/links/demo-links')],
        ['event',   'fa-ticket',             'Événements',  'Billetterie + check-in NFC/QR.', url('/event/demo-concert')],
        ['pos',     'fa-cash-register',      'Caisse (POS)','Caisse tactile offline-first, multi-paiement.', url('/login')],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TAGTOA — {{ __('Tout votre business sur une touche') }}</title>
    <meta name="description" content="{{ __('Menu, paiements, fidélité, événements — NFC & QR pour Haïti.') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root{--blk:#0A0A0A;--bg:#F5F5F3;--sf:#fff;--blue:#0055FF;--blue-deep:#0040CC;--blue-pale:rgba(0,85,255,.08);--mut:#8a8a8a;--bd:rgba(0,0,0,.08);--fh:'Space Grotesk',sans-serif;--fb:'Nunito',sans-serif}
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:var(--fb);background:var(--bg);color:var(--blk);line-height:1.6;-webkit-font-smoothing:antialiased}
        a{text-decoration:none;color:inherit}
        .wrap{max-width:1080px;margin:0 auto;padding:0 20px}
        /* Nav */
        .nav{position:sticky;top:0;z-index:40;background:rgba(245,245,243,.85);backdrop-filter:blur(10px);border-bottom:1px solid var(--bd)}
        .nav .in{display:flex;align-items:center;gap:14px;padding:14px 0}
        .brand{display:flex;align-items:center;gap:9px;font-family:var(--fh);font-weight:700;font-size:19px}
        .brand .lg{width:32px;height:32px;border-radius:9px;background:var(--blue);color:#fff;display:flex;align-items:center;justify-content:center;font-size:16px}
        .nav .sp{flex:1}
        .btn{display:inline-flex;align-items:center;gap:8px;border:0;border-radius:11px;padding:11px 18px;font:600 14px var(--fh);cursor:pointer;transition:transform .12s,filter .15s}
        .btn:active{transform:scale(.97)}
        .btn-p{background:var(--blue);color:#fff}.btn-p:hover{filter:brightness(1.06)}
        .btn-o{background:#fff;border:1.5px solid var(--bd);color:var(--blk)}
        .btn-g{background:rgba(255,255,255,.14);color:#fff;border:1px solid rgba(255,255,255,.25)}
        /* Hero */
        .hero{background:linear-gradient(160deg,#0040CC,#0A0A0A);color:#fff;border-radius:0 0 32px 32px;overflow:hidden;position:relative}
        .hero::after{content:"";position:absolute;right:-80px;top:-80px;width:340px;height:340px;background:radial-gradient(circle,var(--blue) 0%,transparent 70%);opacity:.4}
        .hero .in{padding:64px 0 72px;position:relative;z-index:2;text-align:center}
        .tagpill{display:inline-flex;align-items:center;gap:7px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.22);padding:6px 14px;border-radius:999px;font:600 12px var(--fh);letter-spacing:.06em;text-transform:uppercase}
        .hero h1{font:700 clamp(30px,6vw,52px)/1.08 var(--fh);margin:18px auto 14px;max-width:760px}
        .hero p{font-size:clamp(15px,2.5vw,19px);opacity:.85;max-width:560px;margin:0 auto 28px}
        .cta{display:flex;gap:12px;justify-content:center;flex-wrap:wrap}
        .cta .btn{padding:14px 24px;font-size:15px}
        /* Modules */
        .sec{padding:56px 0 8px;text-align:center}
        .sec h2{font:700 clamp(24px,4vw,34px) var(--fh)}
        .sec .lead{color:var(--mut);max-width:560px;margin:10px auto 0}
        .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:34px}
        .card{background:var(--sf);border:1px solid var(--bd);border-radius:18px;padding:24px;text-align:left;transition:transform .14s,box-shadow .18s;display:block}
        .card:hover{transform:translateY(-3px);box-shadow:0 14px 38px rgba(0,0,0,.08)}
        .card .ic{width:50px;height:50px;border-radius:13px;background:var(--blue-pale);color:var(--blue-deep);display:flex;align-items:center;justify-content:center;font-size:22px;margin-bottom:14px}
        .card h3{font:700 18px var(--fh)}
        .card p{color:var(--mut);font-size:14px;margin-top:5px}
        .card .go{display:inline-flex;align-items:center;gap:6px;color:var(--blue);font:600 13.5px var(--fh);margin-top:12px}
        /* Values */
        .vals{margin-top:60px}
        .vrow{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
        .v{background:var(--sf);border:1px solid var(--bd);border-radius:16px;padding:20px;text-align:center}
        .v i{font-size:24px;color:var(--blue);margin-bottom:10px}
        .v b{display:block;font-family:var(--fh);font-size:15px}
        .v span{font-size:13px;color:var(--mut)}
        /* CTA band */
        .band{margin:64px 0 0;background:var(--blk);color:#fff;border-radius:24px;padding:46px 24px;text-align:center}
        .band h2{font:700 clamp(22px,4vw,30px) var(--fh);max-width:620px;margin:0 auto 8px}
        .band p{opacity:.8;margin-bottom:22px}
        .foot{text-align:center;padding:34px 0 40px;color:var(--mut);font-size:13px}
        .foot b{font-family:var(--fh);color:var(--blk)}
        @media(max-width:820px){.grid{grid-template-columns:1fr 1fr}.vrow{grid-template-columns:1fr 1fr}}
        @media(max-width:520px){.grid{grid-template-columns:1fr}.nav .brand b{font-size:17px}}
        @media (prefers-reduced-motion:reduce){*{transition:none!important}}
    </style>
</head>
<body>
    <nav class="nav"><div class="wrap in">
        <span class="brand"><span class="lg">⚡</span><b>TAGTOA</b></span>
        <span class="sp"></span>
        @include('tagtoa::partials.lang')
        <a class="btn btn-o" href="{{ url('/login') }}"><i class="fa-solid fa-arrow-right-to-bracket"></i> {{ __('Se connecter') }}</a>
    </div></nav>

    <header class="hero"><div class="wrap in">
        <span class="tagpill"><i class="fa-solid fa-wifi"></i> NFC · QR · {{ __('Haïti') }}</span>
        <h1>{{ __('Tout votre business sur une touche') }}</h1>
        <p>{{ __('Menu, paiements, fidélité, événements et caisse — réunis dans une seule plateforme NFC & QR, en créole, français, anglais et espagnol.') }}</p>
        <div class="cta">
            <a class="btn btn-p" href="{{ url('/menu/demo-menu') }}"><i class="fa-solid fa-play"></i> {{ __('Voir la démo') }}</a>
            <a class="btn btn-g" href="{{ url('/login') }}"><i class="fa-solid fa-rocket"></i> {{ __('Démarrer') }}</a>
        </div>
    </div></header>

    <main class="wrap">
        <section class="sec">
            <h2>{{ __('Vos outils TAGTOA') }}</h2>
            <p class="lead">{{ __('Chaque module fonctionne seul ou ensemble. Cliquez pour voir une démo.') }}</p>
            <div class="grid">
                @foreach($modules as $m)
                    <a class="card" href="{{ $m[4] }}" @if(\Illuminate\Support\Str::startsWith($m[4],['http']) && !\Illuminate\Support\Str::contains($m[4],'/login')) target="_blank" rel="noopener" @endif>
                        <div class="ic"><i class="fa-solid {{ $m[1] }}"></i></div>
                        <h3>{{ __($m[2]) }}</h3>
                        <p>{{ __($m[3]) }}</p>
                        <span class="go">{{ __('Voir la démo') }} <i class="fa-solid fa-arrow-right"></i></span>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="vals">
            <div class="vrow">
                <div class="v"><i class="fa-solid fa-globe"></i><b>{{ __('4 langues') }}</b><span>Kreyòl · FR · EN · ES</span></div>
                <div class="v"><i class="fa-solid fa-money-bill-wave"></i><b>{{ __('Paiements locaux') }}</b><span>MonCash · NatCash · {{ __('banques') }}</span></div>
                <div class="v"><i class="fa-brands fa-bitcoin"></i><b>{{ __('International') }}</b><span>PayPal · {{ __('cartes') }} · crypto</span></div>
                <div class="v"><i class="fa-solid fa-wifi"></i><b>NFC + QR</b><span>{{ __('Sans application') }}</span></div>
            </div>
        </section>

        <section class="band">
            <h2>{{ __('Prêt à digitaliser votre business ?') }}</h2>
            <p>{{ __('Créez votre menu, recevez des paiements et fidélisez vos clients dès aujourd\'hui.') }}</p>
            <div class="cta">
                <a class="btn btn-p" href="{{ url('/login') }}"><i class="fa-solid fa-rocket"></i> {{ __('Démarrer') }}</a>
                <a class="btn btn-g" href="{{ url('/menu/demo-menu') }}"><i class="fa-solid fa-play"></i> {{ __('Voir la démo') }}</a>
            </div>
        </section>
    </main>

    <div class="foot">© {{ date('Y') }} <b>TAGTOA</b> · GOVIBE Ecosystem · tagtoa.com</div>
</body>
</html>
