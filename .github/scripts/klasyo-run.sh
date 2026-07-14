#!/usr/bin/env bash
# KLASYO — Phase 1 / Semaine 1, passe 5 : RÉPARER /school.
# Cause identifiée : school/public/.htaccess n'a AUCUN handler PHP LiteSpeed,
# alors que platform/public en a un (SetHandler application/x-lsphp80) et fonctionne.
# → On détecte le handler lsphp qui répond (83>82>81>80, school=Laravel10 veut PHP 8.1+),
#   puis on l'écrit dans school/public/.htaccess ET school/.htaccess. Backups + idempotent.
set -uo pipefail

ROOT="$HOME/domains/klasyo.org/public_html"
S="$ROOT/school"
STAMP="$(date +%Y%m%d-%H%M%S)"

DIAG="$S/public/klasyo_h.php"
echo '<?php echo "OKHANDLER-".PHP_VERSION;' > "$DIAG"
HTA="$S/public/.htaccess"
cp -a "$HTA" "$HTA.bak-$STAMP" && echo "backup: public/.htaccess.bak-$STAMP"

# Retire tout ancien bloc handler KLASYO déjà posé (idempotence)
sed -i '/# KLASYO-PHP-HANDLER/,/<\/FilesMatch>/d' "$HTA" 2>/dev/null || true

WINNER=""
for V in 83 82 81 80; do
  # Injecte le bloc handler pour cette version
  sed -i '/# KLASYO-PHP-HANDLER/,/<\/FilesMatch>/d' "$HTA" 2>/dev/null || true
  cat >> "$HTA" <<EOF
# KLASYO-PHP-HANDLER
<FilesMatch "\.(php|phtml)\$">
SetHandler application/x-lsphp${V}
</FilesMatch>
EOF
  out=$(curl -sk -m 12 -w "%{http_code}" "https://klasyo.org/school/public/klasyo_h.php" 2>&1)
  body=$(echo "$out" | head -c 60)
  code=$(echo "$out" | tail -c 4)
  echo "  lsphp${V} -> ${body}"
  if echo "$out" | grep -q "OKHANDLER-"; then
    WINNER="$V"
    echo "  ==> lsphp${V} FONCTIONNE (PHP $(echo "$out" | grep -oE 'OKHANDLER-[0-9.]+' | cut -d- -f2))"
    break
  fi
done
rm -f "$DIAG"

if [ -z "$WINNER" ]; then
  echo "  (!) Aucun handler lsphp ne répond via .htaccess — restauration du backup."
  cp -a "$HTA.bak-$STAMP" "$HTA"
  echo "  Il faudra régler la version PHP de /school dans DirectAdmin (MultiPHP/PHP Selector)."
else
  # Le bloc gagnant est déjà en place dans public/.htaccess.
  echo
  echo "== Handler lsphp${WINNER} appliqué à school/public/.htaccess =="
  # Poser aussi le handler dans school/.htaccess (racine du sous-dossier) par cohérence
  RHTA="$S/.htaccess"
  cp -a "$RHTA" "$RHTA.bak-$STAMP" 2>/dev/null
  sed -i '/# KLASYO-PHP-HANDLER/,/<\/FilesMatch>/d' "$RHTA" 2>/dev/null || true
  cat >> "$RHTA" <<EOF
# KLASYO-PHP-HANDLER
<FilesMatch "\.(php|phtml)\$">
SetHandler application/x-lsphp${WINNER}
</FilesMatch>
EOF
  echo "  Handler aussi posé sur school/.htaccess."
fi

echo
echo "== Vérification finale HTTP =="
for u in "https://klasyo.org/school/" \
         "https://klasyo.org/school/login" \
         "https://klasyo.org/school/public/index.php" \
         "https://klasyo.org/platform/index.php" \
         "https://klasyo.org/" ; do
  out=$(curl -skL -o /dev/null -m 15 -w "%{http_code} (redirs=%{num_redirects}, %{time_total}s)" "$u" 2>&1)
  echo "  $u -> $out"
done
echo "  -- sécurité (403/404 attendus) :"
for u in "https://klasyo.org/school/.env" "https://klasyo.org/school/public/klasyo_h.php"; do
  code=$(curl -sk -o /dev/null -m 10 -w "%{http_code}" "$u")
  echo "  $u -> $code"
done

echo
echo "== Nouvelles erreurs Laravel school aujourd'hui (tronquées) =="
L=$(ls -t "$S"/storage/logs/*.log 2>/dev/null | head -1)
[ -n "$L" ] && grep -oE "^\[$(date +%Y-%m-%d)[0-9 :]*\] \w+\.\w+: [^{]{0,110}" "$L" | tail -4 || echo "  (aucune)"

echo
echo "== FIN passe 5 =="
