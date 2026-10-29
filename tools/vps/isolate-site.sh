#!/bin/bash
# ============================================================
# Isolation d'un site sur un VPS partagé (GOVIBEHT, TAGTOA, ANYWALEX…)
#
# Donne à UN site :
#   - son propre utilisateur Linux (sans shell, sans mot de passe) ;
#   - son propre pool PHP-FPM, qui tourne sous cet utilisateur ;
#   - son propre dossier temporaire / sessions, et open_basedir ;
#   - des permissions qui empêchent les autres sites de lire ses fichiers.
#
# Nginx continue de servir les fichiers statiques : le groupe du site est
# celui de nginx (www-data), en lecture seule. Les .env restent en 600.
#
# Un site à la fois. Par défaut le script SIMULE et n'écrit rien.
#
# Usage (en root) :
#   SITE=anywalex APP_DIR=/var/www/anywalex bash isolate-site.sh            # simulation
#   SITE=anywalex APP_DIR=/var/www/anywalex APPLY=1 bash isolate-site.sh    # application
#
# Variables optionnelles :
#   SITE_USER=web_anywalex (par défaut : le propriétaire actuel s'il est dédié,
#                           sinon un nouvel utilisateur web_<site>)
#   NGINX_VHOST=/etc/nginx/sites-available/anywalex  (si détection ambiguë)
#   PHP_VERSION=8.3        MAX_CHILDREN=10
#   OPEN_BASEDIR=1         (0 pour désactiver si le script du site casse)
#   HARDEN=0               (1 = désactive exec/shell_exec/system… dans le pool)
#   FORCE=1                (ignorer la détection d'un panel d'hébergement)
#
# Chaque application crée une sauvegarde et un rollback.sh dans
# /root/site-isolation/<site>-<date>/.
# ============================================================

set -euo pipefail

GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; BLUE='\033[0;34m'; NC='\033[0m'
info()  { echo -e "${GREEN}[INFO]${NC} $1"; }
warn()  { echo -e "${YELLOW}[WARN]${NC} $1"; }
error() { echo -e "${RED}[ERREUR]${NC} $1" >&2; exit 1; }
plan()  { echo -e "${BLUE}  →${NC} $1"; }

APPLY="${APPLY:-0}"
OPEN_BASEDIR="${OPEN_BASEDIR:-1}"
HARDEN="${HARDEN:-0}"
MAX_CHILDREN="${MAX_CHILDREN:-10}"
FORCE="${FORCE:-0}"

# ── 1. Vérifications ──────────────────────────────────────
[ "$(id -u)" -eq 0 ] || error "À lancer en root (sudo)."

SITE="${SITE:-}"
[[ "$SITE" =~ ^[a-z][a-z0-9_]{1,23}$ ]] \
    || error "SITE invalide : lettres minuscules, chiffres et _ (ex. SITE=anywalex)."

APP_DIR="${APP_DIR:-}"
[ -n "$APP_DIR" ] && [ -d "$APP_DIR" ] || error "APP_DIR introuvable : '${APP_DIR}'."
APP_DIR="$(realpath "$APP_DIR")"
case "$APP_DIR" in
    /|/root|/home|/var|/var/www|/usr|/etc|/opt|/srv) error "APP_DIR trop large : $APP_DIR" ;;
esac

# Un panel (Hestia, CyberPanel, aaPanel, cPanel, Plesk, DirectAdmin, CloudPanel) régénère
# ses propres fichiers nginx/PHP : nos modifications seraient écrasées.
if [ "$FORCE" != "1" ]; then
    for p in /usr/local/hestia /usr/local/vesta /usr/local/CyberCP /www/server/panel \
             /usr/local/cpanel /usr/local/psa /usr/local/directadmin /home/clp; do
        [ -d "$p" ] && error "Panel détecté ($p). Utiliser l'isolation du panel (un compte/utilisateur par site) au lieu de ce script, ou FORCE=1 si le panel ne gère pas ce site."
    done
fi

command -v nginx   &>/dev/null || error "nginx introuvable."
command -v getfacl &>/dev/null || error "getfacl introuvable (nécessaire à la sauvegarde) : apt install acl  ou  dnf install acl"

