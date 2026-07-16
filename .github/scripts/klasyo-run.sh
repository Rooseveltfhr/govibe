#!/usr/bin/env bash
# KLASYO — Diagnostic K : confirmer que les apps FONCTIONNENT via /public/ (le 404 est
# purement un souci de sous-dossier vs racine), et évaluer l'option sous-domaines.
set -uo pipefail

ROOT="$HOME/domains/klasyo.org/public_html"

echo "== K1. Les apps rendent-elles la VRAIE page via /public/ ? =="
check() {
  local url="$1"
  local body; body=$(curl -sk -m 15 "$url" 2>/dev/null)
  local code; code=$(curl -sk -o /dev/null -m 15 -w "%{http_code}" "$url" 2>/dev/null)
  local title; title=$(printf '%s' "$body" | grep -oiE '<title>[^<]*</title>' | head -1)
  local hasform; printf '%s' "$body" | grep -qiE '<form|password|login|connexion|sign in' && hasform="[formulaire présent]" || hasform=""
  if printf '%s' "$body" | grep -qiE '<\?php|Illuminate\\Contracts'; then
    echo "  $url -> [$code] SOURCE"
  else
    echo "  $url -> [$code] $title $hasform"
  fi
}
check "https://klasyo.org/platform/public/"
check "https://klasyo.org/platform/public/login"
check "https://klasyo.org/platform/public/register"
check "https://klasyo.org/school/public/"
check "https://klasyo.org/school/public/login"

echo
echo "== K2. Sous-domaines déjà présents ? (domains/ du compte) =="
ls -d /home/*/domains/*/ 2>/dev/null | sed 's#.*/domains/##' | head -40

echo
echo "== K3. Outil DirectAdmin CLI dispo pour créer un sous-domaine ? =="
for b in /usr/local/directadmin/directadmin /usr/local/bin/da; do
  [ -x "$b" ] && echo "  trouvé: $b" || echo "  absent: $b"
done
echo "  --- structure d'un sous-domaine existant (sub-dir sous public_html ?) :"
ls -la "$ROOT" 2>/dev/null | grep -iE 'sub|platform|school|store|studio|app' | head -10

echo
echo "== K4. APP_URL actuels (indice de config racine attendue) =="
grep -h '^APP_URL=' "$ROOT/platform/.env" "$ROOT/school/.env" 2>/dev/null

echo
echo "== FIN diagnostic K =="
