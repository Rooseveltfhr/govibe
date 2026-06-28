{{-- TAGTOA — page d'accueil premium (landing). Multilingue, sans emoji (icônes only). --}}
@php
    $services = [
        ['fa-globe',                'Site web',     'Création de site web professionnel par abonnement — vitrine, boutique, réservation.', url('/site/demo-site'), true],
        ['fa-utensils',            'Menu digital', 'Restaurant, club, lounge, hôtel : menu NFC/QR, commande WhatsApp, paiement.', url('/menu/demo-menu'), false],
        ['fa-money-bill-transfer', 'Paiements',    'MonCash, NatCash, cartes, PayPal, crypto — un seul lien, un seul QR.', url('/pay/demo'), false],
        ['fa-id-card',             'Fidélité',     'Cartes NFC de fidélité, points et récompenses automatiques.', url('/login'), false],
        ['fa-link',                'Liens & Bio',  'Page de liens style Linktree avec dons et paiements intégrés.', url('/links/demo-links'), false],
        ['fa-ticket',              'Événements',   'Billetterie en ligne + contrôle d\'accès NFC/QR à l\'entrée.', url('/event/demo-concert'), false],
        ['fa-cash-register',       'Caisse POS',   'Caisse tactile hors-ligne, multi-paiement, rapport de caisse.', url('/login'), false],
        ['fa-address-card',        'Carte de visite', 'Carte de visite digitale NFC : profil, contacts, réseaux en un tap.', url('/login'), false],
    ];
    $methods = [
        ['fa-mobile-screen-button','MonCash','#E2001A'], ['fa-mobile-screen','NatCash','#00A859'],
        ['fa-brands fa-cc-visa','Visa','#1A1F71'], ['fa-brands fa-cc-mastercard','Mastercard','#EB001B'],
        ['fa-brands fa-paypal','PayPal','#003087'], ['fa-dollar-sign','USDT','#26A17B'],
        ['fa-brands fa-bitcoin','Bitcoin','#F7931A'], ['fa-brands fa-ethereum','Ethereum','#627EEA'],
        ['fa-building-columns','Virements','#94a3b8'], ['fa-money-bill-wave','Cash','#1D9E75'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TAGTOA — {{ __('La plateforme business tout-en-un pour Haïti') }}</title>
    <meta name="description" content="{{ __('Site web, menu, paiements, fidélité, événements et caisse — NFC & QR. La plateforme digitale des entrepreneurs haïtiens.') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root{
            --ink:#08110B;--surface:#0E1A12;--surface2:#15271B;--bd:rgba(255,255,255,.08);--bd2:rgba(255,255,255,.14);
            --green:#16A34A;--green2:#15803D;--emerald:#34D399;--gold:#F5C842;--gold2:#E8A820;
            --white:#fff;--muted:rgba(255,255,255,.55);--muted2:rgba(255,255,255,.3);
            --g-green:linear-gradient(135deg,#16A34A,#15803D);--g-em:linear-gradient(135deg,#34D399,#16A34A);
            --fh:'Space Grotesk',sans-serif;--fb:'Nunito',sans-serif;
        }
        *{box-sizing:border-box;margin:0;padding:0}
        html{scroll-behavior:smooth}
        body{font-family:var(--fb);background:var(--ink);color:var(--white);line-height:1.6;overflow-x:hidden;-webkit-font-smoothing:antialiased}
        a{text-decoration:none;color:inherit}
        .wrap{max-width:1140px;margin:0 auto;padding:0 24px}
        .glow{position:fixed;border-radius:50%;pointer-events:none;z-index:0}
        .glow.a{top:-200px;left:-160px;width:560px;height:560px;background:radial-gradient(circle,rgba(22,163,74,.10),transparent 70%)}
        .glow.b{bottom:-220px;right:-180px;width:640px;height:640px;background:radial-gradient(circle,rgba(245,200,66,.05),transparent 70%)}
        /* Nav */
        .nav{position:sticky;top:0;z-index:50;background:rgba(8,17,11,.72);backdrop-filter:blur(20px);border-bottom:1px solid var(--bd)}
        .nav .in{display:flex;align-items:center;gap:14px;height:66px}
        .brand{display:flex;align-items:center;gap:10px;font:700 19px var(--fh);letter-spacing:-.02em}
        .brand .lg{width:36px;height:36px;border-radius:10px;background:var(--g-green);display:flex;align-items:center;justify-content:center;box-shadow:0 4px 16px rgba(22,163,74,.4)}
        .brand .lg i{color:#fff;font-size:17px}
        .nav .sp{flex:1}
        .btn{display:inline-flex;align-items:center;gap:8px;border:0;border-radius:11px;padding:11px 20px;font:700 14px var(--fh);cursor:pointer;transition:transform .12s,box-shadow .18s,background .2s;white-space:nowrap}
        .btn:active{transform:scale(.97)}
        .btn-p{background:var(--g-green);color:#fff;box-shadow:0 6px 22px rgba(22,163,74,.36)}.btn-p:hover{box-shadow:0 10px 30px rgba(22,163,74,.5)}
        .btn-o{background:rgba(255,255,255,.05);border:1px solid var(--bd2);color:#fff}.btn-o:hover{background:rgba(255,255,255,.1)}
        .btn-gold{background:linear-gradient(135deg,var(--gold),var(--gold2));color:var(--ink);box-shadow:0 6px 22px rgba(245,200,66,.3)}
        .btn-lg{padding:15px 26px;font-size:15px}
        @media(max-width:640px){.nav .hidem{display:none}}
        /* Hero */
        .hero{position:relative;z-index:1;padding:84px 0 72px}
        .hero .in{display:grid;grid-template-columns:1.1fr .9fr;gap:56px;align-items:center}
        .pill{display:inline-flex;align-items:center;gap:8px;background:rgba(52,211,153,.08);border:1px solid rgba(52,211,153,.22);color:var(--emerald);padding:6px 14px;border-radius:999px;font:700 11px var(--fh);letter-spacing:.1em;text-transform:uppercase}
        .pill .dot{width:6px;height:6px;border-radius:50%;background:var(--emerald);box-shadow:0 0 8px var(--emerald)}
        h1{font:700 clamp(34px,5.2vw,58px)/1.04 var(--fh);letter-spacing:-.03em;margin:18px 0 16px}
        .grad{background:var(--g-em);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
        .grad-gold{background:linear-gradient(135deg,var(--gold),var(--gold2));-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
        .hero p.sub{font-size:clamp(15px,2vw,18px);color:var(--muted);max-width:480px;margin-bottom:30px}
        .hero .cta{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:30px}
        .trust{display:flex;gap:10px;flex-wrap:wrap}
        .tb{display:inline-flex;align-items:center;gap:7px;background:rgba(255,255,255,.04);border:1px solid var(--bd);border-radius:9px;padding:8px 13px;font:600 12.5px var(--fb);color:var(--muted)}
        .tb i{color:var(--emerald);font-size:12px}
        /* Hero device */
        .device{position:relative;justify-self:center}
        .card3d{width:330px;height:208px;border-radius:20px;background:linear-gradient(135deg,#15271B,#0c1a12 60%,#102b1b);border:1px solid var(--bd2);padding:26px;position:relative;overflow:hidden;box-shadow:0 40px 90px rgba(0,0,0,.6)}
        .card3d::before{content:"";position:absolute;top:-90px;right:-90px;width:220px;height:220px;background:radial-gradient(circle,rgba(22,163,74,.25),transparent 70%)}
        .chip{width:42px;height:32px;border-radius:7px;background:linear-gradient(135deg,var(--gold),var(--gold2));margin-bottom:22px;position:relative}
        .chip::after{content:"";position:absolute;inset:4px;border:1px solid rgba(0,0,0,.25);border-radius:4px}
        .c-num{font:600 15px var(--fh);letter-spacing:.22em;color:rgba(255,255,255,.88);margin-bottom:16px}
        .c-bot{display:flex;justify-content:space-between;align-items:flex-end}
        .c-bot .nm{font:700 11px var(--fh);letter-spacing:.12em;text-transform:uppercase;color:var(--muted)}
        .c-bot .br{font:700 16px var(--fh);color:var(--emerald)}
        .wave{position:absolute;top:22px;right:22px;display:flex;gap:3px;align-items:center}
        .wave span{width:2px;border-radius:9px;background:var(--emerald)}
        .wave span:nth-child(1){height:12px;opacity:.4}.wave span:nth-child(2){height:18px;opacity:.65}.wave span:nth-child(3){height:24px}
        .float{position:absolute;background:var(--surface);border:1px solid var(--bd2);border-radius:13px;padding:10px 14px;display:flex;align-items:center;gap:10px;box-shadow:0 14px 34px rgba(0,0,0,.45);animation:bob 4s ease-in-out infinite}
        .float .fi{width:32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:14px}
        .float .fl-k{font-size:11px;color:var(--muted2)}.float .fl-v{font:800 14px var(--fh)}
        .f1{top:-22px;right:-26px}.f2{bottom:-20px;left:-30px;animation-delay:1.6s}
        @keyframes bob{0%,100%{transform:translateY(0)}50%{transform:translateY(-9px)}}
        /* Stats */
        .stats{border-top:1px solid var(--bd);border-bottom:1px solid var(--bd);background:var(--surface)}
        .stats .in{display:grid;grid-template-columns:repeat(4,1fr)}
        .st{padding:40px 24px;text-align:center;border-right:1px solid var(--bd)}
        .st:last-child{border-right:0}
        .st .n{font:700 34px var(--fh);line-height:1}.st .k{font-size:13px;color:var(--muted);margin-top:6px}
        /* Sections */
        section{position:relative;z-index:1;padding:84px 0}
        .ey{display:inline-flex;align-items:center;gap:9px;font:700 12px var(--fh);letter-spacing:.12em;text-transform:uppercase;color:var(--emerald);margin-bottom:14px}
        .ey::before{content:"";width:22px;height:2px;background:var(--g-em);border-radius:2px}
        .ey.gold{color:var(--gold)}.ey.gold::before{background:linear-gradient(135deg,var(--gold),var(--gold2))}
        h2{font:700 clamp(26px,3.4vw,40px) var(--fh);letter-spacing:-.025em;line-height:1.1}
        .lead{color:var(--muted);max-width:560px;margin-top:12px;font-size:15.5px}
        .center{text-align:center}.center .ey{justify-content:center}.center .lead{margin-left:auto;margin-right:auto}
        /* Services grid */
        .sgrid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-top:44px}
        .scard{background:var(--surface);border:1px solid var(--bd);border-radius:18px;padding:26px;transition:transform .16s,box-shadow .2s,border-color .2s;position:relative;overflow:hidden}
        .scard:hover{transform:translateY(-4px);border-color:rgba(22,163,74,.35);box-shadow:0 18px 44px rgba(0,0,0,.4)}
        .scard.feat{border-color:rgba(245,200,66,.3);background:linear-gradient(160deg,rgba(245,200,66,.06),var(--surface))}
        .scard .si{width:50px;height:50px;border-radius:13px;background:rgba(22,163,74,.12);border:1px solid rgba(22,163,74,.25);display:flex;align-items:center;justify-content:center;font-size:20px;color:var(--emerald);margin-bottom:16px}
        .scard.feat .si{background:rgba(245,200,66,.12);border-color:rgba(245,200,66,.3);color:var(--gold)}
        .scard h3{font:700 16.5px var(--fh)}
        .scard p{color:var(--muted);font-size:13.5px;margin-top:6px;min-height:54px}
        .scard .go{display:inline-flex;align-items:center;gap:6px;color:var(--emerald);font:700 13px var(--fh);margin-top:8px}
        .scard.feat .go{color:var(--gold)}
        .ribbon{position:absolute;top:14px;right:14px;background:linear-gradient(135deg,var(--gold),var(--gold2));color:var(--ink);font:800 9.5px var(--fh);letter-spacing:.06em;padding:3px 9px;border-radius:999px;text-transform:uppercase}
        /* How */
        .how{background:var(--surface)}
        .hgrid{display:grid;grid-template-columns:repeat(3,1fr);gap:0;margin-top:44px;border:1px solid var(--bd);border-radius:18px;overflow:hidden}
        .hstep{padding:36px 30px;border-right:1px solid var(--bd);position:relative}
        .hstep:last-child{border-right:0}
        .hstep .nu{position:absolute;top:18px;right:22px;font:700 52px var(--fh);color:rgba(255,255,255,.05)}
        .hstep .hi{width:50px;height:50px;border-radius:13px;background:rgba(22,163,74,.1);border:1px solid rgba(22,163,74,.22);display:flex;align-items:center;justify-content:center;font-size:19px;color:var(--emerald);margin-bottom:16px}
        .hstep h3{font:700 16px var(--fh);margin-bottom:6px}.hstep p{color:var(--muted);font-size:13.5px}
        /* Methods */
        .mgrid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-top:44px}
        .mcard{background:var(--surface);border:1px solid var(--bd);border-radius:16px;padding:24px 14px;text-align:center;transition:transform .16s,border-color .2s}
        .mcard:hover{transform:translateY(-3px);border-color:rgba(22,163,74,.3)}
        .mcard .mi{width:52px;height:52px;border-radius:14px;background:rgba(255,255,255,.05);display:flex;align-items:center;justify-content:center;font-size:22px;margin:0 auto 12px}
        .mcard .mn{font:700 13.5px var(--fh)}
        /* Pricing */
        .pricing{background:var(--surface)}
        .pgrid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-top:44px}
        .pcard{background:var(--ink);border:1.5px solid var(--bd);border-radius:20px;padding:34px;position:relative;transition:transform .2s}
        .pcard:hover{transform:translateY(-5px)}
        .pcard.pop{border-color:var(--gold);box-shadow:0 0 0 4px rgba(245,200,66,.08),0 24px 60px rgba(0,0,0,.4)}
        .pbadge{position:absolute;top:18px;right:18px;background:linear-gradient(135deg,var(--gold),var(--gold2));color:var(--ink);font:800 10px var(--fh);letter-spacing:.06em;padding:4px 11px;border-radius:999px;text-transform:uppercase}
        .ptier{font:700 12px var(--fh);letter-spacing:.12em;text-transform:uppercase;color:var(--muted)}
        .pname{font:700 22px var(--fh);margin:4px 0 14px}
        .price{display:flex;align-items:baseline;gap:6px;margin-bottom:6px}
        .price .a{font:700 34px var(--fh)}.price .per{color:var(--muted);font-size:13px}
        .pnote{color:var(--muted);font-size:13px;margin-bottom:20px}
        .pfeat{list-style:none;display:flex;flex-direction:column;gap:11px;margin:20px 0 26px}
        .pfeat li{display:flex;gap:10px;font-size:14px;color:rgba(255,255,255,.8)}
        .pfeat li i{color:var(--emerald);font-size:13px;margin-top:3px}
        .pfeat li.off{opacity:.35}.pfeat li.off i{color:var(--muted)}
        .pcard .btn{width:100%;justify-content:center}
        /* CTA */
        .ctaband{position:relative;z-index:1;padding:0 24px 80px}
        .ctabox{max-width:1140px;margin:0 auto;background:linear-gradient(135deg,var(--surface2),rgba(22,163,74,.08),var(--surface2));border:1px solid rgba(22,163,74,.2);border-radius:28px;padding:64px 32px;text-align:center;position:relative;overflow:hidden}
        .ctabox::before{content:"";position:absolute;top:-120px;left:50%;transform:translateX(-50%);width:420px;height:300px;background:radial-gradient(ellipse,rgba(22,163,74,.14),transparent 70%)}
        .ctabox h2{position:relative}.ctabox p{color:var(--muted);margin:10px 0 26px;position:relative}
        .ctabox .cta{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;position:relative}
        /* Footer */
        footer{background:var(--surface);border-top:1px solid var(--bd);padding:54px 0 28px;position:relative;z-index:1}
        .fgrid{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:40px;margin-bottom:36px}
        .fdesc{color:var(--muted);font-size:13.5px;margin-top:12px;max-width:280px}
        .fcol h5{font:700 14px var(--fh);margin-bottom:14px}
        .fcol a{display:block;color:var(--muted);font-size:13.5px;padding:5px 0;transition:color .2s}.fcol a:hover{color:var(--emerald)}
        .fbot{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;border-top:1px solid var(--bd);padding-top:22px;color:var(--muted2);font-size:12.5px}
        .soc{display:flex;gap:8px}
        .soc a{width:34px;height:34px;border-radius:9px;background:rgba(255,255,255,.04);border:1px solid var(--bd);display:flex;align-items:center;justify-content:center;color:var(--muted);transition:all .2s}
        .soc a:hover{color:var(--gold);border-color:rgba(245,200,66,.3)}
        @media(max-width:960px){.hero .in{grid-template-columns:1fr;gap:36px}.device{display:none}.sgrid{grid-template-columns:repeat(2,1fr)}.mgrid{grid-template-columns:repeat(3,1fr)}.hgrid{grid-template-columns:1fr}.hstep{border-right:0;border-bottom:1px solid var(--bd)}.pgrid{grid-template-columns:1fr}.stats .in{grid-template-columns:1fr 1fr}.st:nth-child(2){border-right:0}.fgrid{grid-template-columns:1fr 1fr}}
        @media(max-width:560px){.sgrid{grid-template-columns:1fr}.mgrid{grid-template-columns:1fr 1fr}.fgrid{grid-template-columns:1fr}}
        @media (prefers-reduced-motion:reduce){*{transition:none!important;animation:none!important}}
    </style>
</head>
<body>
<div class="glow a"></div><div class="glow b"></div>

<nav class="nav"><div class="wrap in">
    <a href="{{ url('/') }}" class="brand"><span class="lg"><i class="fa-solid fa-bolt"></i></span>TAGTOA</a>
    <span class="sp"></span>
    @include('tagtoa::partials.lang')
    <a class="btn btn-o hidem" href="{{ url('/login') }}"><i class="fa-solid fa-arrow-right-to-bracket"></i> {{ __('Se connecter') }}</a>
    <a class="btn btn-p" href="#plans"><i class="fa-solid fa-rocket"></i> {{ __('Démarrer') }}</a>
</div></nav>

<header class="hero"><div class="wrap in">
    <div>
        <span class="pill"><span class="dot"></span> NFC · QR · {{ __('Haïti') }}</span>
        <h1>{{ __('Toute votre') }} <span class="grad">{{ __('entreprise') }}</span> {{ __('en un seul tap') }}</h1>
        <p class="sub">{{ __('Site web, menu, paiements, fidélité, événements et caisse — réunis dans une seule plateforme NFC & QR. En créole, français, anglais et espagnol.') }}</p>
        <div class="cta">
            <a class="btn btn-p btn-lg" href="#plans"><i class="fa-solid fa-rocket"></i> {{ __('Démarrer') }}</a>
            <a class="btn btn-o btn-lg" href="{{ url('/menu/demo-menu') }}"><i class="fa-solid fa-play"></i> {{ __('Voir la démo') }}</a>
        </div>
        <div class="trust">
            <span class="tb"><i class="fa-solid fa-wifi"></i> {{ __('Sans contact NFC') }}</span>
            <span class="tb"><i class="fa-solid fa-qrcode"></i> QR {{ __('dynamique') }}</span>
            <span class="tb"><i class="fa-solid fa-shield-halved"></i> {{ __('Sécurisé SSL') }}</span>
            <span class="tb"><i class="fa-solid fa-language"></i> {{ __('4 langues') }}</span>
        </div>
    </div>
    <div class="device">
        <div class="card3d">
            <div class="wave"><span></span><span></span><span></span></div>
            <div class="chip"></div>
            <div class="c-num">•••• •••• •••• 4821</div>
            <div class="c-bot"><div class="nm">Jean Paul Baptiste</div><div class="br">TAGTOA</div></div>
        </div>
        <div class="float f1"><div class="fi" style="background:rgba(52,211,153,.14);color:var(--emerald)"><i class="fa-solid fa-wifi"></i></div><div><div class="fl-k">{{ __('Paiement reçu') }}</div><div class="fl-v">2,400 HTG</div></div></div>
        <div class="float f2"><div class="fi" style="background:rgba(245,200,66,.14);color:var(--gold)"><i class="fa-solid fa-circle-check"></i></div><div><div class="fl-k">{{ __('Confirmé') }}</div><div class="fl-v">2.1s</div></div></div>
    </div>
</div></header>

<div class="stats"><div class="wrap in">
    <div class="st"><div class="n grad">8</div><div class="k">{{ __('outils en un') }}</div></div>
    <div class="st"><div class="n grad-gold">10+</div><div class="k">{{ __('méthodes de paiement') }}</div></div>
    <div class="st"><div class="n grad">4</div><div class="k">{{ __('langues') }}</div></div>
    <div class="st"><div class="n grad-gold">24/7</div><div class="k">{{ __('disponible') }}</div></div>
</div></div>

<section><div class="wrap">
    <div class="center">
        <span class="ey">{{ __('La plateforme') }}</span>
        <h2>{{ __('Tous vos outils business, réunis') }}</h2>
        <p class="lead">{{ __('Créez votre présence digitale et encaissez — sans plusieurs apps, sans complications.') }}</p>
    </div>
    <div class="sgrid">
        @foreach($services as $s)
            <a class="scard {{ $s[4] ? 'feat' : '' }}" href="{{ $s[3] }}">
                @if($s[4])<span class="ribbon">{{ __('Nouveau') }}</span>@endif
                <div class="si"><i class="fa-solid {{ $s[0] }}"></i></div>
                <h3>{{ __($s[1]) }}</h3>
                <p>{{ __($s[2]) }}</p>
                <span class="go">{{ __('Découvrir') }} <i class="fa-solid fa-arrow-right"></i></span>
            </a>
        @endforeach
    </div>
</div></section>

<section class="how"><div class="wrap">
    <div class="center">
        <span class="ey gold">{{ __('Comment ça marche') }}</span>
        <h2>{{ __('En ligne en 3 étapes') }}</h2>
    </div>
    <div class="hgrid">
        <div class="hstep"><div class="nu">01</div><div class="hi"><i class="fa-solid fa-user-plus"></i></div><h3>{{ __('Créez votre compte') }}</h3><p>{{ __('Inscrivez-vous et choisissez vos outils : site, menu, paiements, fidélité…') }}</p></div>
        <div class="hstep"><div class="nu">02</div><div class="hi"><i class="fa-solid fa-sliders"></i></div><h3>{{ __('Personnalisez') }}</h3><p>{{ __('Ajoutez vos produits, vos méthodes de paiement et votre marque en quelques minutes.') }}</p></div>
        <div class="hstep"><div class="nu">03</div><div class="hi"><i class="fa-solid fa-qrcode"></i></div><h3>{{ __('Partagez & encaissez') }}</h3><p>{{ __('Partagez votre lien ou QR, tapez la carte NFC, et recevez vos paiements instantanément.') }}</p></div>
    </div>
</div></section>

<section><div class="wrap">
    <div class="center">
        <span class="ey">{{ __('Paiements') }}</span>
        <h2>{{ __('Acceptez tout paiement, local et international') }}</h2>
        <p class="lead">{{ __('Vos clients paient avec leur méthode préférée — un seul lien, un seul QR.') }}</p>
    </div>
    <div class="mgrid">
        @foreach($methods as $m)
            <div class="mcard"><div class="mi" style="color:{{ $m[2] }}"><i class="{{ \Illuminate\Support\Str::startsWith($m[0],'fa-brands') ? $m[0] : 'fa-solid '.$m[0] }}"></i></div><div class="mn">{{ $m[1] }}</div></div>
        @endforeach
    </div>
</div></section>

<section class="pricing" id="plans"><div class="wrap">
    <div class="center">
        <span class="ey gold">{{ __('Tarifs') }}</span>
        <h2>{{ __('Un abonnement pour chaque ambition') }}</h2>
        <p class="lead">{{ __('Commencez gratuitement. Évoluez quand votre business grandit.') }}</p>
    </div>
    <div class="pgrid">
        <div class="pcard">
            <div class="ptier">{{ __('Débutant') }}</div>
            <div class="pname">{{ __('Gratuit') }}</div>
            <div class="price"><span class="a grad">0</span><span class="per">HTG</span></div>
            <div class="pnote">{{ __('Petite commission par vente') }}</div>
            <ul class="pfeat">
                <li><i class="fa-solid fa-check"></i> {{ __('1 menu digital + lien de paiement') }}</li>
                <li><i class="fa-solid fa-check"></i> {{ __('MonCash & NatCash') }}</li>
                <li><i class="fa-solid fa-check"></i> QR Code</li>
                <li class="off"><i class="fa-solid fa-xmark"></i> {{ __('Site web') }}</li>
                <li class="off"><i class="fa-solid fa-xmark"></i> {{ __('Carte NFC') }}</li>
            </ul>
            <a class="btn btn-o" href="{{ url('/login') }}">{{ __('Démarrer gratuitement') }}</a>
        </div>
        <div class="pcard pop">
            <span class="pbadge"><i class="fa-solid fa-star"></i> {{ __('Populaire') }}</span>
            <div class="ptier">{{ __('Business') }}</div>
            <div class="pname">Pro</div>
            <div class="price"><span class="a grad-gold">1,500</span><span class="per">HTG / {{ __('mois') }}</span></div>
            <div class="pnote">{{ __('Commission réduite par vente') }}</div>
            <ul class="pfeat">
                <li><i class="fa-solid fa-check"></i> {{ __('Tout le plan Gratuit') }}</li>
                <li><i class="fa-solid fa-check"></i> {{ __('Site web professionnel') }}</li>
                <li><i class="fa-solid fa-check"></i> {{ __('Carte NFC TAGTOA') }}</li>
                <li><i class="fa-solid fa-check"></i> {{ __('Cartes, PayPal, crypto') }}</li>
                <li><i class="fa-solid fa-check"></i> {{ __('Fidélité, événements & caisse') }}</li>
                <li><i class="fa-solid fa-check"></i> {{ __('Analytics avancées') }}</li>
            </ul>
            <a class="btn btn-gold" href="{{ url('/login') }}">{{ __('Choisir Pro') }}</a>
        </div>
        <div class="pcard">
            <div class="ptier">{{ __('Entreprise') }}</div>
            <div class="pname">Enterprise</div>
            <div class="price"><span class="a grad">{{ __('Sur devis') }}</span></div>
            <div class="pnote">{{ __('Volume & intégrations sur mesure') }}</div>
            <ul class="pfeat">
                <li><i class="fa-solid fa-check"></i> {{ __('Tout le plan Pro') }}</li>
                <li><i class="fa-solid fa-check"></i> {{ __('Cartes NFC en volume') }}</li>
                <li><i class="fa-solid fa-check"></i> {{ __('Marque blanche (white-label)') }}</li>
                <li><i class="fa-solid fa-check"></i> {{ __('API & intégration POS') }}</li>
                <li><i class="fa-solid fa-check"></i> {{ __('Support dédié 24/7') }}</li>
            </ul>
            <a class="btn btn-o" href="{{ url('/login') }}">{{ __('Contacter l\'équipe') }}</a>
        </div>
    </div>
</div></section>

<div class="ctaband"><div class="ctabox">
    <h2>{{ __('Prêt à digitaliser votre business ?') }}</h2>
    <p>{{ __('Rejoignez les entrepreneurs haïtiens qui vendent partout, à tout moment.') }}</p>
    <div class="cta">
        <a class="btn btn-p btn-lg" href="{{ url('/login') }}"><i class="fa-solid fa-rocket"></i> {{ __('Démarrer') }}</a>
        <a class="btn btn-o btn-lg" href="{{ url('/menu/demo-menu') }}"><i class="fa-solid fa-play"></i> {{ __('Voir la démo') }}</a>
    </div>
</div></div>

<footer><div class="wrap">
    <div class="fgrid">
        <div>
            <span class="brand"><span class="lg"><i class="fa-solid fa-bolt"></i></span>TAGTOA</span>
            <p class="fdesc">{{ __('La plateforme business tout-en-un pour Haïti : site web, paiements, menu, fidélité et plus — NFC & QR.') }}</p>
        </div>
        <div class="fcol"><h5>{{ __('Produit') }}</h5>
            <a href="{{ url('/menu/demo-menu') }}">{{ __('Menu digital') }}</a>
            <a href="{{ url('/pay/demo') }}">{{ __('Paiements') }}</a>
            <a href="{{ url('/links/demo-links') }}">{{ __('Liens & Bio') }}</a>
            <a href="{{ url('/event/demo-concert') }}">{{ __('Événements') }}</a>
        </div>
        <div class="fcol"><h5>{{ __('Entreprise') }}</h5>
            <a href="#plans">{{ __('Tarifs') }}</a>
            <a href="{{ url('/login') }}">{{ __('Se connecter') }}</a>
            <a href="#">{{ __('À propos') }}</a>
            <a href="#">{{ __('Contact') }}</a>
        </div>
        <div class="fcol"><h5>{{ __('Légal') }}</h5>
            <a href="#">{{ __('Confidentialité') }}</a>
            <a href="#">{{ __('Conditions') }}</a>
        </div>
    </div>
    <div class="fbot">
        <span>© {{ date('Y') }} TAGTOA · GOVIBE Ecosystem · tagtoa.com</span>
        <div class="soc">
            <a href="#" aria-label="WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
            <a href="#" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
            <a href="#" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
        </div>
    </div>
</div></footer>
</body>
</html>
