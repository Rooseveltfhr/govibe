<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? __('Administration') }} — LOUVIA</title>
    <style>
        /* Menm palèt ak sit piblik la: fon blan, tèks nwa, vèt pou aksyon. */
        :root {
            --page: #ffffff; --surface: #ffffff; --sunk: #f7f8f7;
            --line: #e0e0e0; --line-strong: #c4c4c4;
            --ink: #000000; --ink-soft: #2b2b2b; --muted: #5f5f5f;
            --accent: #0f8a3d; --accent-hover: #0b6c2f; --accent-soft: #eaf6ee;
            --warn-ink: #7a5300; --warn-bg: #fdf7e8; --warn-line: #ead9ac;
            --danger: #9b1c1c; --radius: 6px;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; background: var(--page); color: var(--ink);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            font-size: 15px; line-height: 1.55;
        }
        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; }

        .shell { display: flex; min-height: 100vh; flex-direction: column; }
        @media (min-width: 900px) { .shell { flex-direction: row; } }

        .side {
            background: var(--sunk); border-bottom: 1px solid var(--line);
            padding: 1rem; display: flex; flex-direction: column; gap: .15rem;
        }
        @media (min-width: 900px) {
            .side { width: 232px; flex: none; border-bottom: 0; border-right: 1px solid var(--line); }
        }
        .side .brand {
            font-weight: 700; letter-spacing: .1em; color: var(--ink);
            font-size: 1rem; margin-bottom: .2rem;
        }
        .side .who { color: var(--muted); font-size: .8rem; margin-bottom: .9rem; }
        .side a.item {
            padding: .5rem .6rem; border-radius: var(--radius); color: var(--ink);
            font-size: .93rem; font-weight: 500;
        }
        .side a.item:hover { background: var(--accent-soft); color: var(--accent); text-decoration: none; }
        .side a.item[aria-current="page"] { background: var(--accent); color: #fff; }
        .side form { margin-top: auto; padding-top: 1rem; }

        main { flex: 1; padding: 1.6rem 1.25rem 4rem; min-width: 0; }
        .inner { max-width: 940px; }

        h1 { font-size: 1.35rem; margin: 0 0 .25rem; }
        h2 { font-size: .78rem; text-transform: uppercase; letter-spacing: .07em;
             color: var(--muted); margin: 2rem 0 .8rem; font-weight: 650; }
        p.lead { color: var(--ink-soft); margin: 0 0 1.2rem; max-width: 66ch; }

        .tiles { display: grid; gap: .8rem; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); }
        .tile { border: 1px solid var(--line); border-radius: var(--radius); padding: .9rem 1rem; background: var(--surface); }
        .tile .n { font-size: 1.7rem; font-weight: 650; line-height: 1.1; }
        .tile .l { color: var(--muted); font-size: .84rem; }
        .tile.act { border-left: 3px solid var(--accent); }

        table { width: 100%; border-collapse: collapse; font-size: .92rem;
                background: var(--surface); border: 1px solid var(--line); border-radius: var(--radius); }
        th { text-align: left; font-size: .72rem; text-transform: uppercase; letter-spacing: .07em;
             color: var(--muted); padding: .6rem .8rem; border-bottom: 1px solid var(--line); font-weight: 650; }
        td { padding: .6rem .8rem; border-bottom: 1px solid var(--line); vertical-align: top; }
        tr:last-child td { border-bottom: 0; }
        .table-wrap { overflow-x: auto; }
        table.kv td:first-child { color: var(--muted); width: 34%; }

        .pill {
            display: inline-block; padding: .1rem .5rem; border-radius: 99px;
            font-size: .74rem; font-weight: 650; text-transform: uppercase; letter-spacing: .04em;
            border: 1px solid var(--line-strong); color: var(--muted); background: var(--surface);
        }
        .pill.nouvo { border-color: var(--accent); color: var(--accent); background: var(--accent-soft); }
        .pill.an_kou { border-color: var(--warn-line); color: var(--warn-ink); background: var(--warn-bg); }
        .pill.fèt { border-color: var(--line-strong); color: var(--muted); }
        .pill.anile { border-color: var(--line-strong); color: var(--muted); text-decoration: line-through; }
        .pill.on { border-color: var(--accent); color: var(--accent); background: var(--accent-soft); }
        .pill.off { border-color: var(--warn-line); color: var(--warn-ink); background: var(--warn-bg); }

        .row { display: flex; gap: .5rem; flex-wrap: wrap; align-items: center; }
        .btn {
            display: inline-block; padding: .45rem .9rem; border-radius: var(--radius);
            border: 1px solid var(--accent); background: var(--surface); color: var(--accent);
            font: inherit; font-size: .89rem; font-weight: 600; cursor: pointer; line-height: 1.5;
        }
        .btn:hover { background: var(--accent-soft); text-decoration: none; }
        .btn-primary { background: var(--accent); border-color: var(--accent); color: #fff; }
        .btn-primary:hover { background: var(--accent-hover); }
        .btn-danger { border-color: var(--danger); color: var(--danger); }
        .btn-danger:hover { background: #fdf0f0; }
        .btn-sm { padding: .25rem .6rem; font-size: .82rem; }

        label { display: block; margin: .9rem 0 .25rem; font-size: .85rem; font-weight: 600; color: var(--ink-soft); }
        label .hint { display: block; font-weight: 400; color: var(--muted); font-size: .82rem; }
        input[type=text], input[type=email], input[type=password], input[type=date], input[type=number], select, textarea {
            width: 100%; padding: .48rem .62rem; border-radius: var(--radius);
            border: 1px solid var(--line-strong); background: var(--surface); color: var(--ink);
            font-family: inherit; font-size: .94rem;
        }
        input:focus, select:focus, textarea:focus {
            outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft);
        }
        textarea { min-height: 80px; resize: vertical; }
        fieldset { border: 1px solid var(--line); border-radius: var(--radius); padding: .25rem 1.1rem 1.2rem; margin: 0 0 1.2rem; }
        legend { font-size: .74rem; font-weight: 650; text-transform: uppercase; letter-spacing: .07em; color: var(--muted); padding: 0 .3rem; }
        .grid2 { display: grid; gap: 0 1rem; grid-template-columns: 1fr; }
        @media (min-width: 640px) { .grid2 { grid-template-columns: 1fr 1fr; } }

        .note { border: 1px solid var(--warn-line); background: var(--warn-bg); color: var(--warn-ink);
                padding: .7rem .9rem; border-radius: var(--radius); font-size: .9rem; margin: 0 0 1.2rem; }
        .note.ok { border-color: #b6ddc4; background: var(--accent-soft); color: var(--accent-hover); }
        .empty { color: var(--muted); font-size: .9rem; }
        .err { color: var(--danger); font-size: .85rem; margin-top: .25rem; }
        .mono { font-family: ui-monospace, Menlo, monospace; font-size: .86rem; }
    </style>
</head>
<body>
<div class="shell">
    <nav class="side">
        <span class="brand">LOUVIA</span>
        <span class="who">{{ auth()->user()?->email }}</span>

        @php($current = request()->route()?->getName())
        <a class="item" href="{{ route('admin.dashboard') }}"
           @if ($current === 'admin.dashboard') aria-current="page" @endif>{{ __('Tableau de bord') }}</a>
        <a class="item" href="{{ route('admin.orders.index') }}"
           @if (str_starts_with((string) $current, 'admin.orders')) aria-current="page" @endif>{{ __('Commandes') }}</a>
        <a class="item" href="{{ route('admin.payments') }}"
           @if (str_starts_with((string) $current, 'admin.payments')) aria-current="page" @endif>{{ __('Paiement') }}</a>
        <a class="item" href="{{ route('admin.voices') }}"
           @if (str_starts_with((string) $current, 'admin.voices')) aria-current="page" @endif>{{ __('Voix') }}</a>
        <a class="item" href="{{ route('admin.settings') }}"
           @if (str_starts_with((string) $current, 'admin.settings')) aria-current="page" @endif>{{ __('Configuration') }}</a>
        <a class="item" href="{{ route('agents.index') }}">{{ __('Agents') }}</a>
        <a class="item" href="{{ route('home') }}">{{ __('Voir le site') }}</a>

        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit" class="btn btn-sm">{{ __('Se déconnecter') }}</button>
        </form>
    </nav>

    <main>
        <div class="inner">
            @if (session('status'))
                <div class="note ok">{{ session('status') }}</div>
            @endif

            {{ $slot }}
        </div>
    </main>
</div>
</body>
</html>
