#!/usr/bin/env bash
# KLASYO — Phase 1 / Semaine 1, passe 3 : DIAGNOSTIC du blocage /school (triangulation).
# 1) statique  2) PHP minimal  3) Laravel via CLI — pour localiser la couche fautive.
# Logs publics : aucun secret. Fichier de test supprimé en fin de script.
set -uo pipefail

ROOT="$HOME/domains/klasyo.org/public_html"
S="$ROOT/school"

echo "== T1. Fichier STATIQUE dans school/public (robots.txt) =="
out=$(curl -sk -o /dev/null -m 10 -w "%{http_code} time=%{time_total}s" "https://klasyo.org/school/public/robots.txt" 2>&1)
echo "  /school/public/robots.txt -> $out"
out=$(curl -sk -o /dev/null -m 10 -w "%{http_code} time=%{time_total}s" "https://klasyo.org/school/robots.txt" 2>&1)
echo "  /school/robots.txt (via rewrite) -> $out"

echo
echo "== T2. PHP MINIMAL dans school/public =="
TESTF="$S/public/klasyo_diag_$(date +%s).php"
echo '<?php echo "OK-PHP-".PHP_VERSION;' > "$TESTF"
BN=$(basename "$TESTF")
out=$(curl -sk -m 15 -w " [%{http_code} time=%{time_total}s]" "https://klasyo.org/school/public/$BN" 2>&1 | head -c 120)
echo "  /school/public/$BN -> $out"
rm -f "$TESTF"

echo
echo "== T3. Même PHP minimal dans platform/public (référence qui marche) =="
TESTP="$ROOT/platform/public/klasyo_diag_$(date +%s).php"
echo '<?php echo "OK-PHP-".PHP_VERSION;' > "$TESTP"
BNP=$(basename "$TESTP")
out=$(curl -sk -m 15 -w " [%{http_code} time=%{time_total}s]" "https://klasyo.org/platform/public/$BNP" 2>&1 | head -c 120)
echo "  /platform/public/$BNP -> $out"
rm -f "$TESTP"

echo
echo "== T4. Laravel school exécuté DIRECTEMENT en CLI (simulate GET /) =="
cd "$S/public"
START=$(date +%s)
timeout 30 php -d display_errors=1 -r '
  $_SERVER["REQUEST_URI"] = "/school/";
  $_SERVER["REQUEST_METHOD"] = "GET";
  $_SERVER["HTTP_HOST"] = "klasyo.org";
  $_SERVER["SCRIPT_NAME"] = "/school/public/index.php";
  $_SERVER["SCRIPT_FILENAME"] = __DIR__ . "/index.php";
  require "index.php";
' > /tmp/klasyo_school_cli.html 2>&1
RC=$?
END=$(date +%s)
echo "  exit=$RC durée=$((END-START))s taille=$(wc -c < /tmp/klasyo_school_cli.html) octets"
echo "  --- premiers 200 caractères de la réponse :"
head -c 200 /tmp/klasyo_school_cli.html | tr -d '\r' | head -5
echo
echo "  --- indices d'erreur éventuels :"
grep -oiE '(fatal|exception|error|timed? ?out|curl|guzzle|connection)[^<]{0,80}' /tmp/klasyo_school_cli.html | head -5 || echo "  (aucun)"
rm -f /tmp/klasyo_school_cli.html

echo
echo "== T5. Config PHP par répertoire (sélecteur DirectAdmin) =="
for f in "$S/.user.ini" "$S/public/.user.ini" "$S/php.ini" "$ROOT/.htaccess"; do
  [ -f "$f" ] && { echo "  --- $f :"; head -10 "$f"; } || echo "  (absent) $f"
done
echo "  --- Handlers/SetHandler dans les .htaccess school :"
grep -riE 'sethandler|addhandler|php' "$S/.htaccess" "$S/public/.htaccess" 2>/dev/null | head -10 || echo "  (rien)"

echo
echo "== FIN diagnostic passe 3 =="
