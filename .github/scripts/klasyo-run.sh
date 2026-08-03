#!/usr/bin/env bash
# KLASYO — Capture R : extraire la STRUCTURE de la landing (public_html/index.html)
# pour l'ajuster à l'histoire 2 produits sans tout redumper (184 Ko).
set -uo pipefail

ROOT="$HOME/domains/klasyo.org/public_html"
LAND="$ROOT/index.html"
[ -f "$LAND" ] || { echo "(!) landing introuvable: $LAND"; exit 0; }
echo "Landing: $LAND ($(wc -c < "$LAND") octets, $(wc -l < "$LAND") lignes)"

PHPX="$(mktemp /tmp/klasyo_land_XXXX.php)"
cat > "$PHPX" <<'PHPEOF'
<?php
$html = file_get_contents($argv[1]);
$d = new DOMDocument();
libxml_use_internal_errors(true);
$d->loadHTML('<?xml encoding="utf-8"?>'.$html);
libxml_clear_errors();
$x = new DOMXPath($d);

function t($n){ return trim(preg_replace('/\s+/', ' ', $n->textContent)); }

echo "== TITLE ==\n";
foreach ($x->query('//title') as $n) echo "  ".t($n)."\n";

echo "\n== NAV / liens d'en-tête (href = texte) ==\n";
$seen=[];
foreach ($x->query('//header//a | //nav//a') as $a){
  $h=$a->getAttribute('href'); $txt=t($a);
  $k="$h|$txt"; if(isset($seen[$k])||$txt==='')continue; $seen[$k]=1;
  echo "  [".($h?:'#')."] $txt\n";
}

echo "\n== HEADINGS (h1/h2/h3) ==\n";
foreach ($x->query('//h1|//h2|//h3') as $n){
  $tag=$n->nodeName; $txt=t($n); if($txt==='')continue;
  echo "  <$tag> ".mb_substr($txt,0,90)."\n";
}

echo "\n== CTA / boutons (a.btn / a.button / liens vers login,register,platform,school) ==\n";
$seen=[];
foreach ($x->query('//a') as $a){
  $h=$a->getAttribute('href'); $c=$a->getAttribute('class'); $txt=t($a);
  $isCta = preg_match('/btn|button|cta|theme-/i',$c) || preg_match('#login|register|sign|platform|school|/tapbiz|aprann|lek|inscri|connex#i',$h);
  if(!$isCta || $txt==='')continue;
  $k="$h|$txt"; if(isset($seen[$k]))continue; $seen[$k]=1;
  echo "  [".($h?:'#')."] \"".mb_substr($txt,0,50)."\" (class: ".mb_substr($c,0,40).")\n";
}

echo "\n== SECTIONS (id/class des <section>) ==\n";
foreach ($x->query('//section') as $s){
  $id=$s->getAttribute('id'); $c=$s->getAttribute('class');
  echo "  #".($id?:'-')." .".mb_substr($c,0,50)."\n";
}

echo "\n== Mentions produits existantes (platform/school/aprann/lekol/formation/ecole) ==\n";
$body = strtolower(strip_tags($html));
foreach (['platform','school','aprann','lekòl','lekol','formation','établissement','etablissement','école','ecole','cours','se connecter','connexion','login'] as $w){
  $n = substr_count($body, strtolower($w));
  if($n>0) echo "  '$w': $n\n";
}
PHPEOF
php "$PHPX" "$LAND" 2>&1 | head -120
rm -f "$PHPX"

echo
echo "== Extrait HERO (600 premiers caractères de texte visible) =="
php -r '$h=strip_tags(file_get_contents($argv[1])); $h=preg_replace("/\s+/"," ",$h); echo mb_substr(trim($h),0,600);' "$LAND"
echo
echo "== FIN capture R =="
