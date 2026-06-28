#!/bin/bash
# ============================================================
# GOVIBE Innovation Hub — VPS Deployment Script
# Compatible: AlmaLinux / Rocky Linux / CentOS / Ubuntu / Debian
# Domain: govibeht.com
# Usage: bash deploy.sh
# ============================================================

set -e

APP_DIR="/var/www/govibe"
REPO_URL="https://github.com/Rooseveltfhr/govibe.git"
BRANCH="claude/govibe-academy-registration-c8j6gd"
DOMAIN="govibeht.com"
DB_NAME="govibe_prod"
DB_USER="govibe_user"

GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; NC='\033[0m'
info()  { echo -e "${GREEN}[INFO]${NC} $1"; }
warn()  { echo -e "${YELLOW}[WARN]${NC} $1"; }
error() { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }

# ── Detect OS & package manager ───────────────────────────
detect_os() {
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        OS_ID=$ID
        OS_VERSION=$VERSION_ID
    fi

    if command -v dnf &>/dev/null; then
        PKG="dnf"
    elif command -v yum &>/dev/null; then
        PKG="yum"
    elif command -v apt-get &>/dev/null; then
        PKG="apt"
    else
        error "Package manager not found (dnf/yum/apt)"
    fi
    info "Detected OS: ${OS_ID:-unknown} | Package manager: $PKG"
}

# ── Install on RHEL-based (AlmaLinux, Rocky, CentOS) ─────
install_rhel() {
    info "Installing dependencies via $PKG..."
    $PKG install -y epel-release 2>/dev/null || true

    # Remi repo for PHP 8.3
    if ! rpm -q remi-release &>/dev/null; then
        $PKG install -y "https://rpms.remirepo.net/enterprise/remi-release-$(rpm -E '%{rhel}').rpm" || true
    fi
    $PKG module reset -y php 2>/dev/null || true
    $PKG module enable -y php:remi-8.3 2>/dev/null || true

    $PKG install -y \
        nginx \
        php \
        php-fpm \
        php-mysqlnd \
        php-mbstring \
        php-xml \
        php-bcmath \
        php-curl \
        php-zip \
        php-gd \
        php-intl \
        php-pdo \
        mariadb-server \
        mariadb \
        git \
        unzip \
        certbot \
        python3-certbot-nginx

    systemctl enable --now mariadb nginx php-fpm

    FPM_SOCKET="/var/run/php-fpm/www.sock"
    PHP_USER="apache"
}

# ── Install on Debian/Ubuntu ──────────────────────────────
install_debian() {
    info "Installing dependencies via apt..."
    apt-get update -qq
    apt-get install -y -qq \
        nginx \
        mysql-server \
        php8.3-fpm \
        php8.3-cli \
        php8.3-mysql \
        php8.3-mbstring \
        php8.3-xml \
        php8.3-bcmath \
        php8.3-curl \
        php8.3-zip \
        php8.3-gd \
        php8.3-intl \
        git \
        unzip \
        certbot \
        python3-certbot-nginx

    FPM_SOCKET="/run/php/php8.3-fpm.sock"
    PHP_USER="www-data"
}

# ── Composer ──────────────────────────────────────────────
install_composer() {
    if ! command -v composer &>/dev/null; then
        info "Installing Composer..."
        curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
    fi
}

# ── MySQL / MariaDB setup ────────────────────────────────
setup_database() {
    info "Setting up database..."
    read -sp "Choisissez un mot de passe pour la base de données MySQL: " DB_PASS
    echo

    mysql -u root <<SQL 2>/dev/null || mysql -u root -p <<SQL
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
    info "Base de données '${DB_NAME}' créée."
}

# ── Clone / update repo ───────────────────────────────────
setup_repo() {
    if [ -d "$APP_DIR/.git" ]; then
        info "Mise à jour du dépôt..."
        cd "$APP_DIR"
        git fetch origin && git checkout "$BRANCH" && git pull origin "$BRANCH"
    else
        info "Clonage du dépôt..."
        mkdir -p /var/www
        git clone --branch "$BRANCH" "$REPO_URL" "$APP_DIR"
        cd "$APP_DIR"
    fi
}

