#!/usr/bin/env bash
# KLASYO — MODULE C : rebrand FR + KLASYO de la home LMS (données: table homes + settings). Backup + vérif + purge cache.
set -uo pipefail
P="$HOME/domains/klasyo.org/public_html/platform"
[ -f "$P/.env" ] || { echo "(!) .env introuvable"; exit 0; }
DB=$(grep -E '^DB_DATABASE=' "$P/.env" | cut -d= -f2- | tr -d '"')
DU=$(grep -E '^DB_USERNAME=' "$P/.env" | cut -d= -f2- | tr -d '"')
DP=$(grep -E '^DB_PASSWORD=' "$P/.env" | cut -d= -f2- | tr -d '"')
MYSQL(){ mysql -u"$DU" -p"$DP" "$DB" "$@"; }

STAMP="$(date +%Y%m%d-%H%M%S)"
BKD="$HOME/klasyo-backups"; mkdir -p "$BKD"
echo "== Backup (table homes + settings home) -> $BKD =="
mysqldump -u"$DU" -p"$DP" --no-tablespaces "$DB" homes > "$BKD/homes-$STAMP.sql" 2>/dev/null && echo "  homes-$STAMP.sql OK"
mysqldump -u"$DU" -p"$DP" --no-tablespaces --no-create-info \
  --where="option_key REGEXP 'top_category_title|top_category_subtitle|bundle_course_title|bundle_course_subtitle|home_special_feature_(first|second|third)_(title|subtitle)|product_section_title|product_section_subtitle|upcoming_course_title|upcoming_course_subtitle'" \
  "$DB" settings > "$BKD/settings-home-$STAMP.sql" 2>/dev/null && echo "  settings-home-$STAMP.sql OK"

echo
echo "== Application du rebrand FR/KLASYO =="
MYSQL <<'SQL'
SET NAMES utf8mb4;
-- Bannière héro (table homes, id=1)
UPDATE homes SET
  banner_mini_words_title = '["Apprenez","en","ligne"]',
  banner_first_line_title = 'Votre',
  banner_second_line_title = 'avenir',
  banner_second_line_changeable_words = '["éducatif","professionnel","numérique"]',
  banner_third_line_title = 'commence sur KLASYO.',
  banner_subtitle = 'Formez-vous en ligne, obtenez des certificats reconnus et développez vos compétences — la plateforme éducative pensée pour Haïti et la Caraïbe.',
  banner_first_button_name = 'Parcourir les cours',
  banner_second_button_name = 'Devenir formateur'
WHERE id = 1;

-- Titres de sections (table settings)
UPDATE settings SET option_value='Nos meilleures catégories' WHERE option_key='top_category_title';
UPDATE settings SET option_value='Explorez des milliers de cours en ligne, avec de nouveaux ajouts chaque semaine.' WHERE option_key='top_category_subtitle';
UPDATE settings SET option_value='Packs de formations' WHERE option_key='bundle_course_title';
UPDATE settings SET option_value='Des parcours complets pour aller plus loin, à prix avantageux.' WHERE option_key='bundle_course_subtitle';
UPDATE settings SET option_value='Apprenez avec des experts' WHERE option_key='home_special_feature_first_title';
UPDATE settings SET option_value='Des formateurs qualifiés vous accompagnent à chaque étape de votre apprentissage.' WHERE option_key='home_special_feature_first_subtitle';
UPDATE settings SET option_value='Obtenez un certificat' WHERE option_key='home_special_feature_second_title';
UPDATE settings SET option_value='Validez vos compétences avec des certificats KLASYO reconnus.' WHERE option_key='home_special_feature_second_subtitle';
UPDATE settings SET option_value='Des centaines de cours' WHERE option_key='home_special_feature_third_title';
UPDATE settings SET option_value='Un catalogue riche et varié, accessible partout, à votre rythme.' WHERE option_key='home_special_feature_third_subtitle';
UPDATE settings SET option_value='Nouveautés' WHERE option_key='upcoming_course_title';
UPDATE settings SET option_value='Les prochains cours à ne pas manquer.' WHERE option_key='upcoming_course_subtitle';
UPDATE settings SET option_value='Produits numériques' WHERE option_key='product_section_title';
UPDATE settings SET option_value='Ressources et produits à télécharger.' WHERE option_key='product_section_subtitle';
SQL
echo "  UPDATE exécutés."

echo
echo "== Vérification (valeurs en base) =="
MYSQL -N -e "SET NAMES utf8mb4; SELECT banner_first_line_title, banner_second_line_title, banner_third_line_title FROM homes WHERE id=1;"
MYSQL -N -e "SET NAMES utf8mb4; SELECT option_key, option_value FROM settings WHERE option_key IN ('top_category_title','bundle_course_title','home_special_feature_second_title');"

echo
echo "== Purge caches (settings/config/vues) =="
( cd "$P" && timeout 40 php artisan cache:clear 2>&1 | head -2 ) || echo "  (cache:clear ignoré)"
( cd "$P" && timeout 40 php artisan optimize:clear 2>&1 | head -3 ) || echo "  (optimize:clear ignoré)"

echo
echo "== Vérif live (home doit contenir le texte FR/KLASYO) =="
BODY="$(curl -sSL -m 25 -A 'Mozilla/5.0 klasyo-check' 'https://klasyo.org/platform/' 2>/dev/null)"
echo "  'commence sur KLASYO' : $(printf '%s' "$BODY" | grep -c 'commence sur KLASYO')"
echo "  'meilleures catégories': $(printf '%s' "$BODY" | grep -c 'meilleures catégories')"
echo "  'Our Top Categories' (résiduel EN): $(printf '%s' "$BODY" | grep -c 'Our Top Categories')"
echo
echo "== FIN MODULE C =="
