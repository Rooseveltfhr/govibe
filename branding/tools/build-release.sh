#!/bin/bash
# Construit le ZIP à envoyer sur l'hébergement (sans outils, docs, données de test).
# Usage : bash branding/tools/build-release.sh [fichier-sortie.zip]
set -e
cd "$(dirname "$0")/.."
OUT="${1:-../rooseveltforestal-site.zip}"
rm -f "$OUT"
node tools/sync-chrome.mjs >/dev/null
zip -qr "$OUT" . -x 'tools/*' 'docs/*' '*.md' '.gitignore' 'data/*' 'uploads/*' 'assets/img/.gitkeep' '*/.DS_Store'
zip -q "$OUT" data/.htaccess data/index.html uploads/.htaccess uploads/index.html
echo "OK → $OUT ($(du -h "$OUT" | cut -f1))"
