#!/usr/bin/env bash
# KLASYO — Étape N : URLs propres pour /platform. La cause du 404 : router vers
# public/index.php donne SCRIPT_NAME=/platform/public/index.php -> Laravel croit que sa base
# est /platform/public -> /platform/login ne matche pas. En routant vers server.php (à la
# RACINE de l'app), SCRIPT_NAME=/platform/server.php -> base=/platform -> /login matche.
# (server.php échouait avant à cause du handler lsphp cassé, désormais corrigé en lsphp83.)
set -uo pipefail

ROOT="$HOME/domains/klasyo.org/public_html"
P="$ROOT/platform"
STAMP="$(date +%Y%m%d-%H%M%S)"
RHTA="$P/.htaccess"

[ -f "$P/server.php" ] && echo "server.php présent (OK)" || { echo "(!) server.php absent — abandon"; exit 0; }
cp -a "$RHTA" "$RHTA.bak-N-$STAMP" && echo "backup: platform/.htaccess.bak-N-$STAMP"

cat > "$RHTA" <<'HTA'
# KLASYO-ROOT-FRONTCTRL — /platform servi via server.php (racine app) pour que
# Laravel calcule la bonne base (/platform) et matche les routes propres.
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Déjà sous public/ : ne pas réécrire
    RewriteRule ^public/ - [L]

    # Asset statique présent dans public/ -> le servir
    RewriteCond %{DOCUMENT_ROOT}/platform/public/$1 -f
    RewriteRule ^(.*)$ public/$1 [L]

    # Tout le reste -> front controller RACINE (server.php) : donne SCRIPT_NAME=/platform/server.php
    RewriteRule ^ server.php [L]

    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
</IfModule>
AddHandler application/x-lsphp83 .php .phtml
HTA
echo "  .htaccess racine réécrit (route -> server.php)."

(cd "$P" && php artisan route:clear 2>&1 | tail -1)
(cd "$P" && php artisan config:clear 2>&1 | tail -1)
(cd "$P" && php artisan view:clear 2>&1 | tail -1)

echo
echo "== VALIDATION (corps ; 2 essais espacés pour contourner un éventuel throttling firewall) =="
check() {
  local url="$1" body code
  for try in 1 2; do
    body=$(curl -sk -m 20 "$url" 2>/dev/null)
    code=$(curl -sk -o /dev/null -m 20 -w "%{http_code}" "$url" 2>/dev/null)
    [ "$code" != "000" ] && break
    sleep 4
  done
  if printf '%s' "$body" | grep -qiE '<\?php|Illuminate\\Contracts|require_once __DIR__'; then
    echo "  $url -> [$code] SOURCE PHP"
  elif printf '%s' "$body" | grep -qiE 'not found|404|error page' && ! printf '%s' "$body" | grep -qiE '<title>KLASYO'; then
    echo "  $url -> [$code] 404/erreur | $(printf '%s' "$body" | grep -oiE '<title>[^<]*</title>' | head -1)"
  elif printf '%s' "$body" | grep -qiE '<!doctype html|<html'; then
    echo "  $url -> [$code] OK HTML | $(printf '%s' "$body" | grep -oiE '<title>[^<]*</title>' | head -1)"
  else
    echo "  $url -> [$code] ? $(printf '%s' "$body" | head -c 50 | tr -d '\n\r')"
  fi
}
check "https://klasyo.org/platform/"
check "https://klasyo.org/platform/login"
check "https://klasyo.org/platform/courses"

echo
echo "== FIN étape N =="