# ── Laravel setup ─────────────────────────────────────────
setup_laravel() {
    cd "$APP_DIR"

    info "Installation des dépendances PHP..."
    composer install --no-dev --optimize-autoloader --no-interaction

    if [ ! -f ".env" ]; then
        info "Création du fichier .env..."
        cp .env.example .env
        php artisan key:generate

        sed -i "s|APP_ENV=.*|APP_ENV=production|g"              .env
        sed -i "s|APP_DEBUG=.*|APP_DEBUG=false|g"               .env
        sed -i "s|APP_URL=.*|APP_URL=https://${DOMAIN}|g"       .env
        sed -i "s|DB_CONNECTION=.*|DB_CONNECTION=mysql|g"       .env
        sed -i "s|DB_HOST=.*|DB_HOST=127.0.0.1|g"              .env
        sed -i "s|DB_PORT=.*|DB_PORT=3306|g"                   .env
        sed -i "s|DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|g"     .env
        sed -i "s|DB_USERNAME=.*|DB_USERNAME=${DB_USER}|g"     .env
        sed -i "s|DB_PASSWORD=.*|DB_PASSWORD=${DB_PASS}|g"     .env
        sed -i "s|MAIL_MAILER=.*|MAIL_MAILER=smtp|g"           .env
        sed -i "s|MAIL_HOST=.*|MAIL_HOST=smtp.gmail.com|g"     .env
        sed -i "s|MAIL_PORT=.*|MAIL_PORT=587|g"                .env
        sed -i "s|MAIL_USERNAME=.*|MAIL_USERNAME=govibeht@gmail.com|g" .env
        sed -i "s|MAIL_FROM_ADDRESS=.*|MAIL_FROM_ADDRESS=govibeht@gmail.com|g" .env
        sed -i "s|SESSION_DRIVER=.*|SESSION_DRIVER=file|g"     .env
        sed -i "s|CACHE_STORE=.*|CACHE_STORE=file|g"           .env
    else
        warn ".env existe déjà — skipping."
    fi

    info "Migrations..."
    php artisan migrate --force
    php artisan db:seed --class=DatabaseSeeder --force
    php artisan db:seed --class=ERPSeeder --force

    info "Optimisation..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan storage:link

    info "Permissions..."
    chown -R "${PHP_USER}:${PHP_USER}" "$APP_DIR"
    chmod -R 755 "$APP_DIR"
    chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
}

# ── Nginx config ──────────────────────────────────────────
setup_nginx() {
    info "Configuration Nginx..."

    if [ "$PKG" = "apt" ]; then
        NGINX_CONF="/etc/nginx/sites-available/govibe"
        NGINX_LINK="/etc/nginx/sites-enabled/govibe"
        rm -f /etc/nginx/sites-enabled/default
    else
        NGINX_CONF="/etc/nginx/conf.d/govibe.conf"
        NGINX_LINK=""
    fi

    cat > "$NGINX_CONF" <<NGINX
server {
    listen 80;
    server_name ${DOMAIN} www.${DOMAIN};
    root ${APP_DIR}/public;
    index index.php;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:${FPM_SOCKET};
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    client_max_body_size 20M;
}
NGINX

    [ -n "$NGINX_LINK" ] && ln -sf "$NGINX_CONF" "$NGINX_LINK"
    nginx -t && systemctl reload nginx
}

# ── SSL ───────────────────────────────────────────────────
setup_ssl() {
    info "Certificat SSL..."
    certbot --nginx -d "$DOMAIN" -d "www.${DOMAIN}" \
        --non-interactive --agree-tos -m govibeht@gmail.com || \
        warn "SSL échoué — vérifiez que ${DOMAIN} pointe vers ce serveur."
    systemctl reload nginx
}

# ── Queue worker ──────────────────────────────────────────
setup_worker() {
    cat > /etc/systemd/system/govibe-worker.service <<SYSTEMD
[Unit]
Description=GOVIBE Laravel Queue Worker
After=network.target

[Service]
User=${PHP_USER}
Group=${PHP_USER}
WorkingDirectory=${APP_DIR}
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=on-failure
RestartSec=5s

[Install]
WantedBy=multi-user.target
SYSTEMD
    systemctl enable --now govibe-worker
}

# ── MAIN ──────────────────────────────────────────────────
detect_os

if [ "$PKG" = "apt" ]; then
    install_debian
else
    install_rhel
fi

install_composer
setup_database
setup_repo
setup_laravel
setup_nginx
setup_ssl
setup_worker

echo ""
echo -e "${GREEN}════════════════════════════════════════${NC}"
echo -e "  ✓ Déploiement terminé !"
echo -e ""
echo -e "  Site       : https://${DOMAIN}"
echo -e "  Admin      : https://${DOMAIN}/admin/login"
echo -e "  ERP        : https://${DOMAIN}/erp/login"
echo -e "  Login      : govibeht@gmail.com"
echo -e "  Password   : admin@govibe2024"
echo -e "${GREEN}════════════════════════════════════════${NC}"
