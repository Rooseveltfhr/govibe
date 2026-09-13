#!/usr/bin/env bash
# KLASYO — DISCOVERY 3 : prérequis pour créer un instructeur (rôle, relation, colonnes users, statut approuvé).
set -uo pipefail
P="$HOME/domains/klasyo.org/public_html/platform"
DB=$(grep -E '^DB_DATABASE=' "$P/.env" | cut -d= -f2- | tr -d '"')
DU=$(grep -E '^DB_USERNAME=' "$P/.env" | cut -d= -f2- | tr -d '"')
DP=$(grep -E '^DB_PASSWORD=' "$P/.env" | cut -d= -f2- | tr -d '"')
q(){ mysql -u"$DU" -p"$DP" "$DB" -N -e "$1" 2>&1; }

echo "== getUserRoleRelation (helper) =="
grep -n -A15 'function getUserRoleRelation' "$P/app/Helper/helper.php" 2>/dev/null | head -20

echo
echo "== Constantes rôle & statut (coreconstant) =="
grep -inE "USER_ROLE|ROLE_INSTRUCTOR|ROLE_ADMIN|ROLE_STUDENT|ROLE_ORGANIZATION|STATUS_APPROVED|INSTRUCTOR_STATUS|ACTIVE\s*=" "$P/app/Helper/coreconstant.php" 2>/dev/null | head -30

echo
echo "== Course->instructor() relation =="
grep -n -A3 'function instructor(' "$P/app/Models/Course.php" 2>/dev/null | head -12
grep -n -A3 'function user(' "$P/app/Models/Course.php" 2>/dev/null | head -8

echo
echo "== users : colonnes NOT NULL (sans défaut) =="
q "SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_DEFAULT FROM information_schema.columns WHERE table_schema='$DB' AND table_name='users' AND IS_NULLABLE='NO';"
echo "== users : toutes colonnes =="
q "SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema='$DB' AND table_name='users' ORDER BY ordinal_position;" | tr '\n' ' '; echo
echo "== user 1 (échantillon valeurs role/username/password non affiché) =="
q "SELECT id, name, email, role FROM users WHERE id=1;"

echo
echo "== instructors : toutes colonnes =="
q "SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema='$DB' AND table_name='instructors' ORDER BY ordinal_position;" | tr '\n' ' '; echo
echo "== getUserRoleRelation usages / STATUS constants instructor =="
grep -inE "APPROVED|STATUS_ACTIVE|const STATUS" "$P/app/Helper/coreconstant.php" 2>/dev/null | head -20

echo
echo "== FIN DISCOVERY 3 =="
