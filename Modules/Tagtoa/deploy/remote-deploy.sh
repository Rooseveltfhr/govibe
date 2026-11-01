#!/usr/bin/env bash
#
# TAGTOA — script de déploiement exécuté SUR le VPS (appelé par la CI ou à la main).
# Usage : bash remote-deploy.sh /chemin/vers/app
#
# Sûr par conception (neutralise le risque "casser la prod") :
#   maintenance -> autoload déterministe -> migrate -> SMOKE TEST -> up,
#   avec rollback automatique (désactive le module + up) si le smoke test échoue.

set -uo pipefail

APP="${1:?Usage: remote-deploy.sh <app_path>}"
cd "$APP" || { echo "App path introuvable: $APP"; exit 1; }

echo "==> TAGTOA deploy @ $APP ($(date -u +%FT%TZ))"

# 0) composer.phar dispo ?
if [ ! -f composer.phar ]; then
  echo "==> Installation de composer.phar"
  php -r "copy('https://getcomposer.org/installer','/tmp/cs.php');"
  php /tmp/cs.php --install-dir=. --filename=composer.phar
  rm -f /tmp/cs.php
fi

# 1) PSR-4 du module enregistré dans composer.json (idempotent — pas de dépendance merge-plugin)
php -r '
$f="composer.json";$j=json_decode(file_get_contents($f),true);
$a=&$j["autoload"]["psr-4"];
$m=[
  "Modules\\Tagtoa\\"=>"Modules/Tagtoa/",
  "Modules\\Tagtoa\\App\\"=>"Modules/Tagtoa/app/",
  "Modules\\Tagtoa\\Database\\Seeders\\"=>"Modules/Tagtoa/database/seeders/",
  "Modules\\Tagtoa\\Database\\Factories\\"=>"Modules/Tagtoa/database/factories/",
];
$ch=false; foreach($m as $k=>$v){ if(($a[$k]??null)!==$v){$a[$k]=$v;$ch=true;} }
if($ch){ copy($f,$f.".bak"); file_put_contents($f,json_encode($j,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)); echo "psr-4: updated\n"; } else { echo "psr-4: ok\n"; }
'

rollback() {
  echo "!! Rollback : désactivation du module + sortie de maintenance"
  php -r '$f="modules_statuses.json";$j=json_decode(file_get_contents($f),true);$j["Tagtoa"]=false;file_put_contents($f,json_encode($j,JSON_PRETTY_PRINT));' || true
  php artisan optimize:clear >/dev/null 2>&1 || true
  php artisan up || true
  exit 1
}

# 1.5) SAUVEGARDE avant migration — AVANT la maintenance, pour qu'un échec de
#      sauvegarde ne coûte jamais une minute d'indisponibilité.
#      rollback() désactive le module mais NE DÉFAIT AUCUNE migration : sans ce
#      dump, une migration ratée à mi-chemin n'a aucun retour arrière.
#      Ne s'exécute que si une migration est en attente (--if-pending).
#      Échec = AVERTISSEMENT bruyant et déploiement poursuivi (comportement
#      historique), sauf TAGTOA_BACKUP_REQUIRED=1 : le déploiement s'arrête alors,
#      site intact. Voir DEPLOY.md (restauration).
#      Le module doit être ACTIVÉ pour que la commande existe : après un rollback
#      il est resté désactivé, et sans cette ligne la sauvegarde du redéploiement
#      (celui qui répare l'échec) serait justement celle qui saute.
php artisan module:enable Tagtoa >/dev/null 2>&1 || true
if [ "${TAGTOA_BACKUP_SKIP:-0}" != "1" ]; then
  echo "==> Sauvegarde de la base (si migration en attente)"
  if ! php artisan tagtoa:backup-db --if-pending --keep="${TAGTOA_BACKUP_KEEP:-14}"; then
    echo "!!!!!!!! WARN: SAUVEGARDE DE LA BASE IMPOSSIBLE — migration sans filet !!!!!!!!"
    if [ "${TAGTOA_BACKUP_REQUIRED:-0}" = "1" ]; then
      echo "TAGTOA_BACKUP_REQUIRED=1 : déploiement ANNULÉ avant la maintenance (site intact)."
      exit 1
    fi
  fi
else
  echo "==> Sauvegarde ignorée (TAGTOA_BACKUP_SKIP=1)"
