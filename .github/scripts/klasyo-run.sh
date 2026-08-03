#!/usr/bin/env bash
# KLASYO — Capture P : lire INTÉGRALEMENT la vue login + le layout auth, pour réécrire
# le login sans casser le formulaire (champs, csrf, recaptcha, social, remember, liens).
set -uo pipefail

ROOT="$HOME/domains/klasyo.org/public_html"
P="$ROOT/platform"
V="$P/resources/views"

# Localiser la vue login
LOGINV=""
for c in "$V/auth/login.blade.php" "$V/frontend/auth/login.blade.php" "$V/frontend/login.blade.php"; do
  [ -f "$c" ] && { LOGINV="$c"; break; }
done
[ -z "$LOGINV" ] && LOGINV=$(grep -rl --include='*.blade.php' "route('login')" "$V" 2>/dev/null | grep -iE 'login' | head -1)
echo "=== VUE LOGIN: $LOGINV ==="
echo "----- DÉBUT login.blade.php -----"
cat -n "$LOGINV"
echo "----- FIN login.blade.php -----"

echo
echo "=== LAYOUT: layouts/auth.blade.php (head + où le CSS/JS charge) ==="
AUTH="$V/layouts/auth.blade.php"
if [ -f "$AUTH" ]; then
  cat -n "$AUTH"
else
  echo "  (introuvable) — recherche :"
  find "$V/layouts" -iname 'auth*' 2>/dev/null
fi

echo
echo "=== helper get_option / getImageFile existent (pour logo) ? ==="
grep -rlE "function (get_option|getImageFile)" "$P/app" 2>/dev/null | head -3

echo
echo "== FIN capture P =="
