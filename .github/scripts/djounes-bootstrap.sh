#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# djounes-bootstrap.sh — pose la marque DJOUNES sur la boutique et amorce un
# catalogue de démonstration modifiable.
#
# Exécuté À DISTANCE via :
#   ssh ... "UPLOAD=<dir> bash -s" < .github/scripts/djounes-bootstrap.sh
# après avoir déposé les images dans $UPLOAD (png/ et demo/).
#
# Garanties :
#   - sauvegarde COMPLÈTE de la base et des logos avant toute écriture ;
#   - le catalogue de démonstration n'est semé QUE si la boutique est vide,
#     donc rejouable sans jamais dupliquer ni écraser de vrais produits ;
#   - vérification HTTP après coup : si la page d'accueil ne répond plus 200,
#     la base est restaurée automatiquement.
# ---------------------------------------------------------------------------
set -uo pipefail

DOM="${DOMAIN:-djounes.com}"
UPLOAD="${UPLOAD:-$HOME/djounes-brand-upload}"
STAMP=$(date +%Y%m%d%H%M%S)
grp() { echo "::group::$*"; }
egrp() { echo "::endgroup::"; }

D="$HOME/domains/$DOM"; PUB="$D/public_html"
[ -d "$PUB" ] || { echo "::error::docroot introuvable ($PUB)"; exit 1; }
APP="$PUB/core"
[ -f "$APP/artisan" ] || { echo "::error::application Laravel introuvable ($APP)"; exit 1; }
ENVF="$APP/.env"

val() { grep "^$1=" "$ENVF" 2>/dev/null | head -1 | cut -d= -f2- | tr -d '"'; }
DBN=$(val DB_DATABASE); DBU=$(val DB_USERNAME); DBP=$(val DB_PASSWORD); DBH=$(val DB_HOST)
[ -n "$DBN" ] && [ -n "$DBU" ] || { echo "::error::identifiants de base introuvables dans .env"; exit 1; }
CNF=$(mktemp); chmod 600 "$CNF"
printf '[client]\nuser=%s\npassword=%s\nhost=%s\n' "$DBU" "$DBP" "${DBH:-localhost}" > "$CNF"
SAFE=$(printf '%s' "$DBN" | tr -cd 'A-Za-z0-9_')
q() { mysql --defaults-extra-file="$CNF" -N -B "$SAFE" -e "$1" 2>&1; }

# --- 1. Sauvegardes ---------------------------------------------------------
grp "Sauvegardes avant écriture"
BK="$HOME/djounes-backups"; mkdir -p "$BK"
DUMP="$BK/db-$STAMP.sql.gz"
if mysqldump --defaults-extra-file="$CNF" --single-transaction "$SAFE" 2>/dev/null | gzip > "$DUMP"; then
  echo "base sauvegardée : $DUMP ($(du -h "$DUMP" | cut -f1))"
else
  echo "::error::échec de la sauvegarde de la base — on n'écrit rien."
  rm -f "$CNF"; exit 1
fi
LOGODIR="$PUB/assets/images/logo_icon"
[ -d "$LOGODIR" ] && cp -a "$LOGODIR" "$BK/logo_icon-$STAMP" && echo "logos sauvegardés : $BK/logo_icon-$STAMP"
cp -a "$ENVF" "$BK/env-$STAMP" 2>/dev/null && echo ".env sauvegardé"
egrp

restaurer() {
  echo "::warning::restauration de la base depuis $DUMP"
  gunzip -c "$DUMP" | mysql --defaults-extra-file="$CNF" "$SAFE" 2>&1 | head -5
  [ -d "$BK/logo_icon-$STAMP" ] && rm -rf "$LOGODIR" && cp -a "$BK/logo_icon-$STAMP" "$LOGODIR"
  cp -a "$BK/env-$STAMP" "$ENVF" 2>/dev/null
  ( cd "$APP" && php artisan optimize:clear >/dev/null 2>&1 ) || true
}

