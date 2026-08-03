#!/usr/bin/env bash
# KLASYO — Étape Q : refonte de la page login (KLASYO, split-screen, 2 produits = 1 marque).
# Préserve le formulaire LMSZAI (route('login'), @csrf, name=email/password, recaptcha_token,
# liens sign-up / forget-password). Compile-test par rendu CLI + rollback auto en cas d'erreur.
set -uo pipefail

ROOT="$HOME/domains/klasyo.org/public_html"
P="$ROOT/platform"
V="$P/resources/views"
STAMP="$(date +%Y%m%d-%H%M%S)"

# Localiser la vue login (même logique qu'en capture)
LOGINV=""
for c in "$V/auth/login.blade.php" "$V/frontend/auth/login.blade.php" "$V/frontend/login.blade.php"; do
  [ -f "$c" ] && { LOGINV="$c"; break; }
done
[ -z "$LOGINV" ] && LOGINV=$(grep -rl --include='*.blade.php' "route('login')" "$V" 2>/dev/null | grep -iE 'login' | head -1)
[ -z "$LOGINV" ] && { echo "(!) vue login introuvable — abandon"; exit 0; }
echo "Vue login: $LOGINV"

NEW="$(mktemp /tmp/klasyo_login_XXXX.blade.php)"
cat > "$NEW" <<'BLADE'
@extends('layouts.auth')

