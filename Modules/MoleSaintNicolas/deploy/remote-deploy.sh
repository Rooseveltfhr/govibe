#!/usr/bin/env bash
#
# molesaintnicolas.com — finalise un déploiement déjà rsyncé sur le serveur.
# Appelé par la CI après rsync du code vers <base_path>/app/.
# Usage : bash remote-deploy.sh <base_path>
#
# Modèle simple (pas de releases/rollback zero-downtime — inutile pour un
# site à faible trafic sur hébergement mutualisé, et ça compliquait le
# setup initial pour rien) :
#   base_path/
#     app/            code + vendor + assets buildés, rsyncés par la CI
#     app/.env        créé UNE FOIS à la main sur le serveur, jamais écrasé
#                      (exclu du rsync --delete)
#     app/storage/    logs, uploads, sessions — persiste entre déploiements
#                      (exclu du rsync --delete)
#     public_html -> app/public   (symlink, créé une fois à la main)

set -uo pipefail

BASE="${1:?Usage: remote-deploy.sh <base_path>}"
APP="$BASE/app"

echo "==> molesaintnicolas.com deploy ($(date -u +%FT%TZ))"

[ -d "$APP" ] || { echo "App introuvable: $APP"; exit 1; }
[ -f "$APP/.env" ] || { echo "Config manquante: $APP/.env (à créer une fois, à la main, sur le serveur — voir deploy/README.md)"; exit 1; }

cd "$APP"

mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions \
  storage/framework/testing storage/framework/views storage/logs

# Bootstrap : le tout premier déploiement peut laisser des tables orphelines
# côté serveur si un run précédent a échoué en cours de migration (une table
# créée dans un fichier de migration multi-tables sans que la ligne
# `migrations` correspondante soit enregistrée — "table already exists" au
# run suivant). Un sentinel dans storage/ (persistant, exclu du rsync)
# distingue ce cas ponctuel des déploiements suivants : une fois franchi,
# on repasse en migrate --force normal, qui ne touche jamais aux données.
BOOTSTRAP_FLAG="storage/.bootstrapped"
if [ ! -f "$BOOTSTRAP_FLAG" ]; then
  echo "==> Premier déploiement détecté : réinitialisation + seed"
  php artisan migrate:fresh --force --seed || { echo "MIGRATE_FAIL"; exit 1; }
  touch "$BOOTSTRAP_FLAG"
else
  php artisan migrate --force || { echo "MIGRATE_FAIL"; exit 1; }
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

# Smoke test — la homepage doit répondre avant de considérer le déploiement bon.
php -S 127.0.0.1:8973 -t public >/tmp/msn-smoke.log 2>&1 &
SMOKE_PID=$!
sleep 1
CODE=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8973/ || echo "000")
kill "$SMOKE_PID" 2>/dev/null || true

if [ "$CODE" != "200" ]; then
  echo "SMOKE_FAIL: homepage a répondu $CODE"
  cat /tmp/msn-smoke.log || true
  exit 1
fi

echo "==> OK — déploiement terminé"
