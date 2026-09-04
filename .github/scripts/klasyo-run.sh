#!/usr/bin/env bash
# KLASYO — MODULE B (prep) : forme JSON exacte des cours (champs des cartes) + API catégories live.
set -uo pipefail
P="$HOME/domains/klasyo.org/public_html/platform"
UA='Mozilla/5.0 klasyo-check'

echo "############ 1) HomeController API (courses / categoryList) ############"
HC="$(find "$P/app/Http/Controllers/Api" -iname 'HomeController.php' | head -1)"
echo "  fichier: ${HC#$P/}"
grep -nE 'function (courses|categoryList|upcomingCourses)|topCourse|Resource|->take\(|orderBy|is_feature|CourseResource|CategoryResource' "$HC" 2>/dev/null | head -40

echo
echo "############ 2) Frontend CourseController (allCourses/getCourse) ############"
CC="$(find "$P/app/Http/Controllers/Api/Frontend" -iname 'CourseController.php' | head -1)"
echo "  fichier: ${CC#$P/}"
grep -nE 'function (allCourses|getCourse|getUpcomingCourseList)|Resource::collection|new .*Resource|->paginate|->take' "$CC" 2>/dev/null | head -30

echo
echo "############ 3) Resources JSON (champs exposés des cours) ############"
find "$P/app/Http/Resources" -iname '*Course*' -o -iname '*Category*' 2>/dev/null | sed "s#$P/##" | head
RES="$(find "$P/app/Http/Resources" -iname '*Course*Resource*' 2>/dev/null | grep -viE 'detail|content|lesson|section' | head -1)"
if [ -n "$RES" ]; then
  echo "  ---- ${RES#$P/} (toArray) ----"
  awk '/function toArray/{f=1} f{print} f&&/^\s*\];?\s*$/{c++; if(c>=1) exit}' "$RES" | head -60
fi

echo
echo "############ 4) API catégories live (URLs images + slugs complets) ############"
curl -sS -m 20 -H 'Accept: application/json' -A "$UA" "https://klasyo.org/platform/api/home/category-course" -o /tmp/cat.json 2>/dev/null
php -r '$d=json_decode(file_get_contents("/tmp/cat.json"),true); foreach(($d["data"]??[]) as $c){ echo "  ".$c["slug"]." | ".$c["name"]." | ".($c["is_feature"]??"")." | ".($c["image_url"]??"")."\n"; }' 2>/dev/null | head -30
rm -f /tmp/cat.json
echo
echo "== FIN MODULE B (prep) =="
