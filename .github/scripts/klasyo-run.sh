#!/usr/bin/env bash
# KLASYO — Étape Z3 (ANALYSE ciblée paiements) : lire les portions exactes à modifier
# pour renommer BANK -> "Paiement Manuel" + ajouter description + redesign du sélecteur.
set -uo pipefail
P="$HOME/domains/klasyo.org/public_html/platform"
CO="$P/resources/views/frontend/student/cart/checkout.blade.php"

echo "############ corearray.php — tableaux de libellés de paiement (150-195) ############"
nl -ba "$P/app/Helper/corearray.php" | sed -n '150,195p'
echo
echo "############ corearray.php — contexte 300-320 & 585-650 (mappings bank) ############"
nl -ba "$P/app/Helper/corearray.php" | sed -n '300,320p'
nl -ba "$P/app/Helper/corearray.php" | sed -n '585,650p'
echo
echo "############ coreconstant.php — 125-140 (const BANK) ############"
nl -ba "$P/app/Helper/coreconstant.php" | sed -n '125,140p'
echo
echo "############ helper.php — 630-670 (bloc bank) ############"
nl -ba "$P/app/Helper/helper.php" | sed -n '630,670p'
echo
echo "############ checkout.blade.php — existence + taille + où se rend le paiement ############"
if [ -f "$CO" ]; then
  echo "  fichier: $(wc -l < "$CO") lignes, $(wc -c < "$CO") octets"
  echo "== lignes mentionnant bank / payment_method / gateway / Paiement / method (n° de ligne) =="
  grep -inE 'bank|payment_method|gateway|paiement|payment-method|payment_option|choose.*payment|method' "$CO" | head -40
else
  echo "  (!) introuvable: $CO"
  echo "  autres checkout:"; find "$P/resources/views" -iname 'checkout.blade.php' | sed "s#$P/##"
fi
echo
echo "== FIN étape Z3 =="
