{{-- TAGTOA — page d'inscription (override versionné de auth/register).
     Auto-suffisante (comme le login) : ne dépend PAS de layouts.auth (qui provoque
     « Mix manifest not found »). Reproduit FIDÈLEMENT les champs et conditions du
     cœur Biztap (téléphone / referral / reCAPTCHA conditionnels, mêmes helpers)
     pour ne rien casser côté validation serveur. Après inscription : HOME='/'
     → redirigé vers le hub TAGTOA unifié. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Créer un compte') }} — TAGTOA</title>
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
        .card{width:100%;max-width:420px}
        .mlogo{display:flex;align-items:center;justify-content:center;gap:10px;font-weight:800;font-size:24px;margin-bottom:6px}
        .mlogo .badge{width:38px;height:38px;border-radius:11px;background:var(--g);color:#fff;display:flex;align-items:center;justify-content:center;font-size:20px}
        h1{font-size:22px;text-align:center;margin-bottom:4px}
        .sub{text-align:center;color:var(--mut);font-size:14px;margin-bottom:24px}
        label{display:block;font-weight:600;font-size:13px;margin:0 0 7px}
        .inp{width:100%;padding:13px 14px;border:1.5px solid var(--bd);border-radius:12px;font-size:16px;background:#fff;color:var(--ink);outline:none;transition:border-color .15s}
        .inp:focus{border-color:var(--g)}
        .field{margin-bottom:16px}
        .two{display:flex;gap:12px}
        .two .field{flex:1}
        .pwrap{position:relative}
        .pwrap .eye{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:0;cursor:pointer;color:var(--mut);font-size:13px;padding:4px 6px}
        .terms{font-size:13px;margin-bottom:18px}
        .terms label{display:flex;gap:8px;align-items:flex-start;font-weight:500;margin:0;cursor:pointer}
        .terms input{margin-top:3px}
        .terms a{color:var(--gd);text-decoration:none;font-weight:600}
        .btn{width:100%;border:0;border-radius:13px;padding:15px;background:var(--g);color:#fff;font-size:16px;font-weight:700;cursor:pointer;transition:filter .15s}
        .btn:hover{filter:brightness(.95)}
        .alert{border-radius:11px;padding:12px 14px;font-size:14px;margin-bottom:16px}
        .alert.err{background:#fdecea;border:1px solid var(--red);color:#9a2820}
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
            <h2>{{ __('Lancez votre business en quelques minutes.') }}</h2>
            <ul style="margin-top:24px">
                <li><span class="dot">✓</span> {{ __('Gratuit pour démarrer') }}</li>
                <li><span class="dot">✓</span> {{ __('Boutique, menu, paiements & événements') }}</li>
                <li><span class="dot">✓</span> {{ __('Cartes NFC & QR pour tout') }}</li>
            </ul>
        </div>
        <div style="opacity:.7;font-size:13px">© {{ date('Y') }} TAGTOA · GOVIBE Ecosystem</div>
    </aside>

    <main class="pane">
        <div class="card">
            <div class="mlogo"><span class="badge">⚡</span> TAGTOA</div>
            <h1>{{ __('Créer un compte') }}</h1>
            <p class="sub">{{ __('C\'est gratuit et rapide.') }}</p>

            @if($errors->any())
                <div class="alert err">
                    @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
                </div>
            @endif
            @if(session('error'))<div class="alert err">{{ session('error') }}</div>@endif

            <form method="POST" id="UserRegisterForm"
                  action="{{ request()->input('referral-code') ? route('register').'?referral-code='.request()->input('referral-code') : route('register') }}"
                  novalidate>
                @csrf
                <input type="hidden" name="redirect" value="{{ url('/tagtoa/home') }}">

                <div class="two">
                    <div class="field">
                        <label for="first_name">{{ __('Prénom') }}</label>
                        <input class="inp" id="first_name" name="first_name" type="text" required autofocus value="{{ old('first_name') }}">
                    </div>
                    <div class="field">
                        <label for="last_name">{{ __('Nom') }}</label>
                        <input class="inp" id="last_name" name="last_name" type="text" required value="{{ old('last_name') }}">
                    </div>
                </div>

                <div class="field">
                    <label for="email">{{ __('E-mail') }}</label>
                    <input class="inp" id="email" name="email" type="email" required value="{{ old('email') }}" placeholder="vous@exemple.com">
                </div>

                @if(function_exists('getSuperAdminSettingValue') && getSuperAdminSettingValue('phone_number_required'))
                    <div class="field">
                        <label for="contact">{{ __('Téléphone') }}</label>
                        <input class="inp" id="contact" name="contact" type="tel" required
                               value="{{ old('contact', function_exists('getDefaultPhoneCode') ? getDefaultPhoneCode() : '') }}" placeholder="+509 ...">
                        <input type="hidden" name="region_code" value="{{ function_exists('getDefaultPhoneCode') ? getDefaultPhoneCode() : '' }}">
                    </div>
                @endif

                <div class="field">
                    <label for="password">{{ __('Mot de passe') }}</label>
                    <div class="pwrap">
                        <input class="inp" id="password" name="password" type="password" required autocomplete="new-password" placeholder="••••••••">
                        <button type="button" class="eye" onclick="var p=document.getElementById('password');p.type=p.type==='password'?'text':'password';this.textContent=p.type==='password'?'{{ __('Voir') }}':'{{ __('Cacher') }}';">{{ __('Voir') }}</button>
                    </div>
                </div>

                <div class="field">
                    <label for="password_confirmation">{{ __('Confirmer le mot de passe') }}</label>
                    <input class="inp" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" placeholder="••••••••">
                </div>

                @if(! request()->has('referral-code') && function_exists('getSuperAdminSettingValue') && getSuperAdminSettingValue('show_referral_code'))
                    <div class="field">
                        <label for="referral_code">{{ __('Code de parrainage') }} <span style="font-weight:400;color:var(--mut)">({{ __('optionnel') }})</span></label>
                        <input class="inp" id="referral_code" name="referral_code" type="text" value="{{ old('referral_code') }}">
                    </div>
                @endif

                <div class="terms">
                    <label>
                        <input type="checkbox" name="term_policy_check">
                        <span>{{ __('En m\'inscrivant, j\'accepte les') }}
                            <a href="{{ route('terms.conditions') }}" target="_blank">{{ __('conditions') }}</a>
                            &amp; <a href="{{ route('privacy.policy') }}" target="_blank">{{ __('la politique de confidentialité') }}</a>
                        </span>
                    </label>
                </div>

                @if(function_exists('getSuperAdminSettingValue') && getSuperAdminSettingValue('captcha_enable'))
                    <div class="field">
                        @if(function_exists('getRecaptchaVersion') && getRecaptchaVersion() == 1)
                            <div class="g-recaptcha" data-sitekey="{{ config('services.recaptcha.site_key') }}"></div>
                            <script src="https://www.google.com/recaptcha/api.js" async defer></script>
                        @else
                            <input type="hidden" name="g-recaptcha-response" id="recaptcha-token">
                            <script src="https://www.google.com/recaptcha/api.js?render={{ config('services.recaptcha.site_key') }}" async defer></script>
                            <script>
                                document.getElementById('UserRegisterForm').addEventListener('submit', function (e) {
                                    e.preventDefault();
                                    grecaptcha.ready(function () {
                                        grecaptcha.execute('{{ config('services.recaptcha.site_key') }}', { action: 'register' }).then(function (token) {
                                            document.getElementById('recaptcha-token').value = token;
                                            e.target.submit();
                                        });
                                    });
                                });
                            </script>
                        @endif
                    </div>
                @endif

                <button type="submit" class="btn">{{ __('Créer mon compte') }}</button>
            </form>

            <div class="foot">{{ __('Déjà un compte ?') }} <a href="{{ route('login') }}">{{ __('Se connecter') }}</a></div>
            <div class="foot"><a href="{{ url('/') }}">{{ __('← Retour à l\'accueil') }}</a></div>
        </div>
    </main>
</div>
</body>
</html>
