#!/usr/bin/env bash
# KLASYO — DISCOVERY GLOBAL : (A) bug "login first" sur enroll, (B) flux cours gratuit/payant,
# (C) restes LMSZAI + couleurs theme, (D) langue par défaut + table languages.
set -uo pipefail
P="$HOME/domains/klasyo.org/public_html/platform"
DB=$(grep -E '^DB_DATABASE=' "$P/.env" | cut -d= -f2- | tr -d '"')
DU=$(grep -E '^DB_USERNAME=' "$P/.env" | cut -d= -f2- | tr -d '"')
DP=$(grep -E '^DB_PASSWORD=' "$P/.env" | cut -d= -f2- | tr -d '"')
q(){ mysql -u"$DU" -p"$DP" "$DB" -N -e "$1" 2>&1; }

echo "########## A) 'You need to login first' — où vit ce message ? ##########"
grep -rniF "need to login" "$P/resources" "$P/public" 2>/dev/null | grep -v node_modules | head -20
echo "-- fichiers JS compilés qui le contiennent --"
grep -rliF "need to login" "$P/public/frontend" 2>/dev/null | head -10

echo
echo "########## B) Bouton Enroll / Start — logique JS ##########"
grep -rniE "enroll.*course|enrollCourse|enroll_course" "$P/public/frontend/assets/js" 2>/dev/null | grep -iE "login|redirect|window.location" | head -20
echo "-- vue course-details : bouton Enroll/Start --"
CD="$(find "$P/resources/views" -path '*course-details*' -iname '*.blade.php' | grep -v instructor | grep -v admin | head -1)"
echo "   fichier: ${CD#$P/}"
grep -niE "enroll|login|route\('login'\)|data-bs-toggle=\"modal\"" "$CD" 2>/dev/null | head -30

echo
echo "########## C) Flux checkout pour cours GRATUIT (price=0) ##########"
grep -rniE "price\s*==\s*0|price\s*\\\\?\s*0|free.*enroll|enrollFreeCourse|freeEnroll" "$P/app/Http/Controllers" 2>/dev/null | head -20
echo "-- CartManagementController : logique free --"
grep -n -B2 -A15 "function.*[Ff]ree" "$P/app/Http/Controllers/Student/CartManagementController.php" 2>/dev/null | head -60

echo
echo "########## D) Restes LMSZAI dans le code/texte visible ##########"
grep -rliE "LMSZAI" "$P/resources/views" "$P/config" "$P/.env" 2>/dev/null | grep -v vendor | head -20
echo "-- occurrences exactes avec contexte --"
grep -rniE "LMSZAI" "$P/resources/views" 2>/dev/null | grep -v vendor | head -15
echo "-- manifest.json / favicon / meta --"
find "$P/public" -maxdepth 2 -iname 'manifest.json' -o -iname 'site.webmanifest' 2>/dev/null | xargs -I{} sh -c 'echo "  {}"; cat "{}" 2>/dev/null | head -5' 2>/dev/null

echo
echo "########## E) Couleurs / thème (settings) ##########"
q "SELECT option_key, option_value FROM settings WHERE option_key REGEXP 'primary_color|secondary_color|theme_color|site_color' LIMIT 20;"
echo "-- fichier CSS variables racine si présent --"
grep -rliE ':root\s*\{' "$P/public/frontend/assets/css" 2>/dev/null | head -5

echo
echo "########## F) Langue par défaut ##########"
q "SELECT option_key, option_value FROM settings WHERE option_key REGEXP 'language_id|default_language|locale';"
q "SHOW TABLES LIKE '%language%';" | grep -v course_language
q "SELECT * FROM languages LIMIT 10;" 2>/dev/null
echo "-- middleware locale --"
grep -rliE "app\(\)->setLocale|App::setLocale" "$P/app/Http" 2>/dev/null | head -10

echo
echo "== FIN DISCOVERY GLOBAL =="
