#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# djounes-ops.sh — inventaire & maintenance SÛRE de djounes.com (DirectAdmin).
#
# djounes.com est sur un hébergement SÉPARÉ (panneau vda8400.is.cc:2222), pas sur
# le VPS de tagtoa.com/govibepay.com. Ce script n'a donc rien à voir avec le
# déploiement TAGTOA : il ne sert qu'à ce compte-là. Voir docs/DJOUNES.md.
#
# Exécuté À DISTANCE, sur le serveur d'hébergement, via :
#   ssh ... "ACTION='...' DOMAIN='...' bash -s" < .github/scripts/djounes-ops.sh
#
# Garanties (mêmes principes que diagnose.yml pour tagtoa.com) :
#   - LECTURE SEULE par défaut (action « inventaire ») ;
#   - ne SUPPRIME jamais un site : la pause renomme public_html <-> public_html.paused ;
#   - ne touche JAMAIS aux données : aucune requête SQL autre que des SELECT/SHOW ;
#   - n'affiche jamais un mot de passe (masqué à la lecture des fichiers de conf).
#
# Piège connu (voir CLAUDE.md §8) : /home n'est PAS listable sur ce type de compte
# → toujours passer par "$HOME/domains/<domaine>", jamais par un glob /home/*.
# ---------------------------------------------------------------------------
set -uo pipefail

DOM="${DOMAIN:-djounes.com}"
ACTION="${ACTION:-inventaire}"

grp() { echo "::group::$*"; }
egrp() { echo "::endgroup::"; }
# Filet de sécurité : masque tout ce qui ressemble à un secret dans les sorties
# de fichiers/logs qu'on affiche (les logs Actions restent lisibles par le repo).
redact() { sed -E "s/((pass(word|wd)?|pwd|secret|token|api[_-]?key)[\"' ]*[:=][\"' ]*)[^\"' ]+/\1***/Ig"; }

# --- 1. Localiser le domaine ------------------------------------------------
D=""
for c in "$HOME/domains/$DOM" "$HOME/domains/www.$DOM" "$HOME/$DOM"; do
  [ -d "$c" ] && { D="$c"; break; }
done

grp "Contexte SSH"
echo "whoami : $(whoami)"
echo "HOME   : $HOME"
echo "hôte   : $(hostname -f 2>/dev/null || hostname 2>/dev/null || echo inconnu)"
echo "domaine demandé : $DOM"
echo "-- domaines présents sur ce compte --"
ls -1 "$HOME/domains" 2>/dev/null || echo "(pas de dossier ~/domains — hébergement non DirectAdmin ?)"
egrp

if [ -z "$D" ]; then
  echo "::warning::$DOM introuvable sous \$HOME/domains — ce compte n'héberge peut-être pas ce domaine (voir la liste ci-dessus)."
  exit 0
fi
echo "Dossier du domaine : $D"

PUB="$D/public_html"
PAUSED="$D/public_html.paused"
[ -d "$PUB" ] || { [ -d "$PAUSED" ] && echo "::warning::Le site est actuellement EN PAUSE (public_html.paused)."; }
DOCROOT="$PUB"; [ -d "$DOCROOT" ] || DOCROOT="$PAUSED"

# --- 2. Arborescence & taille ----------------------------------------------
grp "Arborescence du domaine"
ls -la "$D" 2>&1 | head -40
echo "-- docroot : $DOCROOT --"
ls -la "$DOCROOT" 2>&1 | head -60
egrp

grp "Espace disque"
du -sh "$D" 2>/dev/null || true
df -h "$HOME" 2>/dev/null | head -5 || true
command -v quota >/dev/null && quota -s 2>&1 | head -10 || echo "(quota non disponible)"
egrp

# --- 3. Détection de la stack ----------------------------------------------
# Beaucoup de scripts PHP vendus clé en main (CodeCanyon & co) placent Laravel
# dans un sous-dossier (« core/ », « laravel/ ») avec un index.php de façade à
# la racine. On cherche donc artisan à plusieurs endroits, pas seulement au
# docroot.
APPDIR=""
for c in "$DOCROOT" "$DOCROOT/core" "$DOCROOT/laravel" "$D/laravel" "$DOCROOT/app"; do
  [ -f "$c/artisan" ] && { APPDIR="$c"; break; }
done

STACK="inconnue"
grp "Stack détectée"
if [ -n "$APPDIR" ]; then
  STACK="laravel"
  echo "Laravel détecté : $APPDIR"
  [ "$APPDIR" != "$DOCROOT" ] && echo "→ disposition « script clé en main » : façade publique au docroot, application dans $(basename "$APPDIR")/"
  ( cd "$APPDIR" && php artisan --version 2>&1 | head -2 ) || true
  echo "-- identité du paquet (composer.json) --"
  grep -E '"(name|description|version|type)"' "$APPDIR/composer.json" 2>/dev/null | head -6 || true
  echo "-- dépendances principales --"
  grep -E '"(laravel/framework|nwidart/laravel-modules|stancl/tenancy|livewire/|filament/)' "$APPDIR/composer.json" 2>/dev/null | head -8 || true
  echo "-- contenu de $(basename "$APPDIR")/ --"
  ls -1 "$APPDIR" 2>/dev/null | head -25
  echo "-- modules / sections de l'application --"
  for d in "$APPDIR/Modules" "$APPDIR/app/Http/Controllers" "$APPDIR/app/Http/Controllers/Admin" "$APPDIR/app/Http/Controllers/Gateway"; do
    [ -d "$d" ] && { echo "[$(basename "$d")]"; ls -1 "$d" 2>/dev/null | head -40; }
  done
  echo "-- thème / vues disponibles --"
  ls -1 "$APPDIR/resources/views" 2>/dev/null | head -15 || true
  ls -1 "$APPDIR/resources/views/templates" 2>/dev/null | head -10 || true
  echo "-- façade publique (index.php à la racine) --"
  head -25 "$DOCROOT/index.php" 2>/dev/null | redact || true