fi

# 2) Maintenance
php artisan down --retry=10 || true

# 3) Autoload + activation + migrations (rollback si une étape échoue)
php composer.phar dump-autoload -o || rollback
php artisan module:enable Tagtoa || true
php artisan migrate --force || rollback

# 4) SMOKE TEST — les providers doivent se résoudre
if ! php artisan package:discover >/dev/null 2>&1; then
  echo "SMOKE_FAIL: package:discover"
  rollback
fi

# 4.5) Données de démo (idempotent — firstOrCreate sur les alias).
#      Ne bloque JAMAIS le déploiement. DÉSACTIVÉ par défaut : écrire des
#      commerces fictifs dans la base de PRODUCTION à chaque déploiement n'est
#      pas un comportement sûr. Activer sur un environnement de démo : TAGTOA_SEED_DEMO=1
if [ "${TAGTOA_SEED_DEMO:-0}" = "1" ]; then
  echo "==> Seed démo TAGTOA (idempotent)"
  php artisan db:seed --class="Modules\\Tagtoa\\Database\\Seeders\\TagtoaDemoSeeder" --force \
    || echo "WARN: seed démo ignoré (non bloquant)"
fi

# 4.7) Assets « souverains » (auto-réparation). mix()/asset() cherchent le
#      manifeste + les dossiers dans public/ (public_path), mais le docroot réel
#      est public_html/. On relie (idempotent) pour éviter le 500 « Mix manifest
#      not found » après un déplacement de l'app.
PH="$(dirname "$APP")/public_html"
if [ -d "$PH" ] && [ -d "$APP/public" ]; then
  if [ ! -e "$APP/public/mix-manifest.json" ] && [ -f "$PH/mix-manifest.json" ]; then
    ln -s "$PH/mix-manifest.json" "$APP/public/mix-manifest.json" 2>/dev/null && echo "mix-manifest relié" || true
  fi
  for a in css js fonts img images assets build web front vendor; do
    if [ -e "$PH/$a" ] && [ ! -e "$APP/public/$a" ]; then ln -s "$PH/$a" "$APP/public/$a" 2>/dev/null || true; fi
  done
fi

# 4.75) LE LIEN /storage — sans lui, TOUTE image téléversée est cassée.
#
#       `php artisan storage:link` crée public/storage, mais le serveur web
#       sert public_html/ : le lien est donc fabriqué là où personne ne le
#       cherche. Résultat visible en caisse : une photo de produit remplacée
#       par l'icône « image cassée », sur le bouton que le caissier doit
#       reconnaître d'un coup d'œil en pleine affluence. Et le même trou vaut
#       pour les couvertures de menu, les logos et les preuves de paiement.
#
#       On relie donc dans le DOCROOT, là où /storage/... est réellement
#       demandé. Idempotent, et non destructif : si public_html/storage existe
#       déjà en vrai dossier (fichiers posés là par un ancien déploiement), on
#       n'y touche pas — l'écraser perdrait les images.
mkdir -p "$APP/storage/app/public"
php artisan storage:link >/dev/null 2>&1 || true

if [ -n "${PH:-}" ] && [ -d "$PH" ]; then
  # Lien mort (l'app a été déplacée) : on le refait plutôt que de le garder.
  if [ -L "$PH/storage" ] && [ ! -e "$PH/storage" ]; then
    rm -f "$PH/storage" && echo "storage : lien mort retiré"
  fi
  if [ ! -e "$PH/storage" ]; then
    ln -s "$APP/storage/app/public" "$PH/storage" 2>/dev/null \
      && echo "storage : lien créé dans le docroot" \
      || echo "WARN: storage non relié (droits ?)"
  else
    echo "storage : déjà en place"
  fi
fi

