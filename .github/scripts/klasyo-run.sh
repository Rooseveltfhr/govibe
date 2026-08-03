#!/usr/bin/env bash
# KLASYO — Capture O : préparer la refonte du login. On lit les fichiers (pas de curl massif,
# pour éviter le throttling firewall) : état login, vue Blade du login (pour préserver le form),
# palette/polices exactes de la landing.
set -uo pipefail

ROOT="$HOME/domains/klasyo.org/public_html"
P="$ROOT/platform"

echo "== O1. État HTTP du login (1 essai tolérant) =="
code=$(curl -sk -o /dev/null -m 20 -w '%{http_code}' "https://klasyo.org/platform/login" 2>/dev/null || echo "ERR")
body=$(curl -sk -m 20 "https://klasyo.org/platform/login" 2>/dev/null | head -c 400)
echo "  code=$code"
printf '%s' "$body" | grep -qiE '<\?php|Illuminate' && echo "  -> SOURCE" || { printf '%s' "$body" | grep -qiE '<!doctype|<html' && echo "  -> HTML ($(printf '%s' "$body" | grep -oiE '<title>[^<]*</title>' | head -1))" || echo "  -> $body"; }

echo
echo "== O2. Localiser la vue Blade du login LMSZAI =="
LOGINV=""
for cand in "$P/resources/views/auth/login.blade.php" \
            "$P/resources/views/frontend/auth/login.blade.php" \
            "$P/resources/views/frontend/login.blade.php"; do
  [ -f "$cand" ] && { LOGINV="$cand"; break; }
done
if [ -z "$LOGINV" ]; then
  LOGINV=$(grep -rl --include='*.blade.php' -iE 'name=.email.*|type=.password.|route\(.login' "$P/resources/views" 2>/dev/null | grep -iE 'login|auth' | head -1)
fi
echo "  vue login: ${LOGINV:-INTROUVABLE}"

echo
echo "== O3. Contenu de la vue login (pour préserver action/champs/CSRF/recaptcha) =="
if [ -n "$LOGINV" ]; then
  echo "  --- @extends / layout :"
  grep -nE "@extends|@section|@include" "$LOGINV" | head -10
  echo "  --- form (action, method, champs, csrf, recaptcha, remember) :"
  grep -nE "<form|action=|method=|name=|@csrf|csrf|recaptcha|g-recaptcha|remember|route\(|type=.submit|type=.password|type=.email|type=.text" "$LOGINV" | head -40
fi

echo
echo "== O4. Route POST login + champs attendus (controller) =="
grep -nE "login|Auth::routes" "$P/routes/web.php" | grep -iE 'login|auth' | head -6
echo "  --- LoginController : nom des champs + recaptcha ?"
grep -nE "username|->email|->password|recaptcha|remember|credentials|validate|field_name|login_field" "$P/app/Http/Controllers/Auth/LoginController.php" 2>/dev/null | head -20

echo
echo "== O5. Palette + polices EXACTES de la landing (public_html/index.html) =="
LAND="$ROOT/index.html"
if [ -f "$LAND" ]; then
  echo "  --- :root (variables CSS) :"
  grep -oE '\-\-[a-z0-9-]+:\s*[^;}]{1,70}' "$LAND" | sort -u | head -40
  echo "  --- Google Fonts :"
  grep -oE 'fonts.googleapis.com/css2?[^"'\'' ]*' "$LAND" | sort -u | head -3
  echo "  --- font-family utilisés :"
  grep -oE "font-family:[^;}]{1,60}" "$LAND" | sort -u | head -6
  echo "  --- <title> + logo (img/svg/texte de marque) :"
  grep -oiE '<title>[^<]*</title>' "$LAND" | head -1
  grep -oiE '(logo|brand)[^>]{0,80}' "$LAND" | head -5
else
  echo "  (!) landing index.html introuvable à $LAND"
fi

echo
echo "== O6. Où la vue login charge son CSS (pour injecter le thème KLASYO) =="
if [ -n "$LOGINV" ]; then
  LAYOUT=$(grep -oE "@extends\(['\"][^'\"]+" "$LOGINV" | head -1 | sed "s/@extends(['\"]//")
  echo "  layout: $LAYOUT"
fi
ls "$P/public/frontend/assets/css" 2>/dev/null | head -20

echo
echo "== FIN capture O =="