elif [ -f "$DOCROOT/wp-config.php" ]; then
  STACK="wordpress"
  echo "WordPress détecté (wp-config.php présent)."
  V=$(grep -E "^\\\$wp_version *=" "$DOCROOT/wp-includes/version.php" 2>/dev/null | head -1 | cut -d"'" -f2)
  echo "version WordPress : ${V:-inconnue}"
  echo "-- thèmes --";  ls -1 "$DOCROOT/wp-content/themes"  2>/dev/null | head -20 || true
  echo "-- extensions --"; ls -1 "$DOCROOT/wp-content/plugins" 2>/dev/null | head -40 || true
  echo "-- taille des uploads --"; du -sh "$DOCROOT/wp-content/uploads" 2>/dev/null || true
elif ls "$DOCROOT"/index.htm* >/dev/null 2>&1 && ! ls "$DOCROOT"/*.php >/dev/null 2>&1; then
  STACK="statique"
  echo "Site statique (HTML) — aucun PHP à la racine."
  head -30 "$DOCROOT/index.html" 2>/dev/null | redact || true
elif [ -f "$DOCROOT/index.php" ]; then
  STACK="php"
  echo "PHP générique (index.php). Signatures connues :"
  grep -rlsE "Joomla|Drupal|PrestaShop|OpenCart|Magento" "$DOCROOT" --include="*.php" 2>/dev/null | head -5 || echo "(aucune)"
else
  echo "Aucun index détecté — docroot vide ou site non déployé."
fi
echo "STACK=$STACK"
egrp

# --- 4. Configuration (valeurs sensibles masquées) --------------------------
DBN=""; DBU=""; DBP=""; DBH="localhost"
grp "Configuration base de données (mots de passe masqués)"
if [ "$STACK" = "wordpress" ] && [ -f "$DOCROOT/wp-config.php" ]; then
  val() { grep -E "define\( *'$1'" "$DOCROOT/wp-config.php" 2>/dev/null | head -1 | cut -d, -f2- | cut -d"'" -f2; }
  DBN=$(val DB_NAME); DBU=$(val DB_USER); DBP=$(val DB_PASSWORD); DBH=$(val DB_HOST)
  echo "DB_NAME = ${DBN:-[absent]}"
  echo "DB_USER = ${DBU:-[absent]}"
  echo "DB_HOST = ${DBH:-[absent]}"
  [ -n "$DBP" ] && echo "DB_PASSWORD = [présent]" || echo "DB_PASSWORD = [VIDE ⚠]"
  echo "-- URLs WordPress --"
  grep -E "WP_HOME|WP_SITEURL" "$DOCROOT/wp-config.php" 2>/dev/null || echo "(définies en base, pas dans wp-config)"
elif [ "$STACK" = "laravel" ]; then
  ENVF="$APPDIR/.env"
  if [ -f "$ENVF" ]; then
    for k in APP_NAME APP_ENV APP_DEBUG APP_KEY APP_URL DB_CONNECTION DB_HOST DB_DATABASE DB_USERNAME DB_PASSWORD; do
      v=$(grep "^$k=" "$ENVF" 2>/dev/null | head -1 | cut -d= -f2-)
      case "$k" in
        APP_KEY|DB_PASSWORD) [ -n "$v" ] && echo "$k = [présent]" || echo "$k = [absent/VIDE ⚠]";;
        *) echo "$k = ${v:-[absent]}";;
      esac
    done
    DBN=$(grep "^DB_DATABASE=" "$ENVF" | head -1 | cut -d= -f2-)
    DBU=$(grep "^DB_USERNAME=" "$ENVF" | head -1 | cut -d= -f2-)
    DBP=$(grep "^DB_PASSWORD=" "$ENVF" | head -1 | cut -d= -f2-)
    DBH=$(grep "^DB_HOST=" "$ENVF" | head -1 | cut -d= -f2-)
  else
    echo "⚠ .env absent à $ENVF"
  fi
else
  echo "(pas de configuration DB connue pour cette stack)"
fi
egrp

# --- 5. Bases de données (LECTURE SEULE) ------------------------------------
grp "Bases de données (lecture seule)"
if command -v mysql >/dev/null 2>&1 && [ -n "$DBU" ] && [ -n "$DBP" ]; then
  CNF=$(mktemp); chmod 600 "$CNF"
  printf '[client]\nuser=%s\npassword=%s\nhost=%s\n' "$DBU" "$DBP" "${DBH:-localhost}" > "$CNF"
  echo "-- bases visibles par cet utilisateur --"
  mysql --defaults-extra-file="$CNF" -N -e "SHOW DATABASES;" 2>&1 | head -30
  SAFE_DBN=$(printf '%s' "$DBN" | tr -cd 'A-Za-z0-9_')
  if [ -n "$SAFE_DBN" ]; then
    echo "-- $SAFE_DBN : nombre de tables / taille --"
    mysql --defaults-extra-file="$CNF" -N -e \
      "SELECT COUNT(*), IFNULL(ROUND(SUM(data_length+index_length)/1048576,1),0) FROM information_schema.tables WHERE table_schema='$SAFE_DBN';" 2>&1 | head -3
    echo "(tables, Mo)"
    # Le schéma dit ce que l'application sait faire, et les compteurs disent
    # ce qui est déjà rempli — indispensable avant de planifier une refonte.
    echo "-- tables et nombre de lignes (approx. InnoDB) --"
    mysql --defaults-extra-file="$CNF" -N -e \
      "SELECT table_name, table_rows FROM information_schema.tables WHERE table_schema='$SAFE_DBN' ORDER BY table_name;" 2>&1 | head -120
  fi
  rm -f "$CNF"
else
  echo "(client mysql absent ou identifiants introuvables — voir le panneau DirectAdmin > MySQL Management)"
fi
egrp

# --- 6. PHP, serveur web, HTTP, TLS ----------------------------------------
grp "PHP & serveur"
php -v 2>&1 | head -2 || echo "(php CLI absent)"
echo "-- handler PHP déclaré dans .htaccess (version par domaine) --"
grep -riE "AddHandler|php[0-9]|SetHandler" "$DOCROOT/.htaccess" 2>/dev/null | head -10 || echo "(aucun)"
egrp

# Un code 200 ne dit pas si le catalogue s'affiche : on compte les produits
# réellement rendus dans la page, et on les compare à la base.
grp "Le catalogue s'affiche-t-il ?"
if command -v mysql >/dev/null 2>&1 && [ -n "$DBU" ] && [ -n "$DBP" ]; then
  CNF4=$(mktemp); chmod 600 "$CNF4"
  printf '[client]\nuser=%s\npassword=%s\nhost=%s\n' "$DBU" "$DBP" "${DBH:-localhost}" > "$CNF4"
  SAFE4=$(printf '%s' "$DBN" | tr -cd 'A-Za-z0-9_')
  SLUGS=$(mysql --defaults-extra-file="$CNF4" -N -e \
    "SELECT slug FROM \`$SAFE4\`.products WHERE is_published=1 ORDER BY id;" 2>/dev/null)
  NB=$(printf '%s\n' "$SLUGS" | grep -c . )
  echo "produits publiés en base : $NB"
  for page in "products" ""; do
    HTML=$(curl -sS -m 25 "https://$DOM/$page" 2>/dev/null)
    # La taille est affichée : sans elle, une réponse vide (serveur qui tousse)
    # se lit comme « aucun produit visible » et envoie chercher un problème de
    # catalogue qui n'existe pas.
    VUS=0
    for s in $SLUGS; do
      case "$HTML" in *"$s"*) VUS=$((VUS+1));; esac
    done
    echo "  (page de $(printf '%s' "$HTML" | wc -c) octets)"
    echo "  https://$DOM/$page → $VUS produit(s) visible(s) dans la page"
  done
  echo "-- un produit pris au hasard --"
  UN=$(printf '%s\n' "$SLUGS" | head -1)
  if [ -n "$UN" ]; then
    for forme in "product/$UN" "products/$UN" "$UN"; do
      echo "  /$forme → $(curl -sS -o /dev/null -m 20 -w '%{http_code}' "https://$DOM/$forme" 2>/dev/null)"
    done
  fi
  rm -f "$CNF4"
else
  echo "(base inaccessible)"
fi
egrp

grp "Réponse HTTP & certificat"
echo "-- depuis le serveur lui-même --"
curl -sS -o /dev/null -m 20 -w "https://$DOM/ → %{http_code} (%{time_total}s)\n" "https://$DOM/" 2>&1 || true
echo "-- certificat TLS --"
echo | timeout 20 openssl s_client -servername "$DOM" -connect "$DOM:443" 2>/dev/null \
  | openssl x509 -noout -subject -issuer -dates 2>&1 || echo "(lecture du certificat impossible)"
egrp

# --- 7. Journaux d'erreurs --------------------------------------------------
grp "Journaux d'erreurs (30 dernières lignes, secrets masqués)"
for f in "$D/logs/$DOM.error.log" "$D/logs/error.log" "$DOCROOT/error_log" "$DOCROOT/wp-content/debug.log"; do
  if [ -f "$f" ]; then
    echo "== $f =="
    tail -30 "$f" 2>/dev/null | redact
  fi
done
find "$D/logs" -maxdepth 1 -type f -name "*error*" 2>/dev/null | head -5 || true
egrp

# Restes d'installation : un installeur laissé en place permet souvent de
# reconfigurer le site (et sa base) sans être connecté.
grp "Contrôles de sécurité (lecture seule)"
for leftover in install installer setup update; do
  [ -d "$DOCROOT/$leftover" ] && echo "⚠ dossier « $leftover/ » toujours présent dans le docroot ($(ls -1 "$DOCROOT/$leftover" 2>/dev/null | wc -l) fichiers)"
done
if [ -n "$APPDIR" ] && [ -f "$APPDIR/.env" ]; then
  case "$APPDIR" in
    "$DOCROOT"|"$DOCROOT"/*) echo "⚠ le .env est SOUS le docroot ($APPDIR/.env) — vérifier qu'une règle .htaccess en interdit l'accès web";;
    *) echo "le .env est hors du docroot (bon)";;
  esac
  echo "-- règles .htaccess protégeant .env / dossiers sensibles --"
  grep -iE "\.env|Files|deny|require all denied" "$DOCROOT/.htaccess" 2>/dev/null | head -10 || echo "(aucune règle de ce type)"
fi
echo "-- écriture publique éventuelle (droits 777) --"
find "$DOCROOT" -maxdepth 2 -type d -perm -o+w 2>/dev/null | head -10 || true
egrp

grp "Tâches cron liées à $DOM"
crontab -l 2>/dev/null | grep -i "${DOM%%.*}" || echo "(aucune)"
egrp

# --- 8. Actions (uniquement si demandées explicitement) ---------------------
case "$ACTION" in
  vider_cache)
    grp "Vidage des caches"
    if [ "$STACK" = "laravel" ] && [ -n "$APPDIR" ]; then
      ( cd "$APPDIR" && php artisan optimize:clear 2>&1 ) || true
      chmod -R ug+rwX "$APPDIR/storage" "$APPDIR/bootstrap/cache" 2>/dev/null || true
    elif [ "$STACK" = "wordpress" ]; then
      for c in "$DOCROOT/wp-content/cache" "$DOCROOT/wp-content/uploads/cache"; do
        if [ -d "$c" ]; then
          echo "vidage de $c ($(du -sh "$c" 2>/dev/null | cut -f1))"
          find "$c" -mindepth 1 -maxdepth 1 -exec rm -rf {} + 2>/dev/null || true
        fi
      done
      echo "(les caches WordPress se régénèrent seuls ; aucun contenu ni réglage touché)"
    else
      echo "(aucun cache connu pour la stack « $STACK » — rien fait)"
    fi
    egrp
    ;;
  scan_banner)
    # Les huit diapositives du carrousel vivent dans frontends (banner.element).
    # On lit leurs clés JSON et le chemin d'images attendu avant d'y toucher.
    grp "Structure des diapositives de bannière"
    if command -v mysql >/dev/null 2>&1 && [ -n "$DBU" ] && [ -n "$DBP" ]; then
      CNF7=$(mktemp); chmod 600 "$CNF7"
      printf '[client]\nuser=%s\npassword=%s\nhost=%s\n' "$DBU" "$DBP" "${DBH:-localhost}" > "$CNF7"
      S7=$(printf '%s' "$DBN" | tr -cd 'A-Za-z0-9_')
      echo "-- banner.content --"
      mysql --defaults-extra-file="$CNF7" -N -e \
        "SELECT data_values FROM \`$S7\`.frontends WHERE data_keys='banner.content';" 2>&1 | head -3
      echo "-- banner.element : id + JSON --"
      mysql --defaults-extra-file="$CNF7" -N -e \
        "SELECT CONCAT(id,' | ',data_values) FROM \`$S7\`.frontends
         WHERE data_keys='banner.element' ORDER BY id;" 2>&1 | head -12
      rm -f "$CNF7"
    fi
    egrp

    grp "Où la bannière est rendue"
    grep -rn "banner.element\|banner.content" "$APPDIR/resources/views/templates/basic" 2>/dev/null \
      | sed "s|$APPDIR/||" | head -12
    echo "-- section banner du thème --"
    for f in "$APPDIR/resources/views/templates/basic/sections/banner.blade.php" \
             "$APPDIR/resources/views/templates/basic/partials/headers/"*.blade.php; do
      [ -f "$f" ] && grep -ln "banner" "$f" | sed "s|$APPDIR/||"
    done
    egrp

    grp "Chemin et taille d'image attendus"
    grep -n -A3 "'banner'" "$APPDIR/app/Http/Helpers/helpers.php" 2>/dev/null | head -20
    echo "-- fichiers présents --"
    for d in "$DOCROOT/assets/images/frontend/banner" "$DOCROOT/assets/images/frontend"; do
      [ -d "$d" ] && { echo "[${d#$DOCROOT/}]"; ls -1 "$d" 2>/dev/null | head -15; }
    done
    egrp
    ;;
  scan_collection)
    # Les emplacements collection_1..7 sont déjà actifs sur l'accueil. Reste à
    # savoir sous quelle forme le thème attend product_ids et unique_key.
    grp "Modèle ProductCollection"
    sed -n '1,60p' "$APPDIR/app/Models/ProductCollection.php" 2>/dev/null
    egrp
    grp "Comment l'admin enregistre une collection"
    sed -n '/function \(store\|save\|update\)/,/^    }/p' \
      "$APPDIR/app/Http/Controllers/Admin/ProductCollectionController.php" 2>/dev/null | head -45
    egrp
    grp "Section qui rend une collection"
    F=$(ls "$APPDIR/resources/views/templates/basic/sections/"collection*.blade.php 2>/dev/null | head -1)
    [ -n "$F" ] && { echo "[$(basename "$F")]"; head -40 "$F"; } \
      || { echo "(pas de section collection*.blade.php)"; grep -rln "collection" "$APPDIR/resources/views/templates/basic/sections" 2>/dev/null | head; }
    echo "-- déclaration dans sections.json --"
    grep -A6 -i "collection" "$APPDIR/resources/views/templates/basic/sections.json" 2>/dev/null | head -25
    egrp
    grp "Fiche produit : gabarit à styliser"
    wc -l "$APPDIR/resources/views/templates/basic/product_details.blade.php" 2>/dev/null
    grep -nE "attribute|variant|size|color|add-to-cart|addToCart|product-thumb|main-image" \
      "$APPDIR/resources/views/templates/basic/product_details.blade.php" 2>/dev/null | head -25
    egrp
    ;;
  scan_fiche)
    # Avant de redessiner la fiche produit, on lit exactement ce qu'il y a :
    # le gabarit entier, ce que le contrôleur lui passe, où le thème charge son
    # CSS, et comment les variantes sont choisies. Rien n'est deviné.
    TPL="$APPDIR/resources/views/templates/basic"
    grp "Fiche produit : en-tête du gabarit"
    F="$TPL/product_details.blade.php"
    if [ -f "$F" ]; then
      echo "== $(wc -l < "$F") lignes : ${F#$APPDIR/} — 40 premières =="
      sed -n '1,40p' "$F" | cat -n
    else
      echo "(product_details.blade.php absent)"
      ls -1 "$TPL"/*.blade.php 2>/dev/null | xargs -n1 basename 2>/dev/null | head -30
    fi
    egrp

    grp "Ce que le contrôleur passe à la vue"
    for C in "$APPDIR/app/Http/Controllers/SiteController.php" \
             "$APPDIR/app/Http/Controllers/ProductController.php"; do
      [ -f "$C" ] || continue
      echo "== ${C#$APPDIR/} =="
      sed -n '/function productDetails\|function product(/,/^    }/p' "$C" | head -60
    done
    echo "-- route de la fiche --"
    grep -n "product_details\|productDetails\|product/{" "$APPDIR/routes/web.php" 2>/dev/null | head -8
    egrp

    grp "Où le thème charge son CSS et son JS"
    for L in "$TPL/layouts/frontend.blade.php" "$TPL/layouts/app.blade.php" \
             "$TPL/layouts/master.blade.php"; do
      [ -f "$L" ] || continue
      echo "== ${L#$APPDIR/} ($(wc -l < "$L") lignes) =="
      grep -nE "asset\(|@stack|@yield|@push|<link|<script" "$L" | head -40
    done
    echo "-- feuilles de style présentes --"
    for d in "$DOCROOT/assets/templates/basic/css" "$DOCROOT/assets/global/css"; do
      [ -d "$d" ] && { echo "[${d#$DOCROOT/}]"; ls -1sh "$d" 2>/dev/null | head -15; }
    done
    egrp

    grp "Variantes : ce que le thème sait déjà afficher"
    grep -rn "attribute_values\|variant" "$TPL" 2>/dev/null | sed "s|$APPDIR/||" | head -25
    echo "-- ajout au panier (route + JS) --"
    grep -n "add-to-cart\|addToCart\|cart.store\|cart/add" "$APPDIR/routes/web.php" 2>/dev/null | head -8
    egrp

    # En dernier, et intégral : c'est le bloc d'achat lui-même. « Template:: »
    # est l'alias de namespace du thème actif, pas un dossier — il faut le
    # résoudre vers templates/basic, sinon on cherche un fichier inexistant.
    grp "Partiels inclus par la fiche (bloc d'achat — intégral)"
    if [ -f "$F" ]; then
      grep -oE "@(include|includeIf)\('[^']+'" "$F" | sed "s/.*'\(.*\)'/\1/" | sort -u | while read -r inc; do
        REL=$(printf '%s' "$inc" | sed 's/^Template:://' | tr '.' '/')
        for P in "$TPL/$REL.blade.php" "$APPDIR/resources/views/$REL.blade.php"; do
          [ -f "$P" ] || continue
          echo "===== $inc → ${P#$APPDIR/} ($(wc -l < "$P") lignes) ====="
          cat -n "$P"
          continue 2
        done
        echo "===== $inc : introuvable (essayé $TPL/$REL.blade.php) ====="
      done
    fi
    egrp
    ;;
  scan_accueil)
    # Où accrocher une vitrine de produits sur l'accueil : ce que le contrôleur
    # passe à la vue, comment les sections sont assemblées, et si le thème sait
    # déjà afficher une collection.
    grp "Assemblage de la page d'accueil"
    echo "== SiteController::index() =="
    sed -n '/function index()/,/^    }/p' "$APPDIR/app/Http/Controllers/SiteController.php" 2>/dev/null | head -25
    echo "== sections du thème =="
    for d in "$APPDIR/resources/views/templates/basic/sections" \
             "$APPDIR/resources/views/templates/basic/partials"; do
      [ -d "$d" ] && { echo "[$(basename "$d")]"; ls -1 "$d" 2>/dev/null | head -30; }
    done
    echo "== gabarit de l'accueil =="
    ls -1 "$APPDIR/resources/views/templates/basic/"*.blade.php 2>/dev/null | sed "s|.*/||" | head -20
    egrp

    grp "Le thème sait-il afficher une collection ?"
    grep -rln "product_collection\|ProductCollection\|productCollection" \
      "$APPDIR/app" "$APPDIR/resources/views/templates" 2>/dev/null | sed "s|$APPDIR/||" | head -12 || echo "(aucun)"
    echo "-- où la vitrine « top selling » est rendue --"
    grep -rn "top_selling" "$APPDIR/resources/views/templates/basic" 2>/dev/null | sed "s|$APPDIR/||" | head -8
    echo "-- comment un produit est affiché (composant de carte) --"
    grep -rln "add.to.cart\|addToCart" "$APPDIR/resources/views/templates/basic" 2>/dev/null | sed "s|$APPDIR/||" | head -8
    egrp

    grp "Sections activées sur la page d'accueil"
    if command -v mysql >/dev/null 2>&1 && [ -n "$DBU" ] && [ -n "$DBP" ]; then
      CNF6=$(mktemp); chmod 600 "$CNF6"
      printf '[client]\nuser=%s\npassword=%s\nhost=%s\n' "$DBU" "$DBP" "${DBH:-localhost}" > "$CNF6"
      S6=$(printf '%s' "$DBN" | tr -cd 'A-Za-z0-9_')
      mysql --defaults-extra-file="$CNF6" -t -e \
        "SELECT id, name, slug, tempname FROM \`$S6\`.pages WHERE slug='/';" 2>&1 | head -8
      echo "-- liste des sections de l'accueil (colonne secs) --"
      mysql --defaults-extra-file="$CNF6" -N -e \
        "SELECT secs FROM \`$S6\`.pages WHERE slug='/';" 2>&1 | head -3
      rm -f "$CNF6"
    fi
    egrp
    ;;
  diag_catalogue)
    # Les fiches produit répondent 200 mais les listes semblent vides : soit la
    # requête de liste filtre sur une colonne qu'on n'a pas remplie, soit la
    # grille est chargée en JavaScript et curl ne peut pas la voir.
    grp "État des produits en base"
    if command -v mysql >/dev/null 2>&1 && [ -n "$DBU" ] && [ -n "$DBP" ]; then
      CNF5=$(mktemp); chmod 600 "$CNF5"
      printf '[client]\nuser=%s\npassword=%s\nhost=%s\n' "$DBU" "$DBP" "${DBH:-localhost}" > "$CNF5"
      S5=$(printf '%s' "$DBN" | tr -cd 'A-Za-z0-9_')
      mysql --defaults-extra-file="$CNF5" -t -e \
        "SELECT id, slug, is_published, show_in_products_page, product_type, product_type_id,
                in_stock, main_image_id, brand_id
         FROM \`$S5\`.products ORDER BY id;" 2>&1 | head -15
      echo "-- rattachement aux catégories --"
      mysql --defaults-extra-file="$CNF5" -t -e \
        "SELECT cp.category_id, c.slug, COUNT(*) AS produits
         FROM \`$S5\`.category_product cp
         LEFT JOIN \`$S5\`.categories c ON c.id = cp.category_id
         GROUP BY cp.category_id, c.slug;" 2>&1 | head -10
      echo "-- médias liés --"
      mysql --defaults-extra-file="$CNF5" -N -e \
        "SELECT CONCAT('media=', (SELECT COUNT(*) FROM \`$S5\`.media),
                       ' liens media_product=', (SELECT COUNT(*) FROM \`$S5\`.media_product));" 2>&1
      rm -f "$CNF5"
    fi
    egrp

    grp "Requête de liste dans le contrôleur"
    for f in "$APPDIR/app/Http/Controllers/ProductController.php" \
             "$APPDIR/app/Http/Controllers/SiteController.php"; do
      [ -f "$f" ] || continue
      echo "== $(basename "$f") =="
      grep -nE "function (products|index|home)|Product::|->where|whereHas|active\(\)|published" "$f" 2>/dev/null | head -24
    done
    egrp

    grp "Ce que la page renvoie réellement"
    HTML=$(curl -sS -m 25 "https://$DOM/products" 2>/dev/null)
    echo "taille de la page : $(printf '%s' "$HTML" | wc -c) octets"
    echo "liens vers une fiche produit : $(printf '%s' "$HTML" | grep -oc 'product/' || echo 0)"
    echo "-- marqueurs de liste vide --"
    printf '%s' "$HTML" | grep -oiE "no data found|not found|aucun|empty|data-not-found" | sort | uniq -c | head -5 || echo "(aucun)"
    echo "-- indices de chargement en JavaScript --"
    printf '%s' "$HTML" | grep -oiE "ajax|load-?more|infinite|fetch\(" | sort | uniq -c | head -6 || echo "(aucun)"
    egrp
    ;;
  scan_admin)
    # Le panneau d'administration a sa propre charte (bleu) et affiche le nom
    # et la version du script en pied de barre latérale. On localise les deux
    # avant d'y toucher.
    grp "Nom et version du script dans l'administration"
    grep -rn --include="*.php" --include="*.blade.php" \
      --exclude-dir=vendor --exclude-dir=node_modules \
      -iE "visermart|systemDetails|version" "$APPDIR/resources/views/admin" 2>/dev/null \
      | grep -iE "visermart|version" | sed "s|$APPDIR/||" | head -20
    echo "-- d'où vient le nom et la version --"
    grep -rn --include="*.php" -E "\\\$system\s*\[|'version'|systemDetails" \
      "$APPDIR/app/Http/Helpers/helpers.php" 2>/dev/null | head -10
    echo "-- pied de la barre latérale --"
    for f in "$APPDIR/resources/views/admin/partials/sidenav.blade.php" \
             "$APPDIR/resources/views/admin/partials/sidebar.blade.php" \
             "$APPDIR/resources/views/admin/layouts/master.blade.php"; do
      [ -f "$f" ] && { echo "[$(basename "$f")]"; grep -niE "version|visermart|sidebar__footer|copyright" "$f" | head -8; }
    done
    egrp

    grp "Charte du panneau d'administration"
    ADM="$DOCROOT/assets/admin"
    [ -d "$ADM" ] && ls -1 "$ADM/css" 2>/dev/null | head -10
    echo "-- couleurs les plus fréquentes dans le CSS admin --"
    grep -rhoE "#[0-9a-fA-F]{6}" "$ADM/css" 2>/dev/null | tr 'A-F' 'a-f' | sort | uniq -c | sort -rn | head -12
    echo "-- variables CSS (--*color*) --"
    grep -rhoE "\-\-[a-z-]*color[a-z-]*: *[^;]+" "$ADM/css" 2>/dev/null | sort -u | head -12
    echo "-- feuille de style chargée par l'admin --"
    grep -rn "assets/admin/css" "$APPDIR/resources/views/admin/layouts/master.blade.php" 2>/dev/null | head -8
    egrp
    ;;
  schema_catalogue)
    # Avant d'insérer quoi que ce soit : connaître les colonnes exactes et les
    # emplacements d'images attendus. Une insertion à l'aveugle produit des
    # lignes incomplètes qui font planter la boutique côté client.
    grp "Emplacements d'images du thème"
    for d in "$DOCROOT/assets/images" "$DOCROOT/assets/templates"; do
      [ -d "$d" ] && { echo "[$d]"; find "$d" -maxdepth 2 -type d 2>/dev/null | sed "s|$DOCROOT/||" | head -25; }
    done
    echo "-- fichiers logo / favicon existants --"
    find "$DOCROOT/assets" -maxdepth 4 \( -iname "logo*" -o -iname "favicon*" \) 2>/dev/null \
      | sed "s|$DOCROOT/||" | head -20
    echo "-- dossier des images produits (écriture nécessaire) --"
    ls -ld "$DOCROOT/assets/images" "$DOCROOT/assets/images/product" 2>/dev/null || true
    egrp

    grp "Schéma des tables du catalogue"
    if command -v mysql >/dev/null 2>&1 && [ -n "$DBU" ] && [ -n "$DBP" ]; then
      CNF3=$(mktemp); chmod 600 "$CNF3"
      printf '[client]\nuser=%s\npassword=%s\nhost=%s\n' "$DBU" "$DBP" "${DBH:-localhost}" > "$CNF3"
      SAFE=$(printf '%s' "$DBN" | tr -cd 'A-Za-z0-9_')
      for t in categories brands products product_variants attributes attribute_values \
               attribute_product attribute_value_product media media_product \
               product_types product_collections shipping_methods; do
        echo "== $t =="
        mysql --defaults-extra-file="$CNF3" -N -e "SHOW COLUMNS FROM \`$SAFE\`.$t;" 2>&1 \
          | awk '{printf "%s(%s%s) ", $1, $2, ($3=="NO"?",requis":"")}' | fold -s -w 150
        echo ""
      done
      echo "== réglages actuels =="
      mysql --defaults-extra-file="$CNF3" -e \
        "SELECT site_name, base_color, secondary_color, cur_text, cur_sym, active_template, guest_checkout, cod FROM \`$SAFE\`.general_settings\\G" 2>&1 | head -15
      rm -f "$CNF3"
    else
      echo "(base inaccessible)"
    fi
    egrp
    ;;
  scan_marque)
    # Avant de renommer quoi que ce soit : savoir OÙ la marque du script
    # apparaît. Un remplacement global casserait des chemins d'actifs, des
    # noms de classes ou des espaces de noms qui contiennent le même mot.
    grp "Où apparaît la marque du script (lecture seule)"
    BRAND="${BRAND:-visermart}"
    echo "recherche de « $BRAND » hors vendor/ et node_modules/"
    echo "-- fichiers concernés, avec le nombre d'occurrences --"
    grep -ric --include="*.php" --include="*.json" --include="*.js" --include="*.css" \
      --include="*.txt" --include="*.xml" --include="*.md" \
      --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=.git \
      "$BRAND" "$DOCROOT" 2>/dev/null | grep -v ':0$' | sed "s|$DOCROOT/||" | head -60
    echo "-- formes exactes rencontrées (casse comprise) --"
    grep -rohi --include="*.php" --include="*.json" --include="*.js" \
      --exclude-dir=vendor --exclude-dir=node_modules \
      -E "viser[a-z]*" "$DOCROOT" 2>/dev/null | sort | uniq -c | sort -rn | head -15
    echo "-- contextes typiques (30 premières lignes) --"
    grep -rn --include="*.php" --exclude-dir=vendor --exclude-dir=node_modules \
      -i "$BRAND" "$DOCROOT" 2>/dev/null | sed "s|$DOCROOT/||" | head -30 | redact
    egrp

    grp "Réglages du site (lecture seule)"
    if command -v mysql >/dev/null 2>&1 && [ -n "$DBU" ] && [ -n "$DBP" ]; then
      CNF2=$(mktemp); chmod 600 "$CNF2"
      printf '[client]\nuser=%s\npassword=%s\nhost=%s\n' "$DBU" "$DBP" "${DBH:-localhost}" > "$CNF2"
      SAFE=$(printf '%s' "$DBN" | tr -cd 'A-Za-z0-9_')
      echo "-- colonnes de general_settings --"
      mysql --defaults-extra-file="$CNF2" -N -e "SHOW COLUMNS FROM \`$SAFE\`.general_settings;" 2>&1 | awk '{print $1}' | tr '\n' ' '
      echo ""
      echo "-- sections de contenu éditables (frontends) --"
      mysql --defaults-extra-file="$CNF2" -N -e "SELECT data_keys, COUNT(*) FROM \`$SAFE\`.frontends GROUP BY data_keys;" 2>&1 | head -60
      echo "-- pages statiques --"
      mysql --defaults-extra-file="$CNF2" -N -e "SELECT name, slug FROM \`$SAFE\`.pages;" 2>&1 | head -20
      echo "-- passerelles de paiement disponibles --"
      mysql --defaults-extra-file="$CNF2" -N -e "SELECT name, status FROM \`$SAFE\`.gateways ORDER BY name;" 2>&1 | head -40
      rm -f "$CNF2"
    else
      echo "(base inaccessible)"
    fi
    egrp
    ;;
  securiser_install)
    # Un installeur laissé dans le docroot permet souvent de reconfigurer le
    # site et sa base sans être connecté. On le RENOMME (jamais de suppression) :
    # réversible d'un simple mv si l'installation n'était pas terminée.
    grp "Neutralisation de l'installeur"
    DONE=0
    for leftover in install installer setup; do
      SRC="$DOCROOT/$leftover"
      if [ -d "$SRC" ]; then
        DST="$SRC.desactive-$(date +%Y%m%d%H%M%S)"
        mv "$SRC" "$DST" && echo "renommé : $leftover/ → $(basename "$DST")" && DONE=1
      fi
    done
    [ "$DONE" = 1 ] || echo "aucun installeur trouvé — rien fait"
    echo "(pour revenir en arrière : mv <dossier>.desactive-* $DOCROOT/install)"
    egrp
    ;;
  mettre_en_pause)
    grp "Mise en pause de $DOM"
    if [ -d "$PUB" ] && [ ! -d "$PAUSED" ]; then
      mv "$PUB" "$PAUSED"
      mkdir -p "$PUB"
      printf '%s' '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Maintenance</title></head><body style="font-family:sans-serif;text-align:center;margin-top:15vh"><h1>Maintenance temporaire</h1><p>De retour très bientôt.</p></body></html>' > "$PUB/index.html"
      echo "EN PAUSE : $DOM (contenu intact dans public_html.paused)"
    elif [ -d "$PAUSED" ]; then
      echo "déjà en pause"
    else
      echo "public_html introuvable — rien fait"
    fi
    egrp
    ;;
  reprendre_site)
    grp "Reprise de $DOM"
    if [ -d "$PAUSED" ]; then
      # On ne supprime QUE la page d'attente qu'on a nous-mêmes posée. Si
      # public_html contient autre chose, on l'archive au lieu de l'écraser.
      N=$(find "$PUB" -mindepth 1 2>/dev/null | wc -l)
      if [ ! -d "$PUB" ]; then
        :
      elif [ "$N" -le 1 ] && [ -f "$PUB/index.html" ]; then
        rm -rf "$PUB"
      else
        BK="$PUB.avant-reprise-$(date +%Y%m%d%H%M%S)"
        mv "$PUB" "$BK"
        echo "::warning::public_html contenait $N élément(s) inattendu(s) — archivé dans $(basename "$BK") au lieu d'être supprimé."
      fi
      mv "$PAUSED" "$PUB"
      echo "REPRIS : $DOM"
    else
      echo "pas en pause — rien fait"
    fi
    egrp
    ;;
  *)
    echo "Action « $ACTION » = inventaire seul (lecture). Rien n'a été modifié."
    ;;
esac

echo "Terminé."