# 4.76) LES IMAGES TÉLÉVERSÉES DOIVENT ÊTRE ATTEIGNABLES PAR LE WEB.
#       Constaté en production (diagnostic 4.78) : le disque « public » de l'application hôte écrit dans
#       <app>/public/uploads (URL https://…/uploads), mais le serveur web sert public_html/. Les photos étaient
#       donc bien enregistrées… et introuvables : https://…/uploads/tagtoa/menu-items/x.jpg répondait 404, parce
#       que public_html/uploads/ est un AUTRE dossier.
#       On relie, dans public_html/uploads/tagtoa/, chaque sous-dossier d'images de TAGTOA vers le dossier réel.
#       LISTE BLANCHE, jamais « tagtoa » en bloc : les justificatifs de paiement (tagtoa/pay-proofs) peuvent se
#       trouver sous le même disque et ne doivent JAMAIS devenir publics. Idempotent ; ne touche à aucun vrai
#       dossier ou fichier déjà présent ; un lien mort est refait.
TAGTOA_PUBLIC_DIRS="booking-covers booking-logos business-logos event-covers event-logos hotel-covers link-avatars loyalty-logos menu-covers menu-items menu-logos pay-logos pay-qr pos-products shop site-covers site-gallery site-logos store-covers store-logos store-products"
DISK_LINE="$(php artisan tinker --execute="echo config('filesystems.disks.public.root').'|'.config('filesystems.disks.public.url');" 2>/dev/null | tail -1)"
DISK_ROOT="${DISK_LINE%%|*}"
DISK_URLPATH="$(printf '%s' "${DISK_LINE#*|}" | sed -E 's#^https?://[^/]+##; s#/+$##')"
if [ -n "${PH:-}" ] && [ -d "$PH" ] && [ -n "$DISK_ROOT" ] && [ -n "$DISK_URLPATH" ] && [ -d "$DISK_ROOT" ] \
   && [ "$(readlink -f "$DISK_ROOT")" != "$(readlink -f "$PH$DISK_URLPATH" 2>/dev/null)" ]; then
  echo "==> Images téléversées : $PH$DISK_URLPATH/tagtoa/* -> $DISK_ROOT/tagtoa/*"
  mkdir -p "$PH$DISK_URLPATH/tagtoa" 2>/dev/null || echo "WARN: impossible de créer $PH$DISK_URLPATH/tagtoa"
  for d in $TAGTOA_PUBLIC_DIRS; do
    mkdir -p "$DISK_ROOT/tagtoa/$d" 2>/dev/null
    LNK="$PH$DISK_URLPATH/tagtoa/$d"
    if [ -L "$LNK" ] && [ ! -e "$LNK" ]; then rm -f "$LNK"; fi                  # lien mort : refait
    if [ ! -e "$LNK" ] && [ ! -L "$LNK" ]; then
      ln -s "$DISK_ROOT/tagtoa/$d" "$LNK" 2>/dev/null && echo "   lié : tagtoa/$d" || echo "WARN: lien non créé : tagtoa/$d (droits ?)"
    fi
  done
else
  echo "Images téléversées : rien à relier (disque public déjà servi par le docroot, ou introuvable)."
fi

# 4.78) DIAGNOSTIC DES IMAGES (jamais bloquant, n'affiche ni secret ni contenu).
#       Départage dans le journal de déploiement : (1) où le disque « public » de l'hôte écrit vraiment
#       (racine | URL) ; (2) ce que contient ce dossier ; (3) si l'URL publique de la dernière image répond 200.
echo "==> Diagnostic des images (non bloquant)"
echo "   disque public (racine | url) : ${DISK_LINE:-inconnu}"
PUBDIR="${DISK_ROOT:-$APP/storage/app/public}"
echo "   dossier physique : $PUBDIR — $(ls "$PUBDIR" 2>/dev/null | wc -l) entrée(s), droits $(stat -c '%a %U:%G' "$PUBDIR" 2>/dev/null || echo '?'), PHP-FPM écrit en tant que : $(ps -eo user,comm 2>/dev/null | awk '/php-fpm|lsphp|php[0-9.]*$/ {print $1}' | sort -u | tr '\n' ' ')"
echo "   dossiers « menu-items » trouvés :"
find "$APP/storage" "$APP/public" ${PH:+"$PH"} -maxdepth 8 -name 'menu-items' 2>/dev/null | head -6 | sed 's/^/     /'
NEWEST="$(find "$PUBDIR/tagtoa" -type f \( -iname '*.jpg' -o -iname '*.jpeg' -o -iname '*.png' -o -iname '*.webp' \) -printf '%T@ %p\n' 2>/dev/null | sort -nr | head -1 | cut -d' ' -f2-)"
if [ -n "$NEWEST" ]; then
  REL="${NEWEST#"$PUBDIR"/}"
  DIAG_URL="$(grep -m1 '^APP_URL=' .env 2>/dev/null | cut -d= -f2-)"
  echo "   dernière image : $REL (droits $(stat -c '%a %U' "$NEWEST"))"
  if [ -n "$DIAG_URL" ]; then
    DIAG_CODE="$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "${DIAG_URL%/}${DISK_URLPATH:-/storage}/$REL" 2>/dev/null)"
    echo "   GET ${DIAG_URL%/}${DISK_URLPATH:-/storage}/$REL -> HTTP ${DIAG_CODE:-000}"
  fi
else
  echo "   aucune image sous $PUBDIR/tagtoa pour l'instant."
fi
if [ -n "${DISK_ROOT:-}" ] && [ -e "$DISK_ROOT/tagtoa/pay-proofs" ]; then
  echo "   NOTE : $DISK_ROOT/tagtoa/pay-proofs existe sous le disque public — NON relié au web (liste blanche)."
fi

# 4.79) ISOLATION PAR COMMERCE — inventaire LECTURE SEULE (CS-11a), jamais bloquant.
#       Affiche : modèles sans isolation, lignes sans commerce, comptes sans
#       tenant_id. Ne modifie rien ; sert à décider CS-11b sur des chiffres réels.
echo "==> Isolation par commerce (lecture seule)"
timeout 60 php artisan tagtoa:tenant-audit 2>&1 | sed 's/^/   /' || echo "   (inventaire indisponible — ignoré)"

# 4.8) Perf HTTP pour connexions faibles (Haïti) : compression gzip + cache
#      navigateur. Ajout IDEMPOTENT (marqueur TAGTOA-PERF-BLOCK) en fin du
#      .htaccess existant du docroot — jamais d'écrasement, jamais de doublon.
if [ -n "${PH:-}" ] && [ -f "$PH/.htaccess" ] && ! grep -q "TAGTOA-PERF-BLOCK" "$PH/.htaccess"; then
  cat >> "$PH/.htaccess" <<'HTACCESS_EOF'

# TAGTOA-PERF-BLOCK — compression + cache navigateur (connexions lentes Haïti)
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/css text/plain text/xml text/javascript
    AddOutputFilterByType DEFLATE application/javascript application/x-javascript
    AddOutputFilterByType DEFLATE application/json application/xml application/rss+xml
    AddOutputFilterByType DEFLATE image/svg+xml
</IfModule>
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/webp "access plus 1 year"
    ExpiresByType image/svg+xml "access plus 1 year"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType application/pdf "access plus 1 month"
    ExpiresByType font/woff2 "access plus 1 year"
    ExpiresByType font/woff "access plus 1 year"
</IfModule>
HTACCESS_EOF
  echo "perf .htaccess : bloc compression/cache ajouté"
else
  echo "perf .htaccess : déjà présent, ou .htaccess/public_html introuvable"
fi

# 5) Caches + réouverture. On vide puis on RECACHE (prod plus rapide : évite de
#    relire ~30 fichiers config + .env à chaque requête). Pas de route:cache
#    (Biztap peut avoir des routes en closure).
php artisan optimize:clear >/dev/null 2>&1 || true
php artisan config:cache >/dev/null 2>&1 || true
php artisan view:cache >/dev/null 2>&1 || true
php artisan up

# 5.5) Vide OPcache sur de VRAIS workers PHP-FPM (voir OpcacheController).
#      Un rsync + une migration réussis ne garantissent PAS qu'un worker
#      déjà démarré voie le nouveau code : si OPcache tourne avec
#      validate_timestamps=0 (courant en prod, pour la performance), il
#      continue de servir le bytecode d'AVANT ce déploiement jusqu'à son
#      propre redémarrage. `php artisan` (CLI) a son propre OPcache, séparé
#      de celui des workers FPM — l'appeler ici ne viderait pas le bon
#      cache. OPcache est PAR WORKER : une seule requête n'en réinitialise
#      qu'un seul, donc on répète l'appel pour couvrir tout le pool.
#      Inactif tant que TAGTOA_OPCACHE_RESET_TOKEN n'est pas dans ce .env.
OPCACHE_TOKEN="$(grep -m1 '^TAGTOA_OPCACHE_RESET_TOKEN=' .env 2>/dev/null | cut -d= -f2-)"
APP_BASE_URL="$(grep -m1 '^APP_URL=' .env 2>/dev/null | cut -d= -f2-)"
if [ -n "$OPCACHE_TOKEN" ] && [ -n "$APP_BASE_URL" ]; then
  echo "==> Vidage OPcache (workers FPM)"
  ok=0
  for i in $(seq 1 20); do
    code=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "${APP_BASE_URL%/}/tagtoa-ops/opcache-reset/${OPCACHE_TOKEN}" 2>/dev/null || echo 000)
    [ "$code" = "200" ] && ok=$((ok + 1))
  done
  echo "   OPcache reset : $ok/20 requêtes OK"
