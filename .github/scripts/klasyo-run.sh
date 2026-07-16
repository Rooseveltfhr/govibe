#!/usr/bin/env bash
# KLASYO — Diagnostic L : (1) trouver la cause du 500 sur /register,
# (2) chercher un outil de capture d'écran sur le VPS, (3) reconfirmer le login OK.
# Logs publics : aucun secret.
set -uo pipefail

ROOT="$HOME/domains/klasyo.org/public_html"
P="$ROOT/platform"

echo "== L1. Cause du 500 sur /register (exécution CLI, erreurs affichées) =="
cd "$P/public"
timeout 30 php -d display_errors=1 -d error_reporting=E_ALL -r '
  $_SERVER["REQUEST_URI"]="/register";
  $_SERVER["REQUEST_METHOD"]="GET";
  $_SERVER["HTTP_HOST"]="klasyo.org";
  $_SERVER["SCRIPT_NAME"]="/platform/public/index.php";
  $_SERVER["SCRIPT_FILENAME"]=__DIR__."/index.php";
  require "index.php";
' > /tmp/kl_reg.html 2>&1
echo "  taille réponse: $(wc -c < /tmp/kl_reg.html)o"
echo "  --- erreurs/exception détectées :"
grep -oiE '(Fatal error|ParseError|Exception|Error:|Undefined|Call to|View \[[^]]*\] not found|Class .* not found|SQLSTATE|does not exist|Trait|Target class)[^<]{0,120}' /tmp/kl_reg.html | head -8 || echo "  (aucune ligne d'erreur évidente)"
echo "  --- titre rendu :"
grep -oiE '<title>[^<]*</title>' /tmp/kl_reg.html | head -1
echo "  --- dernières lignes du log Laravel platform (messages) :"
L=$(ls -t "$P"/storage/logs/*.log 2>/dev/null | head -1)
[ -n "$L" ] && grep -oE '^\[[0-9 :-]+\] \w+\.\w+: [^{]{0,140}' "$L" | tail -4 || echo "  (pas de log)"
rm -f /tmp/kl_reg.html

echo
echo "== L2. Outils de capture d'écran disponibles sur le VPS ? =="
for t in chromium chromium-browser google-chrome google-chrome-stable wkhtmltoimage cutycapt; do
  p=$(command -v "$t" 2>/dev/null) && echo "  TROUVÉ: $t -> $p" || echo "  absent: $t"
done
echo "  (node/npx pour puppeteer ?) : $(command -v node 2>/dev/null || echo 'node absent') / $(command -v npx 2>/dev/null || echo 'npx absent')"

echo
echo "== L3. Reconfirmation login (doit rester OK) =="
for u in "https://klasyo.org/platform/public/login" "https://klasyo.org/school/public/login"; do
  code=$(curl -sk -o /dev/null -m 12 -w '%{http_code}' "$u")
  title=$(curl -sk -m 12 "$u" 2>/dev/null | grep -oiE '<title>[^<]*</title>' | head -1)
  echo "  $u -> [$code] $title"
done

echo
echo "== FIN diagnostic L =="
