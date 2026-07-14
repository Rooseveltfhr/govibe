#!/usr/bin/env bash
# Inspection LECTURE SEULE v2 : identifier les scripts platform/ et school/ de klasyo.org.
# Exécuté via GitHub Actions (klasyo-ops.yml) : ssh ... bash -s < ce_script
#
# SÉCURITÉ (le repo est PUBLIC, les logs Actions aussi) :
# - ne JAMAIS afficher .env, mots de passe, clés, credentials DB
# - n'afficher que : structure, frameworks, versions, noms de tables/colonnes
set -uo pipefail

ROOT="$HOME/domains/klasyo.org/public_html"

echo "== Fichiers racine (identité du site) =="
head -c 200 "$ROOT/documentation.txt" 2>/dev/null; echo
head -c 600 "$ROOT/update_note.json" 2>/dev/null; echo
echo "--- <title> de index.html :"
grep -o -m1 '<title>[^<]*</title>' "$ROOT/index.html" 2>/dev/null || true

# Petit assistant PHP : identifie la DB depuis .env (Laravel) ou config CI,
# se connecte, et n'affiche QUE des noms de tables/colonnes (jamais de credentials).
PHPTMP="$(mktemp /tmp/klasyo_dbinfo_XXXX.php)"
cat > "$PHPTMP" <<'PHPEOF'
<?php
$dir = $argv[1];
$host='localhost'; $db=null; $user=null; $pass=null;
$envfile = "$dir/.env";
if (is_file($envfile)) {
    foreach (file($envfile, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $l) {
        if (preg_match('/^(DB_HOST|DB_DATABASE|DB_USERNAME|DB_PASSWORD)\s*=\s*(.*)$/', $l, $m)) {
            $v = trim($m[2], " \t\"'");
            if ($m[1]=='DB_HOST') $host=$v;
            if ($m[1]=='DB_DATABASE') $db=$v;
            if ($m[1]=='DB_USERNAME') $user=$v;
            if ($m[1]=='DB_PASSWORD') $pass=$v;
        }
    }
} elseif (is_file("$dir/application/config/database.php")) {
    if (!defined('BASEPATH')) define('BASEPATH', '1');
    if (!defined('ENVIRONMENT')) define('ENVIRONMENT', 'production');
    @include "$dir/application/config/database.php";
    if (isset($db_config)) { /* rien */ }
    if (isset($db) && is_array($db) && isset($db['default'])) {
        $c = $db['default'];
        $host = $c['hostname'] ?? 'localhost';
        $dbn  = $c['database'] ?? null;
        $user = $c['username'] ?? null;
        $pass = $c['password'] ?? null;
        $db = $dbn;
    }
}
if (!$db || !$user) { echo "DB: introuvable/illisible pour $dir\n"; exit(0); }
echo "DB: config trouvée (credentials masqués)\n";
mysqli_report(MYSQLI_REPORT_OFF);
$m = @mysqli_connect($host, $user, $pass, $db);
if (!$m) { echo "DB: connexion impossible (".mysqli_connect_errno().")\n"; exit(0); }
$res = mysqli_query($m, 'SHOW TABLES');
$tables = [];
while ($r = mysqli_fetch_row($res)) $tables[] = $r[0];
echo "DB: ".count($tables)." tables au total\n";
$interest = array_values(array_filter($tables, function($t){
    return preg_match('/user|student|staff|admin|role|login|auth|enrol|purchase|payment/i', $t);
}));
echo "Tables auth/vente pertinentes:\n";
foreach (array_slice($interest, 0, 30) as $t) echo "  - $t\n";
foreach ($tables as $t) {
    if (preg_match('/^(users|user|tbl_users|staff)$/i', $t)) {
        echo "Colonnes de `$t`:\n";
        $rc = mysqli_query($m, "SHOW COLUMNS FROM `$t`");
        while ($c = mysqli_fetch_assoc($rc)) echo "    ".$c['Field']." (".$c['Type'].")\n";
        $cnt = mysqli_fetch_row(mysqli_query($m, "SELECT COUNT(*) FROM `$t`"));
        echo "    => ".$cnt[0]." lignes\n";
    }
}
PHPEOF

for APP in platform school; do
  D="$ROOT/$APP"
  echo
  echo "########################################"
  echo "== APPLICATION: $APP ($D) =="
  echo "########################################"
  [ -d "$D" ] || { echo "(absent)"; continue; }

  echo "--- Contenu racine :"
  ls -la "$D" | head -40

  echo "--- Détection framework :"
  [ -f "$D/artisan" ] && echo "  * Laravel (artisan présent)"
  [ -d "$D/application" ] && [ -f "$D/index.php" ] && echo "  * CodeIgniter (application/ présent)"
  [ -f "$D/wp-config.php" ] && echo "  * WordPress"

  if [ -f "$D/composer.json" ]; then
    echo "--- composer.json (name/description/laravel) :"
    grep -E '"(name|description|laravel/framework|php)"' "$D/composer.json" | head -8
  fi
  if [ -f "$D/package.json" ]; then
    echo "--- package.json (name/version) :"
    grep -E '"(name|version)"' "$D/package.json" | head -4
  fi

  echo "--- Indices d'identité du script :"
  for f in "$D/documentation.txt" "$D/update_note.json" "$D/version.php" "$D/VERSION" "$D/readme.txt" "$D/README.md"; do
    [ -f "$f" ] && { echo "  [$f]"; head -c 400 "$f" | tr -d '\r'; echo; }
  done
  # title de la page d'accueil ou du login (vues Laravel/CI)
  grep -rlo -m1 --include='*.blade.php' 'APP_NAME\|config(.app.name' "$D/resources/views" 2>/dev/null | head -2 || true
  if [ -d "$D/application" ]; then
    ls "$D/application/config" 2>/dev/null | head -20
    grep -o "\$config\['version'\][^;]*;" "$D/application/config/"*.php 2>/dev/null | head -3
  fi

  echo "--- Config publique (.env : clés NON sensibles uniquement) :"
  if [ -f "$D/.env" ]; then
    grep -E '^(APP_NAME|APP_ENV|APP_DEBUG|APP_URL|DB_CONNECTION|PURCHASE_CODE)=' "$D/.env" | sed 's/PURCHASE_CODE=.*/PURCHASE_CODE=(masqué)/'
    echo "  (toutes les autres clés .env sont volontairement masquées)"
  else
    echo "  (pas de .env)"
  fi

  echo "--- Base de données (tables/colonnes seulement) :"
  php "$PHPTMP" "$D" 2>&1 | head -80
done

rm -f "$PHPTMP"
echo
echo "== Fin de l'inspection v2 =="
