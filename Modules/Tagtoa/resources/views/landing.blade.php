{{-- TAGTOA — page d'accueil premium (landing). Thème clair, blocs, animations.
     Couleur principale #2cb809. Multilingue, sans emoji (icônes uniquement). --}}
@php
    $store = url('/tagtoa/store'); // Boutique désormais NATIVE dans TAGTOA
    $slides = [
        ['fa-bolt',     'Transformez chaque tap en vente',          'Site, menu, paiements et fidélité réunis — un seul tap NFC, un seul QR.'],
        ['fa-bag-shopping', 'Votre boutique WhatsApp en 2 minutes',  'Créez une boutique en ligne gratuite et vendez directement sur WhatsApp.'],
        ['fa-money-bill-transfer', 'Encaissez partout, instantanément', 'MonCash, NatCash, cartes, PayPal et crypto — l\'argent arrive en quelques secondes.'],
    ];
    $services = [
        ['fa-bag-shopping',        'Boutique WhatsApp', 'Vendez en ligne et recevez vos commandes directement sur WhatsApp.', $store, true],
        ['fa-globe',               'Site web',     'Site web professionnel par abonnement — vitrine, services, contact.', url('/site/demo-site'), false],
        ['fa-utensils',            'Menu digital', 'Restaurant, club, lounge, hôtel : menu NFC/QR, commande, paiement.', url('/menu/demo-menu'), false],
        ['fa-money-bill-transfer', 'Paiements',    'MonCash, NatCash, cartes, PayPal, crypto — un seul lien, un seul QR.', url('/pay/demo'), false],
        ['fa-id-card',             'Fidélité',     'Cartes NFC de fidélité, points et récompenses automatiques.', url('/login'), false],
        ['fa-link',                'Liens & Bio',  'Page de liens style Linktree avec dons et paiements intégrés.', url('/links/demo-links'), false],
        ['fa-ticket',              'Événements',   'Billetterie en ligne + contrôle d\'accès NFC/QR à l\'entrée.', url('/event/demo-concert'), false],
        ['fa-calendar-check',      'Réservations', 'Prise de rendez-vous en ligne : prestations, créneaux, confirmation.', url('/book/demo-booking'), false],
        ['fa-cash-register',       'Caisse POS',   'Caisse tactile hors-ligne installable, multi-paiement, rapports.', url('/login'), false],
    ];
    $methods = [
        ['fa-mobile-screen-button','MonCash','#E2001A'], ['fa-mobile-screen','NatCash','#00A859'],
        ['fa-brands fa-cc-visa','Visa','#1A1F71'], ['fa-brands fa-cc-mastercard','Mastercard','#EB001B'],
        ['fa-brands fa-paypal','PayPal','#003087'], ['fa-dollar-sign','USDT','#26A17B'],
        ['fa-brands fa-bitcoin','Bitcoin','#F7931A'], ['fa-brands fa-ethereum','Ethereum','#627EEA'],
        ['fa-building-columns','Virements','#64748b'], ['fa-money-bill-wave','Cash','#1D9E75'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TAGTOA — {{ __('La plateforme business tout-en-un pour Haïti') }}</title>
    <meta name="description" content="{{ __('Site web, boutique WhatsApp, menu, paiements, fidélité et caisse — NFC & QR. La plateforme digitale des entrepreneurs haïtiens.') }}">
    <meta name="theme-color" content="#2cb809">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Space+Grotesk:wght@500;600;700&family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root{
            /* 90% noir & blanc — accents #2cb809 uniquement (boutons + touches de style) */
            --green:#2cb809;--green-d:#239406;--green-l:#f1f1f1;--green-l2:#e4e4e4;
            --ink:#111111;--muted:#666666;--bg:#ffffff;--bg2:#f4f4f5;--bd:rgba(0,0,0,.12);
            --wa:#2cb809;--gold:#2cb809;
            --fh:'Space Grotesk',sans-serif;--fb:'Nunito',sans-serif;--ft:'Anton',sans-serif;
        }
        *{box-sizing:border-box;margin:0;padding:0}
        html{scroll-behavior:smooth}
        body{font-family:var(--fb);background:var(--bg);color:var(--ink);line-height:1.6;overflow-x:hidden;-webkit-font-smoothing:antialiased}
        a{text-decoration:none;color:inherit}
        .wrap{max-width:1140px;margin:0 auto;padding:0 24px}
        section{position:relative;padding:76px 0}
        .bg2{background:var(--bg2)}
        /* Animations d'apparition */
        .reveal{opacity:0;transform:translateY(22px);transition:opacity .6s ease,transform .6s ease}
        .reveal.in{opacity:1;transform:none}
        .reveal.d1{transition-delay:.08s}.reveal.d2{transition-delay:.16s}.reveal.d3{transition-delay:.24s}
        /* Boutons */
        .btn{display:inline-flex;align-items:center;gap:8px;border:0;border-radius:12px;padding:12px 22px;font:700 14px var(--fh);cursor:pointer;transition:transform .12s,box-shadow .2s,background .2s;white-space:nowrap}
        .btn:active{transform:scale(.97)}
        .btn-p{background:var(--green);color:#fff;box-shadow:0 8px 22px rgba(44,184,9,.28)}.btn-p:hover{background:var(--green-d);box-shadow:0 12px 30px rgba(44,184,9,.4)}
        .btn-o{background:#fff;border:1.5px solid var(--bd);color:var(--ink)}.btn-o:hover{border-color:var(--green);color:var(--green-d)}
        .btn-wa{background:var(--wa);color:#fff;box-shadow:0 8px 22px rgba(37,211,102,.32)}.btn-wa:hover{filter:brightness(1.05)}
        .btn-lg{padding:15px 28px;font-size:15.5px}
        /* Nav */
        .nav{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.85);backdrop-filter:blur(16px);border-bottom:1px solid var(--bd)}
        .nav .in{display:flex;align-items:center;gap:14px;height:66px}
        .brand{display:flex;align-items:center;gap:10px;font:700 19px var(--fh);letter-spacing:-.02em;color:var(--ink)}
        .brand .lg{width:36px;height:36px;border-radius:10px;background:var(--green);display:flex;align-items:center;justify-content:center;box-shadow:0 4px 14px rgba(44,184,9,.4)}
        .brand .lg i{color:#fff;font-size:17px}
        .nav .sp{flex:1}
        @media(max-width:720px){.nav .hidem{display:none}}
        /* Hero */
        .hero{padding:70px 0 40px;background:#fff;border-bottom:1px solid var(--bd)}
        .hero .in{display:grid;grid-template-columns:1.08fr .92fr;gap:50px;align-items:center}
        .pill{display:inline-flex;align-items:center;gap:8px;background:#fff;border:1px solid var(--green-l2);color:var(--green-d);padding:7px 15px;border-radius:999px;font:700 11px var(--fh);letter-spacing:.08em;text-transform:uppercase;box-shadow:0 2px 8px rgba(44,184,9,.08)}
        .pill .dot{width:7px;height:7px;border-radius:50%;background:var(--green);box-shadow:0 0 0 4px rgba(44,184,9,.18);animation:ping 1.8s ease-in-out infinite}
        @keyframes ping{0%,100%{box-shadow:0 0 0 4px rgba(44,184,9,.18)}50%{box-shadow:0 0 0 7px rgba(44,184,9,.05)}}
        h1{font:700 clamp(34px,5.2vw,58px)/1.03 var(--fh);letter-spacing:-.03em;margin:18px 0 16px}
        .grad{color:var(--green)}
        .hero p.sub{font-size:clamp(15px,2vw,18px);color:var(--muted);max-width:500px;margin-bottom:28px}
        .cta{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:26px}
        .trust{display:flex;gap:10px;flex-wrap:wrap}
        .tb{display:inline-flex;align-items:center;gap:7px;background:#fff;border:1px solid var(--bd);border-radius:10px;padding:8px 13px;font:600 12.5px var(--fb);color:var(--muted)}
        .tb i{color:var(--green);font-size:12px}
        /* Hero slider */
        .slider{position:relative;background:#fff;border:1px solid var(--bd);border-radius:22px;box-shadow:0 24px 60px rgba(13,20,12,.10);overflow:hidden;min-height:320px}
        .slides{position:relative;height:320px}
        .slide{position:absolute;inset:0;padding:40px 34px;display:flex;flex-direction:column;justify-content:center;gap:14px;opacity:0;transform:scale(1.03);transition:opacity .6s ease,transform .6s ease;pointer-events:none}
        .slide.on{opacity:1;transform:none;pointer-events:auto}
        .slide .si{width:60px;height:60px;border-radius:16px;background:var(--green-l);color:var(--green-d);display:flex;align-items:center;justify-content:center;font-size:26px}
        .slide h3{font:700 24px var(--fh);letter-spacing:-.02em}
        .slide p{color:var(--muted);font-size:15px;max-width:380px}
        .slide .sbar{height:5px;border-radius:9px;background:var(--green-l2);overflow:hidden;max-width:200px;margin-top:6px}
        .slide .sbar b{display:block;height:100%;width:40%;background:var(--green);border-radius:9px}
        .dots{position:absolute;bottom:16px;left:34px;display:flex;gap:8px}
        .dots button{width:9px;height:9px;border-radius:50%;border:0;background:var(--green-l2);cursor:pointer;transition:width .2s,background .2s;padding:0}
        .dots button.on{width:26px;background:var(--green)}
        /* WhatsApp Store band */
        .waband{padding:0 24px}
        .wabox{max-width:1140px;margin:-30px auto 0;position:relative;z-index:2;background:linear-gradient(120deg,#111,#000);border-radius:24px;padding:34px 36px;display:flex;align-items:center;gap:26px;flex-wrap:wrap;box-shadow:0 24px 60px rgba(0,0,0,.22);overflow:hidden}
        .wabox::after{content:"";position:absolute;right:-40px;top:-40px;width:240px;height:240px;background:radial-gradient(circle,rgba(37,211,102,.22),transparent 70%)}
        .wabox .wi{width:64px;height:64px;border-radius:18px;background:var(--wa);color:#fff;display:flex;align-items:center;justify-content:center;font-size:30px;flex:0 0 64px;box-shadow:0 8px 24px rgba(37,211,102,.4);animation:bob 3.4s ease-in-out infinite}
        @keyframes bob{0%,100%{transform:translateY(0)}50%{transform:translateY(-7px)}}
        .wabox .wt{flex:1;min-width:240px;color:#fff;position:relative;z-index:1}
        .wabox .wt .tag{font:700 11px var(--fh);letter-spacing:.1em;text-transform:uppercase;color:#7be07b}
        .wabox .wt h3{font:700 23px var(--fh);letter-spacing:-.02em;margin:4px 0 4px}
        .wabox .wt p{color:rgba(255,255,255,.7);font-size:14px}
        .wabox .wcta{position:relative;z-index:1}
        /* Section headers */
        .ey{display:inline-flex;align-items:center;gap:9px;font:700 12px var(--fh);letter-spacing:.12em;text-transform:uppercase;color:var(--green-d);margin-bottom:14px}
        .ey::before{content:"";width:22px;height:2px;background:var(--green);border-radius:2px}
        h2{font:700 clamp(26px,3.4vw,40px) var(--fh);letter-spacing:-.025em;line-height:1.1}
        .lead{color:var(--muted);max-width:560px;margin-top:12px;font-size:15.5px}
        .center{text-align:center}.center .ey{justify-content:center}.center .lead{margin-left:auto;margin-right:auto}
        /* Stats */
        .statsrow{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-top:44px}
        .st{background:#fff;border:1px solid var(--bd);border-radius:16px;padding:26px 18px;text-align:center}
        .st .n{font:700 34px var(--fh);line-height:1;color:var(--ink)}.st .k{font-size:13px;color:var(--muted);margin-top:6px}
        /* Services grid */
        .sgrid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-top:44px}
        .scard{background:#fff;border:1px solid var(--bd);border-radius:18px;padding:24px;transition:transform .16s,box-shadow .2s,border-color .2s;position:relative;overflow:hidden}
        .scard:hover{transform:translateY(-5px);border-color:var(--green);box-shadow:0 18px 40px rgba(13,20,12,.10)}
        .scard.feat{border-color:var(--green);background:linear-gradient(160deg,var(--green-l),#fff)}
        .scard .si{width:50px;height:50px;border-radius:13px;background:var(--green-l);display:flex;align-items:center;justify-content:center;font-size:20px;color:var(--green-d);margin-bottom:16px}
        .scard.feat .si{background:var(--wa);color:#fff}
        .scard h3{font:700 16.5px var(--fh)}
        .scard p{color:var(--muted);font-size:13.5px;margin-top:6px;min-height:52px}
        .scard .go{display:inline-flex;align-items:center;gap:6px;color:var(--green-d);font:700 13px var(--fh);margin-top:8px}
        .ribbon{position:absolute;top:14px;right:14px;background:var(--wa);color:#fff;font:800 9.5px var(--fh);letter-spacing:.06em;padding:4px 10px;border-radius:999px;text-transform:uppercase}
        /* Promotion */
        .promo{background:linear-gradient(120deg,#111,#000);border-radius:26px;padding:48px 40px;color:#fff;position:relative;overflow:hidden;display:grid;grid-template-columns:1.4fr .6fr;gap:30px;align-items:center}
        .promo::before{content:"";position:absolute;left:-60px;bottom:-80px;width:300px;height:300px;background:radial-gradient(circle,rgba(255,255,255,.14),transparent 70%)}
        .promo .pl{position:relative;z-index:1}
        .promo .ptag{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.18);padding:6px 14px;border-radius:999px;font:700 11px var(--fh);letter-spacing:.08em;text-transform:uppercase}
        .promo h2{color:#fff;margin:14px 0 8px}
        .promo p{color:rgba(255,255,255,.85);max-width:520px}
        .promo .pr{position:relative;z-index:1;text-align:center}
        .promo .big{font:700 64px var(--fh);line-height:1}
        .promo .small{font:700 14px var(--fh);opacity:.85;letter-spacing:.04em}
        .promo .btn{margin-top:16px}
        /* How */
        .hgrid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:44px}
        .hstep{background:#fff;padding:30px 26px;border:1px solid var(--bd);border-radius:18px;position:relative}
        .hstep .nu{position:absolute;top:16px;right:20px;font:700 50px var(--fh);color:var(--green-l2)}
        .hstep .hi{width:50px;height:50px;border-radius:13px;background:var(--green-l);display:flex;align-items:center;justify-content:center;font-size:19px;color:var(--green-d);margin-bottom:16px}
        .hstep h3{font:700 16px var(--fh);margin-bottom:6px}.hstep p{color:var(--muted);font-size:13.5px}
        /* Methods */
        .mgrid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-top:44px}
        .mcard{background:#fff;border:1px solid var(--bd);border-radius:16px;padding:22px 14px;text-align:center;transition:transform .16s,border-color .2s}
        .mcard:hover{transform:translateY(-3px);border-color:var(--green)}
        .mcard .mi{width:52px;height:52px;border-radius:14px;background:var(--bg2);display:flex;align-items:center;justify-content:center;font-size:22px;margin:0 auto 12px}
        .mcard .mn{font:700 13.5px var(--fh)}
        /* Pricing */
        .pgrid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-top:44px}
        .pcard{background:#fff;border:1.5px solid var(--bd);border-radius:20px;padding:32px;position:relative;transition:transform .2s,box-shadow .2s}
        .pcard:hover{transform:translateY(-5px);box-shadow:0 18px 44px rgba(13,20,12,.10)}
        .pcard.pop{border-color:var(--green);box-shadow:0 0 0 4px rgba(44,184,9,.08),0 24px 60px rgba(13,20,12,.12)}
        .pbadge{position:absolute;top:18px;right:18px;background:var(--green);color:#fff;font:800 10px var(--fh);letter-spacing:.06em;padding:4px 11px;border-radius:999px;text-transform:uppercase}
        .ptier{font:700 12px var(--fh);letter-spacing:.12em;text-transform:uppercase;color:var(--muted)}
        .pname{font:700 22px var(--fh);margin:4px 0 14px}
        .price{display:flex;align-items:baseline;gap:6px;margin-bottom:6px}
        .price .a{font:700 34px var(--fh);color:var(--ink)}.price .per{color:var(--muted);font-size:13px}
        .pnote{color:var(--muted);font-size:13px;margin-bottom:20px}
        .pfeat{list-style:none;display:flex;flex-direction:column;gap:11px;margin:20px 0 26px}
        .pfeat li{display:flex;gap:10px;font-size:14px;color:rgba(13,20,12,.82)}
        .pfeat li i{color:var(--green);font-size:13px;margin-top:3px}
        .pfeat li.off{opacity:.4}.pfeat li.off i{color:var(--muted)}
        .pcard .btn{width:100%;justify-content:center}
        /* Feature showcase (présentation détaillée) */
        .feat-row{display:grid;grid-template-columns:1fr 1fr;gap:50px;align-items:center;margin-top:52px}
        .feat-row.rev .fviz{order:2}
        .feat-row .ftag{display:inline-flex;align-items:center;gap:8px;font:700 11px var(--fh);letter-spacing:.1em;text-transform:uppercase;color:var(--green-d);background:var(--green-l);padding:6px 12px;border-radius:999px}
        .feat-row h3{font:700 clamp(22px,2.7vw,31px) var(--fh);letter-spacing:-.02em;margin:14px 0 10px}
        .feat-row p{color:var(--muted);margin-bottom:16px;font-size:15px;max-width:460px}
        .flist{list-style:none;display:flex;flex-direction:column;gap:11px}
        .flist li{display:flex;gap:10px;font-size:14.5px;color:rgba(13,20,12,.82)}
        .flist li i{color:var(--green);margin-top:3px}
        .fviz{background:linear-gradient(160deg,var(--green-l),#fff);border:1px solid var(--bd);border-radius:24px;padding:30px;min-height:280px;display:flex;flex-direction:column;justify-content:center;gap:12px;position:relative;overflow:hidden}
        .fbub{max-width:78%;padding:11px 15px;border-radius:16px;font-size:14px;box-shadow:0 4px 14px rgba(13,20,12,.06)}
        .fbub.in{background:#fff;border:1px solid var(--bd);border-bottom-left-radius:5px}
        .fbub.out{background:var(--wa);color:#fff;align-self:flex-end;border-bottom-right-radius:5px}
        .fprod{background:#fff;border:1px solid var(--bd);border-radius:14px;padding:12px;display:flex;align-items:center;gap:12px;box-shadow:0 6px 18px rgba(13,20,12,.06)}
        .fprod .fph{width:46px;height:46px;border-radius:11px;background:var(--green-l);color:var(--green-d);display:flex;align-items:center;justify-content:center;font-size:20px}
        .fprod .fpn{font:700 14px var(--fh)}.fprod .fpp{color:var(--green-d);font:700 13px var(--fh)}
        .fchips{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
        .fchip{background:#fff;border:1px solid var(--bd);border-radius:12px;padding:14px 8px;text-align:center;font-size:20px}
        .fbars{display:flex;align-items:flex-end;gap:12px;height:150px;padding:10px 6px}
        .fbars .bar{flex:1;background:var(--green);border-radius:8px 8px 0 0;opacity:.85}
        .fkpi{display:flex;gap:10px;flex-wrap:wrap}
        .fkpi .k{background:#fff;border:1px solid var(--bd);border-radius:12px;padding:10px 14px;font:700 13px var(--fh)}
        .fkpi .k b{color:var(--green-d);font-size:18px;display:block}
        @media(max-width:960px){.feat-row{grid-template-columns:1fr;gap:26px}.feat-row.rev .fviz{order:0}}
        /* Testimonials */
        .tgrid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;margin-top:44px}
        .tcard{background:#fff;border:1px solid var(--bd);border-radius:18px;padding:26px;transition:transform .16s,box-shadow .2s}
        .tcard:hover{transform:translateY(-4px);box-shadow:0 18px 40px rgba(13,20,12,.10)}
        .tstars{color:var(--gold);font-size:13px;margin-bottom:12px}
        .tquote{font-size:14.5px;color:var(--ink);line-height:1.65}
        .tu{display:flex;align-items:center;gap:12px;margin-top:18px}
        .tav{width:44px;height:44px;border-radius:50%;background:var(--green-l);color:var(--green-d);display:flex;align-items:center;justify-content:center;font:700 16px var(--fh)}
        .tnm{font:700 14px var(--fh)}.tro{font-size:12.5px;color:var(--muted)}
        /* FAQ */
        .faq{max-width:760px;margin:40px auto 0}
        .qa{background:#fff;border:1px solid var(--bd);border-radius:14px;margin-bottom:12px;overflow:hidden}
        .qa summary{list-style:none;cursor:pointer;padding:18px 22px;font:700 15.5px var(--fh);display:flex;align-items:center;justify-content:space-between;gap:14px}
        .qa summary::-webkit-details-marker{display:none}
        .qa summary .ic{flex:0 0 auto;width:26px;height:26px;border-radius:8px;background:var(--green-l);color:var(--green-d);display:flex;align-items:center;justify-content:center;transition:transform .2s}
        .qa[open] summary .ic{transform:rotate(45deg)}
        .qa .ans{padding:0 22px 20px;color:var(--muted);font-size:14.5px;line-height:1.65}
        @media(max-width:960px){.tgrid{grid-template-columns:1fr}}
        /* Final CTA */
        .ctabox{max-width:1140px;margin:0 auto;background:var(--ink);border-radius:28px;padding:60px 32px;text-align:center;position:relative;overflow:hidden;color:#fff}
        .ctabox::before{content:"";position:absolute;top:-120px;left:50%;transform:translateX(-50%);width:420px;height:300px;background:radial-gradient(ellipse,rgba(44,184,9,.22),transparent 70%)}
        .ctabox h2{color:#fff;position:relative}.ctabox p{color:rgba(255,255,255,.7);margin:10px 0 26px;position:relative}
        .ctabox .cta{justify-content:center;position:relative}
        /* Footer */
        footer{background:var(--bg2);border-top:1px solid var(--bd);padding:54px 0 28px}
        .fgrid{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:40px;margin-bottom:36px}
        .fdesc{color:var(--muted);font-size:13.5px;margin-top:12px;max-width:280px}
        .fcol h5{font:700 14px var(--fh);margin-bottom:14px}
        .fcol a{display:block;color:var(--muted);font-size:13.5px;padding:5px 0;transition:color .2s}.fcol a:hover{color:var(--green-d)}
        .fbot{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;border-top:1px solid var(--bd);padding-top:22px;color:var(--muted);font-size:12.5px}
        .soc{display:flex;gap:8px}
        .soc a{width:34px;height:34px;border-radius:9px;background:#fff;border:1px solid var(--bd);display:flex;align-items:center;justify-content:center;color:var(--muted);transition:all .2s}
        .soc a:hover{color:var(--green-d);border-color:var(--green)}
        /* Bouton WhatsApp flottant */
        .wafab{position:fixed;right:18px;bottom:18px;z-index:60;width:56px;height:56px;border-radius:50%;background:var(--wa);color:#fff;display:flex;align-items:center;justify-content:center;font-size:26px;box-shadow:0 10px 28px rgba(37,211,102,.45);animation:bob 3s ease-in-out infinite}
        @media(max-width:960px){.hero .in{grid-template-columns:1fr;gap:34px}.sgrid{grid-template-columns:repeat(2,1fr)}.mgrid{grid-template-columns:repeat(3,1fr)}.hgrid{grid-template-columns:1fr}.pgrid{grid-template-columns:1fr}.statsrow{grid-template-columns:1fr 1fr}.promo{grid-template-columns:1fr;text-align:center}.promo .ptag,.promo .btn{margin-left:auto;margin-right:auto}.fgrid{grid-template-columns:1fr 1fr}}
        @media(max-width:560px){.sgrid{grid-template-columns:1fr}.mgrid{grid-template-columns:1fr 1fr}.fgrid{grid-template-columns:1fr}.wabox{flex-direction:column;text-align:center}.wabox .wcta{width:100%}.wabox .wcta .btn{width:100%;justify-content:center}}
        /* Tous les titres en Anton (display), noir — accents #2cb809 réservés aux boutons/détails */
        h1,h2,h3,.pname,.slide h3,.feat-row h3,.promo .big,.st .n,.price .a,.brand b{
            font-family:var(--ft)!important;font-weight:400!important;letter-spacing:.005em;
        }
        h1{line-height:1.02}
        @media (prefers-reduced-motion:reduce){*{transition:none!important;animation:none!important}.reveal{opacity:1;transform:none}}
    </style>
</head>
<body>

<nav class="nav"><div class="wrap in">
    <a href="{{ url('/') }}" class="brand"><span class="lg"><i class="fa-solid fa-bolt"></i></span>TAGTOA</a>
    <span class="sp"></span>
    @include('tagtoa::partials.lang')
    <a class="btn btn-o hidem" href="{{ url('/login') }}"><i class="fa-solid fa-arrow-right-to-bracket"></i> {{ __('Se connecter') }}</a>
    <a class="btn btn-p" href="{{ $store }}"><i class="fa-brands fa-whatsapp"></i> {{ __('Boutique gratuite') }}</a>
</div></nav>

<header class="hero"><div class="wrap in">
    <div>
        <span class="pill reveal in"><span class="dot"></span> NFC · QR · {{ __('Haïti') }}</span>
        <h1 class="reveal in d1">{{ __('Lancez votre business en ligne') }} <span class="grad">{{ __('aujourd\'hui') }}</span></h1>
        <p class="sub reveal in d2">{{ __('Gérez vos paiements, créez des boutiques WhatsApp, partagez des cartes de visite NFC, organisez des événements, connectez-vous avec vos clients et développez votre business avec TAGTOA.') }}</p>
        <div class="cta reveal in d2">
            <a class="btn btn-wa btn-lg" href="{{ $store }}"><i class="fa-brands fa-whatsapp"></i> {{ __('Créer ma boutique gratuite') }}</a>
            <a class="btn btn-o btn-lg" href="{{ url('/menu/demo-menu') }}"><i class="fa-solid fa-play"></i> {{ __('Voir la démo') }}</a>
        </div>
        <div class="trust reveal in d3">
            <span class="tb"><i class="fa-solid fa-bolt"></i> {{ __('Prêt en 2 minutes') }}</span>
            <span class="tb"><i class="fa-solid fa-gift"></i> {{ __('Gratuit pour démarrer') }}</span>
            <span class="tb"><i class="fa-solid fa-shield-halved"></i> {{ __('Sécurisé SSL') }}</span>
            <span class="tb"><i class="fa-solid fa-language"></i> {{ __('4 langues') }}</span>
        </div>
    </div>
    <div class="reveal in d1">
        <div class="slider" id="slider">
            <div class="slides">
                @foreach($slides as $i => $sl)
                    <div class="slide {{ $i === 0 ? 'on' : '' }}">
                        <div class="si"><i class="fa-solid {{ $sl[0] }}"></i></div>
                        <h3>{{ __($sl[1]) }}</h3>
                        <p>{{ __($sl[2]) }}</p>
                        <div class="sbar"><b></b></div>
                    </div>
                @endforeach
            </div>
            <div class="dots" id="dots">
                @foreach($slides as $i => $sl)
                    <button class="{{ $i === 0 ? 'on' : '' }}" data-i="{{ $i }}" aria-label="Slide {{ $i + 1 }}"></button>
                @endforeach
            </div>
        </div>
    </div>
</div></header>

{{-- Bande Boutique WhatsApp --}}
<div class="waband"><div class="wabox reveal">
    <div class="wi"><i class="fa-brands fa-whatsapp"></i></div>
    <div class="wt">
        <span class="tag">{{ __('Boutique WhatsApp') }}</span>
        <h3>{{ __('Créez votre boutique en ligne en 2 minutes — gratuit') }}</h3>
        <p>{{ __('Ajoutez vos produits, partagez le lien, recevez les commandes sur WhatsApp et encaissez. Sans code, sans frais de départ.') }}</p>
    </div>
    <div class="wcta"><a class="btn btn-wa btn-lg" href="{{ $store }}"><i class="fa-brands fa-whatsapp"></i> {{ __('Ouvrir ma boutique') }}</a></div>
</div></div>

<section><div class="wrap">
    <div class="statsrow">
        <div class="st reveal"><div class="n">9</div><div class="k">{{ __('outils en un') }}</div></div>
        <div class="st reveal d1"><div class="n">10+</div><div class="k">{{ __('méthodes de paiement') }}</div></div>
        <div class="st reveal d2"><div class="n">2 min</div><div class="k">{{ __('pour démarrer') }}</div></div>
        <div class="st reveal d3"><div class="n">24/7</div><div class="k">{{ __('disponible') }}</div></div>
    </div>
</div></section>

<section class="bg2"><div class="wrap">
    <div class="center">
        <span class="ey reveal">{{ __('La plateforme') }}</span>
        <h2 class="reveal d1">{{ __('Tous vos outils business, réunis') }}</h2>
        <p class="lead reveal d2">{{ __('Créez votre présence digitale et encaissez — sans plusieurs apps, sans complications.') }}</p>
    </div>
    <div class="sgrid">
        @foreach($services as $s)
            <a class="scard reveal {{ $s[4] ? 'feat' : '' }}" href="{{ $s[3] }}">
                @if($s[4])<span class="ribbon">{{ __('Gratuit') }}</span>@endif
                <div class="si"><i class="fa-solid {{ $s[0] }}"></i></div>
                <h3>{{ __($s[1]) }}</h3>
                <p>{{ __($s[2]) }}</p>
                <span class="go">{{ __('Découvrir') }} <i class="fa-solid fa-arrow-right"></i></span>
            </a>
        @endforeach
    </div>
</div></section>

{{-- Section Promotion --}}
<section><div class="wrap">
    <div class="promo reveal">
        <div class="pl">
            <span class="ptag"><i class="fa-solid fa-fire"></i> {{ __('Offre de lancement') }}</span>
            <h2>{{ __('3 mois Pro offerts pour les 100 premiers business') }}</h2>
            <p>{{ __('Profitez de toutes les fonctionnalités Pro — site web, carte NFC, fidélité, événements et analytics — gratuitement pendant 3 mois. Offre limitée.') }}</p>
            <a class="btn btn-o" href="{{ url('/login') }}" style="background:#fff;color:var(--green-d)"><i class="fa-solid fa-gift"></i> {{ __('Profiter de l\'offre') }}</a>
        </div>
        <div class="pr">
            <div class="big">-100%</div>
            <div class="small">{{ __('pendant 3 mois') }}</div>
        </div>
    </div>
</div></section>

<section><div class="wrap">
    <div class="center">
        <span class="ey reveal">{{ __('La plateforme en détail') }}</span>
        <h2 class="reveal d1">{{ __('Tout ce dont votre business a besoin pour vendre') }}</h2>
    </div>

    {{-- Boutique WhatsApp --}}
    <div class="feat-row reveal">
        <div class="fviz">
            <div class="fbub in">{{ __('Bonjou, èske pwodwi a disponib?') }}</div>
            <div class="fprod"><div class="fph"><i class="fa-solid fa-bag-shopping"></i></div><div><div class="fpn">{{ __('Produit vedette') }}</div><div class="fpp">1,200 HTG</div></div></div>
            <div class="fbub out">{{ __('Wi! Klike pou kòmande.') }}</div>
        </div>
        <div>
            <span class="ftag"><i class="fa-brands fa-whatsapp"></i> {{ __('Boutique WhatsApp') }}</span>
            <h3>{{ __('Vendez sur WhatsApp, sans friction') }}</h3>
            <p>{{ __('Vos clients parcourent votre catalogue, commandent et paient — tout depuis la conversation WhatsApp qu\'ils utilisent déjà.') }}</p>
            <ul class="flist">
                <li><i class="fa-solid fa-check"></i> {{ __('Catalogue en ligne prêt en 2 minutes') }}</li>
                <li><i class="fa-solid fa-check"></i> {{ __('Commandes reçues directement sur WhatsApp') }}</li>
                <li><i class="fa-solid fa-check"></i> {{ __('Paiement intégré et confirmation automatique') }}</li>
            </ul>
        </div>
    </div>

    {{-- Paiements --}}
    <div class="feat-row rev reveal">
        <div class="fviz">
            <div class="fchips">
                <div class="fchip" style="color:#E2001A"><i class="fa-solid fa-mobile-screen-button"></i></div>
                <div class="fchip" style="color:#00A859"><i class="fa-solid fa-mobile-screen"></i></div>
                <div class="fchip" style="color:#1A1F71"><i class="fa-brands fa-cc-visa"></i></div>
                <div class="fchip" style="color:#003087"><i class="fa-brands fa-paypal"></i></div>
                <div class="fchip" style="color:#F7931A"><i class="fa-brands fa-bitcoin"></i></div>
                <div class="fchip" style="color:#1D9E75"><i class="fa-solid fa-money-bill-wave"></i></div>
            </div>
            <div class="fprod" style="margin-top:6px"><div class="fph" style="background:var(--wa);color:#fff"><i class="fa-solid fa-circle-check"></i></div><div><div class="fpn">{{ __('Paiement reçu') }}</div><div class="fpp">2,400 HTG · 2.1s</div></div></div>
        </div>
        <div>
            <span class="ftag"><i class="fa-solid fa-money-bill-transfer"></i> {{ __('Paiements') }}</span>
            <h3>{{ __('Encaissez par tous les moyens') }}</h3>
            <p>{{ __('MonCash, NatCash, cartes, PayPal et crypto réunis derrière un seul lien et un seul QR. L\'argent arrive en quelques secondes.') }}</p>
            <ul class="flist">
                <li><i class="fa-solid fa-check"></i> {{ __('10+ méthodes locales et internationales') }}</li>
                <li><i class="fa-solid fa-check"></i> {{ __('Preuve de paiement et validation') }}</li>
                <li><i class="fa-solid fa-check"></i> {{ __('Prix imposés côté serveur (anti-fraude)') }}</li>
            </ul>
        </div>
    </div>

    {{-- Pilotage / Analytics --}}
    <div class="feat-row reveal">
        <div class="fviz">
            <div class="fbars">
                <div class="bar" style="height:45%"></div><div class="bar" style="height:70%"></div>
                <div class="bar" style="height:55%"></div><div class="bar" style="height:88%"></div>
                <div class="bar" style="height:66%"></div><div class="bar" style="height:100%"></div>
            </div>
            <div class="fkpi"><div class="k">{{ __('Revenu') }} <b>184k HTG</b></div><div class="k">{{ __('Commandes') }} <b>312</b></div><div class="k">{{ __('Clients') }} <b>97</b></div></div>
        </div>
        <div>
            <span class="ftag"><i class="fa-solid fa-chart-line"></i> {{ __('Pilotage') }}</span>
            <h3>{{ __('Pilotez votre business en temps réel') }}</h3>
            <p>{{ __('Suivez vos revenus, vos commandes et vos clients depuis un tableau de bord clair — et fidélisez avec le CRM intégré.') }}</p>
            <ul class="flist">
                <li><i class="fa-solid fa-check"></i> {{ __('Analytics : revenus, ventes, visites') }}</li>
                <li><i class="fa-solid fa-check"></i> {{ __('CRM : base clients automatique') }}</li>
                <li><i class="fa-solid fa-check"></i> {{ __('Avis clients, stock et journal d\'audit') }}</li>
            </ul>
        </div>
    </div>
</div></section>

<section class="bg2"><div class="wrap">
    <div class="center">
        <span class="ey reveal">{{ __('Comment ça marche') }}</span>
        <h2 class="reveal d1">{{ __('En ligne en 3 étapes') }}</h2>
    </div>
    <div class="hgrid">
        <div class="hstep reveal"><div class="nu">01</div><div class="hi"><i class="fa-solid fa-user-plus"></i></div><h3>{{ __('Créez votre compte') }}</h3><p>{{ __('Inscrivez-vous et choisissez vos outils : boutique, site, menu, paiements…') }}</p></div>
        <div class="hstep reveal d1"><div class="nu">02</div><div class="hi"><i class="fa-solid fa-sliders"></i></div><h3>{{ __('Personnalisez') }}</h3><p>{{ __('Ajoutez vos produits, vos méthodes de paiement et votre marque en quelques minutes.') }}</p></div>
        <div class="hstep reveal d2"><div class="nu">03</div><div class="hi"><i class="fa-solid fa-qrcode"></i></div><h3>{{ __('Partagez & encaissez') }}</h3><p>{{ __('Partagez votre lien ou QR, tapez la carte NFC, et recevez vos paiements instantanément.') }}</p></div>
    </div>
</div></section>

<section><div class="wrap">
    <div class="center">
        <span class="ey reveal">{{ __('Paiements') }}</span>
        <h2 class="reveal d1">{{ __('Acceptez tout paiement, local et international') }}</h2>
        <p class="lead reveal d2">{{ __('Vos clients paient avec leur méthode préférée — un seul lien, un seul QR.') }}</p>
    </div>
    <div class="mgrid">
        @foreach($methods as $m)
            <div class="mcard reveal"><div class="mi" style="color:{{ $m[2] }}"><i class="{{ \Illuminate\Support\Str::startsWith($m[0],'fa-brands') ? $m[0] : 'fa-solid '.$m[0] }}"></i></div><div class="mn">{{ $m[1] }}</div></div>
        @endforeach
    </div>
</div></section>

<section class="bg2" id="plans"><div class="wrap">
    <div class="center">
        <span class="ey reveal">{{ __('Tarifs') }}</span>
        <h2 class="reveal d1">{{ __('Un abonnement pour chaque ambition') }}</h2>
        <p class="lead reveal d2">{{ __('Commencez gratuitement. Évoluez quand votre business grandit.') }}</p>
    </div>
    <div class="pgrid">
        <div class="pcard reveal">
            <div class="ptier">{{ __('Débutant') }}</div>
            <div class="pname">{{ __('Gratuit') }}</div>
            <div class="price"><span class="a">0</span><span class="per">HTG</span></div>
            <div class="pnote">{{ __('Petite commission par vente') }}</div>
            <ul class="pfeat">
                <li><i class="fa-solid fa-check"></i> {{ __('Boutique WhatsApp') }}</li>
                <li><i class="fa-solid fa-check"></i> {{ __('1 menu digital + lien de paiement') }}</li>
                <li><i class="fa-solid fa-check"></i> {{ __('MonCash & NatCash') }}</li>
                <li class="off"><i class="fa-solid fa-xmark"></i> {{ __('Site web') }}</li>
                <li class="off"><i class="fa-solid fa-xmark"></i> {{ __('Carte NFC') }}</li>
            </ul>
            <a class="btn btn-o" href="{{ url('/login') }}">{{ __('Démarrer gratuitement') }}</a>
        </div>
        <div class="pcard pop reveal d1">
            <span class="pbadge"><i class="fa-solid fa-star"></i> {{ __('Populaire') }}</span>
            <div class="ptier">{{ __('Business') }}</div>
            <div class="pname">Pro</div>
            <div class="price"><span class="a">1,500</span><span class="per">HTG / {{ __('mois') }}</span></div>
            <div class="pnote">{{ __('Commission réduite par vente') }}</div>
            <ul class="pfeat">
                <li><i class="fa-solid fa-check"></i> {{ __('Tout le plan Gratuit') }}</li>
                <li><i class="fa-solid fa-check"></i> {{ __('Site web professionnel') }}</li>
                <li><i class="fa-solid fa-check"></i> {{ __('Carte NFC TAGTOA') }}</li>
                <li><i class="fa-solid fa-check"></i> {{ __('Cartes, PayPal, crypto') }}</li>
                <li><i class="fa-solid fa-check"></i> {{ __('Fidélité, événements & caisse') }}</li>
                <li><i class="fa-solid fa-check"></i> {{ __('Analytics avancées') }}</li>
            </ul>
            <a class="btn btn-p" href="{{ url('/login') }}">{{ __('Choisir Pro') }}</a>
        </div>
        <div class="pcard reveal d2">
            <div class="ptier">{{ __('Entreprise') }}</div>
            <div class="pname">Enterprise</div>
            <div class="price"><span class="a">{{ __('Sur devis') }}</span></div>
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

@php
    $testimonials = [
        ['Marc-Antoine Joseph', 'Restaurant · Pétion-Ville', 'Depuis TAGTOA, mes clients commandent par QR et paient en ligne. Mes ventes ont augmenté et je perds moins de temps.'],
        ['Naïka Pierre', 'Salon de beauté · Cap-Haïtien', 'Ma boutique WhatsApp était prête en quelques minutes. Mes rendez-vous se remplissent tout seuls maintenant.'],
        ['Frantz Délouis', 'Boutique · Gonaïves', 'Un seul lien pour MonCash, NatCash et cartes. Simple pour moi, simple pour mes clients.'],
    ];
    $faqs = [
        ['Combien de temps pour démarrer ?', 'Quelques minutes. Créez votre compte, ajoutez vos produits et partagez votre lien ou votre QR — c\'est en ligne immédiatement.'],
        ['Est-ce vraiment gratuit ?', 'Oui, vous pouvez démarrer gratuitement avec une boutique WhatsApp, un menu et un lien de paiement. Une petite commission s\'applique par vente, sans frais de départ.'],
        ['Quels moyens de paiement sont acceptés ?', 'MonCash, NatCash, cartes Visa/Mastercard, PayPal, virements bancaires, crypto et cash — un seul lien, un seul QR.'],
        ['Ai-je besoin de connaissances techniques ?', 'Non. Tout se fait sans code, depuis un tableau de bord simple, en créole, français, anglais ou espagnol.'],
        ['Mes paiements sont-ils sécurisés ?', 'Oui. Les pages sont servies en HTTPS (SSL) et les prix sont toujours imposés côté serveur pour éviter toute fraude.'],
    ];
@endphp

<section class="bg2"><div class="wrap">
    <div class="center">
        <span class="ey reveal">{{ __('Ils nous font confiance') }}</span>
        <h2 class="reveal d1">{{ __('Des business qui grandissent avec TAGTOA') }}</h2>
    </div>
    <div class="tgrid">
        @foreach($testimonials as $t)
            <div class="tcard reveal">
                <div class="tstars">@for($i=0;$i<5;$i++)<i class="fa-solid fa-star"></i>@endfor</div>
                <p class="tquote">« {{ __($t[2]) }} »</p>
                <div class="tu">
                    <div class="tav">{{ \Illuminate\Support\Str::substr($t[0],0,1) }}</div>
                    <div><div class="tnm">{{ $t[0] }}</div><div class="tro">{{ __($t[1]) }}</div></div>
                </div>
            </div>
        @endforeach
    </div>
</div></section>

<section><div class="wrap">
    <div class="center">
        <span class="ey reveal">{{ __('Questions fréquentes') }}</span>
        <h2 class="reveal d1">{{ __('Tout ce qu\'il faut savoir') }}</h2>
    </div>
    <div class="faq reveal d1">
        @foreach($faqs as $f)
            <details class="qa">
                <summary>{{ __($f[0]) }} <span class="ic"><i class="fa-solid fa-plus"></i></span></summary>
                <div class="ans">{{ __($f[1]) }}</div>
            </details>
        @endforeach
    </div>
</div></section>

<section><div class="wrap">
    <div class="ctabox reveal">
        <h2>{{ __('Prêt à digitaliser votre business ?') }}</h2>
        <p>{{ __('Rejoignez les entrepreneurs haïtiens qui vendent partout, à tout moment.') }}</p>
        <div class="cta">
            <a class="btn btn-wa btn-lg" href="{{ $store }}"><i class="fa-brands fa-whatsapp"></i> {{ __('Créer ma boutique gratuite') }}</a>
            <a class="btn btn-o btn-lg" href="{{ url('/login') }}" style="background:transparent;color:#fff;border-color:rgba(255,255,255,.3)"><i class="fa-solid fa-arrow-right-to-bracket"></i> {{ __('Se connecter') }}</a>
        </div>
    </div>
</div></section>

<footer><div class="wrap">
    <div class="fgrid">
        <div>
            <span class="brand"><span class="lg"><i class="fa-solid fa-bolt"></i></span>TAGTOA</span>
            <p class="fdesc">{{ __('La plateforme business tout-en-un pour Haïti : boutique WhatsApp, site web, paiements, menu, fidélité et plus — NFC & QR.') }}</p>
        </div>
        <div class="fcol"><h5>{{ __('Produit') }}</h5>
            <a href="{{ $store }}">{{ __('Boutique WhatsApp') }}</a>
            <a href="{{ url('/menu/demo-menu') }}">{{ __('Menu digital') }}</a>
            <a href="{{ url('/pay/demo') }}">{{ __('Paiements') }}</a>
            <a href="{{ url('/book/demo-booking') }}">{{ __('Réservations') }}</a>
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
            <a href="{{ $store }}" aria-label="WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
            <a href="#" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
            <a href="#" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
        </div>
    </div>
</div></footer>

<a class="wafab" href="{{ $store }}" aria-label="WhatsApp Store"><i class="fa-brands fa-whatsapp"></i></a>

<script>
// Apparition au scroll
(function(){
    var els=[].slice.call(document.querySelectorAll('.reveal'));
    if(!('IntersectionObserver' in window)){els.forEach(function(e){e.classList.add('in');});return;}
    var io=new IntersectionObserver(function(ent){ent.forEach(function(en){if(en.isIntersecting){en.target.classList.add('in');io.unobserve(en.target);}});},{threshold:.12});
    els.forEach(function(e){if(!e.classList.contains('in'))io.observe(e);});
})();
// Slider auto + points
(function(){
    var slides=[].slice.call(document.querySelectorAll('#slider .slide'));
    var dots=[].slice.call(document.querySelectorAll('#dots button'));
    if(!slides.length)return;
    var i=0,timer=null;
    function go(n){slides[i].classList.remove('on');dots[i].classList.remove('on');i=(n+slides.length)%slides.length;slides[i].classList.add('on');dots[i].classList.add('on');}
    function next(){go(i+1);}
    function start(){timer=setInterval(next,4500);}
    function stop(){clearInterval(timer);}
    dots.forEach(function(d){d.addEventListener('click',function(){stop();go(parseInt(d.getAttribute('data-i'),10));start();});});
    start();
})();
</script>
</body>
</html>
