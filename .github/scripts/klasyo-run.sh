#!/usr/bin/env bash
# KLASYO — DISCOVERY 2 : valeurs exactes pour seeder des cours (user/instructeur, langue, niveau,
# learner_accessibility) + clé secret BBB (config + SettingController).
set -uo pipefail
P="$HOME/domains/klasyo.org/public_html/platform"
DB=$(grep -E '^DB_DATABASE=' "$P/.env" | cut -d= -f2- | tr -d '"')
DU=$(grep -E '^DB_USERNAME=' "$P/.env" | cut -d= -f2- | tr -d '"')
DP=$(grep -E '^DB_PASSWORD=' "$P/.env" | cut -d= -f2- | tr -d '"')
q(){ mysql -u"$DU" -p"$DP" "$DB" -N -e "$1" 2>&1; }

echo "############ Utilisateurs / instructeurs ############"
echo "== colonnes users =="
q "SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema='$DB' AND table_name='users' AND COLUMN_NAME REGEXP 'id|name|email|role|type|status';" | tr '\n' ' '; echo
echo "== users (5) =="
q "SELECT id, first_name, last_name, email FROM users ORDER BY id LIMIT 5;"
echo "== instructors (id,user_id,uuid?) =="
q "SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema='$DB' AND table_name='instructors';" | tr '\n' ' '; echo
q "SELECT id, user_id FROM instructors ORDER BY id LIMIT 5;"

echo
echo "############ Langues & niveaux & accessibilité ############"
echo "== tables candidates =="
q "SHOW TABLES LIKE '%language%';"
q "SHOW TABLES LIKE '%difficult%';"
echo "== course_languages =="
q "SELECT id, name FROM course_languages ORDER BY id LIMIT 8;" 2>/dev/null
echo "== difficulty_levels =="
q "SELECT id, name FROM difficulty_levels ORDER BY id LIMIT 8;" 2>/dev/null
echo "== constantes accessibilité (coreconstant) =="
grep -inE "ACCESSIBILITY|LEARNER_ACCESS|COURSE_ACCESS|'paid'|'free'|'subscription'" "$P/app/Helper/coreconstant.php" 2>/dev/null | head -12
echo "== usage learner_accessibility dans le code =="
grep -rinE "learner_accessibility\s*=>|learner_accessibility'\s*," "$P/app/Http/Controllers/Instructor" 2>/dev/null | head -5

echo
echo "############ BigBlueButton : clés exactes ############"
echo "== config/bigbluebutton.php =="
sed -n '1,40p' "$P/config/bigbluebutton.php" 2>/dev/null
echo "== SettingController lignes 390-420 =="
sed -n '390,420p' "$P/app/Http/Controllers/Admin/SettingController.php" 2>/dev/null
echo "== .env : lignes BBB actuelles =="
grep -iE 'BBB|BIGBLUE' "$P/.env" 2>/dev/null || echo "  (aucune ligne BBB dans .env)"

echo
echo "== FIN DISCOVERY 2 =="
