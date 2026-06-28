#!/bin/bash
# ============================================================
# GOVIBE Innovation Hub — VPS Deployment Script
# Domain: govibeht.com | Branch: main (after PR merge)
# Run as root or a user with sudo privileges
# Usage: bash deploy.sh
# ============================================================

set -e

APP_DIR="/var/www/govibe"
REPO_URL="https://github.com/Rooseveltfhr/govibe.git"
BRANCH="main"
DOMAIN="govibeht.com"
PHP_VERSION="8.3"
DB_NAME="govibe_prod"
DB_USER="govibe_user"

# ── Colors ─────────────────────────────────────────────────
GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; NC='\033[0m'
info()  { echo -e "${GREEN}[INFO]${NC} $1"; }
warn()  { echo -e "${YELLOW}[WARN]${NC} $1"; }
error() { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }

# ── 1. System packages ────────────────────────────────────
info "Installing system dependencies..."
apt-get update -qq
apt-get install -y -qq \
    nginx \
    mysql-server \
    php${PHP_VERSION}-fpm \
    php${PHP_VERSION}-cli \
    php${PHP_VERSION}-mysql \
    php${PHP_VERSION}-sqlite3 \
    php${PHP_VERSION}-mbstring \
    php${PHP_VERSION}-xml \
    php${PHP_VERSION}-bcmath \
    php${PHP_VERSION}-curl \
    php${PHP_VERSION}-zip \
    php${PHP_VERSION}-gd \
    php${PHP_VERSION}-intl \
    unzip \
    git \
    certbot \
    python3-certbot-nginx

# Composer
if ! command -v composer &>/dev/null; then
    info "Installing Composer..."
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

# ── 2. MySQL database & user ─────────────────────────────
info "Setting up MySQL..."
read -sp "Enter a password for MySQL user '${DB_USER}': " DB_PASS
echo
mysql -u root <<SQL
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
info "Database '${DB_NAME}' and user '${DB_USER}' created."

# ── 3. Clone / pull repository ────────────────────────────
if [ -d "$APP_DIR/.git" ]; then
    info "Updating existing repository..."
    cd "$APP_DIR"
    git fetch origin && git checkout "$BRANCH" && git pull origin "$BRANCH"
else
    info "Cloning repository..."
    git clone --branch "$BRANCH" "$REPO_URL" "$APP_DIR"
    cd "$APP_DIR"
fi

# ── 4. Composer dependencies ─────────────────────────────
info "Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# ── 5. Environment file ──────────────────────────────────
if [ ! -f "$APP_DIR/.env" ]; then
    info "Creating .env file..."
    cp "$APP_DIR/.env.example" "$APP_DIR/.env"
    php artisan key:generate

    # Write production values
    sed -i "s|APP_ENV=local|APP_ENV=production|g"       .env
    sed -i "s|APP_DEBUG=true|APP_DEBUG=false|g"          .env
    sed -i "s|APP_URL=http://localhost|APP_URL=https://${DOMAIN}|g" .env
    sed -i "s|DB_CONNECTION=sqlite|DB_CONNECTION=mysql|g" .env
    sed -i "s|# DB_HOST=127.0.0.1|DB_HOST=127.0.0.1|g"  .env
    sed -i "s|# DB_PORT=3306|DB_PORT=3306|g"             .env
    sed -i "s|# DB_DATABASE=laravel|DB_DATABASE=${DB_NAME}|g" .env
    sed -i "s|# DB_USERNAME=root|DB_USERNAME=${DB_USER}|g"    .env
    sed -i "s|# DB_PASSWORD=|DB_PASSWORD=${DB_PASS}|g"        .env
    sed -i "s|MAIL_MAILER=log|MAIL_MAILER=smtp|g"             .env
    sed -i "s|MAIL_FROM_ADDRESS=\"hello@example.com\"|MAIL_FROM_ADDRESS=\"govibeht@gmail.com\"|g" .env
    sed -i "s|MAIL_FROM_NAME=\"\${APP_NAME}\"|MAIL_FROM_NAME=\"GOVIBE Academy\"|g" .env

    warn "Review /var/www/govibe/.env and set MAIL_HOST, MAIL_USERNAME, MAIL_PASSWORD for email."
else
    warn ".env already exists — skipping creation."
fi

# ── 6. Database migrations & seeders ────────────────────
info "Running migrations..."
php artisan migrate --force
php artisan db:seed --class=DatabaseSeeder --force
php artisan db:seed --class=ERPSeeder --force

# ── 7. Optimize for production ───────────────────────────
info "Optimizing..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link

# ── 8. File permissions ──────────────────────────────────
info "Setting permissions..."
chown -R www-data:www-data "$APP_DIR"
chmod -R 755 "$APP_DIR"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

# ── 9. Nginx virtual host ────────────────────────────────
info "Configuring Nginx..."
cat > /etc/nginx/sites-available/govibe <<NGINX
server {
    listen 80;
    server_name ${DOMAIN} www.${DOMAIN};
    root ${APP_DIR}/public;
    index index.php;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header Referrer-Policy "no-referrer-when-downgrade";

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \\.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    location ~ /\\.(?!well-known).* {
        deny all;
    }

    client_max_body_size 20M;
}
NGINX

ln -sf /etc/nginx/sites-available/govibe /etc/nginx/sites-enabled/govibe
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx

# ── 10. SSL certificate ───────────────────────────────────
info "Obtaining SSL certificate..."
certbot --nginx -d "$DOMAIN" -d "www.${DOMAIN}" --non-interactive --agree-tos -m govibeht@gmail.com
systemctl reload nginx

# ── 11. Queue worker (optional) ───────────────────────────
cat > /etc/systemd/system/govibe-worker.service <<SYSTEMD
[Unit]
Description=GOVIBE Laravel Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=${APP_DIR}
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=on-failure
RestartSec=5s

[Install]
WantedBy=multi-user.target
SYSTEMD

systemctl enable govibe-worker
systemctl start govibe-worker

info "✓ Deployment complete!"
echo ""
echo -e "${GREEN}════════════════════════════════════════${NC}"
echo -e "  Site public  : https://${DOMAIN}"
echo -e "  Admin        : https://${DOMAIN}/admin/login"
echo -e "  ERP          : https://${DOMAIN}/erp/login"
echo -e ""
echo -e "  Admin creds  : govibeht@gmail.com / admin@govibe2024"
echo -e "${GREEN}════════════════════════════════════════${NC}"
