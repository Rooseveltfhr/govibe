{{-- TAGTOA — page de connexion (override versionné de auth/login).
     Auto-suffisante : aucune dépendance externe (police système, CSS inline) pour
     que la connexion RENDE TOUJOURS. Le formulaire poste sur route('login') avec
     les champs email/password/remember/redirect attendus par le cœur (inchangés). --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Connexion') }} — TAGTOA</title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        :root{--g:#2cb809;--gd:#1D9E75;--ink:#0d140c;--mut:#5d6b5a;--bd:rgba(13,20,12,.12);--red:#E0473E}
        body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;background:#f5f9f2;color:var(--ink);min-height:100vh;display:flex}
        .split{display:flex;width:100%;min-height:100vh}
        .brand{flex:1;background:linear-gradient(150deg,#0d140c 0%,#14361f 55%,#1D9E75 100%);color:#fff;padding:48px;display:none;flex-direction:column;justify-content:space-between;position:relative;overflow:hidden}
        .brand .logo{display:flex;align-items:center;gap:12px;font-weight:800;font-size:26px;letter-spacing:.02em}
        .brand .badge{width:44px;height:44px;border-radius:12px;background:var(--g);display:flex;align-items:center;justify-content:center;font-size:22px}
        .brand h2{font-size:32px;line-height:1.2;font-weight:800;max-width:12ch}
        .brand ul{list-style:none;display:flex;flex-direction:column;gap:14px;opacity:.92}
        .brand li{display:flex;align-items:center;gap:12px;font-size:15px}
        .brand li .dot{width:26px;height:26px;border-radius:8px;background:rgba(255,255,255,.14);display:flex;align-items:center;justify-content:center;font-size:14px}
        .pane{flex:1;display:flex;align-items:center;justify-content:center;padding:28px 20px}
        .card{width:100%;max-width:400px}
        .mlogo{display:flex;align-items:center;justify-content:center;gap:10px;font-weight:800;font-size:24px;margin-bottom:6px}
        .mlogo .badge{width:38px;height:38px;border-radius:11px;background:var(--g);color:#fff;display:flex;align-items:center;justify-content:center;font-size:20px}
        h1{font-size:22px;text-align:center;margin-bottom:4px}
        .sub{text-align:center;color:var(--mut);font-size:14px;margin-bottom:24px}
        label{display:block;font-weight:600;font-size:13px;margin:0 0 7px}
        .inp{width:100%;padding:13px 14px;border:1.5px solid var(--bd);border-radius:12px;font-size:16px;background:#fff;color:var(--ink);outline:none;transition:border-color .15s}
        .inp:focus{border-color:var(--g)}
        .field{margin-bottom:16px}
        .pwrap{position:relative}
        .pwrap .eye{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:0;cursor:pointer;color:var(--mut);font-size:13px;padding:4px 6px}
        .row{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;font-size:14px}
        .row label{display:flex;align-items:center;gap:8px;font-weight:500;margin:0;cursor:pointer}
        .row a{color:var(--gd);text-decoration:none;font-weight:600}
        .btn{width:100%;border:0;border-radius:13px;padding:15px;background:var(--g);color:#fff;font-size:16px;font-weight:700;cursor:pointer;transition:filter .15s}
        .btn:hover{filter:brightness(.95)}
        .alert{border-radius:11px;padding:12px 14px;font-size:14px;margin-bottom:16px}
        .alert.err{background:#fdecea;border:1px solid var(--red);color:#9a2820}
        .alert.ok{background:#eef9e8;border:1px solid var(--g);color:#1b5e12}
        .foot{text-align:center;color:var(--mut);font-size:13px;margin-top:22px}
        .foot a{color:var(--gd);text-decoration:none;font-weight:600}
        @media(min-width:900px){.brand{display:flex}}
    </style>
</head>
<body>
<div class="split">
    <aside class="brand">
        <div class="logo"><span class="badge">⚡</span> TAGTOA</div>
        <div>
            <h2>{{ __('Votre business, un seul tap.') }}</h2>
            <ul style="margin-top:24px">
                <li><span class="dot">✓</span> {{ __('Paiements, menu, boutique & événements') }}</li>
                <li><span class="dot">✓</span> {{ __('Cartes NFC & QR pour tout') }}</li>
                <li><span class="dot">✓</span> {{ __('Fidélité, réservations & analytics') }}</li>
            </ul>
        </div>
        <div style="opacity:.7;font-size:13px">© {{ date('Y') }} TAGTOA · GOVIBE Ecosystem</div>
    </aside>

    <main class="pane">
        <div class="card">
            <div class="mlogo"><span class="badge">⚡</span> TAGTOA</div>
            <h1>{{ __('Connexion') }}</h1>
            <p class="sub">{{ __('Accédez à votre tableau de bord') }}</p>

            @if($errors->any())
                <div class="alert err">
                    @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
                </div>
            @endif
            @if(session('success'))<div class="alert ok">{{ session('success') }}</div>@endif
            @if(session('error'))<div class="alert err">{{ session('error') }}</div>@endif

            <form method="POST" action="{{ route('login') }}" novalidate>
                @csrf
                <input type="hidden" name="redirect" value="{{ request()->get('redirect') }}">

                <div class="field">
                    <label for="email">{{ __('E-mail') }}</label>
                    <input class="inp" id="email" name="email" type="email" required autofocus
                           autocomplete="username" placeholder="vous@exemple.com" value="{{ old('email') }}">
                </div>

                <div class="field">
                    <label for="password">{{ __('Mot de passe') }}</label>
                    <div class="pwrap">
                        <input class="inp" id="password" name="password" type="password" required
                               autocomplete="current-password" placeholder="••••••••">
                        <button type="button" class="eye" onclick="var p=document.getElementById('password');p.type=p.type==='password'?'text':'password';this.textContent=p.type==='password'?'{{ __('Voir') }}':'{{ __('Cacher') }}';">{{ __('Voir') }}</button>
                    </div>
                </div>

                <div class="row">
                    <label><input type="checkbox" name="remember"> {{ __('Se souvenir de moi') }}</label>
                    @if(\Illuminate\Support\Facades\Route::has('password.request'))
                        <a href="{{ route('password.request') }}">{{ __('Mot de passe oublié ?') }}</a>
                    @endif
                </div>

                <button type="submit" class="btn">{{ __('Se connecter') }}</button>
            </form>

            @if(\Illuminate\Support\Facades\Route::has('register'))
                <div class="foot">{{ __('Nouveau ?') }} <a href="{{ route('register') }}">{{ __('Créer un compte') }}</a></div>
            @endif
            <div class="foot"><a href="{{ url('/') }}">{{ __('← Retour à l\'accueil') }}</a></div>
        </div>
    </main>
</div>
</body>
</html>
