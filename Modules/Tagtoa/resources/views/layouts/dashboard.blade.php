{{-- TAGTOA — Layout dashboard (standalone, design system TAGTOA, mobile-first).
     N'hérite PAS du back-office vcard existant : interface propre et claire.
     Sections : @section('title'), @section('page'), @yield('content'). --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title','TAGTOA') · TAGTOA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Nunito:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root{
            --blk:#0A0A0A;--white:#fff;--bg:#F5F5F3;--surface:#fff;--blue:#16A34A;--blue-deep:#15803D;
            --blue-pale:rgba(22,163,74,.08);--green:#1D9E75;--red:#E0473E;--amber:#E08A1E;
            --bd:rgba(0,0,0,.08);--muted:#8a8a8a;--fh:'Space Grotesk',sans-serif;--fb:'Nunito',sans-serif;
            --sb:248px;
        }
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:var(--fb);background:var(--bg);color:var(--blk);line-height:1.55;-webkit-font-smoothing:antialiased}
        a{color:inherit;text-decoration:none}
        /* Sidebar */
        .sb{position:fixed;inset:0 auto 0 0;width:var(--sb);background:var(--blk);color:#fff;display:flex;flex-direction:column;padding:20px 14px;z-index:50;transition:transform .25s cubic-bezier(.4,0,.2,1)}
        .brand{display:flex;align-items:center;gap:10px;padding:6px 10px 18px}
        .brand .logo{width:34px;height:34px;border-radius:9px;background:var(--blue);display:flex;align-items:center;justify-content:center;font-size:16px}
        .brand b{font-family:var(--fh);font-weight:700;font-size:18px;letter-spacing:.02em}
        .nav{display:flex;flex-direction:column;gap:3px;margin-top:8px;overflow-y:auto}
        .nav a{display:flex;align-items:center;gap:12px;padding:11px 13px;border-radius:11px;color:rgba(255,255,255,.72);font-family:var(--fh);font-weight:500;font-size:14.5px;transition:background .18s,color .18s}
        .nav a i{width:20px;text-align:center;font-size:15px}
        .nav a:hover{background:rgba(255,255,255,.06);color:#fff}
        .nav a.on{background:var(--blue);color:#fff}
        .nav .sep{font-size:10.5px;letter-spacing:.14em;text-transform:uppercase;color:rgba(255,255,255,.35);padding:14px 13px 6px;font-family:var(--fh)}
        .sb-foot{margin-top:auto;padding:12px 13px 4px;font-size:12px;color:rgba(255,255,255,.4)}
        /* Main */
        .main{margin-left:var(--sb);min-height:100vh;display:flex;flex-direction:column}
        .top{position:sticky;top:0;background:rgba(245,245,243,.85);backdrop-filter:blur(10px);border-bottom:1px solid var(--bd);padding:14px 26px;display:flex;align-items:center;gap:14px;z-index:40}
        .top .burger{display:none;background:none;border:0;font-size:20px;cursor:pointer}
        .top h1{font-family:var(--fh);font-weight:700;font-size:20px;flex:1}
        .top .who{font-size:13px;color:var(--muted)}
        .content{padding:26px;max-width:1100px;width:100%;margin:0 auto}
        /* Reusable UI */
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
        .h-row{display:flex;align-items:center;gap:12px;margin-bottom:18px}
        .h-row h2{font-family:var(--fh);font-weight:700;font-size:17px;flex:1}
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
        .switch input{width:42px;height:24px;appearance:none;background:#ccc;border-radius:999px;position:relative;cursor:pointer;transition:background .2s}
        .switch input:checked{background:var(--blue)}
        .switch input::after{content:"";position:absolute;top:2px;left:2px;width:20px;height:20px;background:#fff;border-radius:50%;transition:transform .2s}
        .switch input:checked::after{transform:translateX(18px)}
        @media(max-width:860px){
            .sb{transform:translateX(-100%)}.sb.open{transform:none}
            .main{margin-left:0}.top .burger{display:block}
            .g4{grid-template-columns:repeat(2,1fr)}.g3,.g2{grid-template-columns:1fr}
        }
        @media (prefers-reduced-motion:reduce){*{transition:none!important}}
    </style>
    @stack('head')
</head>
<body>
    @php $cur = request()->segment(3) ?? request()->segment(2); @endphp
    <aside class="sb" id="sb">
        <div class="brand"><span class="logo">⚡</span><b>TAGTOA</b></div>
        <nav class="nav">
            <a href="{{ url('/tagtoa/home') }}" class="{{ request()->is('tagtoa/home') ? 'on' : '' }}"><i class="fa-solid fa-grip"></i> {{ __('Accueil') }}</a>
            <span class="sep">{{ __('Modules') }}</span>
            <a href="{{ url('/tagtoa/site') }}" class="{{ request()->is('tagtoa/site*') ? 'on' : '' }}"><i class="fa-solid fa-globe"></i> {{ __('Site web') }}</a>
            <a href="{{ url('/tagtoa/menu') }}" class="{{ request()->is('tagtoa/menu*') ? 'on' : '' }}"><i class="fa-solid fa-utensils"></i> {{ __('Menu') }}</a>
            <a href="{{ url('/tagtoa/pay') }}" class="{{ request()->is('tagtoa/pay*') ? 'on' : '' }}"><i class="fa-solid fa-money-bill-transfer"></i> {{ __('Paiements') }}</a>
            <a href="{{ url('/tagtoa/loyalty') }}" class="{{ request()->is('tagtoa/loyalty*') ? 'on' : '' }}"><i class="fa-solid fa-id-card"></i> {{ __('Fidélité') }}</a>
            <a href="{{ url('/tagtoa/links') }}" class="{{ request()->is('tagtoa/links*') ? 'on' : '' }}"><i class="fa-solid fa-link"></i> {{ __('Liens') }}</a>
            <a href="{{ url('/tagtoa/event') }}" class="{{ request()->is('tagtoa/event*') ? 'on' : '' }}"><i class="fa-solid fa-ticket"></i> {{ __('Événements') }}</a>
            <a href="{{ url('/tagtoa/booking') }}" class="{{ request()->is('tagtoa/booking*') ? 'on' : '' }}"><i class="fa-solid fa-calendar-check"></i> {{ __('Réservations') }}</a>
            <a href="{{ url('/tagtoa/pos') }}" class="{{ request()->is('tagtoa/pos*') ? 'on' : '' }}"><i class="fa-solid fa-cash-register"></i> {{ __('Caisse (POS)') }}</a>
            <span class="sep">{{ __('Compte') }}</span>
            <a href="{{ url('/tagtoa/analytics') }}" class="{{ request()->is('tagtoa/analytics*') ? 'on' : '' }}"><i class="fa-solid fa-chart-line"></i> {{ __('Analytics') }}</a>
            <a href="{{ url('/tagtoa/customers') }}" class="{{ request()->is('tagtoa/customers*') ? 'on' : '' }}"><i class="fa-solid fa-users"></i> {{ __('Clients') }}</a>
            <a href="{{ url('/tagtoa/qr') }}" class="{{ request()->is('tagtoa/qr*') ? 'on' : '' }}"><i class="fa-solid fa-qrcode"></i> {{ __('QR & Partage') }}</a>
            <a href="{{ url('/tagtoa/plan') }}" class="{{ request()->is('tagtoa/plan*') ? 'on' : '' }}"><i class="fa-solid fa-crown"></i> {{ __('Abonnement') }}</a>
            <a href="{{ url('/tagtoa/billing') }}" class="{{ request()->is('tagtoa/billing*') ? 'on' : '' }}"><i class="fa-solid fa-wallet"></i> {{ __('Revenu & forfait') }}</a>
        </nav>
        <div class="sb-foot">TAGTOA · GOVIBE Ecosystem</div>
    </aside>

    <div class="main">
        <header class="top">
            <button class="burger" onclick="document.getElementById('sb').classList.toggle('open')"><i class="fa-solid fa-bars"></i></button>
            <h1>@yield('page', 'TAGTOA')</h1>
            @include('tagtoa::partials.lang')
            <span class="who">{{ optional(auth()->user())->name ?? '' }}</span>
        </header>
        <main class="content">
            @if(session('success'))<div class="flash ok"><i class="fa-solid fa-circle-check"></i> {{ session('success') }}</div>@endif
            @if(session('error'))<div class="flash err"><i class="fa-solid fa-circle-exclamation"></i> {{ session('error') }}</div>@endif
            @if($errors->any())<div class="flash err"><i class="fa-solid fa-circle-exclamation"></i> {{ $errors->first() }}</div>@endif
            @yield('content')
        </main>
    </div>
    @stack('scripts')
</body>
</html>
