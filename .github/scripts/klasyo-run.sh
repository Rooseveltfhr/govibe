#!/usr/bin/env bash
# KLASYO — Capture T : extraire le markup EXACT des ancres CTA de la landing (toutes en href="#")
# pour les câbler précisément (Connexion, S'inscrire, solutions, école) sans casser le fichier.
set -uo pipefail

ROOT="$HOME/domains/klasyo.org/public_html"
LAND="$ROOT/index.html"
[ -f "$LAND" ] || { echo "(!) landing introuvable"; exit 0; }

PHPX="$(mktemp /tmp/klasyo_cta_XXXX.php)"
cat > "$PHPX" <<'PHPEOF'
<?php
$html = file_get_contents($argv[1]);
$d = new DOMDocument(); libxml_use_internal_errors(true);
$d->loadHTML('<?xml encoding="utf-8"?>'.$html); libxml_clear_errors();
$x = new DOMXPath($d);
function t($n){ return trim(preg_replace('/\s+/',' ',$n->textContent)); }
$wanted = ['Connexion','S\'inscrire','Commencer maintenant','Commencer gratuitement',
  'S\'inscrire maintenant','Rejoindre KLASYO Learn','Intégrer mon organisation',
  'Intégrer mon établissement','Devenir Formateur','Intégrer mon école','Gérer mon école',
  'Demander un devis','Voir la Démo'];
$i=0;
foreach ($x->query('//a') as $a){
  $txt=t($a); if($txt==='')continue;
  foreach($wanted as $w){
    if(mb_strtolower($txt)===mb_strtolower($w) || mb_stripos($txt,$w)!==false){
      $i++;
      $cls=$a->getAttribute('class'); $href=$a->getAttribute('href'); $id=$a->getAttribute('id');
      // Markup ouvrant exact de la balise <a ...>
      $open = $d->saveHTML($a);
      $open = preg_replace('/>.*/s','>',$open); // garder seulement la balise ouvrante
      echo "#$i  TXT=\"$txt\"\n";
      echo "     id=\"$id\" class=\"$cls\" href=\"$href\"\n";
      echo "     OPEN: ".mb_substr($open,0,200)."\n";
      break;
    }
  }
}
echo "TOTAL CTA trouvés: $i\n";
PHPEOF
php "$PHPX" "$LAND" 2>&1 | head -90
rm -f "$PHPX"

echo
echo "== Combien de href=\"#\" au total (ampleur du câblage) =="
grep -oE 'href="#"' "$LAND" | wc -l
echo "== La landing a-t-elle déjà un <base> ou des liens absolus klasyo.org ? =="
grep -oiE '<base[^>]*>|https://klasyo.org/[a-z/]{0,30}' "$LAND" | sort -u | head -10

echo
echo "== FIN capture T =="
