#!/usr/bin/env bash
# KLASYO — Étape D : CORRIGER platform — server.php était servi en SOURCE (pas exécuté).
# Cause : le .htaccess racine rewrite vers server.php + un SetHandler mal appliqué -> code source
# renvoyé en texte (bug fonctionnel + divulgation de code). Fix : router vers public/index.php
# (patron "subdir-safe" identique à school, dont le public/.htaccess a déjà le handler lsphp80).
# On VALIDE le CONTENU (HTML réel, pas du source PHP), pas seulement le code HTTP.
set -uo pipefail

ROOT="$HOME/domains/klasyo.org/public_html"
P="$ROOT/platform"
STAMP="$(date +%Y%m%d-%H%M%S)"
RHTA="$P/.htaccess"

cp -a "$RHTA" "$RHTA.bak-D-$STAMP" && echo "backup: platform/.htaccess.bak-D-$STAMP"

echo "== .htaccess racine platform AVANT :"
sed -n '1,60p' "$RHTA"

# Nouveau .htaccess racine : route tout vers public/ (front controller public/index.php),
# PAS de SetHandler ici (c'est public/.htaccess qui porte le handler lsphp80).
cat > "$RHTA" <<'HTA'
# KLASYO-SUBDIR-SAFE — platform (LMSZAI) servi depuis /platform/, routé vers public/
# (server.php était renvoyé en SOURCE ; on passe par public/index.php comme school)
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Ne pas re-réécrire ce qui est déjà sous public/ (stoppe toute boucle)
    RewriteRule ^public/ - [L]

    # Fichier statique existant dans public/ -> le servir directement
    RewriteCond %{DOCUMENT_ROOT}/platform/public/$1 -f
    RewriteRule ^(.*)$ public/$1 [L]

    # Tout le reste -> front controller Laravel (public/index.php porte le handler lsphp80)
    RewriteRule ^ public/index.php [L]

    # En-tête Authorization pour l'API
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
</IfModule>
HTA
echo "  Nouveau .htaccess racine écrit (route vers public/, sans SetHandler)."

echo
echo "== Purge caches =="
(cd "$P" && php artisan config:clear 2>&1 | tail -1)
(cd "$P" && php artisan view:clear   2>&1 | tail -1)
(cd "$P" && php artisan route:clear  2>&1 | tail -1)

echo
echo "== VALIDATION DU CONTENU (pas seulement le code HTTP) =="
check() {
  local url="$1"
  local body; body=$(curl -skL -m 15 "$url" 2>/dev/null)
  local code; code=$(curl -skL -o /dev/null -m 15 -w "%{http_code}" "$url" 2>/dev/null)
  local first; first=$(printf '%s' "$body" | head -c 40 | tr -d '\n\r')
  local verdict="?"
  if printf '%s' "$body" | grep -qiE 'Taylor Otwell|mod_rewrite|require_once __DIR__|A PHP Framework'; then
    verdict="!!! SOURCE PHP EXPOSÉE"
  elif printf '%s' "$body" | grep -qiE '<!doctype html|<html'; then
    verdict="OK (HTML rendu)"
  elif [ -z "$body" ]; then
    verdict="(vide)"
  else
    verdict="autre"
  fi
  echo "  $url -> [$code] $verdict | début: ${first}"
}
check "https://klasyo.org/platform/"
check "https://klasyo.org/platform/login"
check "https://klasyo.org/platform/register"
check "https://klasyo.org/platform/index.php"

echo
echo "== Titre + présence de 'KLASYO' dans la page login (rebrand) =="
LOGIN=$(curl -skL -m 15 "https://klasyo.org/platform/login" 2>/dev/null)
printf '%s' "$LOGIN" | grep -oiE '<title>[^<]*</title>' | head -1 || echo "  (pas de title)"
printf '%s' "$LOGIN" | grep -oiE 'KLASYO' | head -1 && echo "  -> 'KLASYO' présent" || echo "  -> 'KLASYO' absent (vérifier logo/nom dans les vues)"

echo
echo "== Confirmation école (ne pas régresser) =="
for u in "https://klasyo.org/school/" "https://klasyo.org/school/login" "https://klasyo.org/"; do
  code=$(curl -skL -o /dev/null -m 12 -w "%{http_code}" "$u")
  echo "  $u -> $code"
done

echo
echo "== FIN étape D =="