# Vhost nginx qui sert ce dossier
NGINX_VHOST="${NGINX_VHOST:-}"
if [ -z "$NGINX_VHOST" ]; then
    mapfile -t CANDIDATES < <(grep -lsF "$APP_DIR" /etc/nginx/sites-enabled/* /etc/nginx/conf.d/*.conf 2>/dev/null || true)
    [ "${#CANDIDATES[@]}" -eq 1 ] \
        || error "Vhost nginx introuvable ou ambigu pour $APP_DIR (${#CANDIDATES[@]} trouvé(s) : ${CANDIDATES[*]:-aucun}). Préciser NGINX_VHOST=…"
    NGINX_VHOST="${CANDIDATES[0]}"
fi
NGINX_VHOST="$(realpath "$NGINX_VHOST")"
[ -f "$NGINX_VHOST" ] || error "Vhost introuvable : $NGINX_VHOST"
grep -qE '^\s*fastcgi_pass\s' "$NGINX_VHOST" \
    || error "Aucune ligne fastcgi_pass dans $NGINX_VHOST (PHP passe peut-être par un include ; le modifier à la main)."

DOMAIN="$(awk '$1=="server_name"{gsub(";","",$2); print $2; exit}' "$NGINX_VHOST")"

# Utilisateur de nginx : il doit pouvoir lire les fichiers et parler au socket.
WEB_USER="$(awk '$1=="user"{gsub(";","",$2); print $2; exit}' /etc/nginx/nginx.conf 2>/dev/null || true)"
if [ -z "$WEB_USER" ]; then
    if id -u www-data &>/dev/null; then WEB_USER="www-data"; else WEB_USER="nginx"; fi
fi
id -u "$WEB_USER" &>/dev/null || error "Utilisateur nginx '$WEB_USER' introuvable."
WEB_GROUP="$(id -gn "$WEB_USER")"

# PHP-FPM : Debian/Ubuntu (/etc/php/X/fpm) ou RHEL (/etc/php-fpm.d)
CURRENT_PASS="$(grep -m1 -oE 'fastcgi_pass\s+[^;]+' "$NGINX_VHOST" | awk '{print $2}')"
if [ -d /etc/php ]; then
    PHP_VERSION="${PHP_VERSION:-$(echo "$CURRENT_PASS" | grep -oE 'php[0-9]+\.[0-9]+' | head -1 | tr -d 'php')}"
    PHP_VERSION="${PHP_VERSION:-$(ls /etc/php | sort -V | tail -1)}"
    POOL_DIR="/etc/php/${PHP_VERSION}/fpm/pool.d"
    FPM_SERVICE="php${PHP_VERSION}-fpm"
    FPM_BIN="php-fpm${PHP_VERSION}"
    SOCK_DIR="/run/php"
    SOCK="${SOCK_DIR}/php${PHP_VERSION}-fpm-${SITE}.sock"
elif [ -d /etc/php-fpm.d ]; then
    POOL_DIR="/etc/php-fpm.d"
    FPM_SERVICE="php-fpm"
    FPM_BIN="php-fpm"
    SOCK_DIR="/run/php-fpm"
    SOCK="${SOCK_DIR}/${SITE}.sock"
else
    error "PHP-FPM introuvable (/etc/php ou /etc/php-fpm.d)."
fi
[ -d "$POOL_DIR" ] || error "Dossier des pools introuvable : $POOL_DIR"
command -v "$FPM_BIN" &>/dev/null || FPM_BIN="$(command -v php-fpm || true)"
[ -n "$FPM_BIN" ] || error "Binaire php-fpm introuvable."

POOL_FILE="${POOL_DIR}/${SITE}.conf"
[ -e "$POOL_FILE" ] && error "Le pool $POOL_FILE existe déjà : site déjà isolé."

# Si le dossier appartient déjà à un compte dédié (ex. le compte SSH qui
# déploie le site), on le garde comme utilisateur PHP : sinon git pull et
# update.sh, lancés sous ce compte, ne pourraient plus écrire dans le site.
CURRENT_OWNER="$(stat -c %U "$APP_DIR")"
case "$CURRENT_OWNER" in
    root|www-data|nginx|apache|nobody) DEFAULT_USER="web_${SITE}" ;;
    *)                                 DEFAULT_USER="$CURRENT_OWNER" ;;
esac
SITE_USER="${SITE_USER:-$DEFAULT_USER}"
DATA_DIR="/var/lib/php-sites/${SITE}"
BACKUP_DIR="/root/site-isolation/${SITE}-$(date +%Y%m%d-%H%M%S)"
mapfile -t UNITS < <(grep -lsF "WorkingDirectory=${APP_DIR}" /etc/systemd/system/*.service 2>/dev/null || true)

# ── 2. Plan ───────────────────────────────────────────────
echo
info "Plan d'isolation pour « ${SITE} »"
plan "Dossier du site      : $APP_DIR"
plan "Domaine              : ${DOMAIN:-inconnu}"
plan "Vhost nginx          : $NGINX_VHOST"
plan "fastcgi_pass actuel  : $CURRENT_PASS"
plan "Propriétaire actuel  : $CURRENT_OWNER"
plan "Utilisateur PHP      : $SITE_USER$(id -u "$SITE_USER" &>/dev/null && echo ' (existant, conservé)' || echo ' (nouveau, sans shell)')"
plan "Pool PHP-FPM         : $POOL_FILE → $SOCK"
plan "Temp / sessions      : $DATA_DIR"
plan "Propriétaire         : ${SITE_USER}:${WEB_GROUP}, dossiers 2750, fichiers 640, .env 600"
plan "open_basedir         : $([ "$OPEN_BASEDIR" = "1" ] && echo oui || echo non)"
plan "Fonctions shell PHP  : $([ "$HARDEN" = "1" ] && echo désactivées || echo inchangées)"
plan "Services systemd     : ${UNITS[*]:-aucun}"
plan "Sauvegarde           : $BACKUP_DIR"
echo

if [ "$APPLY" != "1" ]; then
    warn "Simulation uniquement. Relancer avec APPLY=1 pour appliquer."
    exit 0
fi

# ── 3. Sauvegarde + rollback ──────────────────────────────
info "Sauvegarde..."
mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"
cp -a "$NGINX_VHOST" "$BACKUP_DIR/vhost.conf"
getfacl -R -p "$APP_DIR" 2>/dev/null | gzip > "$BACKUP_DIR/permissions.acl.gz"
for u in "${UNITS[@]}"; do cp -a "$u" "$BACKUP_DIR/$(basename "$u")"; done

{
    echo '#!/bin/bash'
    echo "# Annule l'isolation de ${SITE} ($(date))"
    echo 'set -u'
    echo "cp -a '$BACKUP_DIR/vhost.conf' '$NGINX_VHOST'"
    for u in "${UNITS[@]}"; do echo "cp -a '$BACKUP_DIR/$(basename "$u")' '$u'"; done
    echo "rm -f '$POOL_FILE'"
    echo "gunzip -c '$BACKUP_DIR/permissions.acl.gz' | setfacl --restore=-"
    echo 'systemctl daemon-reload'
    echo "systemctl reload '$FPM_SERVICE'"
    echo 'nginx -t && systemctl reload nginx'
    for u in "${UNITS[@]}"; do echo "systemctl restart '$(basename "$u")'"; done
    echo "echo 'Rollback terminé. (Utilisateur $SITE_USER conservé, sans effet.)'"
} > "$BACKUP_DIR/rollback.sh"
chmod 700 "$BACKUP_DIR/rollback.sh"

ROLLBACK_HINT="bash $BACKUP_DIR/rollback.sh"

# ── 4. Utilisateur et dossiers privés ─────────────────────
if id -u "$SITE_USER" &>/dev/null; then
    info "Utilisateur $SITE_USER déjà présent."
else
    info "Création de l'utilisateur $SITE_USER..."
    NOLOGIN="$(command -v nologin || echo /usr/sbin/nologin)"
    useradd --system --user-group --no-create-home --home-dir "$APP_DIR" --shell "$NOLOGIN" "$SITE_USER"
fi

mkdir -p "$DATA_DIR/tmp" "$DATA_DIR/sessions"
chown -R "$SITE_USER:$SITE_USER" "$DATA_DIR"
chmod -R 700 "$DATA_DIR"

# ── 5. Pool PHP-FPM ───────────────────────────────────────
info "Écriture du pool $POOL_FILE..."
{
    echo "; Pool isolé du site ${SITE} — généré par isolate-site.sh le $(date +%F)"
    echo "[${SITE}]"
    echo "user = ${SITE_USER}"
    echo "group = ${SITE_USER}"
    echo "listen = ${SOCK}"
    echo "listen.owner = ${WEB_USER}"
    echo "listen.group = ${WEB_GROUP}"
    echo "listen.mode = 0660"
    echo
    echo "; ondemand : aucun process tant qu'il n'y a pas de trafic (VPS partagé)."
    echo "pm = ondemand"
    echo "pm.max_children = ${MAX_CHILDREN}"
    echo "pm.process_idle_timeout = 10s"
    echo "pm.max_requests = 500"
    echo
    echo "security.limit_extensions = .php"
    echo "php_admin_value[upload_tmp_dir] = ${DATA_DIR}/tmp"
    echo "php_admin_value[sys_temp_dir] = ${DATA_DIR}/tmp"
    echo "php_admin_value[session.save_path] = ${DATA_DIR}/sessions"
    echo "php_admin_flag[log_errors] = on"
    echo "php_admin_value[error_log] = ${DATA_DIR}/php-error.log"
    echo "php_admin_flag[expose_php] = off"
    if [ "$OPEN_BASEDIR" = "1" ]; then
        echo "php_admin_value[open_basedir] = ${APP_DIR}:${DATA_DIR}:/usr/share/php:/dev/urandom"
    fi
    if [ "$HARDEN" = "1" ]; then
        echo "php_admin_value[disable_functions] = exec,passthru,shell_exec,system,proc_open,popen,pcntl_exec"
    fi
} > "$POOL_FILE"

if ! "$FPM_BIN" -t 2>&1 | tail -2; then
    rm -f "$POOL_FILE"
    error "Configuration PHP-FPM invalide, pool retiré. Rien d'autre n'a été modifié."
fi
systemctl reload "$FPM_SERVICE"
for _ in 1 2 3 4 5 6 7 8 9 10; do [ -S "$SOCK" ] && break; sleep 1; done
[ -S "$SOCK" ] || { rm -f "$POOL_FILE"; systemctl reload "$FPM_SERVICE"; error "Socket $SOCK non créé, pool retiré."; }

# ── 6. Permissions ────────────────────────────────────────
# PHP tourne sous SITE_USER (propriétaire, lecture/écriture) ; nginx lit via le
# groupe. Le bit setgid garde le groupe nginx sur les fichiers créés ensuite
# (uploads, cache). « X » conserve l'exécution des binaires déjà exécutables.
info "Permissions..."
chown -R "$SITE_USER:$WEB_GROUP" "$APP_DIR"
chmod -R u+rwX,g+rX,g-w,o-rwx "$APP_DIR"
find "$APP_DIR" -type d -exec chmod g+s {} +
find "$APP_DIR" -maxdepth 3 -name '.env*' -type f -exec chmod 600 {} +

# ── 7. Nginx ──────────────────────────────────────────────
info "Bascule de nginx vers le nouveau socket..."
sed -i -E "s#^(\s*fastcgi_pass\s+)[^;]+;#\1unix:${SOCK};#" "$NGINX_VHOST"
if ! nginx -t 2>&1 | tail -2; then
    cp -a "$BACKUP_DIR/vhost.conf" "$NGINX_VHOST"
    error "nginx -t a échoué, vhost restauré. Pour tout annuler : $ROLLBACK_HINT"
fi
systemctl reload nginx

# ── 8. Workers systemd (queue Laravel…) ───────────────────
for u in "${UNITS[@]}"; do
    info "Worker $(basename "$u") → $SITE_USER"
    sed -i -E "s#^User=.*#User=${SITE_USER}#; s#^Group=.*#Group=${SITE_USER}#" "$u"
done
if [ "${#UNITS[@]}" -gt 0 ]; then
    systemctl daemon-reload
    for u in "${UNITS[@]}"; do systemctl restart "$(basename "$u")" || warn "$(basename "$u") non redémarré."; done
fi

# ── 9. Vérifications ──────────────────────────────────────
echo
info "Vérifications..."
if [ -n "$DOMAIN" ] && command -v curl &>/dev/null; then
    CODE="$(curl -sk -o /dev/null -w '%{http_code}' --resolve "${DOMAIN}:443:127.0.0.1" "https://${DOMAIN}/" 2>/dev/null || true)"
    if [ "${CODE:-000}" = "000" ]; then
        CODE="$(curl -s -o /dev/null -w '%{http_code}' -H "Host: ${DOMAIN}" http://127.0.0.1/ 2>/dev/null || true)"
    fi
    case "$CODE" in
        2*|3*) info "${DOMAIN} répond ${CODE}." ;;
        *)     warn "${DOMAIN} répond ${CODE}. Vérifier le site ; pour annuler : $ROLLBACK_HINT" ;;
    esac
fi
if ps -o user= -C "$(basename "$FPM_BIN")" 2>/dev/null | grep -q "^${SITE_USER}$" ; then
    info "PHP tourne bien sous $SITE_USER."
else
    info "Aucun process PHP pour l'instant (normal en mode ondemand avant la première requête)."
fi
[ -s "$DATA_DIR/php-error.log" ] && warn "Erreurs PHP : tail $DATA_DIR/php-error.log"

# Tâches cron encore lancées sous un autre utilisateur
for u in root "$WEB_USER"; do
    if crontab -l -u "$u" 2>/dev/null | grep -qF "$APP_DIR"; then
        warn "Le crontab de '$u' lance encore $APP_DIR. Déplacer ces lignes dans : crontab -u $SITE_USER -e"
    fi
done

echo
info "Isolation de « ${SITE} » terminée."
echo "  Utilisateur : $SITE_USER"
echo "  Pool        : $POOL_FILE"
echo "  Logs PHP    : $DATA_DIR/php-error.log"
echo "  Annuler     : $ROLLBACK_HINT"
echo
echo "  Étape suivante recommandée : un utilisateur MySQL par site, limité à sa base :"
echo "    CREATE USER '${SITE}_app'@'localhost' IDENTIFIED BY '<mot-de-passe-fort>';"
echo "    GRANT ALL PRIVILEGES ON \`<base_du_site>\`.* TO '${SITE}_app'@'localhost';"
echo "  puis mettre DB_USERNAME / DB_PASSWORD à jour dans le .env du site."
