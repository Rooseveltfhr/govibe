<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Administration') }} — LOUVIA</title>
    <style>
        :root { --ink:#000; --muted:#5f5f5f; --line:#c4c4c4; --accent:#0f8a3d; --accent-soft:#eaf6ee; --danger:#9b1c1c; }
        * { box-sizing: border-box; }
        body { margin:0; background:#fff; color:var(--ink); font-family:system-ui,-apple-system,"Segoe UI",sans-serif;
               display:flex; align-items:center; justify-content:center; min-height:100vh; padding:1.5rem; }
        .card { width:100%; max-width:380px; }
        .brand { font-weight:700; letter-spacing:.1em; font-size:1.1rem; }
        h1 { font-size:1.2rem; margin:.4rem 0 1.3rem; font-weight:600; }
        label { display:block; margin:.9rem 0 .25rem; font-size:.85rem; font-weight:600; }
        input { width:100%; padding:.55rem .65rem; border:1px solid var(--line); border-radius:6px;
                font-family:inherit; font-size:.95rem; }
        input:focus { outline:none; border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-soft); }
        button { width:100%; margin-top:1.3rem; padding:.6rem; border:1px solid var(--accent);
                 background:var(--accent); color:#fff; border-radius:6px; font:inherit; font-weight:600; cursor:pointer; }
        button:hover { background:#0b6c2f; }
        .err { color:var(--danger); font-size:.86rem; margin-top:.5rem; }
        .hint { color:var(--muted); font-size:.82rem; margin-top:1.4rem; }
    </style>
</head>
<body>
<form class="card" method="POST" action="{{ route('admin.login.attempt') }}">
    @csrf
    <span class="brand">LOUVIA</span>
    <h1>{{ __('Administration') }}</h1>

    <label for="email">{{ __('E-mail') }}</label>
    <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">

    <label for="password">{{ __('Mot de passe') }}</label>
    <input type="password" id="password" name="password" required autocomplete="current-password">

    @error('email') <div class="err">{{ $message }}</div> @enderror

    <button type="submit">{{ __('Se connecter') }}</button>

    <p class="hint">{{ __("Pas d'inscription : les comptes se créent sur le serveur avec php artisan govibe:admin.") }}</p>
</form>
</body>
</html>
