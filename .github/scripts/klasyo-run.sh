#!/usr/bin/env bash
# KLASYO — Phase 1 / Semaine 1, passe 6 : nettoyage final + confirmation.
# Supprime tout fichier de diagnostic laissé dans les public/, purge caches Laravel,
# et confirme l'état HTTP final des deux apps.
set -uo pipefail

ROOT="$HOME/domains/klasyo.org/public_html"
S="$ROOT/school"
P="$ROOT/platform"

echo "== 1. Suppression des fichiers de diagnostic éventuels =="
for f in "$S"/public/klasyo_h.php "$S"/public/klasyo_diag*.php "$P"/public/klasyo_diag*.php; do
  if [ -e "$f" ]; then rm -f "$f" && echo "  supprimé : $(basename "$f")"; fi
done
echo "  Restes klasyo_* dans les public/ :"
find "$S/public" "$P/public" -maxdepth 1 -name 'klasyo_*' 2>/dev/null || true
echo "  (liste vide = propre)"

echo
echo "== 2. Purge caches Laravel (school) après changement de handler =="
(cd "$S" && php artisan config:clear 2>&1 | tail -1)
(cd "$S" && php artisan cache:clear  2>&1 | tail -1) || true
(cd "$S" && php artisan view:clear   2>&1 | tail -1)

echo
echo "== 3. Confirmation HTTP finale =="
for u in "https://klasyo.org/" \
         "https://klasyo.org/platform/index.php" \
         "https://klasyo.org/school/" \
         "https://klasyo.org/school/login" ; do
  out=$(curl -skL -o /dev/null -m 15 -w "%{http_code} (%{time_total}s)" "$u" 2>&1)
  echo "  $u -> $out"
done
echo "  -- sécurité (403/404 attendus) :"
for u in "https://klasyo.org/school/.env" "https://klasyo.org/platform/.env" \
         "https://klasyo.org/school/public/klasyo_h.php" ; do
  code=$(curl -sk -o /dev/null -m 10 -w "%{http_code}" "$u")
  echo "  $u -> $code"
done

echo
echo "== FIN passe 6 — Phase 1 / Semaine 1 (P0) terminée =="
