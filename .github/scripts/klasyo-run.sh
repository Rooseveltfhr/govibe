#!/usr/bin/env bash
# KLASYO — MODULE C (prep) : identifier le thème actif + localiser les textes visibles (hero/sections)
# de la home LMS, et voir s'ils sont en __() (traduisibles) ou en dur.
set -uo pipefail
P="$HOME/domains/klasyo.org/public_html/platform"

echo "############ 1) Thème frontend actif (DB settings) ############"
if [ -f "$P/.env" ]; then
  DB=$(grep -E '^DB_DATABASE=' "$P/.env" | cut -d= -f2- | tr -d '"')
  DU=$(grep -E '^DB_USERNAME=' "$P/.env" | cut -d= -f2- | tr -d '"')
  DP=$(grep -E '^DB_PASSWORD=' "$P/.env" | cut -d= -f2- | tr -d '"')
  mysql -u"$DU" -p"$DP" "$DB" -N -e \
    "SELECT option_key, option_value FROM settings WHERE option_key REGEXP 'theme|locale|lang|default_language' LIMIT 40;" 2>/dev/null
fi
echo
echo "############ 2) Quel fichier thème contient les textes visibles ? ############"
for S in "Our Top Categories" "A Broad Selection Of Courses" "Top Rated Courses From Our Top Instructor" "Starts Here" "top-categories-area"; do
  echo "== \"$S\" =="
  grep -rl "$S" "$P/resources/views" 2>/dev/null | sed "s#$P/##" | head -5
done
echo
echo "############ 3) Sont-ils en __() ou en dur ? (échantillon avec n° ligne) ############"
grep -rnE "Our Top Categories|A Broad Selection|Top Rated Courses From|Starts Here|Quality Course, Instructor" "$P/resources/views" 2>/dev/null | head -20
echo
echo "############ 4) Fichiers de langue (fr) présents ? ############"
find "$P/resources/lang" -maxdepth 2 -iname 'fr*' -o -maxdepth 2 -iname '*fr.json' 2>/dev/null | sed "s#$P/##" | head
ls "$P/resources/lang" 2>/dev/null | head
echo
echo "== FIN MODULE C (prep) =="
