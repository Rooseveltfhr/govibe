#!/usr/bin/env bash
# KLASYO — Phase 1 / Semaine 2, étape A : dé-brander LMSZAI -> KLASYO (app_name)
# + réactiver l'inscription publique sur platform (routes/web.php: register=>false -> true).
# Scripts achetés : modifications minimales, chirurgicales, sauvegardées, réversibles.
# Logs publics : aucun secret (on n'affiche jamais .env ni credentials DB).
set -uo pipefail

ROOT="$HOME/domains/klasyo.org/public_html"
P="$ROOT/platform"
BK="$HOME/backups_klasyo"
STAMP="$(date +%Y%m%d-%H%M%S)"
mkdir -p "$BK"

# Petit assistant PHP : lit la config DB depuis platform/.env, exécute une requête,
# n'affiche que ce qu'on lui demande (jamais de credentials).
run_sql() { # $1 = requête SQL
  php -r '
    $dir=$argv[1]; $host="localhost";$db=null;$u=null;$p=null;
    foreach(file("$dir/.env",FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $l){
      if(preg_match("/^(DB_HOST|DB_DATABASE|DB_USERNAME|DB_PASSWORD)\s*=\s*(.*)$/",$l,$m)){
        $v=trim($m[2]," \t\"'"'"'");
        if($m[1]=="DB_HOST")$host=$v; if($m[1]=="DB_DATABASE")$db=$v;
        if($m[1]=="DB_USERNAME")$u=$v; if($m[1]=="DB_PASSWORD")$p=$v;
      }
    }
    mysqli_report(MYSQLI_REPORT_OFF);
    $m=@mysqli_connect($host,$u,$p,$db); if(!$m){echo "DB-ERR\n";exit(1);}
    $r=mysqli_query($m,$argv[2]);
    if($r===true){echo "OK rows=".mysqli_affected_rows($m)."\n";}
    elseif($r){while($row=mysqli_fetch_row($r))echo implode(" | ",$row)."\n";}
    else echo "SQL-ERR: ".mysqli_error($m)."\n";
  ' "$P" "$1"
}

echo "########################################"
echo "== A1. Sauvegarde des réglages de marque (avant modification) =="
echo "########################################"
run_sql "SELECT option_key, option_value FROM settings WHERE option_key IN ('app_name','app_title','site_title','copyright','meta_title')" \
  | tee "$BK/settings_brand.before-$STAMP.txt"

echo
echo "########################################"
echo "== A2. app_name : LMSZAI -> KLASYO (DB) =="
echo "########################################"
run_sql "UPDATE settings SET option_value='KLASYO' WHERE option_key='app_name'"
# Certaines vues LMSZAI utilisent d'autres clés de titre : on les aligne si elles existent
for K in app_title site_title meta_title; do
  run_sql "UPDATE settings SET option_value='KLASYO' WHERE option_key='$K' AND option_value LIKE '%LMSzai%'"
done
echo "  --- après :"
run_sql "SELECT option_key, option_value FROM settings WHERE option_key IN ('app_name','app_title','site_title','meta_title')"

echo
echo "########################################"
echo "== A3. APP_NAME dans platform/.env =="
echo "########################################"
cp -a "$P/.env" "$P/.env.bak-name-$STAMP"
if grep -q '^APP_NAME=' "$P/.env"; then
  sed -i 's|^APP_NAME=.*|APP_NAME=KLASYO|' "$P/.env"
else
  printf 'APP_NAME=KLASYO\n' >> "$P/.env"
fi
grep '^APP_NAME=' "$P/.env"

echo
echo "########################################"
echo "== A4. Réactivation de l'inscription publique (routes/web.php) =="
echo "########################################"
WEB="$P/routes/web.php"
echo "  Ligne actuelle Auth::routes :"
grep -n "Auth::routes" "$WEB" || echo "  (introuvable)"
if grep -q "Auth::routes(\['register' => false\])" "$WEB"; then
  cp -a "$WEB" "$BK/web.php.bak-$STAMP"
  echo "  backup: web.php.bak-$STAMP"
  sed -i "s/Auth::routes(\['register' => false\])/Auth::routes(['register' => true])/" "$WEB"
  echo "  -> inscription RÉACTIVÉE. Nouvelle ligne :"
  grep -n "Auth::routes" "$WEB"
elif grep -q "Auth::routes(\['register' => true\])" "$WEB"; then
  echo "  Déjà réactivée (register => true)."
else
  echo "  (!) Motif register=>false non trouvé — inspection manuelle nécessaire :"
  grep -n "Auth::routes" "$WEB"
fi

echo
echo "########################################"
echo "== A5. Purge caches + vérification HTTP =="
echo "########################################"
(cd "$P" && php artisan config:clear 2>&1 | tail -1)
(cd "$P" && php artisan route:clear  2>&1 | tail -1)
(cd "$P" && php artisan view:clear   2>&1 | tail -1)
for u in "https://klasyo.org/platform/index.php" \
         "https://klasyo.org/platform/register" \
         "https://klasyo.org/platform/login" ; do
  out=$(curl -skL -o /dev/null -m 15 -w "%{http_code} (%{time_total}s)" "$u" 2>&1)
  echo "  $u -> $out"
done
echo "  --- <title> de la page d'accueil platform (doit refléter KLASYO) :"
curl -skL -m 12 "https://klasyo.org/platform/index.php" 2>/dev/null | grep -oiE '<title>[^<]*</title>' | head -1

echo
echo "== FIN étape A (rebrand + inscription) =="
