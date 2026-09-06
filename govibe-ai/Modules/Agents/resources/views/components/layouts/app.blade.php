<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'LOUVIA' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Anton&display=swap">
    <style>
        /* Fon blan, tèks nwa, bouton vèt. Gri yo sèvi sèlman pou liy ak
           tèks segondè — yo pa yon dezyèm fon. */
        :root {
            --page: #ffffff;
            --surface: #ffffff;
            --line: #e0e0e0;
            --line-strong: #c4c4c4;
            --ink: #000000;
            --ink-soft: #2b2b2b;
            --muted: #5f5f5f;
            --accent: #0f8a3d;
            --accent-hover: #0b6c2f;
            --accent-soft: #eaf6ee;
            --ok-ink: #0b6c2f;
            --ok-bg: #eaf6ee;
            --ok-line: #b6ddc4;
            --warn-ink: #7a5300;
            --warn-bg: #fdf7e8;
            --warn-line: #ead9ac;
            --danger: #9b1c1c;
            --radius: 6px;
            --display: "Anton", "Arial Narrow", Impact, system-ui, sans-serif;
            --topbar-h: 56px;
        }
        * { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body {
            margin: 0;
            background: var(--page);
            color: var(--ink);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            font-size: 15px;
            line-height: 1.55;
        }
        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; }

        /* ---- Chapo + meni sou kote ----
           Drawer la mache SAN JavaScript: yon checkbox kache pote eta a.
           Se enpòtan sou telefòn — si JS la pa chaje, meni an ap louvri
           kanmenm. */
        .topbar {
            background: var(--surface); border-bottom: 1px solid var(--line);
            position: sticky; top: 0; z-index: 30;
        }
        .topbar .inner {
            max-width: 1180px; margin: 0 auto; padding: 0 1rem;
            height: var(--topbar-h); display: flex; align-items: center; gap: .75rem;
        }
        .topbar .brand {
            font-size: 1.05rem; font-weight: 700; letter-spacing: .1em;
            color: var(--ink); text-decoration: none;
        }
        .topbar .brand:hover { text-decoration: none; }
        .topbar .sub { color: var(--muted); font-size: .85rem; display: none; }
        .topbar .spacer { margin-left: auto; }
        .topbar .cta { font-size: .86rem; padding: .38rem .8rem; }

        .nav-toggle { position: absolute; opacity: 0; pointer-events: none; }
        .burger {
            width: 40px; height: 40px; flex: none; border-radius: var(--radius);
            border: 1px solid var(--line-strong); background: var(--surface);
            display: inline-flex; align-items: center; justify-content: center;
            cursor: pointer; color: var(--accent);
        }
        .burger:hover { background: var(--accent-soft); }
        .burger svg { width: 20px; height: 20px; fill: currentColor; }
        .nav-toggle:focus-visible + .burger { outline: 2px solid var(--accent); outline-offset: 2px; }

        .drawer {
            position: fixed; inset: 0 auto 0 0; width: min(84vw, 300px); z-index: 50;
            background: var(--surface); border-right: 1px solid var(--line);
            transform: translateX(-102%); transition: transform .22s ease;
            display: flex; flex-direction: column; padding: 1rem 1rem 2rem; gap: .15rem;
            overflow-y: auto;
        }
        .scrim {
            position: fixed; inset: 0; z-index: 40; background: rgba(0, 0, 0, .35);
            opacity: 0; pointer-events: none; transition: opacity .22s ease;
        }
        .nav-toggle:checked ~ .drawer { transform: none; }
        .nav-toggle:checked ~ .scrim { opacity: 1; pointer-events: auto; }
        .drawer .head { display: flex; align-items: center; margin-bottom: .75rem; }
        .drawer .head .brand { font-size: 1rem; font-weight: 700; letter-spacing: .1em; }
        .drawer .close { margin-left: auto; }
        .drawer a.item {
            padding: .6rem .55rem; border-radius: var(--radius); color: var(--ink);
            text-decoration: none; font-size: .95rem; font-weight: 500;
        }
        .drawer a.item:hover { background: var(--accent-soft); color: var(--accent); text-decoration: none; }
        .drawer .group {
            margin: 1rem 0 .3rem; padding: 0 .55rem; font-size: .72rem; font-weight: 650;
            letter-spacing: .08em; text-transform: uppercase; color: var(--muted);
        }
        @media (prefers-reduced-motion: reduce) {
            .drawer, .scrim { transition: none; }
        }
        @media (min-width: 720px) {
            .topbar .sub { display: inline; }
        }

        /* ---- Kò paj la ---- */
        .wrap { max-width: 960px; margin: 0 auto; padding: 1.75rem 1.25rem 4rem; }
        .page-head { margin-bottom: 1.5rem; }
        h1 { font-size: 1.4rem; font-weight: 650; margin: 0 0 .3rem; letter-spacing: -.01em; }
        h2 {
            font-size: .78rem; font-weight: 650; margin: 2.25rem 0 .9rem;
            color: var(--muted); text-transform: uppercase; letter-spacing: .07em;
        }
        h3 { font-size: 1rem; font-weight: 600; margin: 0; }
        p.lead { color: var(--ink-soft); margin: 0; max-width: 62ch; }

        /* ---- Kat ---- */
        .grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); }
        .card {
            background: var(--surface); border: 1px solid var(--line);
            border-radius: var(--radius); padding: 1.1rem 1.15rem;
            display: flex; flex-direction: column;
        }
        .card > .card-body { flex: 1; }
        .card .desc { margin: .35rem 0 0; color: var(--muted); font-size: .9rem; }
        .card ul.does {
            list-style: none; padding: 0; margin: .85rem 0 0;
            font-size: .88rem; color: var(--ink-soft);
        }
        .card ul.does li { padding: .18rem 0 .18rem .95rem; position: relative; }
        .card ul.does li::before {
            content: ""; position: absolute; left: 0; top: .72em;
            width: 5px; height: 1px; background: var(--line-strong);
        }
        .card .row { margin-top: 1.1rem; }

        /* Lis ajan ki deja kreye: ranje, pa kat — se yon envantè, se pa yon chwa. */
        .list { background: var(--surface); border: 1px solid var(--line); border-radius: var(--radius); }
        .list .item { padding: .8rem 1.15rem; border-bottom: 1px solid var(--line); display: flex; gap: .75rem; align-items: center; flex-wrap: wrap; }
        .list .item:last-child { border-bottom: 0; }
        .list .item .name { font-weight: 600; }
        .list .item .right { margin-left: auto; display: flex; gap: .4rem; }

        /* ---- Bouton ---- */
        .row { display: flex; gap: .5rem; flex-wrap: wrap; }
        .btn {
            display: inline-block; padding: .45rem .9rem; border-radius: var(--radius);
            border: 1px solid var(--line-strong); background: var(--surface); color: var(--ink);
            text-decoration: none; font-size: .89rem; font-weight: 500;
            cursor: pointer; font-family: inherit; line-height: 1.5;
        }
        /* Tout bouton yo vèt. Yon bouton plen pou aksyon prensipal la, yon
           bouton ak kontou pou lòt yo: menm koulè, de nivo. */
        .btn { border-color: var(--accent); color: var(--accent); font-weight: 600; }
        .btn:hover { background: var(--accent-soft); text-decoration: none; }
        .btn-primary { background: var(--accent); border-color: var(--accent); color: #fff; }
        .btn-primary:hover { background: var(--accent-hover); }
        .btn-quiet { border-color: transparent; background: transparent; color: var(--accent); padding-left: 0; }
        .btn-quiet:hover { background: transparent; text-decoration: underline; }
        .btn[disabled] { opacity: .5; cursor: default; }

        /* ---- Demo santre: apèl ak chat ---- */
        .stage { max-width: 620px; margin: 0 auto; text-align: center; }
        .stage h1 { font-size: 1.5rem; }
        .stage .lead { margin: 0 auto; }
        .modes { display: flex; gap: .6rem; justify-content: center; margin: 1.6rem 0; }
        .mode-btn {
            display: inline-flex; align-items: center; gap: .5rem;
            padding: .8rem 1.6rem; border-radius: 999px; font-size: 1rem; font-weight: 600;
            border: 2px solid var(--accent); background: var(--surface); color: var(--accent);
            text-decoration: none; cursor: pointer; font-family: inherit;
        }
        .mode-btn:hover { background: var(--accent-soft); text-decoration: none; }
        .mode-btn[aria-current="true"] { background: var(--accent); color: #fff; }
        .mode-btn svg { width: 18px; height: 18px; fill: currentColor; }

        .stage .thread { text-align: left; margin: 1.2rem 0; }
        .composer {
            border: 1px solid var(--line); border-radius: var(--radius);
            background: var(--surface); padding: .9rem; margin-top: 1.2rem;
        }
        .composer .fields { display: flex; gap: .5rem; align-items: center; }
        .composer input[type=text] { flex: 1; }
        .mic {
            width: 44px; height: 44px; flex: none; border-radius: 999px;
            border: 2px solid var(--accent); background: var(--surface); color: var(--accent);
            cursor: pointer; display: inline-flex; align-items: center; justify-content: center;
        }
        .mic svg { width: 18px; height: 18px; fill: currentColor; }
        .mic[data-state="recording"] { background: var(--accent); color: #fff; animation: pulse 1.4s infinite; }
        @keyframes pulse { 0%,100% { box-shadow: 0 0 0 0 var(--accent-soft); } 50% { box-shadow: 0 0 0 8px var(--accent-soft); } }
        .composer .status { margin: .6rem 0 0; font-size: .86rem; color: var(--muted); min-height: 1.2em; }

        /* ---- Fòm ---- */
        label { display: block; margin: 1.1rem 0 .3rem; font-size: .86rem; font-weight: 600; color: var(--ink-soft); }
        label .hint { display: block; font-weight: 400; color: var(--muted); font-size: .84rem; margin-top: .1rem; }
        input[type=text], textarea {
            width: 100%; padding: .5rem .65rem; border-radius: var(--radius);
            border: 1px solid var(--line-strong); background: var(--surface); color: var(--ink);
            font-family: inherit; font-size: .95rem;
        }
        input[type=text]:focus, textarea:focus {
            outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft);
        }
        textarea { min-height: 72px; resize: vertical; }
        select {
            width: 100%; padding: .5rem .65rem; border-radius: var(--radius);
            border: 1px solid var(--line-strong); background: var(--surface); color: var(--ink);
            font-family: inherit; font-size: .95rem;
        }
        select:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }

        /* Chwa yo se gwo sib, pa ti kare: paj la ap viv sou telefòn. */
        .choices { display: flex; flex-direction: column; gap: .5rem; margin-top: .4rem; }
        .choice {
            display: flex; gap: .65rem; align-items: flex-start; margin: 0;
            border: 1px solid var(--line-strong); border-radius: var(--radius);
            padding: .7rem .8rem; cursor: pointer; font-weight: 400; color: var(--ink);
        }
        .choice:hover { border-color: var(--accent); background: var(--accent-soft); }
        .choice input { margin: .25rem 0 0; accent-color: var(--accent); flex: none; }
        .choice strong { display: block; font-weight: 600; font-size: .95rem; }
        .choice small { display: block; color: var(--muted); font-size: .86rem; margin-top: .1rem; }
        .choice:has(input:checked) { border-color: var(--accent); background: var(--accent-soft); }
        fieldset { border: 1px solid var(--line); border-radius: var(--radius); background: var(--surface);
                   padding: .25rem 1.15rem 1.25rem; margin: 0 0 1.25rem; }
        legend { font-size: .78rem; font-weight: 650; text-transform: uppercase; letter-spacing: .07em;
                 color: var(--muted); padding: 0 .35rem; }

        /* ---- Nòt ---- */
        .note {
            border: 1px solid var(--warn-line); background: var(--warn-bg); color: var(--warn-ink);
            padding: .7rem .9rem; border-radius: var(--radius); font-size: .89rem; margin: 1.15rem 0;
        }
        .note.ok { border-color: var(--ok-line); background: var(--ok-bg); color: var(--ok-ink); }
        .note strong { font-weight: 650; }

        /* ---- Konvèsasyon ---- */
        .thread { background: var(--surface); border: 1px solid var(--line); border-radius: var(--radius); padding: .35rem 0; }
        .msg { padding: .8rem 1.15rem; }
        .msg + .msg { border-top: 1px solid var(--line); }
        .msg .who { font-size: .72rem; text-transform: uppercase; letter-spacing: .07em;
                    color: var(--muted); margin: 0 0 .25rem; font-weight: 650; }
        .msg .body { margin: 0; white-space: pre-wrap; }
        .msg.from-user { background: #fafbfc; }
        .msg .meta { margin-top: .5rem; color: var(--muted); font-size: .78rem; }

        /* ---- Divès ---- */
        .tag {
            display: inline-block; padding: .1rem .5rem; border: 1px solid var(--line-strong);
            border-radius: 3px; font-size: .74rem; color: var(--muted); background: var(--surface);
            text-transform: uppercase; letter-spacing: .04em;
        }
        table { width: 100%; border-collapse: collapse; font-size: .9rem;
                background: var(--surface); border: 1px solid var(--line); border-radius: var(--radius); }
        td { padding: .55rem .9rem; border-bottom: 1px solid var(--line); vertical-align: top; }
        tr:last-child td { border-bottom: 0; }
        td:first-child { color: var(--muted); width: 38%; }
        code { font-size: .85em; background: #f0f2f5; border: 1px solid var(--line);
               border-radius: 3px; padding: .05rem .3rem; }
        .empty { color: var(--muted); font-size: .9rem; }
        .err { color: var(--danger); font-size: .85rem; margin-top: .3rem; }
        .back { margin-top: 2rem; font-size: .89rem; }
    </style>
</head>
<body>
<input type="checkbox" id="nav-toggle" class="nav-toggle" aria-label="{{ __('Menu') }}">

<aside class="drawer" aria-label="{{ __('Menu') }}">
    <div class="head">
        <span class="brand">LOUVIA</span>
        <label for="nav-toggle" class="btn close">{{ __('Fermer') }}</label>
    </div>
    <a class="item" href="{{ route('home') }}">{{ __('Accueil') }}</a>
    <a class="item" href="{{ route('home') }}#chatbots">{{ __('Chatbots et assistants') }}</a>
    <a class="item" href="{{ route('home') }}#agents">{{ __('Agents IA') }}</a>
    <a class="item" href="{{ route('home') }}#catalogue">{{ __('Tous les modèles') }}</a>
    <a class="item" href="{{ route('home') }}#integrations">{{ __('Intégrations') }}</a>
    <div class="group">{{ __('Votre espace') }}</div>
    <a class="item" href="{{ route('agents.index') }}">{{ __('Vos agents') }}</a>
    <a class="item" href="{{ route('orders.create') }}">{{ __('Commander un agent') }}</a>
    <a class="item" href="{{ route('home') }}#support">{{ __('Support') }}</a>
</aside>
<label for="nav-toggle" class="scrim" aria-hidden="true"></label>

<div class="topbar">
    <div class="inner">
        <label for="nav-toggle" class="burger" title="{{ __('Menu') }}">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18v2H3zm0 5h18v2H3zm0 5h18v2H3z"/></svg>
        </label>
        <a class="brand" href="{{ route('home') }}">LOUVIA</a>
        <span class="sub">{{ __('Agents IA pour votre entreprise') }}</span>
        <span class="spacer"></span>
        <a class="btn btn-primary cta" href="{{ route('orders.create') }}">{{ __('Commander') }}</a>
    </div>
</div>

{{ $head ?? '' }}

@if (($bare ?? false) === true)
    @if (session('status'))
        <div class="wrap" style="padding-bottom:0"><div class="note ok">{{ session('status') }}</div></div>
    @endif
    {{ $slot }}
@else
    <div class="wrap">
        @if (session('status'))
            <div class="note ok">{{ session('status') }}</div>
        @endif

        {{ $slot }}
    </div>
@endif
</body>
</html>
