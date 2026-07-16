#!/usr/bin/env bash
# KLASYO — Étape M : annuler le hack register=>true (mauvais mécanisme : LMSZAI n'a pas de
# vue auth.register -> 500). LMSZAI a sa propre inscription native, qui fonctionnera une fois
# l'app servie à la racine (sous-domaine). On restaure register=>false (état propre, pas de 500).
set -uo pipefail

ROOT="$HOME/domains/klasyo.org/public_html"
P="$ROOT/platform"
STAMP="$(date +%Y%m%d-%H%M%S)"
WEB="$P/routes/web.php"

echo "== Ligne actuelle :"
grep -n "Auth::routes" "$WEB"
if grep -q "Auth::routes(\['register' => true\])" "$WEB"; then
  cp -a "$WEB" "$WEB.bak-M-$STAMP"
  sed -i "s/Auth::routes(\['register' => true\])/Auth::routes(['register' => false])/" "$WEB"
  echo "  -> restauré register=>false (plus de 500 sur /register)."
else
  echo "  (déjà register=>false ou motif absent)"
fi
grep -n "Auth::routes" "$WEB"
(cd "$P" && php artisan route:clear 2>&1 | tail -1)
(cd "$P" && php artisan config:clear 2>&1 | tail -1)

echo
echo "== Chercher le flux d'inscription NATIF de LMSZAI (routes signup/register) =="
grep -rniE "register|sign.?up|signup|enroll" "$P/routes/web.php" 2>/dev/null | grep -viE 'Auth::routes|verification|version' | head -12 || echo "  (aucune route register native évidente dans web.php)"
echo "  --- vues d'inscription présentes ?"
find "$P/resources/views" -iname '*register*' -o -iname '*signup*' 2>/dev/null | grep -v vendor | head -10 || echo "  (aucune vue register/signup)"
echo "  --- lien d'inscription dans la page login (frontend) :"
grep -rniE "href=.*(register|signup|sign-up)" "$P/resources/views/frontend" 2>/dev/null | head -5 || echo "  (aucun lien évident)"

echo
echo "== État final (un seul appel HTTP, pour éviter le throttling firewall) =="
sleep 2
code=$(curl -sk -o /dev/null -m 20 -w '%{http_code}' "https://klasyo.org/platform/public/login" 2>/dev/null)
echo "  /platform/public/login -> [$code]"

echo
echo "== FIN étape M =="
