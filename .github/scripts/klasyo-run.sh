#!/usr/bin/env bash
# KLASYO — Étape F : la pièce manquante. lsphp83 exécute bien un .php DIRECT dans
# platform/public, mais /platform/ (rewrite root -> public/index.php) sert du SOURCE
# car le .htaccess RACINE n'a pas de handler (LiteSpeed applique le handler du contexte
# où la règle de rewrite se déclenche). School marche car son .htaccess racine a lsphp83.
# -> On pose lsphp83 dans platform/.htaccess (racine) et on VALIDE le HTML rendu.
set -uo pipefail

ROOT="$HOME/domains/klasyo.org/public_html"
P="$ROOT/platform"
STAMP="$(date +%Y%m%d-%H%M%S)"
RHTA="$P/.htaccess"

cp -a "$RHTA" "$RHTA.bak-F-$STAMP" && echo "backup: platform/.htaccess.bak-F-$STAMP"
# Idempotence : retire un éventuel ancien bloc KLASYO handler
sed -i '/# KLASYO-PHP-HANDLER/,/<\/FilesMatch>/d' "$RHTA" 2>/dev/null || true
# Ajoute le handler exécutant (lsphp83) au niveau RACINE
cat >> "$RHTA" <<'HTA'
# KLASYO-PHP-HANDLER
<FilesMatch "\.(php|phtml)$">
SetHandler application/x-lsphp83
</FilesMatch>
HTA
echo "  lsphp83 posé sur platform/.htaccess (racine)."

# Nettoyage d'éventuels fichiers diag laissés
rm -f "$P"/public/klasyo_exec.php "$P"/public/klasyo_*.php 2>/dev/null || true

echo
echo "== Purge caches =="
(cd "$P" && php artisan config:clear 2>&1 | tail -1)
(cd "$P" && php artisan view:clear   2>&1 | tail -1)

echo
echo "== VALIDATION FINALE (corps HTML, jamais du source) =="
check() {
  local url="$1"
  local body; body=$(curl -skL -m 15 "$url" 2>/dev/null)
  local code; code=$(curl -skL -o /dev/null -m 15 -w "%{http_code}" "$url" 2>/dev/null)
  if printf '%s' "$body" | grep -qiE '<\?php|Illuminate\\Contracts|Taylor Otwell|require_once __DIR__'; then
    echo "  $url -> [$code] !!! SOURCE PHP"
  elif printf '%s' "$body" | grep -qiE '<!doctype html|<html'; then
    local t; t=$(printf '%s' "$body" | grep -oiE '<title>[^<]*</title>' | head -1)
    echo "  $url -> [$code] OK HTML | $t"
  else
    echo "  $url -> [$code] ? début: $(printf '%s' "$body" | head -c 40 | tr -d '\n\r')"
  fi
}
check "https://klasyo.org/platform/"
check "https://klasyo.org/platform/login"
check "https://klasyo.org/platform/register"

echo
echo "== Rebrand : 'KLASYO' visible dans la page login ? =="
curl -skL -m 15 "https://klasyo.org/platform/login" 2>/dev/null | grep -oiE 'KLASYO|LMSzai' | head -3

echo
echo "== Non-régression école + landing =="
for u in "https://klasyo.org/" "https://klasyo.org/school/" "https://klasyo.org/school/login"; do
  echo "  $u -> $(curl -skL -o /dev/null -m 12 -w '%{http_code}' "$u")"
done
echo "  -- sécurité : plus aucun .php servi en source ? test index.php :"
b=$(curl -skL -m 12 "https://klasyo.org/platform/index.php" 2>/dev/null)
printf '%s' "$b" | grep -qiE '<\?php|Illuminate\\Contracts' && echo "  !!! index.php encore en source" || echo "  OK index.php ne divulgue pas de source"

echo
echo "== FIN étape F =="
