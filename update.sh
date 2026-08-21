#!/bin/bash
# ============================================================
# GOVIBE Innovation Hub — Mise à jour d'un déploiement existant
# Domain: govibeht.com
#
# À utiliser pour publier de nouveaux commits sur un serveur
# DÉJÀ provisionné. Pour une première installation, utiliser
# deploy.sh (installe nginx/php/mysql, SSL, worker systemd).
#
# S'exécute aussi bien en root (serveur dédié) que sous le compte
# propriétaire du site (hébergement mutualisé / panel, sans sudo).
#
# Usage:  bash update.sh
#         BRANCH=main APP_DIR=~/domains/govibeht.com/govibe bash update.sh
#         PHP_BIN=/opt/alt/php83/usr/bin/php bash update.sh
# ============================================================

set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/govibe}"
BRANCH="${BRANCH:-main}"

GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; NC='\033[0m'
info()  { echo -e "${GREEN}[INFO]${NC} $1"; }
warn()  { echo -e "${YELLOW}[WARN]${NC} $1"; }
error() { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }

[ -d "$APP_DIR/.git" ] || error "Dépôt introuvable dans $APP_DIR — lancer deploy.sh pour une première installation."

cd "$APP_DIR"

# Deux contextes très différents :
#  - serveur dédié : on tourne en root, l'app est servie par www-data/apache,
#    il faut donc rendre les fichiers à cet utilisateur et piloter systemd ;
#  - hébergement mutualisé / panel : on est l'utilisateur propriétaire du site,
#    sans sudo. Les fichiers nous appartiennent déjà et systemctl est interdit.
# Chown et systemctl ne sont donc tentés qu'en root, et jamais bloquants.
if [ "$(id -u)" -eq 0 ]; then
    IS_ROOT=1
    if id -u www-data &>/dev/null; then PHP_USER="www-data"; else PHP_USER="apache"; fi
else
    IS_ROOT=0
    info "Exécution sans privilèges root — permissions système et redémarrage des services ignorés."
fi

# Sur un panel, « php » peut pointer vers une version trop ancienne pour
# l'application (composer.json exige >= 8.3). On cherche un binaire adapté.
PHP_BIN="${PHP_BIN:-}"
if [ -z "$PHP_BIN" ]; then
    for cand in php php8.4 php8.3 /usr/local/bin/php8.3 /opt/alt/php83/usr/bin/php; do
        command -v "$cand" &>/dev/null || [ -x "$cand" ] || continue
        if "$cand" -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);' 2>/dev/null; then
            PHP_BIN="$cand"; break
        fi
    done
fi
[ -n "$PHP_BIN" ] || error "Aucun PHP >= 8.3 trouvé (requis par composer.json). Définir PHP_BIN=/chemin/vers/php."
info "PHP utilisé : $PHP_BIN ($("$PHP_BIN" -r 'echo PHP_VERSION;'))"

# Composer peut ne pas être dans le PATH ; un composer.phar local fait l'affaire.
if command -v composer &>/dev/null; then
    COMPOSER_CMD="$PHP_BIN $(command -v composer)"
elif [ -f composer.phar ]; then
    COMPOSER_CMD="$PHP_BIN composer.phar"
else
    error "Composer introuvable (ni dans le PATH, ni composer.phar dans $APP_DIR)."
fi

# ── 1. Récupérer le code ──────────────────────────────────
info "Récupération de la branche '$BRANCH'..."
git fetch origin "$BRANCH"

if ! git diff --quiet || ! git diff --cached --quiet; then
    warn "Modifications locales détectées — sauvegarde dans un stash horodaté."
    git stash push -u -m "update.sh $(date -u +%Y-%m-%dT%H:%M:%SZ)"
fi

# Le serveur peut être resté sur une autre branche (deploy.sh la codait en dur
# avant #118) : la branche cible n'existe alors pas encore localement.
if git show-ref --verify --quiet "refs/heads/$BRANCH"; then
    git checkout "$BRANCH"
    git pull --ff-only origin "$BRANCH"
else
    info "Branche '$BRANCH' absente localement — création depuis origin."
    git checkout -b "$BRANCH" "origin/$BRANCH"
fi
info "Code à jour : $(git log --oneline -1)"

# ── 2. Mode maintenance ───────────────────────────────────
# --render évite l'erreur 500 si les caches sont reconstruits pendant une requête.
"$PHP_BIN" artisan down --render="errors::503" --retry=15 || warn "Impossible d'activer le mode maintenance."
trap '"$PHP_BIN" artisan up || true' EXIT

# ── 3. Dépendances ────────────────────────────────────────
info "Installation des dépendances PHP..."
$COMPOSER_CMD install --no-dev --optimize-autoloader --no-interaction

# ── 4. Migrations ─────────────────────────────────────────
# --force : obligatoire en production (pas de confirmation interactive).
info "Migrations de base de données..."
"$PHP_BIN" artisan migrate --force

# ── 5. Caches ─────────────────────────────────────────────
# On vide AVANT de reconstruire : un cache de config obsolète peut
# pointer vers d'anciennes routes/vues et provoquer des 500.
info "Reconstruction des caches..."
"$PHP_BIN" artisan config:clear
"$PHP_BIN" artisan route:clear
"$PHP_BIN" artisan view:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

[ -L "public/storage" ] || "$PHP_BIN" artisan storage:link

# ── 6. Permissions ────────────────────────────────────────
# storage/ et bootstrap/cache doivent rester inscriptibles quel que soit le
# contexte ; le chown, lui, n'a de sens qu'en root sur un serveur dédié.
info "Permissions..."
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" 2>/dev/null \
    || warn "Permissions de storage/ non modifiées."
if [ "$IS_ROOT" -eq 1 ]; then
    chown -R "${PHP_USER}:${PHP_USER}" "$APP_DIR"
    chmod -R 755 "$APP_DIR"
fi

# ── 7. Redémarrage des services ───────────────────────────
# Sans root, systemctl est refusé — et inutile : le panel gère PHP-FPM, et
# OPcache relit les fichiers modifiés dès lors que validate_timestamps est actif.
if [ "$IS_ROOT" -eq 1 ]; then
    info "Redémarrage des services..."
    systemctl reload nginx 2>/dev/null || warn "Nginx non rechargé."
    systemctl restart php*-fpm 2>/dev/null || systemctl restart php-fpm 2>/dev/null || warn "PHP-FPM non redémarré."
    # Le worker doit repartir pour charger le nouveau code en mémoire.
    systemctl restart govibe-worker 2>/dev/null || warn "Worker govibe-worker non redémarré."
else
    # Le worker éventuel doit tout de même relire le code : queue:restart pose
    # un drapeau que les workers lisent entre deux jobs, sans privilèges.
    "$PHP_BIN" artisan queue:restart 2>/dev/null || true
fi

# ── 8. Fin ────────────────────────────────────────────────
"$PHP_BIN" artisan up
trap - EXIT

echo ""
echo -e "${GREEN}════════════════════════════════════════${NC}"
echo -e "  ✓ Mise à jour terminée !"
echo -e ""
echo -e "  Site    : https://govibeht.com"
echo -e "  Commit  : $(git log --oneline -1)"
echo -e "${GREEN}════════════════════════════════════════${NC}"