else
  echo "OPcache reset : TAGTOA_OPCACHE_RESET_TOKEN absent du .env (étape ignorée — voir docs/SECURITY.md)"
fi

# 5.9) SESSION / « Page expirée » (419) — diagnostic + réparation SÛRE, jamais bloquant.
#      Un 419 à chaque connexion = la session écrite au GET /login n'est pas relue
#      au POST. Causes typiques : dossier de sessions absent ou non inscriptible,
#      disque/quota plein, cookie `Secure`/`Domain` qui ne correspond pas à l'URL.
#      Seule réparation automatique : (re)créer les dossiers storage/framework/*
#      (sans rien supprimer). Le reste est AFFICHÉ (aucune valeur secrète) pour
#      décider la correction du .env.
echo "==> Session / connexion (419)"
for d in storage/framework/sessions storage/framework/cache/data storage/framework/views; do
  if [ ! -d "$d" ]; then mkdir -p "$d" && echo "   CRÉÉ : $d (il manquait — sans lui, Laravel ne peut pas garder la session : 419)"; fi
  if [ -w "$d" ]; then echo "   $d : inscriptible ($(find "$d" -maxdepth 1 -type f 2>/dev/null | wc -l) fichiers)"; else echo "   ATTENTION : $d NON inscriptible (propriétaire $(stat -c %U "$d" 2>/dev/null))"; fi
