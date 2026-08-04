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
STACK="inconnue"
grp "Stack détectée"
if [ -f "$DOCROOT/wp-config.php" ]; then
  STACK="wordpress"
  echo "WordPress détecté (wp-config.php présent)."
  V=$(grep -E "^\\\$wp_version *=" "$DOCROOT/wp-includes/version.php" 2>/dev/null | head -1 | cut -d"'" -f2)
  echo "version WordPress : ${V:-inconnue}"
  echo "-- thèmes --";  ls -1 "$DOCROOT/wp-content/themes"  2>/dev/null | head -20 || true
  echo "-- extensions --"; ls -1 "$DOCROOT/wp-content/plugins" 2>/dev/null | head -40 || true
  echo "-- taille des uploads --"; du -sh "$DOCROOT/wp-content/uploads" 2>/dev/null || true
elif [ -f "$DOCROOT/artisan" ] || [ -f "$D/laravel/artisan" ]; then
  STACK="laravel"
  APPDIR="$DOCROOT"; [ -f "$APPDIR/artisan" ] || APPDIR="$D/laravel"
  echo "Laravel détecté : $APPDIR"
  ( cd "$APPDIR" && php artisan --version 2>&1 | head -2 ) || true
  grep -E '"name"|"laravel/framework"' "$APPDIR/composer.json" 2>/dev/null | head -4 || true
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
  APPDIR="$DOCROOT"; [ -f "$APPDIR/artisan" ] || APPDIR="$D/laravel"
  ENVF="$APPDIR/.env"
  if [ -f "$ENVF" ]; then
    for k in APP_ENV APP_DEBUG APP_KEY APP_URL DB_CONNECTION DB_HOST DB_DATABASE DB_USERNAME DB_PASSWORD; do
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

grp "Tâches cron liées à $DOM"
crontab -l 2>/dev/null | grep -i "${DOM%%.*}" || echo "(aucune)"
egrp

# --- 8. Actions (uniquement si demandées explicitement) ---------------------
case "$ACTION" in
  vider_cache)
    grp "Vidage des caches"
    if [ "$STACK" = "laravel" ]; then
      APPDIR="$DOCROOT"; [ -f "$APPDIR/artisan" ] || APPDIR="$D/laravel"
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
