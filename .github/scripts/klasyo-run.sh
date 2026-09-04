#!/usr/bin/env bash
# KLASYO — Étape Z1 (ANALYSE, lecture seule) : paiements (méthode "BANK"/manuel), home/branding LMS,
# et exposition des cours (populaires / récents / catégories) pour décider de la fusion sur la landing.
set -uo pipefail
P="$HOME/domains/klasyo.org/public_html/platform"
UA='Mozilla/5.0 klasyo-check'
[ -d "$P" ] || { echo "(!) dossier platform introuvable: $P"; ls "$HOME/domains/klasyo.org/public_html" | head; exit 0; }

echo "############ 1) PAIEMENTS ############"
echo "== Vues liées au checkout / paiement =="
find "$P/resources/views" -iname '*.blade.php' \( -ipath '*payment*' -o -ipath '*checkout*' -o -ipath '*cart*' -o -ipath '*bank*' \) 2>/dev/null | sed "s#$P/##" | head -30
echo
echo "== Occurrences 'bank' / 'offline' / 'manual' dans les vues (fichiers) =="
grep -rilE 'bank|offline|manual|virement' "$P/resources/views" 2>/dev/null | sed "s#$P/##" | head -25
echo
echo "== Contrôleurs de paiement =="
find "$P/app" -iname '*Payment*' -o -iname '*Checkout*' -o -iname '*Gateway*' 2>/dev/null | sed "s#$P/##" | head -25
echo
echo "== Comment les passerelles/paiements sont listés (labels 'Bank', 'Offline') =="
grep -rinE "('|\")[[:space:]]*(bank|offline|manual)[[:space:]]*('|\")|Bank Transfer|Offline Payment|manual_payment|offline_payment|bank_payment" "$P/app" "$P/resources/views" 2>/dev/null | sed "s#$P/##" | head -25
echo
echo "== Table(s) de config paiement en base (via .env + mysql) =="
if [ -f "$P/.env" ]; then
  DB=$(grep -E '^DB_DATABASE=' "$P/.env" | cut -d= -f2- | tr -d '"' )
  DU=$(grep -E '^DB_USERNAME=' "$P/.env" | cut -d= -f2- | tr -d '"' )
  DP=$(grep -E '^DB_PASSWORD=' "$P/.env" | cut -d= -f2- | tr -d '"' )
  echo "  base: $DB"
  mysql -u"$DU" -p"$DP" "$DB" -N -e "SHOW TABLES LIKE '%payment%';" 2>/dev/null | head
  echo "  -- settings paiement (clé/val) --"
  mysql -u"$DU" -p"$DP" "$DB" -N -e "SELECT option_key, LEFT(option_value,60) FROM settings WHERE option_key REGEXP 'bank|offline|manual|payment|gateway' LIMIT 40;" 2>/dev/null | head -40
  echo "  -- table payment_gateways si existe --"
  mysql -u"$DU" -p"$DP" "$DB" -N -e "SELECT id,name,LEFT(COALESCE(title,''),40),status FROM payment_gateways LIMIT 40;" 2>/dev/null | head -40
fi

echo
echo "############ 2) HOME / BRANDING LMS ############"
curl -sSL -m 25 -A "$UA" "https://klasyo.org/platform/" -o /tmp/ph.html 2>/dev/null
echo "  /platform/ octets: $(wc -c < /tmp/ph.html)  title: $(grep -oiE '<title>[^<]*</title>' /tmp/ph.html | head -1)"
echo "== Marqueurs de marque par défaut restants (LMSZAI, 'Learningx', etc.) dans le code =="
grep -rilE 'lmszai|learningx|edumen|academy lms|default logo' "$P/resources/views" 2>/dev/null | sed "s#$P/##" | head -15
echo "== Vue home / index frontend =="
find "$P/resources/views" -maxdepth 3 -iname 'index.blade.php' -o -iname 'home.blade.php' 2>/dev/null | sed "s#$P/##" | head -15
echo "== Sections déjà présentes sur /platform/ (sliders/popular/latest/categories) =="
grep -oiE 'class="[^"]*(slider|swiper|owl|popular|latest|trending|feature|categor|top-course)[^"]*"' /tmp/ph.html | sort | uniq -c | sort -rn | head -15
echo "== Titres de sections visibles sur /platform/ =="
grep -oiE '<h[123][^>]*>[^<]{3,50}</h[123]>' /tmp/ph.html | sed 's/<[^>]*>//g' | head -20

echo
echo "############ 3) EXPOSITION DES COURS (popular/latest/categories) ############"
echo "== Routes/params catalogue (sort=popular/latest ?) =="
grep -rinE "orderBy\((['\"])(views|rating|created_at|enrolled|sale)|'popular'|'latest'|'trending'|withCount\(" "$P/app" 2>/dev/null | sed "s#$P/##" | head -20
echo "== Existe-t-il une API JSON de cours ? (routes/api.php) =="
[ -f "$P/routes/api.php" ] && grep -inE 'course|categor|popular|latest' "$P/routes/api.php" 2>/dev/null | head -20 || echo "  (pas de routes/api.php lisible)"
echo "== Test de tri catalogue en live =="
for q in "https://klasyo.org/platform/courses?sort=popular" "https://klasyo.org/platform/courses?sort=latest" "https://klasyo.org/platform/courses?sort=newest"; do
  C=$(curl -sSL -m 20 -o /dev/null -w '%{http_code}' -A "$UA" "$q" 2>/dev/null); echo "  $q -> HTTP $C"
done
rm -f /tmp/ph.html
echo
echo "== FIN étape Z1 (analyse) =="