done
newest=$(find storage/framework/sessions -maxdepth 1 -type f -printf '%T@\n' 2>/dev/null | sort -n | tail -1)
[ -n "$newest" ] && echo "   session la plus récente : il y a $(( $(date +%s) - ${newest%.*} )) s" || echo "   aucune session enregistrée"
# Écriture réelle (un dossier « inscriptible » sur un quota plein échoue quand même).
if printf 'x' > storage/framework/sessions/.tagtoa-write-test 2>/dev/null; then rm -f storage/framework/sessions/.tagtoa-write-test; echo "   écriture réelle : OK"; else echo "   ATTENTION : écriture IMPOSSIBLE dans storage/framework/sessions (disque ou quota plein ?)"; fi
df -h . 2>/dev/null | tail -1 | awk '{print "   disque : "$5" utilisé, "$4" libre"}'
(quota -s 2>/dev/null | tail -n +3 | head -3 | sed 's/^/   quota : /') || true
timeout 30 php -r '
  require "vendor/autoload.php"; $app = require "bootstrap/app.php";
  $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
  $s = config("session");
  printf("   config : driver=%s domain=%s secure=%s same_site=%s path=%s lifetime=%s cookie=%s\n",
    $s["driver"] ?? "?", var_export($s["domain"] ?? null, true), var_export($s["secure"] ?? null, true),
    var_export($s["same_site"] ?? null, true), $s["path"] ?? "?", $s["lifetime"] ?? "?", $s["cookie"] ?? "?");
  printf("   app.url=%s\n", config("app.url"));
