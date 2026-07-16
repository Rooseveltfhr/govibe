#!/usr/bin/env bash
# KLASYO — Étape H : platform/public/.htaccess garde le bloc LMSZAI d'origine
# <FilesMatch ...>SetHandler application/x-lsphp80</FilesMatch> (PHP 8.0 = inactif ici),
# qui prime sur mon AddHandler lsphp83 -> source. On convertit TOUTE référence lsphpNN
# en lsphp83 dans les .htaccess de platform. Validation du corps HTML.
set -uo pipefail

ROOT="$HOME/domains/klasyo.org/public_html"
P="$ROOT/platform"
STAMP="$(date +%Y%m%d-%H%M%S)"

echo "== Références lsphp AVANT (platform) =="
grep -rniE 'lsphp[0-9]+' "$P/.htaccess" "$P/public/.htaccess" 2>/dev/null || echo "  (aucune)"

for f in "$P/.htaccess" "$P/public/.htaccess"; do
  [ -f "$f" ] || continue
  cp -a "$f" "$f.bak-H-$STAMP"
  # Toute version lsphpNN -> lsphp83 (corrige le bloc FilesMatch LMSZAI d'origine)
  sed -i -E 's#(application/x-lsphp)[0-9]+#\183#g' "$f"
  echo "  corrigé : $(basename "$(dirname "$f")")/.htaccess"
done

echo "== Références lsphp APRÈS (platform, doivent toutes être 83) =="
grep -rniE 'lsphp[0-9]+' "$P/.htaccess" "$P/public/.htaccess" 2>/dev/null

echo
echo "== Purge caches platform =="
(cd "$P" && php artisan config:clear 2>&1 | tail -1)
(cd "$P" && php artisan view:clear   2>&1 | tail -1)

echo
echo "== VALIDATION (corps HTML, jamais du source) =="
check() {
  local url="$1"
  local body; body=$(curl -skL -m 15 "$url" 2>/dev/null)
  local code; code=$(curl -skL -o /dev/null -m 15 -w "%{http_code}" "$url" 2>/dev/null)
  if printf '%s' "$body" | grep -qiE '<\?php|Illuminate\\Contracts|Taylor Otwell|require_once __DIR__'; then
    echo "  $url -> [$code] !!! SOURCE PHP"
  elif printf '%s' "$body" | grep -qiE '<!doctype html|<html'; then
    echo "  $url -> [$code] OK HTML | $(printf '%s' "$body" | grep -oiE '<title>[^<]*</title>' | head -1)"
  else
    echo "  $url -> [$code] ? $(printf '%s' "$body" | head -c 45 | tr -d '\n\r')"
  fi
}
check "https://klasyo.org/platform/"
check "https://klasyo.org/platform/login"
check "https://klasyo.org/platform/register"

echo
echo "== Rebrand : KLASYO / LMSzai dans la page d'accueil platform =="
curl -skL -m 15 "https://klasyo.org/platform/" 2>/dev/null | grep -oiE 'KLASYO|LMSzai' | sort -u | head -3 || echo "  (aucun des deux)"

echo
echo "== Non-régression =="
for u in "https://klasyo.org/" "https://klasyo.org/school/login"; do
  echo "  $u -> $(curl -skL -o /dev/null -m 12 -w '%{http_code}' "$u")"
done

echo
echo "== FIN étape H =="
