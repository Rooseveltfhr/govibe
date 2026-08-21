#!/bin/bash
# ============================================================
# GOVIBE Innovation Hub — Mise à jour d'un déploiement existant
# Domain: govibeht.com
#
# À utiliser pour publier de nouveaux commits sur un serveur
# DÉJÀ provisionné. Pour une première installation, utiliser
# deploy.sh (installe nginx/php/mysql, SSL, worker systemd).
#
# Usage:  sudo bash update.sh
#         sudo BRANCH=main APP_DIR=/var/www/govibe bash update.sh
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

# Détecte l'utilisateur PHP-FPM pour restaurer les permissions à la fin.
if id -u www-data &>/dev/null; then PHP_USER="www-data"; else PHP_USER="apache"; fi

# ── 1. Récupérer le code ──────────────────────────────────
info "Récupération de la branche '$BRANCH'..."
git fetch origin "$BRANCH"

if ! git diff --quiet || ! git diff --cached --quiet; then
    warn "Modifications locales détectées — sauvegarde dans un stash horodaté."
    git stash push -u -m "update.sh $(date -u +%Y-%m-%dT%H:%M:%SZ)"
fi

git checkout "$BRANCH"
git pull --ff-only origin "$BRANCH"
info "Code à jour : $(git log --oneline -1)"

# ── 2. Mode maintenance ───────────────────────────────────
# --render évite l'erreur 500 si les caches sont reconstruits pendant une requête.
php artisan down --render="errors::503" --retry=15 || warn "Impossible d'activer le mode maintenance."
trap 'php artisan up || true' EXIT

# ── 3. Dépendances ────────────────────────────────────────
info "Installation des dépendances PHP..."
composer install --no-dev --optimize-autoloader --no-interaction

# ── 4. Migrations ─────────────────────────────────────────
# --force : obligatoire en production (pas de confirmation interactive).
info "Migrations de base de données..."
php artisan migrate --force

# ── 5. Caches ─────────────────────────────────────────────
# On vide AVANT de reconstruire : un cache de config obsolète peut
# pointer vers d'anciennes routes/vues et provoquer des 500.
info "Reconstruction des caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

[ -L "public/storage" ] || php artisan storage:link

# ── 6. Permissions ────────────────────────────────────────
info "Permissions..."
chown -R "${PHP_USER}:${PHP_USER}" "$APP_DIR"
chmod -R 755 "$APP_DIR"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

# ── 7. Redémarrage des services ───────────────────────────
info "Redémarrage des services..."
systemctl reload nginx 2>/dev/null || warn "Nginx non rechargé."
systemctl restart php*-fpm 2>/dev/null || systemctl restart php-fpm 2>/dev/null || warn "PHP-FPM non redémarré."
# Le worker doit repartir pour charger le nouveau code en mémoire.
systemctl restart govibe-worker 2>/dev/null || warn "Worker govibe-worker non redémarré."

# ── 8. Fin ────────────────────────────────────────────────
php artisan up
trap - EXIT

echo ""
echo -e "${GREEN}════════════════════════════════════════${NC}"
echo -e "  ✓ Mise à jour terminée !"
echo -e ""
echo -e "  Site    : https://govibeht.com"
echo -e "  Commit  : $(git log --oneline -1)"
echo -e "${GREEN}════════════════════════════════════════${NC}"
