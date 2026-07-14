#!/usr/bin/env bash
# KLASYO — Phase 1 / Semaine 1 : correctifs P0 sécurité + réparation de /school.
# Exécuté sur le VPS via GitHub Actions (klasyo-ops.yml : ssh ... bash -s < ce script).
#
# SÉCURITÉ (les logs Actions sont PUBLICS) :
# - ne jamais afficher le contenu de .env, de clés ni de credentials
# - toute modification est précédée d'un backup ; rien n'est supprimé (déplacé vers ~/backups_klasyo)
# - idempotent : ré-exécutable sans danger
set -uo pipefail

ROOT="$HOME/domains/klasyo.org/public_html"
P="$ROOT/platform"
S="$ROOT/school"
BK="$HOME/backups_klasyo"
STAMP="$(date +%Y%m%d-%H%M%S)"
mkdir -p "$BK"

echo "########################################"
echo "== ÉTAPE 1 : zips de code source hors du web =="
echo "########################################"
move_out() {
  local f="$1"
  if [ -e "$f" ]; then
    mv "$f" "$BK/" && echo "  DÉPLACÉ -> backups_klasyo/ : $(basename "$f")"
  else
    echo "  (déjà absent) $(basename "$f")"
  fi
}
move_out "$P/klasyo.zip"
move_out "$S/school.zip"
move_out "$S/source_code.zip"
move_out "$S/New_Installation_V1.9.3.zip"
move_out "$S/Update 1.9.2-to-1.9.3.zip"
move_out "$S/eSchool Sass student web v-1.9.3.zip"
move_out "$S/eSchool-Saas-V1.9.3"
# Fichiers parasites / fuite d'identité du script
move_out "$P/index.html"          # ancien index statique "webetud.com" (parasite)
move_out "$ROOT/documentation.txt"
move_out "$ROOT/update_note.json"
move_out "$P/documentation.txt"
move_out "$P/update_note.json"
echo "  Contenu de backups_klasyo (noms seulement) :"
ls "$BK" | head -20

echo
echo "########################################"
echo "== ÉTAPE 2 : correction des .env (backup préalable) =="
echo "########################################"
fix_env() {
  local envfile="$1"; shift
  [ -f "$envfile" ] || { echo "  (!) introuvable : $envfile"; return; }
  cp -a "$envfile" "$envfile.bak-$STAMP"
  echo "  backup : $(basename "$envfile").bak-$STAMP"
  while [ $# -gt 0 ]; do
    local key="${1%%=*}"
    if grep -q "^${key}=" "$envfile"; then
      sed -i "s|^${key}=.*|${1}|" "$envfile"
    else
      printf '%s\n' "$1" >> "$envfile"
    fi
    shift
  done
}
fix_env "$P/.env" "APP_ENV=production" "APP_DEBUG=false" "APP_URL=https://klasyo.org/platform"
fix_env "$S/.env" "APP_ENV=production" "APP_DEBUG=false" "APP_URL=https://klasyo.org/school"

echo "  Vérification (clés non sensibles uniquement) :"
echo "  --- platform :"; grep -E '^(APP_ENV|APP_DEBUG|APP_URL)=' "$P/.env"
echo "  --- school   :"; grep -E '^(APP_ENV|APP_DEBUG|APP_URL)=' "$S/.env"

echo
echo "########################################"
echo "== ÉTAPE 3 : APP_KEY de school =="
echo "########################################"
if grep -q '^APP_KEY=base64:.\+' "$S/.env"; then
  echo "  APP_KEY déjà présente (non affichée)."
else
  echo "  APP_KEY absente -> génération…"
  (cd "$S" && php artisan key:generate --force 2>&1 | grep -iv 'base64') || echo "  (!) échec key:generate"
  grep -q '^APP_KEY=base64:.\+' "$S/.env" && echo "  APP_KEY générée avec succès (non affichée)."
fi
# Platform : contrôle seulement
grep -q '^APP_KEY=base64:.\+' "$P/.env" && echo "  APP_KEY platform : présente." || echo "  (!) APP_KEY platform ABSENTE"

echo
echo "########################################"
echo "== ÉTAPE 4 : purge des caches Laravel =="
echo "########################################"
for APPDIR in "$P" "$S"; do
  echo "  --- $(basename "$APPDIR") :"
  (cd "$APPDIR" && php artisan config:clear 2>&1 | tail -1)
  (cd "$APPDIR" && php artisan route:clear  2>&1 | tail -1)
  (cd "$APPDIR" && php artisan view:clear   2>&1 | tail -1)
  (cd "$APPDIR" && php artisan cache:clear  2>&1 | tail -1) || true
done
echo "  Version PHP CLI : $(php -r 'echo PHP_VERSION;')"

echo
echo "########################################"
echo "== ÉTAPE 5 : vérification HTTP (codes seulement) =="
echo "########################################"
for u in "https://klasyo.org/" \
         "https://klasyo.org/platform/index.php" \
         "https://klasyo.org/platform/public/" \
         "https://klasyo.org/school" \
         "https://klasyo.org/school/public/index.php" ; do
  code=$(curl -skL -o /dev/null -m 12 -w "%{http_code}" "$u")
  echo "  $u -> $code"
done
echo "  -- exposition (on veut 403/404 partout) :"
for u in "https://klasyo.org/platform/.env" "https://klasyo.org/school/.env" \
         "https://klasyo.org/platform/klasyo.zip" "https://klasyo.org/school/school.zip"; do
  code=$(curl -sk -o /dev/null -m 10 -r 0-0 -w "%{http_code}" "$u")
  echo "  $u -> $code"
done

echo
echo "== Dernière erreur Laravel school (si encore en panne, message tronqué sans données) =="
L=$(ls -t "$S"/storage/logs/*.log 2>/dev/null | head -1)
[ -n "$L" ] && grep -oE '^\[[0-9 :-]+\] \w+\.\w+: [^{]{0,120}' "$L" | tail -3 || echo "(pas de log)"

echo
echo "== FIN Phase 1 / Semaine 1 (P0) =="
