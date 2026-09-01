#!/usr/bin/env bash
#
# molesaintnicolas.com — bascule une release déjà rsyncée sur le serveur.
# Appelé par la CI après rsync de la release vers releases/<timestamp>/.
# Usage : bash remote-deploy.sh <base_path> <release_dir_name>
#
# Modèle "releases" (zéro-downtime, rollback trivial) :
#   base_path/
#     releases/<timestamp>/   (code + vendor + assets buildés, rsyncés par la CI)
#     shared/.env
#     shared/storage/         (logs, uploads, sessions — persiste entre releases)
#     current -> releases/<timestamp>  (symlink, bascule seulement si tout est vert)
#
# Le rollback est trivial : si le smoke test échoue, `current` n'est jamais
# basculé — le site continue de servir l'ancienne release, intacte.

set -uo pipefail

BASE="${1:?Usage: remote-deploy.sh <base_path> <release_dir_name>}"
RELEASE_NAME="${2:?Usage: remote-deploy.sh <base_path> <release_dir_name>}"
RELEASE="$BASE/releases/$RELEASE_NAME"
SHARED="$BASE/shared"

echo "==> molesaintnicolas.com deploy — release $RELEASE_NAME ($(date -u +%FT%TZ))"

[ -d "$RELEASE" ] || { echo "Release introuvable: $RELEASE"; exit 1; }
[ -f "$SHARED/.env" ] || { echo "Config manquante: $SHARED/.env (à créer une fois, à la main, sur le serveur)"; exit 1; }

cd "$RELEASE"

# 1) Liens vers le partagé (config + stockage persistant, jamais dans la release)
ln -sfn "$SHARED/.env" "$RELEASE/.env"
rm -rf "$RELEASE/storage"
ln -sfn "$SHARED/storage" "$RELEASE/storage"

# 2) Migrations (idempotent, jamais destructif — pas de migrate:fresh en prod)
php artisan migrate --force || { echo "MIGRATE_FAIL — release non activée, current inchangé"; exit 1; }

# 3) Caches (config/route/view) — la release ne devient "vraie" qu'une fois optimisée
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 4) Smoke test — la homepage doit répondre AVANT de basculer le trafic
php -S 127.0.0.1:8973 -t public >/tmp/msn-smoke.log 2>&1 &
SMOKE_PID=$!
sleep 1
CODE=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:8973/ || echo "000")
kill "$SMOKE_PID" 2>/dev/null || true

if [ "$CODE" != "200" ]; then
  echo "SMOKE_FAIL: homepage a répondu $CODE — current inchangé, rollback automatique (aucune bascule)"
  cat /tmp/msn-smoke.log || true
  exit 1
fi

# 5) Bascule atomique — seule cette ligne rend la release live
ln -sfn "$RELEASE" "$BASE/current"
echo "==> OK — current -> releases/$RELEASE_NAME"

# 6) Ménage : garder les 5 dernières releases pour un rollback manuel rapide
cd "$BASE/releases" && ls -1t | tail -n +6 | xargs -r rm -rf
