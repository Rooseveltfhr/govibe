#!/usr/bin/env bash
# KLASYO — Étape J : la SEULE différence restante entre platform (source) et school (exécute)
# est le bloc <FilesMatch "\.(php...)">SetHandler</FilesMatch> présent dans
# platform/public/.htaccess et ABSENT de school/public/.htaccess. Sur ce LiteSpeed,
# SetHandler + AddHandler cohabitent mal -> source. On retire le bloc SetHandler,
# on garde uniquement AddHandler (config identique à school). Validation du corps.
set -uo pipefail

ROOT="$HOME/domains/klasyo.org/public_html"
P="$ROOT/platform"
STAMP="$(date +%Y%m%d-%H%M%S)"
PHTA="$P/public/.htaccess"

cp -a "$PHTA" "$PHTA.bak-J-$STAMP" && echo "backup: platform/public/.htaccess.bak-J-$STAMP"

echo "== AVANT (lignes handler) :"
grep -niE 'filesmatch.*php|sethandler|addhandler' "$PHTA" || echo "  (aucune)"

# Supprime le bloc FilesMatch qui cible les extensions PHP (celui avec SetHandler),
# sans toucher un éventuel FilesMatch d'images.
perl -0pi -e 's/<FilesMatch\s+"\\\.\(php[^"]*"\s*>\s*\n\s*SetHandler[^\n]*\n\s*<\/FilesMatch>\s*\n?//g' "$PHTA"

echo "== APRÈS (lignes handler ; doit rester seulement AddHandler) :"
grep -niE 'filesmatch.*php|sethandler|addhandler' "$PHTA" || echo "  (aucune)"

echo
echo "== Purge caches platform =="
(cd "$P" && php artisan config:clear 2>&1 | tail -1)
(cd "$P" && php artisan view:clear   2>&1 | tail -1)

echo
echo "== VALIDATION (corps HTML, jamais du source) — + cache-buster =="
check() {
  local url="$1"
  local body; body=$(curl -sk -m 15 "$url" 2>/dev/null)
  local code; code=$(curl -sk -o /dev/null -m 15 -w "%{http_code}" "$url" 2>/dev/null)
  if printf '%s' "$body" | grep -qiE '<\?php|Illuminate\\Contracts|Taylor Otwell|require_once __DIR__'; then
    echo "  $url -> [$code] !!! SOURCE PHP"
  elif printf '%s' "$body" | grep -qiE '<!doctype html|<html'; then
    echo "  $url -> [$code] OK HTML | $(printf '%s' "$body" | grep -oiE '<title>[^<]*</title>' | head -1)"
  else
    echo "  $url -> [$code] ? $(printf '%s' "$body" | head -c 45 | tr -d '\n\r')"
  fi
}
check "https://klasyo.org/platform/public/"
check "https://klasyo.org/platform/?cb=$(date +%s)"
check "https://klasyo.org/platform/login?cb=$(date +%s)"
check "https://klasyo.org/platform/register?cb=$(date +%s)"

echo
echo "== Rebrand KLASYO visible sur la page login ? =="
curl -sk -m 15 "https://klasyo.org/platform/login?cb=$(date +%s)" 2>/dev/null | grep -oiE 'KLASYO|LMSzai' | sort -u | head -3 || echo "  (aucun)"

echo
echo "== Non-régression =="
for u in "https://klasyo.org/" "https://klasyo.org/school/login"; do
  echo "  $u -> $(curl -skL -o /dev/null -m 12 -w '%{http_code}' "$u")"
done

echo
echo "== FIN étape J =="
