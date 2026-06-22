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

# 5) Caches + réouverture
php artisan optimize:clear >/dev/null 2>&1 || true
php artisan up

echo "DEPLOY_OK"
