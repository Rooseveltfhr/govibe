#!/usr/bin/env bash
# Inspection LECTURE SEULE v3 : frontend LMSZAI (platform), routing web, marque KLASYO.
# Exécuté via GitHub Actions (klasyo-ops.yml) : ssh ... bash -s < ce_script
#
# SÉCURITÉ (logs publics) : jamais de contenu .env / credentials / clés.
# Les tests HTTP n'affichent QUE des codes de statut.
set -uo pipefail

ROOT="$HOME/domains/klasyo.org/public_html"
P="$ROOT/platform"
S="$ROOT/school"

echo "== 1. Tests HTTP depuis le VPS (codes de statut seulement) =="
for u in "https://klasyo.org/" "https://klasyo.org/platform/" "https://klasyo.org/platform/public/" \
         "https://klasyo.org/school/" "https://klasyo.org/school/public/" ; do
  code=$(curl -sk -o /dev/null -m 15 -w "%{http_code}" "$u")
  echo "  $u -> $code"
done
echo "  -- exposition potentielle (on veut 403/404 partout ici) :"
for u in "https://klasyo.org/platform/.env" "https://klasyo.org/school/.env" \
         "https://klasyo.org/platform/klasyo.zip" "https://klasyo.org/school/school.zip" \
         "https://klasyo.org/school/source_code.zip"; do
  code=$(curl -sk -o /dev/null -m 15 -r 0-0 -w "%{http_code}" "$u")
  echo "  $u -> $code"
done

echo
echo "== 2. Réécriture d'URL (comment /platform est servi) =="
echo "--- $ROOT/.htaccess :"
cat "$ROOT/.htaccess" 2>/dev/null || echo "(absent)"
echo "--- $P/.htaccess :"
cat "$P/.htaccess" 2>/dev/null || echo "(absent)"
echo "--- titre de $P/index.html (fichier statique à la racine platform) :"
grep -o -m1 '<title>[^<]*</title>' "$P/index.html" 2>/dev/null || echo "(pas de title)"

echo
echo "== 3. Marque KLASYO (extraite de la landing racine) =="
echo "--- Couleurs les plus utilisées :"
grep -oE '#[0-9a-fA-F]{6}\b' "$ROOT/index.html" | tr 'A-F' 'a-f' | sort | uniq -c | sort -rn | head -12
echo "--- Variables CSS :"
grep -oE '\-\-[a-z0-9-]+:\s*[^;}]{1,60}' "$ROOT/index.html" | sort -u | head -25
echo "--- Polices :"
grep -oE 'font-family:[^;}]{1,80}' "$ROOT/index.html" | sort -u | head -8
grep -oE 'fonts.googleapis.com/css2?\?family=[^"'\'' ]*' "$ROOT/index.html" | sort -u | head -5

echo
echo "== 4. Structure frontend LMSZAI (platform) =="
echo "--- resources/views (niveau 1) :"
ls "$P/resources/views" 2>/dev/null
echo "--- resources/views/frontend (si présent) :"
ls "$P/resources/views/frontend" 2>/dev/null | head -40
echo "--- thèmes éventuels :"
ls -d "$P"/resources/views/*/ 2>/dev/null | head -20
echo "--- public/ (niveau 1) :"
ls "$P/public" 2>/dev/null
echo "--- public/frontend (assets du thème) :"
ls "$P/public/frontend" 2>/dev/null | head -30

echo
echo "== 5. Routing LMSZAI (60 premières lignes de routes/web.php) =="
head -60 "$P/routes/web.php" 2>/dev/null

echo
echo "== 6. Page d'accueil LMSZAI : quel contrôleur/vue ? =="
grep -rn "function index" "$P/app/Http/Controllers/Frontend"*.php 2>/dev/null | head -5 || true
ls "$P/app/Http/Controllers" 2>/dev/null | head -25
echo "--- vues home probables :"
find "$P/resources/views" -maxdepth 3 -iname '*home*' -o -maxdepth 3 -iname '*landing*' -o -maxdepth 3 -iname '*index*' 2>/dev/null | grep -v vendor | head -15

echo
echo "== 7. Réglages d'apparence stockés en DB (LMSZAI settings) =="
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
// tables de réglages fréquentes dans LMSZAI
foreach (['settings','general_settings','home_settings','theme_settings'] as $t) {
    $r = @mysqli_query($m, "SHOW COLUMNS FROM `$t`");
    if (!$r) continue;
    $cols = [];
    while ($c = mysqli_fetch_assoc($r)) $cols[] = $c['Field'];
    echo "table `$t`: ".implode(', ', array_slice($cols,0,20))."\n";
    // n'afficher que des clés/valeurs d'apparence, jamais de secrets
    if (in_array('key', $cols) && in_array('value', $cols)) {
        $q = mysqli_query($m, "SELECT `key`, LEFT(`value`,80) v FROM `$t` WHERE `key` REGEXP 'logo|color|colour|theme|title|name|favicon|font' LIMIT 25");
        while ($row = mysqli_fetch_assoc($q)) echo "    {$row['key']} = {$row['v']}\n";
    }
}
PHPEOF
php "$PHPTMP" "$P" 2>&1 | head -60
rm -f "$PHPTMP"

echo
echo "== Fin de l'inspection v3 =="
