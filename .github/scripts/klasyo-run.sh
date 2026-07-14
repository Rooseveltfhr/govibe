#!/usr/bin/env bash
# KLASYO — Phase 1 / Semaine 1, passe 4 : isoler la cause du hang PHP dans /school.
# Le PHP direct dans school/public timeout (000/15s) alors que platform/public répond.
# On teste : (a) contenu du .htaccess de school/public vs platform/public,
# (b) réponse PHP quand on met school/public/.htaccess de côté.
# Logs publics : aucun secret (ces .htaccess ne contiennent pas de secrets).
set -uo pipefail

ROOT="$HOME/domains/klasyo.org/public_html"
S="$ROOT/school"
P="$ROOT/platform"
STAMP="$(date +%Y%m%d-%H%M%S)"

echo "== A. school/public/.htaccess (intégral) =="
cat "$S/public/.htaccess" 2>/dev/null || echo "(absent)"
echo
echo "== B. platform/public/.htaccess (intégral, référence qui marche) =="
cat "$P/public/.htaccess" 2>/dev/null || echo "(absent)"

echo
echo "== C. Test décisif : PHP direct SANS le .htaccess de school/public =="
DIAG="$S/public/klasyo_diag2.php"
echo '<?php echo "OK-NOHTA-".PHP_VERSION;' > "$DIAG"
if [ -f "$S/public/.htaccess" ]; then
  mv "$S/public/.htaccess" "$S/public/.htaccess.OFF-$STAMP"
  echo "  .htaccess mis de côté (.htaccess.OFF-$STAMP)"
fi
out=$(curl -sk -m 15 -w " [%{http_code} time=%{time_total}s]" "https://klasyo.org/school/public/klasyo_diag2.php" 2>&1 | head -c 100)
echo "  résultat SANS .htaccess -> $out"
# Restaurer immédiatement
if [ -f "$S/public/.htaccess.OFF-$STAMP" ]; then
  mv "$S/public/.htaccess.OFF-$STAMP" "$S/public/.htaccess"
  echo "  .htaccess restauré."
fi
rm -f "$DIAG"

echo
echo "== D. Comparaison des handlers PHP (school vs platform) au niveau du domaine =="
echo "  --- lignes PHP/handler dans TOUS les .htaccess de school :"
grep -rniE 'php|handler|fcgi|lsapi|application/x-httpd' "$S"/.htaccess "$S"/public/.htaccess 2>/dev/null | head -15 || echo "  (rien)"
echo "  --- idem platform (référence) :"
grep -rniE 'php|handler|fcgi|lsapi|application/x-httpd' "$P"/.htaccess "$P"/public/.htaccess 2>/dev/null | head -15 || echo "  (rien)"

echo
echo "== E. Différence de permissions / propriété entre les deux public/ =="
stat -c '%A %U:%G %n' "$S/public" "$S/public/index.php" "$P/public" "$P/public/index.php" 2>/dev/null

echo
echo "== F. .htaccess éventuels plus haut dans l'arborescence (hériteraient sur school) =="
for d in "$ROOT" "$HOME"; do
  [ -f "$d/.htaccess" ] && { echo "  --- $d/.htaccess :"; grep -niE 'php|handler|rewrite|deny|require' "$d/.htaccess" | head -10; } || echo "  (pas de .htaccess dans $d)"
done

echo
echo "== FIN passe 4 =="