' 2>&1 | head -4 || echo "   (config session illisible)"
# Parcours RÉEL de connexion, sur ce serveur, sans identifiants : GET /login, jeton
# CSRF, POST d'un compte inexistant. 419 = session cassée ; 302/422/200 = session OK
# (le compte est simplement refusé). Aucun mot de passe réel n'est utilisé.
HOST_ONLY="$(grep -m1 '^APP_URL=' .env 2>/dev/null | cut -d= -f2- | sed -E 's#^https?://##; s#/.*$##')"
if [ -n "$HOST_ONLY" ]; then
  # Les routes de connexion déclarées par l'application (POST …login), plus les classiques.
  # Toutes les routes GET dont l'adresse contient « login ». On interroge le routeur directement
  # (et NON `artisan route:list`, qui instancie les contrôleurs : sur tagtoa.com il plante sur
  # GooglePlayService, dont le constructeur lit un google-service-account.json absent).
  ROUTES="$(timeout 40 php -r '
    require "vendor/autoload.php"; $app = require "bootstrap/app.php";
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    foreach ($app["router"]->getRoutes() as $r) { echo implode("|", $r->methods()), " ", $r->uri(), "\n"; } /*getRoutes*/
  ' 2>/dev/null)"
  LPS="$(printf '%s\n' "$ROUTES" | grep -E '^GET' | awk '{print $2}' | grep -i 'login' | grep -v '[{]' | sed 's#^#/#; s#^//#/#' | sort -u | head -8)"
  echo "   routes de connexion déclarées : $(printf '%s ' $LPS)"
  if [ -z "$LPS" ]; then
    echo "   (aucune route GET contenant « login » — routes proches ci-dessous : $(printf '%s\n' "$ROUTES" | grep -c .) routes au total)"
    printf '%s\n' "$ROUTES" | grep -iE 'sign|auth|account|register|password|connexion|session' | head -15 | cut -c1-150 | sed 's/^/     /'
  fi
  # DEUX chemins : « public » = comme un visiteur (DNS → adresse publique → proxy/CDN → application) ;
  # « loopback » = 127.0.0.1 avec l'en-tête d'hôte, qui peut tomber sur un AUTRE hôte virtuel que
  # celui du site (sur tagtoa.com : page 404 d'Apache pour /pay/result alors que « / » répond 200).
  for MODE in public loopback; do
    if [ "$MODE" = loopback ]; then RES=(--resolve "$HOST_ONLY:443:127.0.0.1" --resolve "www.$HOST_ONLY:443:127.0.0.1"); else RES=(); fi
  # Pages de RÉFÉRENCE : si « / » et une route publique TAGTOA répondent 200 mais que /login répond 404,
  # le 404 vient de l'application ; sinon c'est le serveur web (mauvais hôte virtuel) qu'on interroge.
  for REF in / /pay/result; do
    rc="$(curl -sk -m 15 -L --max-redirs 5 -o /dev/null -w '%{http_code} %{url_effective}' ${RES[@]+"${RES[@]}"} "https://$HOST_ONLY$REF" 2>/dev/null)"
    echo "   [$MODE] référence GET $REF → $rc"
  done
  for LP in $(printf '%s\n' /login /admin/login $LPS | sort -u); do
    JAR="$(mktemp)"
    # GET en suivant les redirections : on garde l'URL finale (/fr/login, www.…).
    page="$(curl -sk -m 20 -L --max-redirs 5 -c "$JAR" -b "$JAR" ${RES[@]+"${RES[@]}"} -w '\n__FINAL__ %{http_code} %{url_effective}' "https://$HOST_ONLY$LP" 2>/dev/null)"
    fin="$(printf '%s' "$page" | sed -n 's/^__FINAL__ //p' | tail -1)"
    tok="$(printf '%s' "$page" | grep -o 'name="_token" value="[^"]*"' | head -1 | sed 's/.*value="//; s/"$//')"
    [ -z "$tok" ] && tok="$(printf '%s' "$page" | grep -o 'name="csrf-token" content="[^"]*"' | head -1 | sed 's/.*content="//; s/"$//')"
    if [ -z "$tok" ]; then
      echo "   [$MODE] GET $LP → $fin : pas de formulaire avec jeton CSRF"
      echo "     corps : $(printf '%s' "$page" | grep -v '^__FINAL__' | tr '\n' ' ' | sed 's/<[^>]*>/ /g' | tr -s ' ' | cut -c1-200)"
      rm -f "$JAR"; continue
    fi
    finurl="${fin#* }"
    # Où le formulaire envoie-t-il vraiment ? (un autre hôte = cookie non envoyé = 419)
    action="$(printf '%s' "$page" | tr '\n' ' ' | grep -oE '<form[^>]*method="(post|POST)"[^>]*>' | head -1 | grep -oE 'action="[^"]*"' | sed 's/action="//; s/"$//; s/&amp;/\&/g')"
    [ -z "$action" ] && action="$finurl"
    case "$action" in http*) ;; /*) action="https://$HOST_ONLY$action" ;; *) action="$finurl" ;; esac
    hf="$(printf '%s' "$finurl" | sed -E 's#^https?://##; s#/.*$##')"; ha="$(printf '%s' "$action" | sed -E 's#^https?://##; s#/.*$##')"
    sa="$(printf '%s' "$action" | cut -d: -f1)"
    note=""; [ "$hf" != "$ha" ] && note=" ATTENTION : la page est servie sur $hf mais le formulaire poste vers $ha — le cookie de session n'y est pas envoyé (cause classique de 419)."
    [ "$sa" = "http" ] && note="$note ATTENTION : le formulaire poste en http:// (cookie perdu/bloqué)."
    flags="$(curl -sk -m 15 -o /dev/null -D - -L ${RES[@]+"${RES[@]}"} "https://$HOST_ONLY$LP" 2>/dev/null | grep -i '^set-cookie' | sed -E 's/=[^;]*/=…/' | tr -d '\r' | tail -3 | tr '\n' ' ')"
    code="$(curl -sk -m 15 -o /dev/null -w '%{http_code}' -c "$JAR" -b "$JAR" ${RES[@]+"${RES[@]}"} \
            -H "Referer: $finurl" --data-urlencode "_token=$tok" \
            --data-urlencode "email=diag-419@invalid.invalid" --data-urlencode "password=diag-not-a-password" \
            "$action" 2>/dev/null)"
    rm -f "$JAR"
    echo "   [$MODE] GET $LP → $fin ; formulaire → $action"
    case "$code" in
      419) echo "     [$MODE] POST → 419 PAGE EXPIRÉE — la session n'est pas conservée. Cookies : $flags$note" ;;
      000) echo "     [$MODE] POST → injoignable en local$note" ;;
      *)   echo "     [$MODE] POST → $code — session OK (compte de test refusé, normal). Cookies : $flags$note" ;;
    esac
  done
  done
