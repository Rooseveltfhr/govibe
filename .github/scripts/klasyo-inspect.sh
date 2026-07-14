#!/usr/bin/env bash
# Inspection LECTURE SEULE du domaine klasyo.org sur le VPS.
# Exécuté via GitHub Actions (workflow klasyo-ops.yml) : ssh ... bash -s < ce_script
# Ne modifie rien ; n'affiche jamais de secrets (.env exclu volontairement).
set -uo pipefail

echo "== Utilisateur SSH =="
whoami
echo "HOME=$HOME"

echo
echo "== Domaines présents (tous comptes visibles) =="
ls -d /home/*/domains/*/ 2>/dev/null || true
ls -d "$HOME"/domains/*/ 2>/dev/null || true

echo
echo "== Racine klasyo.org =="
FOUND=""
for d in /home/*/domains/klasyo.org "$HOME/domains/klasyo.org"; do
  if [ -d "$d" ]; then
    FOUND="$d"
    echo "--- $d"
    ls -la "$d"
    echo
    echo "--- Arborescence (2 niveaux, .env masqué) :"
    find "$d" -maxdepth 2 -name '.env*' -prune -o -print 2>/dev/null | head -80
  fi
done

if [ -z "$FOUND" ]; then
  echo "(!) Aucun dossier domains/klasyo.org trouvé pour cet utilisateur."
  echo "    Recherche large :"
  find /home -maxdepth 4 -iname '*klasyo*' 2>/dev/null | head -20
fi

echo
echo "== public_html de klasyo.org =="
for p in /home/*/domains/klasyo.org/public_html "$HOME/domains/klasyo.org/public_html"; do
  if [ -d "$p" ]; then
    echo "--- $p"
    ls -la "$p"
  fi
done

echo
echo "== Config web visible (DirectAdmin/Apache/Nginx) pour klasyo.org =="
grep -rls "klasyo" /usr/local/directadmin/data/users/*/domains/ 2>/dev/null | head -10 || true
grep -rls "klasyo" /etc/nginx /etc/httpd /etc/apache2 2>/dev/null | head -10 || true

echo
echo "== Fin de l'inspection =="
