{{-- TAGTOA — Layout dashboard (standalone, design system TAGTOA, mobile-first).
     N'hérite PAS du back-office vcard existant : interface propre et claire.
     Sections : @section('title'), @section('page'), @yield('content').

     La navigation est HIÉRARCHIQUE : chaque module ouvre ses propres écrans
     (DashboardModules::CATALOG → `children`). Un écran comme le Stock ne vit
     plus au même niveau que la Caisse — on l'atteint par la Caisse, parce que
     c'est pour elle qu'on l'ouvre.

     Sur téléphone la barre latérale est repliée : c'est la barre d'écrans
     (.subnav) sous le titre qui montre « tout ce qu'il y a dans ce module ».
     Les deux lisent la même source, elles ne peuvent pas diverger. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0A0A0A">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title','TAGTOA') · TAGTOA</title>
    <link rel="stylesheet" href="{{ route('tagtoa.asset', 'tagtoa-fonts.css') }}">
    <link rel="stylesheet" href="/tagtoa-asset/fontawesome-6.5.1.css">
    <style>
        :root{
            --blk:#0A0A0A;--white:#fff;--bg:#F5F5F3;--surface:#fff;--blue:#2cb809;--blue-deep:#239406;
            --blue-pale:rgba(44,184,9,.08);--green:#1D9E75;--red:#E0473E;--amber:#E08A1E;
            --bd:rgba(0,0,0,.08);--muted:#8a8a8a;--fh:'Space Grotesk',sans-serif;--fb:'Nunito',sans-serif;
            --ft:'Anton',sans-serif;--sb:260px;
        }
        *{box-sizing:border-box;margin:0;padding:0}
        /* Titres en Anton (display) */
        .brand b,.top h1,.h-row h2,.stat .v,.card>h2,h1.pg{font-family:var(--ft)!important;font-weight:400!important;letter-spacing:.01em}
        body{font-family:var(--fb);background:var(--bg);color:var(--blk);line-height:1.55;-webkit-font-smoothing:antialiased;overflow-x:hidden}
        a{color:inherit;text-decoration:none}
        /* ── Barre latérale ────────────────────────────────────────────── */
        .sb{position:fixed;inset:0 auto 0 0;width:var(--sb);background:var(--blk);color:#fff;display:flex;flex-direction:column;
            padding:calc(18px + env(safe-area-inset-top)) 12px calc(14px + env(safe-area-inset-bottom));z-index:50;
            transition:transform .25s cubic-bezier(.4,0,.2,1)}
        .brand{display:flex;align-items:center;gap:10px;padding:6px 10px 16px;flex:0 0 auto}
        .brand .logo{width:34px;height:34px;border-radius:9px;background:var(--blue);display:flex;align-items:center;justify-content:center;font-size:16px}
        .brand b{font-family:var(--fh);font-weight:700;font-size:18px;letter-spacing:.02em}
        .nav{display:flex;flex-direction:column;gap:2px;margin-top:4px;overflow-y:auto;overscroll-behavior:contain;flex:1 1 auto;
             scrollbar-width:thin;scrollbar-color:rgba(255,255,255,.18) transparent}
        .nav::-webkit-scrollbar{width:6px}
        .nav::-webkit-scrollbar-thumb{background:rgba(255,255,255,.18);border-radius:3px}
        .nav a,.nav summary{display:flex;align-items:center;gap:12px;padding:11px 13px;border-radius:11px;color:rgba(255,255,255,.72);
             font-family:var(--fh);font-weight:500;font-size:14.5px;transition:background .18s,color .18s}
        .nav a i,.nav summary>i{width:20px;text-align:center;font-size:15px;flex:0 0 auto}
        .nav a:hover,.nav summary:hover{background:rgba(255,255,255,.06);color:#fff}
        .nav a.on{background:var(--blue);color:#fff}
        .nav .sep{font-size:10.5px;letter-spacing:.14em;text-transform:uppercase;color:rgba(255,255,255,.35);padding:16px 13px 6px;font-family:var(--fh)}
        /* Groupes dépliables : <details> natif — la navigation marche même
           sans JavaScript, et le groupe courant s'ouvre tout seul. */
        .nav details>summary{cursor:pointer;list-style:none;user-select:none}
        .nav details>summary::-webkit-details-marker{display:none}
        .nav details>summary .chev{margin-left:auto;font-size:11px;opacity:.45;transition:transform .2s}
        .nav details[open]>summary{color:#fff;background:rgba(255,255,255,.05)}
        .nav details[open]>summary .chev{transform:rotate(90deg)}
        .nav details>summary.cur{background:var(--blue);color:#fff}
        .nav details>summary.cur .chev{opacity:.85}
        .nav .sub{display:flex;flex-direction:column;gap:1px;margin:2px 0 8px 23px;padding-left:11px;border-left:1.5px solid rgba(255,255,255,.12)}
        .nav .sub a{padding:9px 12px;border-radius:9px;font-size:13.5px;font-weight:500;color:rgba(255,255,255,.58)}
        /* Titre de bloc DANS un module. Treize entrées d'affilée se lisent
           comme une liste de courses ; en blocs, on trouve sans lire. */
        .nav .sub .bloc{font-size:9.5px;letter-spacing:.13em;text-transform:uppercase;
              color:rgba(255,255,255,.3);padding:11px 12px 3px;font-family:var(--fh)}
        .nav .sub .bloc:first-child{padding-top:3px}
        .nav .sub a i{width:17px;font-size:12.5px}
        .sb-foot{flex:0 0 auto;margin-top:6px;padding:12px 13px 2px;border-top:1px solid rgba(255,255,255,.07);font-size:12px;color:rgba(255,255,255,.4)}
        /* Voile du tiroir (téléphone uniquement) */
        .scrim{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:45;opacity:0;pointer-events:none;transition:opacity .25s}
        /* ── Zone principale ───────────────────────────────────────────── */
        .main{margin-left:var(--sb);min-height:100vh;min-height:100dvh;display:flex;flex-direction:column}
        .bar{position:sticky;top:0;z-index:40;background:rgba(245,245,243,.88);backdrop-filter:blur(12px);border-bottom:1px solid var(--bd)}
        .top{padding:13px 26px;display:flex;align-items:center;gap:12px;min-height:56px}
        .top .burger{display:none;background:none;border:0;font-size:19px;cursor:pointer;color:var(--blk);padding:6px 8px;border-radius:9px;margin-left:-8px}
        .top .home{background:none;border:0;font-size:18px;cursor:pointer;color:var(--blk);display:flex;align-items:center;padding:6px;border-radius:9px}
        .top .home:hover,.top .home:active{background:var(--blue-pale)}
        .top h1{font-family:var(--fh);font-weight:700;font-size:20px;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .top .who{font-size:13px;color:var(--muted);max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .top .out{background:none;border:0;cursor:pointer;color:var(--muted);font-size:16px;padding:6px;border-radius:9px}
        /* Barre d'écrans du module courant : sur téléphone, c'est ELLE qui
           montre tout ce que contient le module (la latérale est repliée). */
        .subnav{display:flex;gap:7px;padding:0 26px 10px;overflow-x:auto;scrollbar-width:none;-webkit-overflow-scrolling:touch}
        .subnav::-webkit-scrollbar{display:none}
        .subnav a{flex:0 0 auto;display:inline-flex;align-items:center;gap:7px;padding:8px 14px;border-radius:999px;
                  font:600 13.5px var(--fh);color:#4a4a4a;background:#fff;border:1.5px solid var(--bd);white-space:nowrap;transition:.15s}
        .subnav a:hover{border-color:var(--blue);color:var(--blue-deep)}
        .subnav a.on{background:var(--blk);border-color:var(--blk);color:#fff}
        .subnav a i{font-size:12.5px}
        /* Un filet entre deux blocs : la barre défile, un titre y coûterait la
           place d'un écran entier. */
        .subnav .coupe{flex:0 0 1px;width:1px;background:var(--bd);margin:4px 3px;align-self:stretch}
        .content{padding:26px;max-width:1100px;width:100%;margin:0 auto;flex:1 1 auto}
        /* ── Composants réutilisables ──────────────────────────────────── */
        .flash{border-radius:12px;padding:13px 16px;margin-bottom:18px;font-size:14px;display:flex;gap:10px;align-items:center}
        .flash.ok{background:#eafaf3;color:#0e5f44;border:1px solid var(--green)}
        .flash.err{background:#fdecea;color:#9a2820;border:1px solid var(--red)}
        .card{background:var(--surface);border:1px solid var(--bd);border-radius:16px;padding:20px}
        .card+.card{margin-top:16px}
        .grid{display:grid;gap:16px}
        .g2{grid-template-columns:repeat(2,1fr)}.g3{grid-template-columns:repeat(3,1fr)}.g4{grid-template-columns:repeat(4,1fr)}
        .stat{background:var(--surface);border:1px solid var(--bd);border-radius:16px;padding:18px}
        .stat .ic{width:42px;height:42px;border-radius:11px;background:var(--blue-pale);color:var(--blue-deep);display:flex;align-items:center;justify-content:center;font-size:18px;margin-bottom:12px}
        .stat .v{font-family:var(--fh);font-weight:700;font-size:26px}
        .stat .k{font-size:13px;color:var(--muted)}
        .h-row{display:flex;align-items:center;gap:12px;margin-bottom:18px;flex-wrap:wrap}
        .h-row h2{font-family:var(--fh);font-weight:700;font-size:17px;flex:1;min-width:140px}
        .btn{display:inline-flex;align-items:center;gap:8px;border:0;border-radius:11px;padding:11px 18px;font:600 14px var(--fh);cursor:pointer;transition:transform .12s,filter .15s}
        .btn:active{transform:scale(.97)}
        .btn-p{background:var(--blue);color:#fff}.btn-p:hover{filter:brightness(1.05)}
        .btn-d{background:var(--blk);color:#fff}
        .btn-o{background:#fff;border:1.5px solid var(--bd);color:var(--blk)}
        .btn-sm{padding:7px 12px;font-size:13px;border-radius:9px}
        .lbl{display:block;font:600 12.5px var(--fh);color:#555;margin:14px 0 6px;letter-spacing:.01em}
        .inp,.sel,textarea.inp{width:100%;padding:12px 14px;border:1.5px solid var(--bd);border-radius:11px;font:15px var(--fb);background:#fff;transition:border-color .18s}
        .inp:focus,.sel:focus,textarea.inp:focus{outline:0;border-color:var(--blue)}
        .row{display:flex;gap:12px;flex-wrap:wrap}.row>*{flex:1;min-width:160px}
        table{width:100%;border-collapse:collapse}
        th{font:600 12px var(--fh);text-transform:uppercase;letter-spacing:.05em;color:var(--muted);text-align:left;padding:10px 12px;border-bottom:1px solid var(--bd)}
        td{padding:12px;border-bottom:1px solid var(--bd);font-size:14px}
        .pill{display:inline-flex;align-items:center;gap:5px;font:600 11.5px var(--fh);padding:4px 10px;border-radius:999px}
        .pill.g{background:#eafaf3;color:#0e5f44}.pill.r{background:#fdecea;color:#9a2820}.pill.a{background:#fff5e6;color:#7a5200}.pill.n{background:#eee;color:#666}
        .empty{text-align:center;color:var(--muted);padding:48px 20px}
        .empty i{font-size:34px;color:#cfcfcf;display:block;margin-bottom:12px}
        .switch{display:flex;align-items:center;gap:10px;font-size:14px;margin-top:8px}
        .switch input{width:42px;height:24px;appearance:none;background:#ccc;border-radius:999px;position:relative;cursor:pointer;transition:background .2s;flex:0 0 auto}
        .switch input:checked{background:var(--blue)}
        .switch input::after{content:"";position:absolute;top:2px;left:2px;width:20px;height:20px;background:#fff;border-radius:50%;transition:transform .2s}
        .switch input:checked::after{transform:translateX(18px)}
        /* ── La barre du bas : cinq destinations, toujours sous le pouce ──
           Sur un téléphone, le tiroir latéral demande deux gestes et n'affiche
           rien qui dise où l'on peut aller. Les cinq endroits où un marchand
           retourne toute la journée restent donc visibles en permanence. */
        .tabs{display:none;position:fixed;left:0;right:0;bottom:0;z-index:44;
              background:rgba(255,255,255,.94);backdrop-filter:blur(14px);
              border-top:1px solid var(--bd);
              padding-bottom:env(safe-area-inset-bottom)}
        .tabs>div{display:grid;grid-template-columns:repeat(5,1fr);max-width:640px;margin:0 auto}
        .tabs a,.tabs button{display:flex;flex-direction:column;align-items:center;justify-content:center;
              gap:3px;padding:8px 2px 7px;background:none;border:0;cursor:pointer;
              color:var(--muted);font:600 10.5px var(--fh);min-height:54px;
              -webkit-tap-highlight-color:transparent}
        .tabs i{font-size:18px;line-height:1}
        .tabs .on{color:var(--blue-deep)}
        /* Le repère d'onglet actif : une barre courte au-dessus de l'icône, pas
           un fond plein — un fond plein sur cinq onglets fait une mosaïque. */
        .tabs .on::before{content:"";position:absolute;top:0;width:30px;height:3px;
              border-radius:0 0 3px 3px;background:var(--blue)}
        .tabs a,.tabs button{position:relative}
        /* La feuille « Plus » : tout le reste, à portée de pouce. */
        .sheet-more{position:fixed;inset:0;z-index:70;display:none}
        .sheet-more.open{display:block}
        .sheet-more .fond{position:absolute;inset:0;background:rgba(0,0,0,.5)}
        .sheet-more .panneau{position:absolute;left:0;right:0;bottom:0;background:var(--surface);
              border-radius:20px 20px 0 0;padding:8px 16px calc(20px + env(safe-area-inset-bottom));
              max-height:80vh;overflow-y:auto;box-shadow:0 -10px 40px rgba(0,0,0,.2)}
        .sheet-more .poignee{width:40px;height:4px;border-radius:2px;background:var(--bd);margin:8px auto 14px}
        .sheet-more h3{font-family:var(--ft);font-weight:400;font-size:19px;margin-bottom:12px}
        .sheet-more .liste{display:grid;grid-template-columns:repeat(auto-fit,minmax(96px,1fr));gap:8px}
        .sheet-more .liste a{display:flex;flex-direction:column;align-items:center;gap:7px;
              padding:14px 6px;border-radius:14px;border:1px solid var(--bd);
              font:600 12px var(--fh);text-align:center;color:var(--blk);background:#fff}
        .sheet-more .liste a i{font-size:19px;color:var(--blue-deep)}
        /* ── Tablette ──────────────────────────────────────────────────── */
        @media(max-width:1100px){.g4{grid-template-columns:repeat(2,1fr)}}
        /* ── Téléphone : la latérale devient un tiroir ─────────────────── */
        @media(max-width:860px){
            .sb{transform:translateX(-100%);box-shadow:0 0 40px rgba(0,0,0,.35)}
            .sb.open{transform:none}
            .main{margin-left:0}
            .top .burger{display:flex}
            .scrim{display:block}
            body.nav-open{overflow:hidden}
            body.nav-open .scrim{opacity:1;pointer-events:auto}
            .g3,.g2{grid-template-columns:1fr}
            .tabs{display:block}
            /* La barre recouvre 54px : sans cette réserve, le dernier bouton de
               chaque page se retrouve dessous et devient inatteignable. */
            .content{padding-bottom:calc(74px + env(safe-area-inset-bottom))}
        }
        @media(max-width:640px){
            .top{padding:11px 16px;gap:8px}
            .subnav{padding:0 16px 9px}
            .content{padding:18px 16px calc(24px + env(safe-area-inset-bottom))}
            .top h1{font-size:17px}
            .top .who{display:none}
            .card{padding:16px;border-radius:14px}
            .grid{gap:12px}
            .stat{padding:15px}.stat .v{font-size:22px}
            .row>*{min-width:100%}
            /* Un tableau large ne doit jamais pousser la page de côté :
               il défile seul, à l'intérieur de sa carte. */
            .content table{display:block;overflow-x:auto;white-space:nowrap;-webkit-overflow-scrolling:touch}
            .btn{padding:11px 15px}
        }
        /* Sous 360px, deux colonnes de statistiques deviennent illisibles. */
        @media(max-width:360px){.g4{grid-template-columns:1fr}}
        /* Doigt : jamais de cible plus petite que 44px */
        @media(hover:none){.nav a,.nav summary,.subnav a,.top .burger,.top .home,.top .out{min-height:44px}}
        @media (prefers-reduced-motion:reduce){*{transition:none!important}}
        @media print{.sb,.bar,.scrim{display:none!important}.main{margin-left:0}.content{padding:0;max-width:none}}
    </style>
    @stack('head')
</head>
<body>
    @php
        $mods = \Modules\Tagtoa\App\Support\DashboardModules::class;
        // Où sommes-nous ? Une seule réponse, partagée par la barre latérale et
        // la barre d'écrans — impossible que l'une souligne un module et
        // l'autre un autre.
        [$modCourant, $ecranCourant] = $mods::locate(request()->path());
        $estAccueil = request()->is('tagtoa/home');
        $u = auth()->user();
        $isSuper = false;
        try { $isSuper = $u && method_exists($u, 'hasRole') && $u->hasRole('super_admin'); } catch (\Throwable $e) { $isSuper = false; }
    @endphp

    <div class="scrim" id="scrim" hidden></div>

    <aside class="sb" id="sb" aria-label="{{ __('Navigation') }}">
        <div class="brand"><span class="logo">⚡</span><b>TAGTOA</b></div>
        <nav class="nav">
            <a href="{{ url('/tagtoa/home') }}" class="{{ $estAccueil ? 'on' : '' }}"><i class="fa-solid fa-grip"></i> {{ __('Accueil') }}</a>

            <span class="sep">{{ __('Modules') }}</span>
            @foreach($mods::enabled('module') as $m)
                @php $ouvert = $modCourant === $m['key']; @endphp
                @if(count($m['children']) <= 1)
                    {{-- Un seul écran : un groupe dépliable serait un clic pour rien. --}}
                    <a href="{{ url($m['url']) }}" class="{{ $ouvert ? 'on' : '' }}">
                        <i class="fa-solid {{ $m['icon'] }}"></i> {{ __($m['label']) }}
                    </a>
                @else
                    <details {{ $ouvert ? 'open' : '' }}>
                        <summary class="{{ $ouvert ? 'cur' : '' }}">
                            <i class="fa-solid {{ $m['icon'] }}"></i>
                            <span>{{ __($m['label']) }}</span>
                            <i class="fa-solid fa-chevron-right chev" aria-hidden="true"></i>
                        </summary>
                        <div class="sub">
                            @foreach($m['children'] as $c)
                                @isset($c['sep'])<span class="bloc">{{ __($c['sep']) }}</span>@endisset
                                <a href="{{ url($c['url']) }}" class="{{ $ouvert && $ecranCourant === rtrim($c['url'],'/') ? 'on' : '' }}">
                                    <i class="fa-solid {{ $c['icon'] }}"></i> {{ __($c['label']) }}
                                </a>
                            @endforeach
                        </div>
                    </details>
                @endif
            @endforeach

            <span class="sep">{{ __('Compte') }}</span>
            @foreach($mods::enabled('account') as $m)
                <a href="{{ url($m['url']) }}" class="{{ $modCourant === $m['key'] ? 'on' : '' }}">
                    <i class="fa-solid {{ $m['icon'] }}"></i> {{ __($m['label']) }}
                </a>
            @endforeach

            @if($isSuper)
                <span class="sep">{{ __('Plateforme') }}</span>
                <a href="{{ url('/tagtoa/admin/plans') }}" class="{{ request()->is('tagtoa/admin/plans*') ? 'on' : '' }}"><i class="fa-solid fa-layer-group"></i> {{ __('Forfaits TAGTOA') }}</a>
                <a href="{{ url('/tagtoa/admin/card-credits') }}" class="{{ request()->is('tagtoa/admin/card-credits*') ? 'on' : '' }}"><i class="fa-solid fa-coins"></i> {{ __('Crédits cartes') }}</a>
                {{-- Journal d'audit : retiré du menu marchand, mais c'est une pièce
                     de conformité (BRH) — elle reste accessible au fondateur. --}}
                @unless($mods::isEnabled('audit'))
                    <a href="{{ url('/tagtoa/audit') }}" class="{{ request()->is('tagtoa/audit*') ? 'on' : '' }}"><i class="fa-solid fa-clipboard-list"></i> {{ __('Journal d\'audit') }}</a>
                @endunless
                <a href="{{ url('/sadmin/dashboard') }}"><i class="fa-solid fa-shield-halved"></i> {{ __('Super Admin') }}</a>
            @endif
        </nav>
        <div class="sb-foot">TAGTOA · GOVIBE Ecosystem</div>
    </aside>

    <div class="main">
        <div class="bar">
            <header class="top">
                <button class="burger" id="burger" type="button" aria-controls="sb" aria-expanded="false" aria-label="{{ __('Ouvrir le menu') }}"><i class="fa-solid fa-bars"></i></button>
                @unless($estAccueil)
                    <a class="home" href="{{ url('/tagtoa/home') }}" title="{{ __('Retour à l\'accueil') }}"><i class="fa-solid fa-house"></i></a>
                @endunless
                <h1>@yield('page', 'TAGTOA')</h1>
                @include('tagtoa::partials.lang')
                <span class="who">{{ optional($u)->name ?? '' }}</span>
                @if(\Illuminate\Support\Facades\Route::has('logout'))
                    <form method="POST" action="{{ route('logout') }}" style="margin:0">@csrf
                        <button type="submit" class="out" title="{{ __('Se déconnecter') }}"><i class="fa-solid fa-right-from-bracket"></i></button>
                    </form>
                @endif
            </header>
            @php $ecrans = $modCourant ? $mods::children($modCourant) : []; @endphp
            @if(count($ecrans) > 1)
                <nav class="subnav" aria-label="{{ __($mods::CATALOG[$modCourant]['label']) }}">
                    @foreach($ecrans as $i => $c)
                        @if($i > 0 && isset($c['sep']))<span class="coupe" aria-hidden="true"></span>@endif
                        <a href="{{ url($c['url']) }}" class="{{ $ecranCourant === rtrim($c['url'],'/') ? 'on' : '' }}"
                           @isset($c['sep']) title="{{ __($c['sep']) }}" @endisset>
                            <i class="fa-solid {{ $c['icon'] }}"></i> {{ __($c['label']) }}
                        </a>
                    @endforeach
                </nav>
            @endif
        </div>
        <main class="content">
            @if(session('success'))<div class="flash ok"><i class="fa-solid fa-circle-check"></i> {{ session('success') }}</div>@endif
            @if(session('error'))<div class="flash err"><i class="fa-solid fa-circle-exclamation"></i> {{ session('error') }}</div>@endif
            @if($errors->any())<div class="flash err"><i class="fa-solid fa-circle-exclamation"></i> {{ $errors->first() }}</div>@endif
            @yield('content')
        </main>
    </div>

    @php $ongletActif = $mods::bottomActive(request()->path()); @endphp
    <nav class="tabs" aria-label="{{ __('Navigation principale') }}">
        <div>
            @foreach($mods::BOTTOM as $t)
                @if($t['key'] === 'more')
                    <button type="button" id="moreBtn" aria-haspopup="dialog" aria-expanded="false">
                        <i class="fa-solid {{ $t['icon'] }}"></i>{{ __($t['label']) }}
                    </button>
                @else
                    <a href="{{ url($t['url']) }}" class="{{ $ongletActif === $t['key'] ? 'on' : '' }}"
                       @if($ongletActif === $t['key']) aria-current="page" @endif>
                        <i class="fa-solid {{ $t['icon'] }}"></i>{{ __($t['label']) }}
                    </a>
                @endif
            @endforeach
        </div>
    </nav>

    <div class="sheet-more" id="sheetMore" role="dialog" aria-modal="true" aria-label="{{ __('Tout TAGTOA') }}">
        <div class="fond" data-fermer></div>
        <div class="panneau">
            <div class="poignee"></div>
            <h3>{{ __('Tout TAGTOA') }}</h3>
            {{-- Lu depuis le MÊME catalogue que la barre latérale : une liste
                 écrite à la main ici divergerait au premier module ajouté. --}}
            <div class="liste">
                @foreach($mods::more() as $m)
                    <a href="{{ url($m['url']) }}">
                        <i class="fa-solid {{ $m['icon'] }}"></i>{{ __($m['label']) }}
                    </a>
                @endforeach
                @if($isSuper)
                    <a href="{{ url('/sadmin/dashboard') }}"><i class="fa-solid fa-shield-halved"></i>{{ __('Super Admin') }}</a>
                @endif
            </div>
        </div>
    </div>

    <script>
    (function () {
        var sb = document.getElementById('sb'),
            scrim = document.getElementById('scrim'),
            burger = document.getElementById('burger');
        if (!sb || !burger) return;

        function ouvrir(oui) {
            sb.classList.toggle('open', oui);
            document.body.classList.toggle('nav-open', oui);
            burger.setAttribute('aria-expanded', oui ? 'true' : 'false');
            // Le voile ne doit pas intercepter les clics quand il est invisible.
            if (scrim) scrim.hidden = !oui;
        }

        burger.addEventListener('click', function () { ouvrir(!sb.classList.contains('open')); });
        if (scrim) scrim.addEventListener('click', function () { ouvrir(false); });

        // Refermer en partant : sinon le tiroir reste ouvert par-dessus la page
        // suivante sur les navigateurs qui restaurent l'état (bfcache).
        sb.addEventListener('click', function (e) {
            if (e.target.closest('a')) ouvrir(false);
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && sb.classList.contains('open')) ouvrir(false);
        });

        // Passage téléphone → bureau : la latérale redevient fixe, le verrou de
        // défilement n'aurait plus rien à verrouiller.
        window.addEventListener('resize', function () {
            if (window.innerWidth > 860 && sb.classList.contains('open')) ouvrir(false);
        });

        window.addEventListener('pageshow', function () { ouvrir(false); });

        // La feuille « Plus » : même règles que le tiroir — voile qui ferme,
        // Échap qui ferme, défilement de fond bloqué.
        var feuille = document.getElementById('sheetMore'),
            moreBtn = document.getElementById('moreBtn');

        function feuilleOuvrir(oui) {
            if (!feuille) return;
            feuille.classList.toggle('open', oui);
            document.body.classList.toggle('nav-open', oui);
            if (moreBtn) moreBtn.setAttribute('aria-expanded', oui ? 'true' : 'false');
        }

        if (moreBtn) moreBtn.addEventListener('click', function () {
            feuilleOuvrir(!feuille.classList.contains('open'));
        });
        if (feuille) feuille.addEventListener('click', function (e) {
            // Un lien ferme aussi : sinon la feuille reste par-dessus la page
            // suivante sur les navigateurs qui restaurent l'état.
            if (e.target.closest('[data-fermer]') || e.target.closest('a')) feuilleOuvrir(false);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && feuille && feuille.classList.contains('open')) feuilleOuvrir(false);
        });
        window.addEventListener('pageshow', function () { feuilleOuvrir(false); });

        // L'écran actif peut être hors champ dans la barre d'écrans : on
        // l'amène sous les yeux plutôt que de laisser le marchand deviner.
        var actif = document.querySelector('.subnav a.on');
        if (actif && actif.scrollIntoView) {
            actif.scrollIntoView({ block: 'nearest', inline: 'center' });
        }
    })();
    </script>
    @stack('scripts')
</body>
</html>
