#!/usr/bin/env bash
# KLASYO — DIAGNOSTIC : pourquoi /api/courses-list = 0 malgré 6 cours actifs ? (filtre instructeur ?)
set -uo pipefail
P="$HOME/domains/klasyo.org/public_html/platform"
DB=$(grep -E '^DB_DATABASE=' "$P/.env" | cut -d= -f2- | tr -d '"')
DU=$(grep -E '^DB_USERNAME=' "$P/.env" | cut -d= -f2- | tr -d '"')
DP=$(grep -E '^DB_PASSWORD=' "$P/.env" | cut -d= -f2- | tr -d '"')
q(){ mysql -u"$DU" -p"$DP" "$DB" -N -e "$1" 2>&1; }

echo "== Mes cours seedés (id,title,status,instr,cat,accessibility) =="
q "SELECT id, LEFT(title,32), status, instructor_id, category_id, learner_accessibility FROM courses ORDER BY id;"

echo
echo "== allCourses / getCourse : filtres (CourseController Frontend 25-120) =="
sed -n '25,120p' "$P/app/Http/Controllers/Api/Frontend/CourseController.php"

echo
echo "== instructors : colonnes NOT NULL (sans défaut) =="
q "SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_DEFAULT FROM information_schema.columns WHERE table_schema='$DB' AND table_name='instructors' AND IS_NULLABLE='NO';"
echo "== instructors : nb lignes =="
q "SELECT COUNT(*) FROM instructors;"
echo "== users : id/role/status de user 1 =="
q "SELECT id, name, role, status FROM users WHERE id=1;" 2>/dev/null || q "SELECT id, name, role FROM users WHERE id=1;"
echo "== filterCourseData / scope publié éventuel (grep) =="
grep -nE "whereHas\('instructor|instructor_id|approval|is_approved|published|active\(\)|verified" "$P/app/Http/Controllers/Api/Frontend/CourseController.php" | head -20

echo
echo "== FIN DIAGNOSTIC =="
