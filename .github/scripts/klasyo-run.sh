#!/usr/bin/env bash
# KLASYO — Étape C : réparer le hang des routes platform (server.php sans handler PHP).
# CLI prouve que Laravel/login rendent bien (2s). Le hang est au niveau du handler PHP
# du .htaccess RACINE de platform (server.php n'a pas de SetHandler lsphp).
# On pose le handler et on valide contre les VRAIES routes /login /register. Backup + idempotent.
set -uo pipefail

ROOT="$HOME/domains/klasyo.org/public_html"
P="$ROOT/platform"
STAMP="$(date +%Y%m%d-%H%M%S)"
RHTA="$P/.htaccess"

cp -a "$RHTA" "$RHTA.bak-$STAMP" && echo "backup: platform/.htaccess.bak-$STAMP"
# Retire un éventuel ancien bloc KLASYO (idempotence)
sed -i '/# KLASYO-PHP-HANDLER/,/<\/FilesMatch>/d' "$RHTA" 2>/dev/null || true

echo "== .htaccess racine platform AVANT (handlers) :"
grep -niE 'sethandler|lsphp|server.php' "$RHTA" || echo "  (aucun handler)"

WINNER=""
for V in 80 81 82 83; do
  sed -i '/# KLASYO-PHP-HANDLER/,/<\/FilesMatch>/d' "$RHTA" 2>/dev/null || true
  cat >> "$RHTA" <<EOF
# KLASYO-PHP-HANDLER
<FilesMatch "\.(php|phtml)\$">
SetHandler application/x-lsphp${V}
</FilesMatch>
EOF
  # Valider contre la vraie route /login (celle qui hangait)
  code=$(curl -sk -o /dev/null -m 12 -w "%{http_code}" "https://klasyo.org/platform/login" 2>&1)
  echo "  lsphp${V} -> /platform/login = $code"
  if [ "$code" != "000" ] && [ "$code" != "" ]; then
    WINNER="$V"; echo "  ==> lsphp${V} débloque les routes platform."
    break
  fi
done

if [ -z "$WINNER" ]; then
  echo "  (!) Aucun handler ne débloque via .htaccess racine — restauration."
  cp -a "$RHTA.bak-$STAMP" "$RHTA"
else
  echo "  Handler lsphp${WINNER} appliqué à platform/.htaccess (racine)."
fi

echo
echo "== Purge caches + vérification finale des routes platform =="
(cd "$P" && php artisan config:clear 2>&1 | tail -1)
(cd "$P" && php artisan view:clear   2>&1 | tail -1)
for u in "https://klasyo.org/" \
         "https://klasyo.org/platform/" \
         "https://klasyo.org/platform/login" \
         "https://klasyo.org/platform/register" \
         "https://klasyo.org/school/" \
         "https://klasyo.org/school/login" ; do
  out=$(curl -skL -o /dev/null -m 15 -w "%{http_code} (%{time_total}s)" "$u" 2>&1)
  echo "  $u -> $out"
done
echo "  --- <title> page login platform (doit mentionner KLASYO après rebrand) :"
curl -skL -m 12 "https://klasyo.org/platform/login" 2>/dev/null | grep -oiE '<title>[^<]*</title>' | head -1

echo
echo "== FIN étape C =="
