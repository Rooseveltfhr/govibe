#!/usr/bin/env bash
# KLASYO — Diagnostic B : pourquoi /platform/login et /platform/register hangent (15s)
# alors que la page d'accueil (même front controller server.php) répond en 200.
# Hypothèse : appel réseau sortant (license check / captcha serveur) sur ces routes.
# Logs publics : aucun secret.
set -uo pipefail

ROOT="$HOME/domains/klasyo.org/public_html"
P="$ROOT/platform"

echo "== B1. Timing HTTP fin : accueil vs login vs register =="
for u in "https://klasyo.org/platform/" \
         "https://klasyo.org/platform/index.php" \
         "https://klasyo.org/platform/login" \
         "https://klasyo.org/platform/register" ; do
  out=$(curl -sk -o /dev/null -m 20 -w "%{http_code} time=%{time_total}s" "$u" 2>&1)
  echo "  $u -> $out"
done

echo
echo "== B2. Exécution CLI de la route /login (localise le hang, timeout 25s) =="
cd "$P"
START=$(date +%s)
timeout 25 php -d display_errors=1 -r '
  $_SERVER["REQUEST_URI"]="/login";
  $_SERVER["REQUEST_METHOD"]="GET";
  $_SERVER["HTTP_HOST"]="klasyo.org";
  $_SERVER["SCRIPT_NAME"]="/platform/index.php";
  $_SERVER["SCRIPT_FILENAME"]=__DIR__."/server.php";
  require "server.php";
' > /tmp/kl_login.html 2>&1
RC=$?; END=$(date +%s)
echo "  exit=$RC durée=$((END-START))s taille=$(wc -c < /tmp/kl_login.html)o"
if [ $RC -eq 124 ]; then echo "  => TIMEOUT en CLI aussi : le hang est DANS le code PHP (pas le web-server)."; fi
echo "  --- 200 premiers caractères :"; head -c 200 /tmp/kl_login.html | tr -d '\r'
echo
echo "  --- erreurs/traces :"; grep -oiE '(fatal|exception|error|curl|guzzle|timed? ?out|curl_exec|file_get_contents|stream_socket|verify|license|licen[cs]e|activation)[^<]{0,80}' /tmp/kl_login.html | head -8 || echo "  (aucune)"
rm -f /tmp/kl_login.html

echo
echo "== B3. Recherche d'appels réseau sortants sur les routes auth (code LMSZAI) =="
echo "  --- LoginController / RegisterController :"
for f in "$P"/app/Http/Controllers/Auth/LoginController.php "$P"/app/Http/Controllers/Auth/RegisterController.php; do
  [ -f "$f" ] && { echo "  [$f]"; grep -niE 'curl|guzzle|http::|file_get_contents|Client\(|verify|licen[cs]e|activation|gethostby|fsockopen|checkPurchase|envato' "$f" | head -8 || echo "    (rien)"; }
done
echo "  --- middlewares globaux appelant l'extérieur :"
grep -rniE 'curl_exec|file_get_contents\(.?http|Http::(get|post)|new Client|checkPurchase|envato|verify.*license|license.*verify' \
  "$P"/app/Http/Middleware "$P"/app/Providers 2>/dev/null | head -12 || echo "  (rien)"

echo
echo "== B4. Sortie internet du serveur (un timeout ici expliquerait le hang) =="
for host in "api.envato.com" "google.com" "codecanyon.net"; do
  t=$(curl -s -o /dev/null -m 8 -w "%{http_code} %{time_total}s" "https://$host" 2>&1 || echo "FAIL")
  echo "  https://$host -> $t"
done

echo
echo "== B5. version.update middleware (LMSZAI enveloppe les routes dedans) =="
grep -rniE 'version.update|VersionUpdate|process-update' "$P"/app/Http/Kernel.php "$P"/app/Http/Middleware 2>/dev/null | head -8 || echo "  (rien)"
ls "$P"/app/Http/Middleware 2>/dev/null | head -30

echo
echo "== FIN diagnostic B =="
