#!/usr/bin/env bash
# KLASYO — Étape G : LA vraie cause. <FilesMatch "\.php$">SetHandler ne s'applique qu'aux
# URLs finissant par .php. Les URLs propres (/platform/login) réécrites en interne vers
# index.php n'exécutent pas -> PHP servi en SOURCE. Fix : AddHandler (clé sur l'extension
# du FICHIER servi, pas l'URL). On corrige platform ET school, et on VALIDE le corps HTML.
set -uo pipefail

ROOT="$HOME/domains/klasyo.org/public_html"
STAMP="$(date +%Y%m%d-%H%M%S)"

apply_addhandler() { # $1 = fichier .htaccess ; $2 = version lsphp
  local f="$1" v="$2"
  [ -f "$f" ] || { echo "  (absent) $f"; return; }
  cp -a "$f" "$f.bak-G-$STAMP"
  # Retire l'ancien bloc KLASYO handler (FilesMatch/SetHandler) posé précédemment
  sed -i '/# KLASYO-PHP-HANDLER/,/<\/FilesMatch>/d' "$f" 2>/dev/null || true
  # Retire d'éventuelles lignes AddHandler/SetHandler lsphp existantes pour repartir propre
  sed -i -E '/AddHandler +application\/x-lsphp[0-9]+/d' "$f" 2>/dev/null || true
  # Ajoute AddHandler (s'applique au fichier résolu, y compris index.php après rewrite)
  cat >> "$f" <<EOF
# KLASYO-PHP-HANDLER-ADD
AddHandler application/x-lsphp${v} .php .phtml
EOF
  echo "  AddHandler lsphp${v} -> $(basename "$(dirname "$f")")/.htaccess"
}

# platform (Laravel 9) et school (Laravel 10) : PHP 8.3 est le seul handler qui exécute ici
for APP in platform school; do
  apply_addhandler "$ROOT/$APP/.htaccess" 83
  apply_addhandler "$ROOT/$APP/public/.htaccess" 83
  (cd "$ROOT/$APP" && php artisan config:clear 2>&1 | tail -1)
  (cd "$ROOT/$APP" && php artisan view:clear 2>&1 | tail -1)
done

echo
echo "== VALIDATION — le CORPS doit être du HTML, jamais du source PHP =="
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
echo "--- PLATFORM :"
check "https://klasyo.org/platform/"
check "https://klasyo.org/platform/login"
check "https://klasyo.org/platform/register"
echo "--- SCHOOL :"
check "https://klasyo.org/school/"
check "https://klasyo.org/school/login"
echo "--- LANDING :"
check "https://klasyo.org/"

echo
echo "== Rebrand KLASYO visible ? (page login platform) =="
curl -skL -m 15 "https://klasyo.org/platform/login" 2>/dev/null | grep -oiE 'KLASYO|LMSzai' | sort -u | head -3 || echo "  (ni KLASYO ni LMSzai trouvés)"

echo
echo "== FIN étape G =="
