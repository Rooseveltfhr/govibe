#!/usr/bin/env bash
# Inspection LECTURE SEULE v4 : thème actif LMSZAI + diagnostic /school/ qui ne répond pas.
# Exécuté via GitHub Actions (klasyo-ops.yml). Logs publics : jamais de secrets.
set -uo pipefail

ROOT="$HOME/domains/klasyo.org/public_html"
P="$ROOT/platform"
S="$ROOT/school"

echo "== 1. Réglages d'apparence LMSZAI (option_key/option_value) =="
PHPTMP="$(mktemp /tmp/klasyo_settings_XXXX.php)"
cat > "$PHPTMP" <<'PHPEOF'
<?php
$dir = $argv[1];
$host='localhost'; $db=null; $user=null; $pass=null;
foreach (file("$dir/.env", FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $l) {
    if (preg_match('/^(DB_HOST|DB_DATABASE|DB_USERNAME|DB_PASSWORD)\s*=\s*(.*)$/', $l, $m)) {
        $v = trim($m[2], " \t\"'");
        if ($m[1]=='DB_HOST') $host=$v;
        if ($m[1]=='DB_DATABASE') $db=$v;
        if ($m[1]=='DB_USERNAME') $user=$v;
        if ($m[1]=='DB_PASSWORD') $pass=$v;
    }
}
mysqli_report(MYSQLI_REPORT_OFF);
$m = @mysqli_connect($host, $user, $pass, $db);
if (!$m) { echo "DB: connexion impossible\n"; exit(0); }
$q = mysqli_query($m, "SELECT option_key, LEFT(option_value,70) v FROM settings WHERE option_key REGEXP 'theme|logo|color|colour|title|site|name|favicon|font|version' ORDER BY option_key LIMIT 40");
if ($q) while ($row = mysqli_fetch_assoc($q)) echo "  {$row['option_key']} = {$row['v']}\n";
PHPEOF
php "$PHPTMP" "$P" 2>&1 | head -50
rm -f "$PHPTMP"

echo
echo "== 2. Diagnostic /school/ (ne répond pas) =="
echo "--- $S/.htaccess :"
cat "$S/.htaccess" 2>/dev/null || echo "(absent)"
echo "--- $S/public (niveau 1, 20 max) :"
ls "$S/public" 2>/dev/null | head -20
echo "--- Test HTTP avec suivi de redirections (codes + URL finale, max 8s) :"
for u in "https://klasyo.org/school/public/index.php" "https://klasyo.org/school/index.php"; do
  out=$(curl -sk -o /dev/null -m 8 -w "%{http_code} redir=%{redirect_url}" "$u" 2>&1)
  echo "  $u -> $out"
done
echo "--- Dernières erreurs Laravel school (message seulement, 5 lignes) :"
L=$(ls -t "$S"/storage/logs/*.log 2>/dev/null | head -1)
if [ -n "$L" ]; then
  grep -oE '^\[[0-9 :-]+\] \w+\.\w+: [^{]{0,140}' "$L" | tail -5
else
  echo "(pas de log)"
fi

echo
echo "== 3. Diagnostic /platform/ (000) : test avec redirections =="
out=$(curl -sk -o /dev/null -m 8 -w "%{http_code} redir=%{redirect_url}" "https://klasyo.org/platform/index.php" 2>&1)
echo "  /platform/index.php -> $out"
out=$(curl -skL -o /dev/null -m 12 -w "final=%{http_code} url=%{url_effective} redirs=%{num_redirects}" "https://klasyo.org/platform/public/" 2>&1)
echo "  /platform/public/ (suivi) -> $out"

echo
echo "== 4. Layout frontend LMSZAI : où le CSS est chargé =="
for f in "$P/resources/views/frontend/layouts/app.blade.php" "$P/resources/views/frontend/layouts/master.blade.php" "$P/resources/views/layouts/app.blade.php"; do
  [ -f "$f" ] && { echo "--- $f (balises link/css, 15 max) :"; grep -oE '<link[^>]{0,140}' "$f" | head -15; }
done
echo "--- fichiers layout frontend disponibles :"
ls "$P/resources/views/frontend/layouts" 2>/dev/null | head -15

echo
echo "== Fin de l'inspection v4 =="
