#!/usr/bin/env bash
# KLASYO — DISCOVERY : (A) clés de réglage BigBlueButton, (B) schéma courses + scopes + instructeurs/catégories
# + gabarit demo.sql, pour publier des cours démo FR et configurer BBB.
set -uo pipefail
P="$HOME/domains/klasyo.org/public_html/platform"
DB=$(grep -E '^DB_DATABASE=' "$P/.env" | cut -d= -f2- | tr -d '"')
DU=$(grep -E '^DB_USERNAME=' "$P/.env" | cut -d= -f2- | tr -d '"')
DP=$(grep -E '^DB_PASSWORD=' "$P/.env" | cut -d= -f2- | tr -d '"')
q(){ mysql -u"$DU" -p"$DP" "$DB" -N -e "$1" 2>/dev/null; }

echo "############ A) BigBlueButton ############"
echo "== fichiers app mentionnant BBB / BigBlueButton =="
grep -rilE 'bigbluebutton|bbb' "$P/app" "$P/config" 2>/dev/null | sed "s#$P/##" | head -15
echo "== clés/labels dans le code (bbb_url, bbb_secret, etc.) =="
grep -rinE "bbb[_-]?(url|secret|server)|bigbluebutton[_-]?(url|secret|server)|'bbb'|get_option\([\"']bbb" "$P/app" 2>/dev/null | sed "s#$P/##" | head -20
echo "== clés BBB déjà en base settings =="
q "SELECT option_key, LEFT(option_value,50) FROM settings WHERE option_key REGEXP 'bbb|bigblue|live_class|liveclass|conference|meeting';"

echo
echo "############ B) COURSES ############"
echo "== DESCRIBE courses (col | type | null | default) =="
q "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM information_schema.columns WHERE table_schema='$DB' AND table_name='courses' ORDER BY ordinal_position;" | head -80
echo
echo "== Scopes Course (active/featured/upcoming/published) =="
CM="$P/app/Models/Course.php"
grep -nE 'function scope(Active|Featured|Upcoming|Published)|STATUS_|status|is_feature|course_type|learner_accessibility' "$CM" 2>/dev/null | head -30
echo
echo "== Catégories & sous-catégories (id/slug) =="
q "SELECT id, slug FROM categories ORDER BY id LIMIT 12;"
echo "  -- sub --"
q "SELECT id, slug, category_id FROM sub_categories ORDER BY id LIMIT 12;"
echo
echo "== Utilisateurs / instructeurs disponibles =="
q "SELECT id, first_name, last_name, email, role FROM users ORDER BY id LIMIT 10;"
echo "  -- table instructors ? --"
q "SHOW TABLES LIKE 'instructors';"
q "SELECT id, user_id FROM instructors LIMIT 5;" 2>/dev/null
echo
echo "== Constantes statut cours (coreconstant) =="
grep -nE "COURSE_STATUS|COURSE_ACTIVE|ACTIVE|COURSE_TYPE|LEARNER" "$P/app/Helper/coreconstant.php" 2>/dev/null | head -20
echo
echo "== Gabarit demo.sql : colonnes INSERT INTO courses =="
grep -n "INSERT INTO \`courses\`" "$P/app/demo.sql" 2>/dev/null | head -2
grep -m1 -A2 "INSERT INTO \`courses\`" "$P/app/demo.sql" 2>/dev/null | head -3 | cut -c1-400
echo
echo "== FIN DISCOVERY =="
