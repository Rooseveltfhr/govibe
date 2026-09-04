#!/usr/bin/env bash
# KLASYO — MODULE C (prep2) : voir COMMENT la home active (frontend/home/home.blade.php) rend
# le hero + les titres de sections (en dur ? __() ? get_option ?), pour rebrander proprement.
set -uo pipefail
P="$HOME/domains/klasyo.org/public_html/platform"
H="$P/resources/views/frontend/home/home.blade.php"
echo "  fichier: ${H#$P/} — $(wc -l < "$H") lignes"
echo
echo "===== en-tête (1-15) ====="
nl -ba "$H" | sed -n '1,15p'
echo
echo "===== toutes les balises titres + get_option + __() visibles (n° ligne) ====="
grep -nE '<h1|<h2|<h3|<h4|get_option\(|__\(' "$H" | head -60
echo
echo "===== zone HERO (chercher banner/hero/slider) ====="
grep -nE 'banner|hero|slider|main-banner|home-banner' "$H" | head -20
echo
echo "===== APERCU des ~50 premières lignes de contenu (16-70) ====="
nl -ba "$H" | sed -n '16,70p'
echo
echo "== FIN MODULE C (prep2) =="