@push('style')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=DM+Sans:ital,wght@0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
<style>
:root{
  --k-blue:#1d4ed8; --k-blue-dark:#1e3a8a; --k-blue-mid:#2563eb; --k-pale:#eff6ff;
  --k-green:#16a34a; --k-green-dark:#166534; --k-dark:#0c1524; --k-bg:#f8faff;
  --k-grad-hero:linear-gradient(140deg,#1d4ed8 0%,#0f2d6b 55%,#16a34a 130%);
  --k-grad-main:linear-gradient(135deg,#1d4ed8,#1e3a8a);
  --k-radius:18px; --k-shadow:0 24px 64px rgba(29,78,216,.16);
}
#preloader{display:none!important}
.klasyo-auth,.klasyo-auth *{box-sizing:border-box;margin:0;padding:0}
.klasyo-auth{font-family:'DM Sans',system-ui,sans-serif;color:var(--k-dark);
  min-height:100vh;display:grid;grid-template-columns:1.02fr .98fr;background:var(--k-bg)}
.klasyo-auth h1,.klasyo-auth h2,.klasyo-auth h3,.k-brand *{font-family:'Sora',sans-serif}

/* ---- Panneau marque (histoire 2 produits) ---- */
.k-brand{position:relative;overflow:hidden;background:var(--k-grad-hero);color:#fff;
  padding:56px 52px;display:flex;flex-direction:column;justify-content:space-between}
.k-brand::after{content:"";position:absolute;right:-120px;top:-120px;width:420px;height:420px;
  background:radial-gradient(circle,rgba(34,197,94,.35),transparent 70%);filter:blur(10px)}
.k-brand::before{content:"";position:absolute;left:-100px;bottom:-140px;width:380px;height:380px;
  background:radial-gradient(circle,rgba(59,130,246,.35),transparent 70%)}
.k-brand>*{position:relative;z-index:2}
.k-logo{display:flex;align-items:center;gap:12px;text-decoration:none;color:#fff}
.k-logo-box{width:46px;height:46px;border-radius:12px;background:rgba(255,255,255,.16);
  border:1px solid rgba(255,255,255,.28);display:grid;place-items:center;font-weight:800;font-size:22px;
  font-family:'Sora',sans-serif;backdrop-filter:blur(4px)}
.k-logo-txt{font-family:'Sora',sans-serif;font-weight:800;font-size:23px;letter-spacing:.5px}
.k-hero h1{font-size:40px;line-height:1.12;font-weight:800;margin-bottom:16px}
.k-hero h1 span{color:#a7f3d0}
.k-hero p{font-size:16px;line-height:1.6;color:rgba(255,255,255,.86);max-width:440px}
.k-products{display:grid;gap:14px;margin-top:30px;max-width:460px}
.k-prod{display:flex;gap:14px;align-items:flex-start;background:rgba(255,255,255,.10);
  border:1px solid rgba(255,255,255,.16);padding:16px 18px;border-radius:14px}
.k-prod .ic{width:42px;height:42px;border-radius:11px;background:rgba(255,255,255,.16);
  display:grid;place-items:center;flex:none}
.k-prod .ic svg{width:22px;height:22px;stroke:#fff}
.k-prod h3{font-size:16px;font-weight:700;margin-bottom:3px}
.k-prod p{font-size:13.5px;color:rgba(255,255,255,.82);line-height:1.45}
.k-oneaccount{display:inline-flex;align-items:center;gap:9px;margin-top:26px;
  background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.22);
  padding:10px 16px;border-radius:999px;font-size:13.5px;font-weight:600}
.k-oneaccount svg{width:16px;height:16px;stroke:#a7f3d0}
.k-foot{font-size:12.5px;color:rgba(255,255,255,.7);margin-top:26px}

/* ---- Panneau formulaire ---- */
.k-form-wrap{display:flex;align-items:center;justify-content:center;padding:40px 30px}
.k-card{width:100%;max-width:410px}
.k-card .m-logo{display:none;align-items:center;gap:10px;margin-bottom:22px}
.k-card .m-logo .b{width:40px;height:40px;border-radius:11px;background:var(--k-grad-main);
  display:grid;place-items:center;color:#fff;font-weight:800;font-family:'Sora',sans-serif}
.k-card h2{font-size:27px;font-weight:800;margin-bottom:7px}
.k-sub{color:#5b6675;font-size:14.5px;margin-bottom:26px;line-height:1.5}
.k-alert{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;font-size:13px;
  padding:11px 14px;border-radius:12px;margin-bottom:18px}
.k-label{display:block;font-size:13px;font-weight:600;color:#374151;margin:0 0 7px 2px}
.k-field{position:relative;margin-bottom:17px}
.k-field .fic{position:absolute;left:15px;top:50%;transform:translateY(-50%);color:#93a1b5}
.k-field .fic svg{width:19px;height:19px}
.klasyo-auth .k-field input{width:100%;height:52px;border:1.6px solid #e2e8f0;border-radius:13px;
  padding:0 46px 0 44px;font-size:15px;font-family:'DM Sans',sans-serif;background:#fff;
  color:var(--k-dark);transition:.18s;outline:none}
.klasyo-auth .k-field input:focus{border-color:var(--k-blue);box-shadow:0 0 0 4px rgba(29,78,216,.12);background:#fff}
.k-eye{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:0;
  cursor:pointer;color:#93a1b5;padding:6px}
.k-eye svg{width:19px;height:19px}
.k-row{display:flex;align-items:center;justify-content:space-between;margin:2px 2px 22px}
.k-check{display:flex;align-items:center;gap:8px;font-size:13.5px;color:#475569;cursor:pointer;user-select:none}
.k-check input{width:16px;height:16px;accent-color:var(--k-blue)}
.k-row a{color:var(--k-blue);font-size:13.5px;font-weight:600;text-decoration:none}
.k-row a:hover{text-decoration:underline}
.k-btn{width:100%;height:53px;border:0;border-radius:13px;background:var(--k-grad-main);color:#fff;
  font-family:'Sora',sans-serif;font-weight:700;font-size:15.5px;cursor:pointer;
  box-shadow:0 12px 26px rgba(29,78,216,.28);transition:.18s;display:flex;align-items:center;justify-content:center;gap:9px}
.k-btn:hover{transform:translateY(-1px);box-shadow:0 16px 32px rgba(29,78,216,.34)}
.k-btn svg{width:19px;height:19px}
.k-signup{text-align:center;font-size:14px;color:#5b6675;margin-top:24px}
.k-signup a{color:var(--k-blue);font-weight:700;text-decoration:none}
.k-signup a:hover{text-decoration:underline}
.k-langhint{text-align:center;font-size:12px;color:#94a3b8;margin-top:30px}

@media(max-width:960px){
  .klasyo-auth{grid-template-columns:1fr}
  .k-brand{display:none}
  .k-card .m-logo{display:flex}
  .k-form-wrap{padding:34px 22px;min-height:100vh}
}
</style>
@endpush

@section('content')
<div class="klasyo-auth">

  <aside class="k-brand">
    <a href="{{ route('main.index') }}" class="k-logo">
      <span class="k-logo-box">K</span><span class="k-logo-txt">KLASYO</span>
    </a>

    <div class="k-hero">
      <h1>Yon sèl kont.<br><span>De pwodwi pwisan.</span></h1>
      <p>Aprann, anseye, epi jere lekòl ou — tout sou yon sèl platfòm edikatif fèt pou Ayiti ak Karayib la.</p>

      <div class="k-products">
        <div class="k-prod">
          <span class="ic"><svg fill="none" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.25 3 10l9 3.75L21 10 12 6.25Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6.5 11.5V16c0 .8 2.46 2 5.5 2s5.5-1.2 5.5-2v-4.5"/></svg></span>
          <div><h3>KLASYO Aprann</h3><p>Marketplace konplè pou fòmasyon ak kou an liy — vann, aprann, sètifika (LMS SaaS).</p></div>
        </div>
        <div class="k-prod">
          <span class="ic"><svg fill="none" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.5 20.5h17M5 20.5V9l7-4.5L19 9v11.5M9.5 20.5V14h5v6.5"/></svg></span>
          <div><h3>KLASYO Lekòl</h3><p>Lojisyèl jesyon etablisman eskolè — elèv, klas, nòt, peman (SaaS konplè).</p></div>
        </div>
      </div>

      <div class="k-oneaccount">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.5 5 5 10-11"/></svg>
        Menm idantifyan · 2 dashboard · yon sèl mak fò
      </div>
    </div>

    <p class="k-foot">© {{ date('Y') }} KLASYO — Platfòm Edikatif Ayiti · GOVIBE Ecosystem</p>
  </aside>

  <main class="k-form-wrap">
    <div class="k-card">
      <div class="m-logo"><span class="b">K</span><strong style="font-family:'Sora',sans-serif;font-size:20px">KLASYO</strong></div>

      <h2>Byenveni 👋</h2>
      <p class="k-sub">Konekte ak kont ou an pou aksede Aprann ak Lekòl.</p>

      @if ($errors->any())
        <div class="k-alert">{{ $errors->first() }}</div>
      @endif

      <form method="POST" action="{{ route('login') }}">
        @csrf

        <label class="k-label">{{ __('Email') }} {{ __('or') }} {{ __('Username') }}</label>
        <div class="k-field">
          <span class="fic"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path stroke-linecap="round" d="m3.5 7 8.5 6 8.5-6"/></svg></span>
          <input type="text" name="email" value="{{ old('email') }}" class="form-control email" placeholder="ou@egzanp.com" autocomplete="username" required>
        </div>

        <label class="k-label">{{ __('Password') }}</label>
        <div class="k-field">
          <span class="fic"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><rect x="4.5" y="10" width="15" height="10" rx="2"/><path stroke-linecap="round" d="M8 10V7.5a4 4 0 0 1 8 0V10"/></svg></span>
          <input type="password" name="password" class="form-control password" id="k-pass" placeholder="••••••••" autocomplete="current-password" required>
          <button type="button" class="k-eye" onclick="(function(b){var i=document.getElementById('k-pass');i.type=i.type==='password'?'text':'password';})()" aria-label="show">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>

        <div class="k-row">
          <label class="k-check"><input type="checkbox" name="remember"> {{ __('Remember me') }}</label>
          <a href="{{ route('forget-password') }}">{{ __('Forgot password?') }}</a>
        </div>

        <input type="hidden" name="recaptcha_token" id="recaptcha_token">

        <button type="submit" class="k-btn">
          {{ __('Sign In') }}
          <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h14m-5-5 5 5-5 5"/></svg>
        </button>
      </form>

      <p class="k-signup">{{ __('New User') }}? <a href="{{ route('sign-up') }}">{{ __('Create your account') }}</a></p>
      <p class="k-langhint">🇭🇹 Kreyòl · 🇫🇷 Français · 🇬🇧 English · 🇪🇸 Español</p>
    </div>
  </main>

</div>
@endsection

@push('script')
@if(get_option('recaptcha_status', 0))
<script>
    grecaptcha.ready(function () {
        grecaptcha.execute('{{ get_option('recaptcha_site_key') }}', {action: 'submit'}).then(function (token) {
            document.getElementById('recaptcha_token').value = token;
        });
    });
</script>
@endif
@endpush
BLADE

echo "Nouvelle vue écrite ($(wc -l < "$NEW") lignes). Backup + installation…"
cp -a "$LOGINV" "$LOGINV.bak-Q-$STAMP"
cp "$NEW" "$LOGINV"
rm -f "$NEW"
(cd "$P" && php artisan view:clear 2>&1 | tail -1)

echo
echo "== Compile-test : rendu CLI de /login (rollback auto si erreur) =="
cd "$P/public"
timeout 30 php -d display_errors=1 -r '
  $_SERVER["REQUEST_URI"]="/login"; $_SERVER["REQUEST_METHOD"]="GET";
  $_SERVER["HTTP_HOST"]="klasyo.org"; $_SERVER["SCRIPT_NAME"]="/platform/public/index.php";
  $_SERVER["SCRIPT_FILENAME"]=__DIR__."/index.php"; require "index.php";
' > /tmp/kl_render.html 2>&1 || true
SZ=$(wc -c < /tmp/kl_render.html)
echo "  taille rendu: ${SZ}o"
if grep -qiE 'ParseError|syntax error|Fatal error|Uncaught|Undefined (variable|constant)|Blade|Facade\\Ignition|InvalidArgumentException' /tmp/kl_render.html; then
  echo "  !!! ERREUR détectée dans le rendu -> ROLLBACK"
  grep -oiE '(ParseError|syntax error|Fatal error|Uncaught)[^<]{0,140}' /tmp/kl_render.html | head -3
  cp -a "$LOGINV.bak-Q-$STAMP" "$LOGINV"
  (cd "$P" && php artisan view:clear 2>&1 | tail -1)
  echo "  vue restaurée."
elif grep -q 'klasyo-auth' /tmp/kl_render.html; then
  echo "  ✅ OK — nouvelle page KLASYO rendue (classe klasyo-auth présente)."
  echo "  titre: $(grep -oiE '<title>[^<]*</title>' /tmp/kl_render.html | head -1)"
  echo "  histoire 2 produits: $(grep -oiE 'KLASYO Aprann|KLASYO Lekòl' /tmp/kl_render.html | sort -u | tr '\n' ' ')"
else
  echo "  ?? rendu sans marqueur klasyo-auth ni erreur — début :"
  head -c 200 /tmp/kl_render.html | tr -d '\n\r'
fi
rm -f /tmp/kl_render.html

echo
echo "== FIN étape Q =="
