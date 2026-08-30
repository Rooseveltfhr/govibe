<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'LOUVIA' }}</title>
    <style>
        :root {
            --bg: #0b1020; --panel: #141b33; --line: #26304f;
            --ink: #e8ecf8; --dim: #9aa7c7; --accent: #7aa2ff; --ok: #4ade80; --warn: #fbbf24;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; background: var(--bg); color: var(--ink);
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif; line-height: 1.55;
        }
        a { color: var(--accent); }
        .wrap { max-width: 860px; margin: 0 auto; padding: 1.5rem 1rem 4rem; }
        header { border-bottom: 1px solid var(--line); margin-bottom: 1.5rem; padding-bottom: 1rem; }
        header .brand { font-size: 1.3rem; font-weight: 600; text-decoration: none; color: var(--ink); }
        header .sub { color: var(--dim); font-size: .85rem; margin-top: .2rem; }
        h1 { font-size: 1.5rem; margin: 0 0 .3rem; }
        h2 { font-size: 1.05rem; margin: 2rem 0 .8rem; color: var(--dim);
             text-transform: uppercase; letter-spacing: .04em; font-weight: 600; }
        p.lead { color: var(--dim); margin-top: 0; }
        .grid { display: grid; gap: .9rem; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); }
        .card {
            background: var(--panel); border: 1px solid var(--line);
            border-radius: 10px; padding: 1rem;
        }
        .card h3 { margin: 0 0 .35rem; font-size: 1.05rem; }
        .card p { margin: 0 0 .9rem; color: var(--dim); font-size: .9rem; }
        .row { display: flex; gap: .5rem; flex-wrap: wrap; }
        .btn {
            display: inline-block; padding: .5rem .9rem; border-radius: 7px;
            border: 1px solid var(--line); background: #1c2542; color: var(--ink);
            text-decoration: none; font-size: .9rem; cursor: pointer; font-family: inherit;
        }
        .btn:hover { border-color: var(--accent); }
        .btn-primary { background: var(--accent); border-color: var(--accent); color: #0b1020; font-weight: 600; }
        label { display: block; margin: .9rem 0 .3rem; font-size: .9rem; color: var(--dim); }
        input[type=text], textarea {
            width: 100%; padding: .55rem .7rem; border-radius: 7px;
            border: 1px solid var(--line); background: #0e1428; color: var(--ink);
            font-family: inherit; font-size: .95rem;
        }
        textarea { min-height: 70px; resize: vertical; }
        .note { border-left: 3px solid var(--warn); padding: .6rem .9rem; background: #1a1f36;
                border-radius: 0 7px 7px 0; color: var(--dim); font-size: .88rem; margin: 1rem 0; }
        .note.ok { border-left-color: var(--ok); }
        .turn { border: 1px solid var(--line); border-radius: 9px; padding: .9rem; margin-bottom: .8rem;
                background: var(--panel); }
        .turn .q { font-weight: 600; margin: 0 0 .5rem; }
        .turn .a { margin: 0; white-space: pre-wrap; }
        .turn .meta { margin-top: .6rem; color: var(--dim); font-size: .8rem; }
        .tag { display: inline-block; padding: .12rem .5rem; border: 1px solid var(--line);
               border-radius: 99px; font-size: .75rem; color: var(--dim); }
        table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        td { padding: .45rem 0; border-bottom: 1px solid var(--line); vertical-align: top; }
        td:first-child { color: var(--dim); width: 38%; padding-right: 1rem; }
        .empty { color: var(--dim); font-size: .9rem; }
        .err { color: #fca5a5; font-size: .85rem; margin-top: .25rem; }
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <a class="brand" href="{{ route('agents.index') }}">LOUVIA</a>
        <div class="sub">{{ __('Agents IA pour votre entreprise') }}</div>
    </header>

    @if (session('status'))
        <div class="note ok">{{ session('status') }}</div>
    @endif

    {{ $slot }}
</div>
</body>
</html>
