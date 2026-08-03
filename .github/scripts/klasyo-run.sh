#!/usr/bin/env bash
# KLASYO — Étape W : (A) structure des cartes de cours sur /platform/courses (pour les fusionner
# sur la landing), (B) diagnostic du 404 sur /school/.
set -uo pipefail

ROOT="$HOME/domains/klasyo.org/public_html"
UA='Mozilla/5.0 klasyo-check'

echo "########## A) STRUCTURE DE /platform/courses ##########"
curl -sSL -m 25 -A "$UA" "https://klasyo.org/platform/courses" -o /tmp/courses.html 2>/dev/null
echo "  octets: $(wc -c < /tmp/courses.html)"
echo
echo "== Liens vers des cours individuels (href contenant course/cours) — 15 premiers uniques =="
grep -oiE 'href="[^"]*(course|cours)[^"]*"' /tmp/courses.html | sort -u | head -15
echo
echo "== Classes de conteneurs de cartes probables (card/course/item/col) =="
grep -oiE 'class="[^"]*(course|card|single|item)[^"]*"' /tmp/courses.html | sort | uniq -c | sort -rn | head -20
echo
echo "== Catégories listées (liens category) =="
grep -oiE 'href="[^"]*categor[^"]*"[^>]*>[^<]{2,40}' /tmp/courses.html | sed 's/<[^>]*>//g' | sort -u | head -20
echo
echo "== Extrait: une carte de cours (contexte autour du 1er lien de cours) =="
PHPX="$(mktemp /tmp/kx_XXXX.php)"
cat > "$PHPX" <<'PHPEOF'
<?php
$h=file_get_contents('/tmp/courses.html');
$d=new DOMDocument(); libxml_use_internal_errors(true);
$d->loadHTML('<?xml encoding="utf-8"?>'.$h); libxml_clear_errors();
$x=new DOMXPath($d);
// premier lien de cours
$node=null;
foreach($x->query('//a') as $a){
  $href=$a->getAttribute('href');
  if(preg_match('~/(course|cours)/~i',$href) || preg_match('~courses?/[^/"]+$~i',$href)){ $node=$a; break; }
}
if(!$node){ echo "  (aucun lien de cours détecté par xpath)\n"; exit; }
// remonter jusqu'au conteneur "carte" (parent avec class course/card/col)
$card=$node;
for($i=0;$i<5 && $card->parentNode;$i++){
  $p=$card->parentNode;
  if($p->nodeType===XML_ELEMENT_NODE){
    $cls=$p->getAttribute('class');
    if(preg_match('/(course|card|col-|single)/i',$cls)){ $card=$p; }
  }
  $card=$card->parentNode ?: $card;
  if(preg_match('/(course-item|single-course|course-card|card)/i',$card->getAttribute('class'))) break;
}
$out=$d->saveHTML($card);
echo "  --- classe carte: \"".$card->getAttribute('class')."\" ---\n";
echo mb_substr(preg_replace('/\s+/',' ',$out),0,900)."\n";
// combien de cartes de ce type ?
$cls=trim($card->getAttribute('class'));
if($cls!==''){ $first=preg_split('/\s+/',$cls)[0];
  $n=$x->query("//*[contains(@class,'$first')]")->length;
  echo "  ~nombre d'éléments .$first : $n\n";
}
PHPEOF
php "$PHPX" 2>&1 | head -30
rm -f "$PHPX"

echo
echo "########## B) DIAGNOSTIC /school/ (404) ##########"
SCH="$ROOT/school"
echo "== Le dossier school existe-t-il ? =="
if [ -d "$SCH" ]; then
  echo "  OUI: $SCH"
  echo "  -- contenu racine (10) --"; ls -la "$SCH" 2>/dev/null | head -12
  echo "  -- présence entrées Laravel --"
  for f in server.php artisan public/index.php public/.htaccess .htaccess index.php; do
    [ -e "$SCH/$f" ] && echo "    [x] $f" || echo "    [ ] $f (absent)"
  done
  echo "  -- .htaccess racine school (30 lignes) --"
  [ -f "$SCH/.htaccess" ] && sed -n '1,30p' "$SCH/.htaccess" || echo "    (pas de .htaccess racine)"
else
  echo "  NON — $SCH introuvable. Autres dossiers 'school*' :"
  ls -la "$ROOT" 2>/dev/null | grep -iE 'school|lekol|ecole' || echo "    (aucun)"
fi
echo
echo "== Réponse brute /school/ (type de contenu) =="
B="$(curl -sSL -m 20 -A "$UA" "https://klasyo.org/school/" 2>/dev/null)"
case "$B" in
  *"<?php"*|*'Illuminate\'*) echo "  SOURCE-PHP(!)";;
  *"<!doctype"*|*"<!DOCTYPE"*|*"<html"*) echo "  HTML";;
  "") echo "  VIDE";;
  *) echo "  AUTRE (200 premiers): $(printf '%s' "$B" | head -c 200)";;
esac
printf '%s' "$B" | grep -oiE '<title>[^<]*</title>|404|not found' | head -3

echo
echo "== FIN étape W =="
