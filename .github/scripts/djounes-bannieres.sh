#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# djounes-bannieres.sh — remplace les huit diapositives du carrousel par des
# bannières à la charte DJOUNES, et leur donne enfin une destination.
#
# Les diapositives sont des lignes frontends dont le JSON ne contient que
# has_image / link / slider : tout le visuel est DANS l'image. On envoie donc
# huit images, on redimensionne chacune à la taille de celle qu'elle remplace
# (pour que la hauteur du carrousel ne bouge pas), et on ne réécrit que les
# clés déjà présentes — la structure du thème reste intacte.
#
# Les anciennes images ne sont jamais supprimées : retour en arrière possible.
# ---------------------------------------------------------------------------
set -uo pipefail

DOM="${DOMAIN:-djounes.com}"
UPLOAD="${UPLOAD:-$HOME/djounes-brand-upload}"
STAMP=$(date +%Y%m%d%H%M%S)
grp() { echo "::group::$*"; }
egrp() { echo "::endgroup::"; }

PUB="$HOME/domains/$DOM/public_html"; APP="$PUB/core"; ENVF="$APP/.env"
[ -f "$APP/artisan" ] || { echo "::error::application introuvable"; exit 1; }
val() { grep "^$1=" "$ENVF" 2>/dev/null | head -1 | cut -d= -f2- | tr -d '"'; }
DBN=$(val DB_DATABASE); DBU=$(val DB_USERNAME); DBP=$(val DB_PASSWORD); DBH=$(val DB_HOST)
SAFE=$(printf '%s' "$DBN" | tr -cd 'A-Za-z0-9_')
BANDIR="$PUB/assets/images/frontend/banner"

grp "Sauvegardes"
BK="$HOME/djounes-backups"; mkdir -p "$BK"
CNF=$(mktemp); chmod 600 "$CNF"
printf '[client]\nuser=%s\npassword=%s\nhost=%s\n' "$DBU" "$DBP" "${DBH:-localhost}" > "$CNF"
DUMP="$BK/db-$STAMP.sql.gz"
mysqldump --defaults-extra-file="$CNF" --single-transaction "$SAFE" 2>/dev/null | gzip > "$DUMP" \
  || { echo "::error::sauvegarde impossible — on n'écrit rien."; rm -f "$CNF"; exit 1; }
rm -f "$CNF"
echo "base : $DUMP ($(du -h "$DUMP" | cut -f1))"
[ -d "$BANDIR" ] && cp -a "$BANDIR" "$BK/banner-$STAMP" && echo "anciennes bannières : $BK/banner-$STAMP"
egrp

grp "Envoi des visuels"
if [ -d "$UPLOAD/banners" ]; then
  mkdir -p "$BANDIR"
  cp -f "$UPLOAD/banners/"*.png "$BANDIR/" 2>/dev/null
  chmod 644 "$BANDIR"/*.png 2>/dev/null
  echo "$(ls -1 "$UPLOAD/banners/"*.png 2>/dev/null | wc -l) visuels copiés dans assets/images/frontend/banner"
else
  echo "::error::$UPLOAD/banners absent"; exit 1
fi
egrp

grp "Diapositives : image et destination"
cd "$APP" || exit 1
BANDIR="$BANDIR" php <<'PHP'
<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Frontend;
use App\Models\Page;

/* Le modèle Frontend caste data_values : on reçoit déjà un objet, et une
   affectation le ré-encode. Décoder ou encoder soi-même casse tout — c'est
   ce qui a fait échouer le premier passage. */
function charger($v) {
    if (is_object($v) || is_array($v)) return (object) $v;
    $d = json_decode((string) $v);
    return is_object($d) ? $d : null;
}

$dir = getenv('BANDIR');

/* Une destination par diapositive : un carrousel dont les huit slides
   pointent sur « # » gaspille la plus grande zone cliquable du site. */
$slides = [
    ['01-shift.png',    '/products'],
    ['02-scrubs.png',   '/product/nurse-scrub-set'],
    ['03-socks.png',    '/product/compression-socks'],
    ['04-bodycare.png', '/product/whipped-body-butter'],
    ['05-shipping.png', '/products'],
    ['06-shiftkit.png', '/products'],
    ['07-welcome.png',  '/products'],
    ['08-tote.png',     '/product/canvas-tote-bag'],
];

$rows = Frontend::where('data_keys', 'banner.element')->orderBy('id')->get();
echo "diapositives en base : " . $rows->count() . "\n";

/* Taille des diapositives du thème, relevée sur les visuels de démonstration
   d'origine. C'est la géométrie pour laquelle le carrousel a été écrit, et la
   seule dont on ait la preuve qu'elle s'affiche correctement : on y ramène
   chaque image. Les visuels sont dessinés au double de ce format, donc la
   réduction reste nette.

   On ne déduit plus la cible de l'image remplacée : au deuxième passage cette
   image est déjà l'une des nôtres, la cible deviendrait sa propre taille et
   plus rien ne serait redimensionné. */
const NATIF_L = 1284;
const NATIF_H = 345;

