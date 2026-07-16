#!/usr/bin/env bash
# KLASYO — Étape E : platform sert le PHP en SOURCE car son handler lsphp80 n'exécute pas
# (PHP 8.0 non enregistré sur ce LiteSpeed). School marche avec lsphp83.
# On DÉTECTE le handler qui EXÉCUTE réellement (on valide le CORPS, pas le code HTTP),
# on l'applique à platform/public/.htaccess, puis on valide que les pages rendent du HTML.
set -uo pipefail

ROOT="$HOME/domains/klasyo.org/public_html"
P="$ROOT/platform"
PUB="$P/public"
STAMP="$(date +%Y%m%d-%H%M%S)"
PHTA="$PUB/.htaccess"

cp -a "$PHTA" "$PHTA.bak-E-$STAMP" && echo "backup: platform/public/.htaccess.bak-E-$STAMP"

# Fichier diag qui, s'il EXÉCUTE, imprime un marqueur unique + la version PHP
DIAG="$PUB/klasyo_exec.php"
echo '<?php echo "LSPHPEXEC-".PHP_VERSION;' > "$DIAG"

echo "== Handler actuel dans platform/public/.htaccess :"
grep -niE 'sethandler|lsphp' "$PHTA" || echo "  (aucun)"

WINNER=""
for V in 83 82 81 80; do
  # Remplace toute valeur x-lsphpNN existante par la version testée
  if grep -qiE 'x-lsphp[0-9]+' "$PHTA"; then
    sed -i -E "s#application/x-lsphp[0-9]+#application/x-lsphp${V}#g" "$PHTA"
  else
    # pas de SetHandler -> on en ajoute un (bloc KLASYO), nettoyé à chaque tour
    sed -i '/# KLASYO-PHP-HANDLER/,/<\/FilesMatch>/d' "$PHTA" 2>/dev/null || true
    printf '# KLASYO-PHP-HANDLER\n<FilesMatch "\\.(php|phtml)$">\nSetHandler application/x-lsphp%s\n</FilesMatch>\n' "$V" >> "$PHTA"
  fi
  body=$(curl -sk -m 12 "https://klasyo.org/platform/public/klasyo_exec.php" 2>/dev/null | head -c 60)
  if printf '%s' "$body" | grep -q "LSPHPEXEC-"; then
    WINNER="$V"
    echo "  lsphp${V} -> EXÉCUTE : ${body}"
    break
  else
    echo "  lsphp${V} -> pas d'exécution (début: $(printf '%s' "$body" | head -c 25))"
  fi
done
rm -f "$DIAG"

if [ -z "$WINNER" ]; then
  echo "  (!) Aucun handler n'exécute — restauration + il faudra fixer la version PHP dans DirectAdmin."
  cp -a "$PHTA.bak-E-$STAMP" "$PHTA"
else
  echo "  ==> Handler lsphp${WINNER} appliqué à platform/public/.htaccess."
fi

echo
echo "== Purge caches =="
(cd "$P" && php artisan config:clear 2>&1 | tail -1)
(cd "$P" && php artisan view:clear   2>&1 | tail -1)

echo
echo "== VALIDATION FINALE — le corps doit être du HTML, jamais du source PHP =="
check() {
  local url="$1"
  local body; body=$(curl -skL -m 15 "$url" 2>/dev/null)
  local code; code=$(curl -skL -o /dev/null -m 15 -w "%{http_code}" "$url" 2>/dev/null)
  if printf '%s' "$body" | grep -qiE '<\?php|Illuminate\\Contracts|Taylor Otwell|require_once __DIR__'; then
    echo "  $url -> [$code] !!! SOURCE PHP"
  elif printf '%s' "$body" | grep -qiE '<!doctype html|<html'; then
    echo "  $url -> [$code] OK HTML | $(printf '%s' "$body" | grep -oiE '<title>[^<]*</title>' | head -1)"
  else
    echo "  $url -> [$code] ? ($(printf '%s' "$body" | head -c 30 | tr -d '\n\r'))"
  fi
}
check "https://klasyo.org/platform/"
check "https://klasyo.org/platform/login"
check "https://klasyo.org/platform/register"

echo
echo "== 'KLASYO' présent dans la page login ? =="
curl -skL -m 15 "https://klasyo.org/platform/login" 2>/dev/null | grep -oiE 'KLASYO' | head -1 && echo "  -> présent" || echo "  -> absent"

echo
echo "== École (non-régression) + reste public/ propre =="
for u in "https://klasyo.org/school/login" "https://klasyo.org/"; do
  echo "  $u -> $(curl -skL -o /dev/null -m 12 -w '%{http_code}' "$u")"
done
find "$PUB" -maxdepth 1 -name 'klasyo_*' 2>/dev/null && echo "  (fichiers diag restants ci-dessus)" || echo "  public/ propre (aucun diag)"

echo
echo "== FIN étape E =="