fi
echo "   419 dans laravel.log : $(grep -c 'TokenMismatchException\|CSRF token mismatch' storage/logs/laravel.log 2>/dev/null || echo 0)"
echo "   dernières erreurs (titres) :"
grep -E '^\[[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9:]{8}\] [a-z]+\.(ERROR|CRITICAL|ALERT|EMERGENCY):' storage/logs/laravel.log 2>/dev/null \
  | tail -5 | cut -c1-260 | sed 's/^/     /' || true

# 6) Smoke test public (non bloquant). Activer : TAGTOA_SMOKE_BASE=https://tagtoa.com/tapbiz/public
if [ -n "${TAGTOA_SMOKE_BASE:-}" ]; then
  echo "==> Smoke test public @ ${TAGTOA_SMOKE_BASE}"
  for p in /menu/demo-menu /pay/demo /links/demo-links /event/demo-concert; do
    code=$(curl -s -o /dev/null -w "%{http_code}" -L --max-time 15 "${TAGTOA_SMOKE_BASE}${p}" 2>/dev/null || echo 000)
    case "$code" in
      2*|3*) echo "   OK   $p -> $code" ;;
      *)     echo "   WARN $p -> $code (non bloquant)" ;;
    esac
  done
fi


# 6) FILET DES PAIEMENTS « EN ATTENTE » (CS-12) — après la réouverture, jamais bloquant.
#    Un paiement dont le webhook a échoué et dont le client n'est pas revenu reste
#    « en attente » pour toujours. `tagtoa:pay:reconcile` le re-vérifie auprès de la
#    passerelle par le même chemin que le webhook (verrou, atomique, idempotent).
#    Le planificateur de l'hôte la lance toutes les 10 min SI un cron `schedule:run`
#    existe ; on le constate ici (lecture seule) et on lance un passage de rattrapage.
echo "==> Paiements en attente (réconciliation)"
if crontab -l 2>/dev/null | grep -q 'schedule:run'; then
  echo "   planificateur : cron schedule:run présent — la réconciliation tourne toutes les 10 min"
else
  echo "   planificateur : aucun cron schedule:run détecté dans le crontab de cet utilisateur (crontab absent, ou cron géré par le panneau d'hébergement)."
  echo "   Sans lui, la réconciliation ne tourne qu'à chaque déploiement. Ajoutez au cron : * * * * * cd '$(pwd)' && php artisan schedule:run >> /dev/null 2>&1"
fi
timeout 90 php artisan tagtoa:pay:reconcile --limit=20 2>&1 | sed 's/^/   /' || echo "   (réconciliation indisponible — ignorée)"

echo "DEPLOY_OK"
