#!/usr/bin/env bash
# KLASYO — (A) ajouter 12 cours démo FR de plus (total 18) rattachés à l'instructeur démo,
#          (B) rendre TOUTES les catégories "featured" pour qu'elles s'affichent sur le home depuis la plateforme.
set -uo pipefail
P="$HOME/domains/klasyo.org/public_html/platform"
DB=$(grep -E '^DB_DATABASE=' "$P/.env" | cut -d= -f2- | tr -d '"')
DU=$(grep -E '^DB_USERNAME=' "$P/.env" | cut -d= -f2- | tr -d '"')
DP=$(grep -E '^DB_PASSWORD=' "$P/.env" | cut -d= -f2- | tr -d '"')
MYSQL(){ mysql -u"$DU" -p"$DP" "$DB" "$@"; }
Q(){ mysql -u"$DU" -p"$DP" "$DB" -N -e "$1" 2>/dev/null; }

U="$(Q "SELECT id FROM users WHERE email='formateur.demo@klasyo.org' LIMIT 1;" | head -1)"
I="$(Q "SELECT id FROM instructors WHERE user_id=$U LIMIT 1;" | head -1)"
echo "== instructeur: user_id=$U instructor_id=$I =="
[ -z "$U" ] || [ -z "$I" ] && { echo "(!) instructeur démo introuvable — abandon"; exit 0; }

echo "== A) Ajout de 12 cours FR (si pas déjà présents) =="
if [ "$(Q "SELECT COUNT(*) FROM courses WHERE slug='wordpress-creer-site-sans-coder';" | head -1)" != "0" ]; then
  echo "   déjà ajoutés (on saute)"
else
MYSQL <<SQL
SET NAMES utf8mb4;
INSERT INTO courses
(uuid,user_id,course_type,instructor_id,category_id,course_language_id,difficulty_level_id,title,subtitle,description,price,old_price,image,is_subscription_enable,private_mode,slug,is_featured,status,average_rating,drip_content,created_at,updated_at)
VALUES
(UUID(),$U,1,$I,1,1,2,'WordPress : créer un site sans coder','Lancez votre site en quelques heures','Apprenez à installer et personnaliser WordPress pour créer un site professionnel sans écrire de code. Thèmes, extensions, pages et menus expliqués simplement en français.',0.00,0.00,'uploads_demo/category/1.png',1,0,'wordpress-creer-site-sans-coder',1,1,4.70,1,DATE_SUB(NOW(),INTERVAL 7 DAY),NOW()),
(UUID(),$U,1,$I,4,1,2,'Anglais professionnel pour débutants','Communiquez avec assurance au travail','Développez votre anglais professionnel : vocabulaire, emails, réunions et présentations. Formation en français conçue pour progresser rapidement au quotidien.',0.00,0.00,'uploads_demo/category/4.png',1,0,'anglais-professionnel-debutants',1,1,4.60,1,DATE_SUB(NOW(),INTERVAL 8 DAY),NOW()),
(UUID(),$U,1,$I,7,1,2,'Photographie : bien débuter','Réussissez vos premières photos','Maîtrisez les bases de la photographie : cadrage, lumière, composition et réglages. Une formation claire en français pour progresser avec tout appareil.',0.00,0.00,'uploads_demo/category/2.png',1,0,'photographie-bien-debuter',1,1,4.80,1,DATE_SUB(NOW(),INTERVAL 9 DAY),NOW()),
(UUID(),$U,1,$I,5,1,2,'Gestion de projet avec la méthode Agile','Livrez vos projets efficacement','Découvrez la gestion de projet Agile et Scrum : sprints, rôles, backlog et cérémonies. Formation pratique en français pour équipes et entrepreneurs.',20.00,0.00,'uploads_demo/category/3.png',1,0,'gestion-projet-agile',1,1,4.50,1,DATE_SUB(NOW(),INTERVAL 10 DAY),NOW()),
(UUID(),$U,1,$I,2,1,2,'Intelligence artificielle pour débutants','Comprenez enfin le sujet du moment','Explorez les fondamentaux de intelligence artificielle : concepts, usages concrets et outils accessibles. Formation en français, sans prérequis technique.',0.00,0.00,'uploads_demo/category/2.png',1,0,'intelligence-artificielle-debutants',1,1,4.90,1,DATE_SUB(NOW(),INTERVAL 11 DAY),NOW()),
(UUID(),$U,1,$I,6,1,2,'Community management et réseaux sociaux','Animez une communauté engagée','Apprenez à gérer les réseaux sociaux : stratégie de contenu, calendrier, engagement et statistiques. Formation en français pour marques et indépendants.',0.00,0.00,'uploads_demo/category/3.png',1,0,'community-management-reseaux-sociaux',1,1,4.70,1,DATE_SUB(NOW(),INTERVAL 12 DAY),NOW()),
(UUID(),$U,1,$I,9,1,2,'QuickBooks : comptabilité pour PME','Gérez vos finances simplement','Prenez en main QuickBooks pour la comptabilité des petites entreprises : factures, dépenses, rapports et TVA. Formation en français orientée pratique.',30.00,0.00,'uploads_demo/category/4.png',1,0,'quickbooks-comptabilite-pme',1,1,4.40,1,DATE_SUB(NOW(),INTERVAL 13 DAY),NOW()),
(UUID(),$U,1,$I,8,1,2,'Nutrition et bien-être au quotidien','Adoptez de meilleures habitudes','Comprenez les bases de la nutrition et du bien-être : alimentation équilibrée, énergie et hygiène de vie. Formation en français accessible à tous.',0.00,0.00,'uploads_demo/category/1.png',1,0,'nutrition-bien-etre-quotidien',1,1,4.60,1,DATE_SUB(NOW(),INTERVAL 14 DAY),NOW()),
(UUID(),$U,1,$I,4,1,2,'Confiance en soi et développement personnel','Révélez votre plein potentiel','Renforcez votre confiance en soi : gestion du stress, motivation et objectifs. Formation en français pour avancer sereinement dans la vie et le travail.',0.00,0.00,'uploads_demo/category/4.png',1,0,'confiance-en-soi-developpement-personnel',1,1,4.80,1,DATE_SUB(NOW(),INTERVAL 15 DAY),NOW()),
(UUID(),$U,1,$I,1,1,2,'SQL : bases de données pour débutants','Interrogez vos données avec aisance','Apprenez le langage SQL : tables, requêtes, jointures et filtres. Formation en français pour débutants qui veulent exploiter les bases de données.',15.00,0.00,'uploads_demo/category/1.png',1,0,'sql-bases-de-donnees-debutants',1,1,4.70,1,DATE_SUB(NOW(),INTERVAL 16 DAY),NOW()),
(UUID(),$U,1,$I,7,1,2,'Illustrator : dessin vectoriel','Créez logos et illustrations','Maîtrisez Adobe Illustrator pour le dessin vectoriel : formes, plumes, couleurs et exports. Formation en français pour créer des visuels nets et pro.',25.00,0.00,'uploads_demo/category/2.png',1,0,'illustrator-dessin-vectoriel',1,1,4.60,1,DATE_SUB(NOW(),INTERVAL 17 DAY),NOW()),
(UUID(),$U,1,$I,3,1,2,'Excel avancé : tableaux croisés dynamiques','Analysez vos données comme un pro','Passez au niveau supérieur sur Excel : tableaux croisés dynamiques, fonctions avancées et tableaux de bord. Formation en français orientée résultats.',20.00,0.00,'uploads_demo/category/1.png',1,0,'excel-avance-tableaux-croises-dynamiques',1,1,4.80,1,DATE_SUB(NOW(),INTERVAL 18 DAY),NOW());
SQL
echo "   -> total cours actifs: $(Q "SELECT COUNT(*) FROM courses WHERE status=1;" | head -1)"
fi