# --- 2. Logos ---------------------------------------------------------------
grp "Pose du logo"
if [ -d "$UPLOAD/png" ]; then
  mkdir -p "$LOGODIR"
  cp -f "$UPLOAD/png/logo.png"         "$LOGODIR/logo.png"      && echo "logo.png posé"
  cp -f "$UPLOAD/png/logo-dark-bg.png" "$LOGODIR/logo_dark.png" && echo "logo_dark.png posé"
  cp -f "$UPLOAD/png/favicon.png"      "$LOGODIR/favicon.png"   && echo "favicon.png posé"
  chmod 644 "$LOGODIR"/*.png 2>/dev/null
else
  echo "::warning::aucune image envoyée ($UPLOAD/png absent) — logos inchangés"
fi
egrp

# --- 3. Images produits -----------------------------------------------------
PRODDIR="$PUB/assets/images/product"
grp "Images des produits de démonstration"
if [ -d "$UPLOAD/demo" ]; then
  mkdir -p "$PRODDIR" && cp -f "$UPLOAD/demo/"*.jpg "$PRODDIR/" 2>/dev/null
  chmod 644 "$PRODDIR"/*.jpg 2>/dev/null
  echo "$(ls -1 "$PRODDIR"/*.jpg 2>/dev/null | wc -l) images en place dans assets/images/product"
else
  echo "(aucune image de démonstration envoyée)"
fi
egrp

# --- 4. Identité : couleurs, devise, réglages -------------------------------
grp "Réglages de marque"
q "UPDATE general_settings SET
     site_name='DJOUNES',
     base_color='0B3B2E',
     secondary_color='D4AF37',
     cur_text='USD', cur_sym='\$',
     guest_checkout=1,
     cod=0,
     product_review=1, product_review_auto_approval=0;"
q "SELECT CONCAT('site_name=',site_name,'  base=',base_color,'  secondaire=',secondary_color,'  devise=',cur_text,'  cod=',cod) FROM general_settings;"

# APP_NAME et APP_URL (le slash final produit des URLs à double slash)
sed -i 's|^APP_NAME=.*|APP_NAME=DJOUNES|' "$ENVF"
sed -i 's|^APP_URL=\(.*[^/]\)/*$|APP_URL=\1|' "$ENVF"
grep -E '^(APP_NAME|APP_URL)=' "$ENVF"
egrp

# --- 5. Catalogue de démonstration (uniquement si la boutique est vide) -----
NBP=$(q "SELECT COUNT(*) FROM products;" | tail -1)
grp "Catalogue de démonstration"
if [ "${NBP:-0}" != "0" ]; then
  echo "La boutique contient déjà $NBP produit(s) — aucun ajout, rien n'est écrasé."
else
  echo "Boutique vide : amorçage de 3 catégories, 1 marque, 8 produits, 3 méthodes de livraison."

  q "INSERT INTO categories (name, slug, is_featured, feature_in_banner, position, meta_title, created_at, updated_at) VALUES
      ('Scrubs & Apparel','scrubs-apparel',1,1,1,'Scrubs & Apparel | DJOUNES',NOW(),NOW()),
      ('Body Care','body-care',1,1,2,'Body Care | DJOUNES',NOW(),NOW()),
      ('Accessories','accessories',1,0,3,'Accessories | DJOUNES',NOW(),NOW());"
  q "INSERT INTO brands (name, slug, is_featured, meta_title, created_at, updated_at)
      VALUES ('DJOUNES','djounes',1,'DJOUNES',NOW(),NOW());"

  # Médias : le thème reconstruit l'URL à partir de path + file_name.
  for s in nurse-scrub-set compression-socks everyday-underwear performance-headband \
           canvas-tote-bag whipped-body-butter body-oil soap-bar; do
    q "INSERT INTO media (path, file_name, created_at, updated_at)
        VALUES ('assets/images/product','$s.jpg',NOW(),NOW());"
  done

  BRAND=$(q "SELECT id FROM brands WHERE slug='djounes';" | tail -1)
  CAT_A=$(q "SELECT id FROM categories WHERE slug='scrubs-apparel';" | tail -1)
  CAT_B=$(q "SELECT id FROM categories WHERE slug='body-care';" | tail -1)
  CAT_C=$(q "SELECT id FROM categories WHERE slug='accessories';" | tail -1)

  # nom | slug | sku | prix | prix barré | catégorie | résumé
  PRODUITS="Nurse Scrub Set|nurse-scrub-set|DJ-SCR-001|54.00|64.00|$CAT_A|Four-way stretch scrub set with deep pockets, built for a 12-hour shift.
Compression Socks 20-30 mmHg|compression-socks|DJ-SOC-001|19.00|0|$CAT_A|Graduated compression that keeps legs fresh through back-to-back shifts.
Everyday Underwear|everyday-underwear|DJ-UND-001|16.00|0|$CAT_A|Breathable, tagless, no-ride-up. The layer you forget you are wearing.
Performance Headband|performance-headband|DJ-HDB-001|12.00|0|$CAT_A|Non-slip band that holds all shift and washes clean.
Canvas Tote Bag|canvas-tote-bag|DJ-TOT-001|24.00|0|$CAT_C|Heavy canvas tote sized for scrubs, lunch and a water bottle.
Whipped Body Butter 4 oz|whipped-body-butter|DJ-BUT-001|22.00|26.00|$CAT_B|Rich shea butter whipped light. For hands washed twenty times a day.
Body Oil 2 oz|body-oil|DJ-OIL-001|18.00|0|$CAT_B|Fast-absorbing oil for after the shower, after the shift.
Handmade Soap Bar|soap-bar|DJ-SOP-001|9.00|0|$CAT_B|Gentle handmade bar, no harsh detergents."

  echo "$PRODUITS" | while IFS='|' read -r NAME SLUG SKU PRICE COMPARE CAT SUMMARY; do
    [ -n "$SLUG" ] || continue
    MID=$(q "SELECT id FROM media WHERE file_name='$SLUG.jpg' LIMIT 1;" | tail -1)
    SALE="NULL"; [ "$COMPARE" != "0" ] && SALE="$PRICE" && PRICE="$COMPARE"
    q "INSERT INTO products
        (brand_id, name, slug, sku, regular_price, sale_price, main_image_id, summary, description,
         product_type, delivery_type, is_downloadable, track_inventory, show_stock, in_stock,
         alert_quantity, is_published, show_in_products_page, meta_title, meta_description,
         created_at, updated_at)
       VALUES
        ($BRAND,'$NAME','$SLUG','$SKU',$PRICE,$SALE,$MID,'$SUMMARY','<p>$SUMMARY</p>',
         1, 1, 0, 1, 1, 25,
         5, 1, 1,'$NAME | DJOUNES','$SUMMARY',
         NOW(),NOW());"
    PID=$(q "SELECT id FROM products WHERE slug='$SLUG' LIMIT 1;" | tail -1)
    q "INSERT INTO category_product (category_id, product_id) VALUES ($CAT, $PID);"
    q "INSERT INTO media_product (product_id, media_id) VALUES ($PID, $MID);"
    echo "produit : $NAME (#$PID)"
  done

  q "INSERT INTO shipping_methods (name, description, charge, shipping_time, status, created_at, updated_at) VALUES
      ('Standard Shipping','Delivered in 3-5 business days.',5.95,5,1,NOW(),NOW()),
      ('Free Shipping','Free on orders over \$75.',0.00,5,1,NOW(),NOW()),
      ('Express Shipping','Delivered in 1-2 business days.',14.95,2,1,NOW(),NOW());"
  echo "produits en base : $(q "SELECT COUNT(*) FROM products;" | tail -1)"
fi
egrp

# --- 6. Vider les caches ----------------------------------------------------
grp "Vidage des caches"
( cd "$APP" && php artisan optimize:clear 2>&1 | head -6 ) || true
egrp

# --- 7. Vérification, et restauration si le site est cassé ------------------
grp "Vérification"
CODE=$(curl -sS -o /dev/null -m 25 -w '%{http_code}' "https://$DOM/" 2>/dev/null)
echo "https://$DOM/ → $CODE"
if [ "$CODE" != "200" ]; then
  echo "::error::La page d'accueil ne répond plus 200 après les changements."
  restaurer
  echo "État précédent restauré. Rien n'a été conservé."
  rm -f "$CNF"; exit 1
fi
for p in "categories" "products"; do
  echo "https://$DOM/$p → $(curl -sS -o /dev/null -m 25 -w '%{http_code}' "https://$DOM/$p" 2>/dev/null)"
done
echo "Sauvegarde conservée : $DUMP (restauration : gunzip -c <fichier> | mysql <base>)"
egrp

rm -f "$CNF"
echo "Terminé."
