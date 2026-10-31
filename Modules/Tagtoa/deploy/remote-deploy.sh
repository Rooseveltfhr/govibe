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

# 4.78) DIAGNOSTIC DES IMAGES (jamais bloquant, n'affiche ni secret ni contenu).
#       « Les images téléversées ne s'affichent pas » a trois causes possibles, que ce bloc départage dans
#       le journal de déploiement : (1) le disque « public » de l'application hôte n'écrit pas où l'on croit
#       (racine ou URL différentes) ; (2) le dossier physique est vide ou non inscriptible ; (3) le lien
#       public_html/storage manque, est mort, ou le serveur web refuse de le suivre (code HTTP ≠ 200).
echo "==> Diagnostic des images (non bloquant)"
DISK_INFO="$(php artisan tinker --execute="echo config('filesystems.disks.public.root').' | '.config('filesystems.disks.public.url');" 2>/dev/null | tail -1)"
echo "   disque public (racine | url) : ${DISK_INFO:-inconnu}"
PUBDIR="$APP/storage/app/public"
echo "   dossier physique : $PUBDIR — $(ls "$PUBDIR" 2>/dev/null | wc -l) entrée(s), droits $(stat -c '%a %U:%G' "$PUBDIR" 2>/dev/null || echo '?'), PHP-FPM écrit en tant que : $(ps -eo user,comm 2>/dev/null | awk '/php-fpm|lsphp|php[0-9.]*$/ {print $1}' | sort -u | tr '\n' ' ')"
if [ -n "${PH:-}" ]; then
  echo "   lien docroot : $(ls -ld "$PH/storage" 2>/dev/null | awk '{print $1, $3":"$4, $(NF-2), $(NF-1), $NF}') $( [ -e "$PH/storage" ] && echo '(cible lisible)' || echo '(ABSENT ou MORT)')"
fi
echo "   dossiers « menu-items » trouvés (là où les photos de plats atterrissent vraiment) :"
find "$APP/storage" "$APP/public" ${PH:+"$PH"} -maxdepth 8 -type d -name 'menu-items' 2>/dev/null | head -5 | sed 's/^/     /'
NEWEST="$(find "$PUBDIR/tagtoa" -type f \( -iname '*.jpg' -o -iname '*.jpeg' -o -iname '*.png' -o -iname '*.webp' \) -printf '%T@ %p\n' 2>/dev/null | sort -nr | head -1 | cut -d' ' -f2-)"
if [ -n "$NEWEST" ]; then
  REL="${NEWEST#"$PUBDIR"/}"
  DIAG_URL="$(grep -m1 '^APP_URL=' .env 2>/dev/null | cut -d= -f2-)"
  echo "   dernière image : $REL (droits $(stat -c '%a %U' "$NEWEST"))"
  if [ -n "$DIAG_URL" ]; then
    DIAG_CODE="$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "${DIAG_URL%/}/storage/$REL" 2>/dev/null)"
    echo "   GET ${DIAG_URL%/}/storage/$REL -> HTTP ${DIAG_CODE:-000}"
  fi
else
  echo "   AUCUNE image sous $PUBDIR/tagtoa — rien n'a été écrit là (voir « disque public » ci-dessus)."
fi

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

echo "DEPLOY_OK"