function adapter(string $dir, string $fichier): void {
    $src = "$dir/$fichier";
    if (!is_file($src)) return;
    $nouveau = @getimagesize($src);
    if (!$nouveau) return;
    if ($nouveau[0] === NATIF_L && $nouveau[1] === NATIF_H) {
        echo "    déjà au format natif (" . NATIF_L . "x" . NATIF_H . ")\n"; return;
    }
    if (!function_exists('imagecreatefrompng')) { echo "    (GD absent, taille inchangée)\n"; return; }
    $im = @imagecreatefrompng($src);
    if (!$im) return;
    $out = imagecreatetruecolor(NATIF_L, NATIF_H);
    imagecopyresampled($out, $im, 0, 0, 0, 0, NATIF_L, NATIF_H, $nouveau[0], $nouveau[1]);
    imagepng($out, $src, 8);
    imagedestroy($im); imagedestroy($out);
    echo "    redimensionnée {$nouveau[0]}x{$nouveau[1]} → " . NATIF_L . "x" . NATIF_H . "\n";
}

foreach ($rows as $i => $row) {
    if (!isset($slides[$i])) { echo "  #{$row->id} : pas de visuel prévu, inchangée\n"; continue; }
    [$fichier, $lien] = $slides[$i];
    $data = charger($row->data_values);
    if (!$data) { echo "  #{$row->id} : contenu illisible, ignorée\n"; continue; }

    $ancien = property_exists($data, 'slider') ? $data->slider : null;
    adapter($dir, $fichier);

    // On ne touche que des clés déjà présentes.
    if (property_exists($data, 'slider')) $data->slider = $fichier;
    if (property_exists($data, 'link'))   $data->link   = $lien;
    if (property_exists($data, 'has_image')) $data->has_image = '1';

    $row->data_values = $data;
    $row->save();
    echo "  #{$row->id} : $fichier → $lien" . ($ancien ? "  (remplace $ancien)" : "") . "\n";
}

/* Un carrousel invisible ne sert à rien : la section « banner » n'était pas
   dans la liste des sections de l'accueil. On la met en tête, devant
   « recent_viewed » qui n'a rien à montrer à un premier visiteur. */
$home = Page::where('slug', '/')->first();
if ($home) {
    $secs = $home->secs;
    if (is_string($secs)) $secs = json_decode($secs, true);
    $secs = is_array($secs) ? $secs : [];
    if (!in_array('banner', $secs, true)) {
        array_unshift($secs, 'banner');
        $home->secs = $secs;
        $home->save();
        echo "section « banner » activée en tête de l'accueil\n";
    } else {
        echo "section « banner » déjà active sur l'accueil\n";
    }
    echo "sections de l'accueil : " . implode(', ', $secs) . "\n";
}

/* Le titre de la section, au cas où il porte encore un texte de démonstration. */
$c = Frontend::where('data_keys', 'banner.content')->first();
if ($c) {
    $d = charger($c->data_values);
    if ($d) {
        foreach (['heading' => 'Built for the 12-hour shift', 'title' => 'Built for the 12-hour shift',
                  'short_description' => 'Scrubs for the floor. Body care for after.',
                  'description' => 'Scrubs for the floor. Body care for after.'] as $k => $v) {
            if (property_exists($d, $k) && is_string($d->$k)) $d->$k = $v;
        }
        $c->data_values = $d;
        $c->save();
        echo "banner.content mis à jour\n";
    }
}
PHP
egrp

grp "Vidage des caches"
( cd "$APP" && php artisan optimize:clear 2>&1 | head -4 ) || true
egrp

grp "Vérification"
C=$(curl -sS -o /dev/null -m 25 -w '%{http_code}' "https://$DOM/" 2>/dev/null)
echo "https://$DOM/ → $C"
if [ "$C" != "200" ]; then
  echo "::error::l'accueil ne répond plus 200 — restauration."
  RCNF=$(mktemp); chmod 600 "$RCNF"
  printf '[client]\nuser=%s\npassword=%s\nhost=%s\n' "$DBU" "$DBP" "${DBH:-localhost}" > "$RCNF"
  gunzip -c "$DUMP" | mysql --defaults-extra-file="$RCNF" "$SAFE" 2>&1 | head -3
  rm -f "$RCNF"
  [ -d "$BK/banner-$STAMP" ] && rm -rf "$BANDIR" && cp -a "$BK/banner-$STAMP" "$BANDIR"
  ( cd "$APP" && php artisan optimize:clear >/dev/null 2>&1 ) || true
  exit 1
fi
HTML=$(curl -sS -m 25 "https://$DOM/" 2>/dev/null)
VUS=0
for f in 01-shift 02-scrubs 03-socks 04-bodycare 05-shipping 06-shiftkit 07-welcome 08-tote; do
  case "$HTML" in *"$f.png"*) VUS=$((VUS+1));; esac
done
echo "bannières DJOUNES visibles dans l'accueil : $VUS / 8  (page de $(printf '%s' "$HTML" | wc -c) octets)"
if [ "$VUS" = "0" ]; then
  echo "::warning::les visuels sont en place et en base, mais l'accueil n'affiche pas le carrousel."
  echo "La section « banner » n'est probablement pas activée sur la page d'accueil"
  echo "(ses sections sont : recent_viewed, banner_4, featured_categories, collection_*, offer_1, featured_brands, services)."
  echo "→ à activer depuis l'administration : Frontend > Sections de la page d'accueil."
fi
echo "Retour en arrière : cp -a $BK/banner-$STAMP/* $BANDIR/ puis restaurer $DUMP"
egrp
echo "Terminé."
