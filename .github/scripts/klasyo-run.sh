#!/usr/bin/env bash
# KLASYO — MODULE C (prep3) : valeurs ACTUELLES des réglages home (bannière + titres de sections)
# et localisation de la source $home (table/settings), pour rebrander en FR via données (sans toucher le code).
set -uo pipefail
P="$HOME/domains/klasyo.org/public_html/platform"
[ -f "$P/.env" ] || { echo "(!) .env introuvable"; exit 0; }
DB=$(grep -E '^DB_DATABASE=' "$P/.env" | cut -d= -f2- | tr -d '"')
DU=$(grep -E '^DB_USERNAME=' "$P/.env" | cut -d= -f2- | tr -d '"')
DP=$(grep -E '^DB_PASSWORD=' "$P/.env" | cut -d= -f2- | tr -d '"')
q(){ mysql -u"$DU" -p"$DP" "$DB" -N -e "$1" 2>/dev/null; }

echo "############ 1) Réglages de SECTION (settings: titres/sous-titres) ############"
q "SELECT option_key, LEFT(option_value,90) FROM settings WHERE option_key REGEXP 'top_category_title|top_category_subtitle|product_section_title|product_section_subtitle|bundle_course_title|bundle_course_subtitle|upcoming_course_title|upcoming_course_subtitle|instructor_section|feature|come_for_learn' ORDER BY option_key;"

echo
echo "############ 2) Réglages BANNIERE (settings LIKE banner%) ############"
q "SELECT option_key, LEFT(option_value,120) FROM settings WHERE option_key LIKE 'banner%' OR option_key LIKE 'home%' ORDER BY option_key;"

echo
echo "############ 3) D'où vient \$home ? (tables home / home_page_settings) ############"
q "SHOW TABLES LIKE '%home%';"
q "SHOW TABLES LIKE '%banner%';"
echo "  -- grep du controller web qui rend frontend.home --"
grep -rlnE "view\(['\"]frontend\.home|frontend/home|'home'" "$P/app/Http/Controllers" 2>/dev/null | grep -iE 'home|frontend' | head -5 | sed "s#$P/##"
grep -rnE '\$home\s*=|\$data\[.home.\]\s*=|home_page|HomePage|banner_first_line_title' "$P/app" 2>/dev/null | grep -viE 'Api/' | head -15 | sed "s#$P/##"

echo
echo "############ 4) Table home_page_settings si présente (contenu bannière) ############"
T="$(q "SHOW TABLES LIKE '%home_page%';" | head -1)"
if [ -n "$T" ]; then
  echo "  table: $T"
  q "SELECT column_name FROM information_schema.columns WHERE table_schema='$DB' AND table_name='$T';" | tr '\n' ' '; echo
  q "SELECT banner_first_line_title, banner_second_line_title, banner_third_line_title, LEFT(banner_subtitle,120) FROM $T LIMIT 1;"
fi
echo
echo "== FIN MODULE C (prep3) =="