echo
echo "== B) Toutes les catégories -> featured (affichage complet sur le home) =="
COL="$(Q "SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema='$DB' AND table_name='categories' AND COLUMN_NAME IN ('is_feature','is_featured') LIMIT 1;" | head -1)"
echo "   colonne feature détectée: ${COL:-?}"
if [ -n "$COL" ]; then
  MYSQL -e "UPDATE categories SET \`$COL\`='yes' WHERE \`$COL\` IN ('no','0','') OR \`$COL\` IS NULL;" 2>/dev/null \
    || MYSQL -e "UPDATE categories SET \`$COL\`=1 WHERE \`$COL\`!=1;" 2>/dev/null
  echo "   catégories featured maintenant: $(Q "SELECT COUNT(*) FROM categories WHERE \`$COL\` IN ('yes','1');" | head -1) / $(Q "SELECT COUNT(*) FROM categories;" | head -1)"
fi

echo
echo "== Purge + vérif API =="
( cd "$P" && timeout 40 php artisan optimize:clear 2>&1 | tail -1 ) || true
UA='Mozilla/5.0 klasyo-check'
echo "  /api/courses-list (nb cours):"
curl -sS -m 20 -H 'Accept: application/json' -A "$UA" "https://klasyo.org/platform/api/courses-list" 2>/dev/null | php -r '$j=json_decode(file_get_contents("php://stdin"),true); $c=$j["data"]["courses"]??[]; echo "   ".count($c)." (page 1)\n";' 2>/dev/null || true
echo "  total actifs (DB): $(Q "SELECT COUNT(*) FROM courses WHERE status=1;" | head -1)"
echo "  /api/home/category-course (nb catégories affichées sur le home):"
curl -sS -m 20 -H 'Accept: application/json' -A "$UA" "https://klasyo.org/platform/api/home/category-course" 2>/dev/null | php -r '$j=json_decode(file_get_contents("php://stdin"),true); $d=$j["data"]??[]; echo "   ".count($d)."\n"; foreach($d as $x){ echo "   - ".($x["name"]??"?")."\n"; }' 2>/dev/null || true
echo
echo "== FIN =="
