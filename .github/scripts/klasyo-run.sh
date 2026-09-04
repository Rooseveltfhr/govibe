#!/usr/bin/env bash
# KLASYO — Étape Z4 (ANALYSE ciblée) : lire l'entête + le bloc "Payment Method" exact du checkout cours
# pour renommer Bank -> Paiement Manuel, ajouter la description, et styliser proprement les tuiles.
set -uo pipefail
P="$HOME/domains/klasyo.org/public_html/platform"
CO="$P/resources/views/frontend/student/cart/checkout.blade.php"

echo "===== checkout.blade.php : lignes 1-30 (extends / @push style / structure) ====="
nl -ba "$CO" | sed -n '1,30p'
echo
echo "===== checkout.blade.php : lignes 215-400 (bloc Payment Method complet) ====="
nl -ba "$CO" | sed -n '215,400p'
echo
echo "===== @push('style') / @push('script') présents ? ====="
grep -nE "@push\('?(style|script|css|js)'?\)|@section\('?(style|script)'?\)" "$CO" | head
echo
echo "== FIN étape Z4 =="
