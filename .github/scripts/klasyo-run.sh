#!/usr/bin/env bash
# KLASYO — Créer un instructeur (user role=2 + instructors approuvé) et y rattacher les 6 cours démo,
# afin qu'ils apparaissent dans le catalogue/API (l'auteur est résolu via le rôle du propriétaire).
set -uo pipefail
P="$HOME/domains/klasyo.org/public_html/platform"
DB=$(grep -E '^DB_DATABASE=' "$P/.env" | cut -d= -f2- | tr -d '"')
DU=$(grep -E '^DB_USERNAME=' "$P/.env" | cut -d= -f2- | tr -d '"')
DP=$(grep -E '^DB_PASSWORD=' "$P/.env" | cut -d= -f2- | tr -d '"')
MYSQL(){ mysql -u"$DU" -p"$DP" "$DB" "$@"; }
Q(){ mysql -u"$DU" -p"$DP" "$DB" -N -e "$1" 2>/dev/null; }
EMAIL='formateur.demo@klasyo.org'

echo "== 1) Utilisateur instructeur (role=2) =="
U="$(Q "SELECT id FROM users WHERE email='$EMAIL' LIMIT 1;" | head -1)"
if [ -z "$U" ]; then
  HASH="$(cd "$P" && php -r "echo password_hash('KlasyoDemo2026', PASSWORD_BCRYPT);" 2>/dev/null)"
  MYSQL -e "SET NAMES utf8mb4; INSERT INTO users (name,email,email_verified_at,password,role,created_at,updated_at)
            VALUES ('KLASYO Formation','$EMAIL',NOW(),'$HASH',2,NOW(),NOW());"
  U="$(Q "SELECT id FROM users WHERE email='$EMAIL' LIMIT 1;" | head -1)"
  echo "   créé user_id=$U"
else
  echo "   existe déjà user_id=$U"
fi

echo "== 2) Profil instructeur (approuvé, status=1) =="
I="$(Q "SELECT id FROM instructors WHERE user_id=$U LIMIT 1;" | head -1)"
if [ -z "$I" ]; then
  MYSQL -e "SET NAMES utf8mb4; INSERT INTO instructors
            (uuid,user_id,first_name,last_name,professional_title,slug,status,is_subscription_enable,created_at,updated_at)
            VALUES (UUID(),$U,'KLASYO','Formation','Formateur KLASYO','klasyo-formation',1,1,NOW(),NOW());"
  I="$(Q "SELECT id FROM instructors WHERE user_id=$U LIMIT 1;" | head -1)"
  echo "   créé instructor_id=$I"
else
  MYSQL -e "UPDATE instructors SET status=1 WHERE id=$I;"
  echo "   existe déjà instructor_id=$I (status forcé à 1)"
fi

echo "== 3) Rattacher les 6 cours démo à l'instructeur =="
MYSQL -e "SET NAMES utf8mb4; UPDATE courses SET user_id=$U, instructor_id=$I
  WHERE slug IN ('introduction-programmation-python','html-css-premier-site-web','marketing-digital-debutants','comptabilite-generale-bases','design-graphique-canva','excel-debutant-confirme');"
echo "   cours rattachés: $(Q "SELECT COUNT(*) FROM courses WHERE instructor_id=$I;")"

echo "== 4) Purge cache + vérif API =="
( cd "$P" && timeout 40 php artisan optimize:clear 2>&1 | tail -1 ) || true
UA='Mozilla/5.0 klasyo-check'
echo "  /api/courses-list :"
curl -sS -m 20 -H 'Accept: application/json' -A "$UA" "https://klasyo.org/platform/api/courses-list" 2>/dev/null | php -r '$j=json_decode(file_get_contents("php://stdin"),true); $c=$j["data"]["courses"]??[]; echo "   ".count($c)." cours\n"; foreach(array_slice($c,0,6) as $x){ echo "   - ".($x["title"]??"?")." | prix ".($x["price"]??"?")." | note ".($x["average_rating"]??"?")." | ".($x["author"]??"?")."\n"; }' 2>/dev/null || echo "   (lecture API échouée)"
echo "  /api/home/category-course (nb catégories):"
curl -sS -m 20 -H 'Accept: application/json' -A "$UA" "https://klasyo.org/platform/api/home/category-course" 2>/dev/null | php -r '$j=json_decode(file_get_contents("php://stdin"),true); echo "   ".count($j["data"]??[])."\n";' 2>/dev/null || true
echo
echo "== FIN =="
