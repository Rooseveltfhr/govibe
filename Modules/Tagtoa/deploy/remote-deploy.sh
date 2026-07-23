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
#      Ne bloque JAMAIS le déploiement. Désactivable : TAGTOA_SEED_DEMO=0
if [ "${TAGTOA_SEED_DEMO:-1}" != "0" ]; then
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

# 5) Caches + réouverture. On vide puis on RECACHE (prod plus rapide : évite de
#    relire ~30 fichiers config + .env à chaque requête). Pas de route:cache
#    (Biztap peut avoir des routes en closure).
php artisan optimize:clear >/dev/null 2>&1 || true
php artisan config:cache >/dev/null 2>&1 || true
php artisan view:cache >/dev/null 2>&1 || true
php artisan up

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
