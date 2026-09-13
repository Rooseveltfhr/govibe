#!/usr/bin/env bash
# KLASYO — (A) config BigBlueButton (.env) + (B) seed 6 cours démo FR + (C) patch landing "Populaires".
set -uo pipefail
P="$HOME/domains/klasyo.org/public_html/platform"
ROOT="$HOME/domains/klasyo.org/public_html"
LAND="$ROOT/index.html"
DB=$(grep -E '^DB_DATABASE=' "$P/.env" | cut -d= -f2- | tr -d '"')
DU=$(grep -E '^DB_USERNAME=' "$P/.env" | cut -d= -f2- | tr -d '"')
DP=$(grep -E '^DB_PASSWORD=' "$P/.env" | cut -d= -f2- | tr -d '"')
MYSQL(){ mysql -u"$DU" -p"$DP" "$DB" "$@"; }
STAMP="$(date +%Y%m%d-%H%M%S)"
BKD="$HOME/klasyo-backups"; mkdir -p "$BKD"

echo "############ A) BigBlueButton (.env) ############"
cp -p "$P/.env" "$BKD/env-bbb-$STAMP.bak" && echo "  backup .env -> $BKD/env-bbb-$STAMP.bak"
sed -i 's|^BBB_SERVER_BASE_URL=.*|BBB_SERVER_BASE_URL="https://test-install.blindsidenetworks.com/bigbluebutton/api"|' "$P/.env"
sed -i 's|^BBB_SECURITY_SALT=.*|BBB_SECURITY_SALT="8cd8ef52e8e101574e400365b55e11a6"|' "$P/.env"
grep -iE '^BBB_' "$P/.env" | sed 's/=.*SALT.*/=***(masqué)/; s/\(SALT="\)[^"]*/\1***/' || true
# s'assurer que bbb_status = 1
MYSQL -e "INSERT INTO settings (option_key, option_value) VALUES ('bbb_status','1') ON DUPLICATE KEY UPDATE option_value='1';" 2>/dev/null && echo "  bbb_status=1 OK"
( cd "$P" && timeout 60 php artisan optimize:clear 2>&1 | tail -2 ) || echo "  (optimize:clear ignoré)"
echo "  Vérif config live BBB:"
( cd "$P" && php -r '$c=require "config/bigbluebutton.php"; echo "   URL=".$c["BBB_SERVER_BASE_URL"]."\n   SALT set=".(strlen($c["BBB_SECURITY_SALT"])>0?"oui":"non")."\n";' 2>/dev/null ) || echo "   (lecture config échouée)"

echo
echo "############ B) Seed 6 cours démo (français) ############"
UID_VAL="$(MYSQL -N -e "SELECT id FROM users ORDER BY id LIMIT 1;" 2>/dev/null | head -1)"
IID_VAL="$(MYSQL -N -e "SELECT id FROM instructors ORDER BY id LIMIT 1;" 2>/dev/null | head -1)"
echo "  owner user_id=$UID_VAL  instructor_id=${IID_VAL:-NULL}"
EXIST="$(MYSQL -N -e "SELECT COUNT(*) FROM courses WHERE slug='introduction-programmation-python';" 2>/dev/null | head -1)"
if [ -z "$UID_VAL" ]; then
  echo "  (!) aucun utilisateur pour être propriétaire -> seed ignoré"
elif [ "${EXIST:-0}" != "0" ]; then
  echo "  (déjà seedé, on saute) courses démo présents"
else
  IIDSQL="NULL"; [ -n "$IID_VAL" ] && IIDSQL="$IID_VAL"
  MYSQL <<SQL
