#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# djounes-admin.sh — met le panneau d'administration à la charte DJOUNES :
# le bleu du thème devient le vert de la marque, et la signature du script
# disparaît de la barre latérale.
#
# Ce qu'on NE touche PAS : systemDetails() dans helpers.php. Le scan a montré
# que son 'name' alimente l'appel de vérification de licence
# (VugiChugi::gttmp() . systemDetails()['name']). On change donc ce que la
# vue AFFICHE, pas ce que le script utilise pour se valider.
#
# Sauvegardes horodatées avant chaque écriture, et remise en état possible
# fichier par fichier.
# ---------------------------------------------------------------------------
set -uo pipefail

DOM="${DOMAIN:-djounes.com}"
STAMP=$(date +%Y%m%d%H%M%S)
grp() { echo "::group::$*"; }
egrp() { echo "::endgroup::"; }

PUB="$HOME/domains/$DOM/public_html"; APP="$PUB/core"
[ -f "$APP/artisan" ] || { echo "::error::application introuvable"; exit 1; }
BK="$HOME/djounes-backups/admin-$STAMP"; mkdir -p "$BK"

CSS="$PUB/assets/admin/css/app.css"
CUSTOM="$PUB/assets/admin/css/custom.css"
NAV="$APP/resources/views/admin/partials/sidenav.blade.php"

BLEU="#4634ff"; VERT="#0B3B2E"; OR="#D4AF37"

grp "Sauvegardes"
for f in "$CSS" "$CUSTOM" "$NAV"; do
  [ -f "$f" ] && cp -a "$f" "$BK/$(basename "$f")" && echo "sauvegardé : $(basename "$f")"
done
echo "dossier : $BK"
egrp

# --- 1. Couleur du thème d'administration ----------------------------------
grp "Couleur du panneau"
if [ -f "$CSS" ]; then
  AVANT=$(grep -oiE "$BLEU" "$CSS" | wc -l)
  RGBA_AVANT=$(grep -oiE "rgba\( *70, *52, *255" "$CSS" | wc -l)
  echo "avant : $AVANT occurrence(s) de $BLEU, $RGBA_AVANT en rgba"
  sed -i "s/${BLEU}/${VERT}/gI" "$CSS"
  sed -i -E "s/rgba\( *70, *52, *255/rgba(11, 59, 46/gI" "$CSS"
  # Nuance foncée du thème (états survolés) → vert plus foncé, pas le même vert,
  # sinon le survol ne se voit plus.
  sed -i "s/#2948ff/#082C22/gI" "$CSS"
  APRES=$(grep -oiE "$BLEU" "$CSS" | wc -l)
  echo "après : $APRES occurrence(s) restante(s) de $BLEU"
  echo "-- autres bleus encore présents (à surveiller) --"
  grep -ohiE "#(4[0-9a-f]{2}[0-9a-f]ff|[0-9a-f]{2}[0-9a-f]{2}ff)" "$CSS" 2>/dev/null \
    | tr 'A-F' 'a-f' | sort | uniq -c | sort -rn | head -6 || echo "(aucun)"
else
  echo "::warning::app.css introuvable"
fi
egrp

# --- 2. Retouches ciblées, dans le fichier prévu pour ça --------------------
# custom.css est chargé APRÈS app.css : c'est l'endroit conçu pour surcharger,
# et le bloc est délimité pour être remplacé proprement à chaque passage.
grp "Retouches custom.css"
if [ -f "$CUSTOM" ]; then
  sed -i '/\/\* --- DJOUNES: début --- \*\//,/\/\* --- DJOUNES: fin --- \*\//d' "$CUSTOM"
  cat >> "$CUSTOM" <<CSSEOF
/* --- DJOUNES: début --- */
/* Charte DJOUNES. Bloc régénéré à chaque passage du script d'administration. */
:root {
    --djounes-green: $VERT;
    --djounes-gold: $OR;
}
.text--primary { color: $VERT !important; }
.bg--primary   { background-color: $VERT !important; }
.btn--primary  { background-color: $VERT !important; border-color: $VERT !important; }
.btn--primary:hover { background-color: #175644 !important; }
::selection { background: $VERT; color: #fff; }
.sidebar__menu-header, .version-info { letter-spacing: .06em; }
.version-info span { color: $OR !important; }
/* --- DJOUNES: fin --- */
CSSEOF
  echo "bloc DJOUNES écrit dans custom.css ($(wc -l < "$CUSTOM") lignes au total)"
else
  echo "::warning::custom.css introuvable"
fi
egrp

# --- 3. Signature dans la barre latérale ------------------------------------
grp "Signature de la barre latérale"
if [ -f "$NAV" ]; then
  echo "-- avant --"
  sed -n '78,90p' "$NAV"
  NAV_PATH="$NAV" php <<'PHP'
<?php
$f = getenv('NAV_PATH');
$src = file_get_contents($f);
$remplacement = <<<HTML
<div class="version-info text-center text-uppercase">
            <span>DJOUNES</span>
        </div>
HTML;
$n = 0;
$out = preg_replace(
    '~<div class="version-info[^"]*"[^>]*>.*?</div>~s',
    $remplacement,
    $src, 1, $n
);
if ($n === 1 && $out !== null && strlen($out) > 100) {
    file_put_contents($f, $out);
    echo "bloc version-info remplacé.\n";
} else {
    echo "::warning::bloc version-info introuvable ou motif ambigu — fichier inchangé.\n";
}
PHP
  echo "-- après --"
  sed -n '78,90p' "$NAV"
  echo "-- reste-t-il « viser » dans les vues admin ? --"
  grep -rli "visermart" "$APP/resources/views/admin" 2>/dev/null | sed "s|$APP/||" || echo "(aucun)"
else
  echo "::warning::sidenav.blade.php introuvable"
fi
egrp

grp "Vidage des caches"
( cd "$APP" && php artisan optimize:clear 2>&1 | head -5 ) || true
rm -f "$APP/storage/framework/views/"*.php 2>/dev/null && echo "vues compilées supprimées"
egrp

grp "Vérification"
for p in "" "admin" "products"; do
  echo "https://$DOM/$p → $(curl -sS -o /dev/null -m 25 -w '%{http_code}' "https://$DOM/$p" 2>/dev/null)"
done
echo "Remise en état si besoin : cp $BK/<fichier> vers son emplacement d'origine."
egrp
echo "Terminé."