SET NAMES utf8mb4;
INSERT INTO courses
(uuid,user_id,course_type,instructor_id,category_id,course_language_id,difficulty_level_id,title,subtitle,description,price,old_price,image,is_subscription_enable,private_mode,slug,is_featured,status,average_rating,drip_content,created_at,updated_at)
VALUES
(UUID(),$UID_VAL,1,$IIDSQL,1,1,2,'Introduction à la programmation Python','Débutez la programmation pas à pas','Apprenez les bases de la programmation avec Python : variables, boucles, fonctions et petits projets pratiques. Formation en français basée sur des ressources ouvertes disponibles en ligne, idéale pour les débutants.',0.00,0.00,'uploads_demo/category/1.png',1,0,'introduction-programmation-python',1,1,4.80,1,DATE_SUB(NOW(),INTERVAL 1 DAY),NOW()),
(UUID(),$UID_VAL,1,$IIDSQL,1,1,2,'HTML & CSS : créer son premier site web','Construisez une page web moderne','Découvrez le HTML et le CSS pour créer votre premier site web responsive. Balises, mise en page, couleurs et bonnes pratiques, expliqués simplement en français.',0.00,0.00,'uploads_demo/category/2.png',1,0,'html-css-premier-site-web',1,1,4.60,1,DATE_SUB(NOW(),INTERVAL 2 DAY),NOW()),
(UUID(),$UID_VAL,1,$IIDSQL,6,1,2,'Marketing digital pour débutants','Attirez vos premiers clients en ligne','Comprenez les fondamentaux du marketing digital : réseaux sociaux, référencement, publicité et email. Une formation pratique en français pour lancer votre présence en ligne.',25.00,40.00,'uploads_demo/category/3.png',1,0,'marketing-digital-debutants',1,1,4.70,1,DATE_SUB(NOW(),INTERVAL 3 DAY),NOW()),
(UUID(),$UID_VAL,1,$IIDSQL,9,1,2,'Comptabilité générale : les bases','Maîtrisez les écritures comptables','Initiez-vous à la comptabilité générale : bilan, compte de résultat, journal et grand livre. Cours clair en français pour entrepreneurs et étudiants.',30.00,45.00,'uploads_demo/category/4.png',1,0,'comptabilite-generale-bases',1,1,4.50,1,DATE_SUB(NOW(),INTERVAL 4 DAY),NOW()),
(UUID(),$UID_VAL,1,$IIDSQL,7,1,2,'Design graphique avec Canva','Créez des visuels professionnels','Apprenez à concevoir des affiches, logos et publications pour les réseaux sociaux avec Canva. Formation en français accessible à tous, sans expérience en design.',0.00,0.00,'uploads_demo/category/2.png',1,0,'design-graphique-canva',1,1,4.90,1,DATE_SUB(NOW(),INTERVAL 5 DAY),NOW()),
(UUID(),$UID_VAL,1,$IIDSQL,3,1,2,'Excel : de débutant à confirmé','Gagnez du temps avec les tableurs','Maîtrisez Excel : formules, tableaux, graphiques et tableaux croisés dynamiques. Une formation progressive en français pour devenir efficace au bureau.',20.00,35.00,'uploads_demo/category/1.png',1,0,'excel-debutant-confirme',1,1,4.70,1,DATE_SUB(NOW(),INTERVAL 6 DAY),NOW());
SQL
  echo "  -> cours insérés: $(MYSQL -N -e "SELECT COUNT(*) FROM courses WHERE status=1;" 2>/dev/null)"
fi

echo
echo "############ C) Patch landing : Populaires = mieux notés ############"
if [ -f "$LAND" ] && grep -q 'id="klasyo-courses-live"' "$LAND"; then
  cp -p "$LAND" "$BKD/index-pop-$STAMP.bak"
  SZ0=$(wc -c < "$LAND")
  PHPX="$(mktemp /tmp/kc_pop_XXXX.php)"
  cat > "$PHPX" <<'PHPEOF'
<?php
$f=$argv[1]; $h=file_get_contents($f); $b=$h; $n1=0;$n2=0;
$h=str_replace('getJSON(API+"/home/courses")','getJSON(API+"/courses-list")',$h,$n1);
$old='var arr=(j&&j.data&&j.data.topCourse)||[];';
$new='var arr=((j&&j.data&&(j.data.courses||j.data.topCourse))||[]).slice().sort(function(a,b){return (Number(b.average_rating||0))-(Number(a.average_rating||0));}).slice(0,8);';
$h=str_replace($old,$new,$h,$n2);
if($n1===1 && $n2===1){ file_put_contents($f,$h); echo "  patch OK (endpoint+tri)\n"; }
else { fwrite(STDERR,"  patch NON appliqué (n1=$n1 n2=$n2) — inchangé\n"); }
PHPEOF
  if php "$PHPX" "$LAND"; then
    SZ1=$(wc -c < "$LAND")
    if [ "$(head -c 15 "$LAND")" = "<!DOCTYPE html>" ] && grep -q 'id="klasyo-courses-live"' "$LAND"; then
      echo "  landing OK ($SZ0 -> $SZ1)"
    else
      echo "  (!) landing invalide -> ROLLBACK"; cp -p "$BKD/index-pop-$STAMP.bak" "$LAND"
    fi
  else
    echo "  (patch sauté)"
  fi
  rm -f "$PHPX"
else
  echo "  (section cours live absente — patch ignoré)"
fi

echo
echo "############ Vérifications live ############"
UA='Mozilla/5.0 klasyo-check'
echo "  API courses-list (nb cours):"
curl -sS -m 20 -H 'Accept: application/json' -A "$UA" "https://klasyo.org/platform/api/courses-list" 2>/dev/null | php -r '$j=json_decode(file_get_contents("php://stdin"),true); $d=$j["data"]??[]; $c=$d["courses"]??[]; echo "   ".count($c)." cours\n"; foreach(array_slice($c,0,6) as $x){ echo "   - ".($x["title"]??"?")." | ".($x["price"]??"")." | note ".($x["average_rating"]??"")."\n"; }' 2>/dev/null || echo "   (lecture API échouée)"
echo "  Landing HTTP:"
curl -sSL -m 20 -o /dev/null -w '   https://klasyo.org/ -> %{http_code}\n' -A "$UA" "https://klasyo.org/" 2>/dev/null
echo
echo "== FIN =="
