#!/usr/bin/env bash
# KLASYO — Étape X : matière pour fusionner les cours sur la landing + localiser la bonne route school.
set -uo pipefail
UA='Mozilla/5.0 klasyo-check'

echo "########## A) PAGE D'ACCUEIL LMS /platform/ : cours en vedette ##########"
curl -sSL -m 25 -A "$UA" "https://klasyo.org/platform/" -o /tmp/home.html 2>/dev/null
echo "  octets: $(wc -c < /tmp/home.html)"
PHPX="$(mktemp /tmp/kx_XXXX.php)"
cat > "$PHPX" <<'PHPEOF'
<?php
$h=file_get_contents($argv[1]);
$d=new DOMDocument(); libxml_use_internal_errors(true);
$d->loadHTML('<?xml encoding="utf-8"?>'.$h); libxml_clear_errors();
$x=new DOMXPath($d);
function txt($n){ return trim(preg_replace('/\s+/',' ',$n->textContent)); }
$seen=[]; $cards=0;
// Liens vers un cours individuel : /platform/course/<slug> (souvent) — collecter carte = titre+img+prix
foreach($x->query('//a[contains(@href,"/course/") or contains(@href,"/courses/")]') as $a){
  $href=$a->getAttribute('href');
  if(!preg_match('~/(course|courses)/[^/#?]+~',$href)) continue;      // ignorer /courses (liste)
  if(preg_match('~/(category|subcategory)/~',$href)) continue;
  if(isset($seen[$href])) continue; $seen[$href]=1;
  // remonter au conteneur carte
  $card=$a; for($i=0;$i<4 && $card->parentNode && $card->parentNode->nodeType===XML_ELEMENT_NODE;$i++){
    $c=$card->parentNode->getAttribute('class');
    if(preg_match('/(course|card|item|col)/i',$c)){ $card=$card->parentNode; break; }
    $card=$card->parentNode;
  }
  $img=''; foreach($x->query('.//img',$card) as $im){ $img=$im->getAttribute('src')?:$im->getAttribute('data-src'); if($img)break; }
  $title=txt($a); if(mb_strlen($title)<3){ foreach($x->query('.//h1|.//h2|.//h3|.//h4|.//h5',$card) as $hh){ $title=txt($hh); if($title)break; } }
  $price=''; foreach($x->query(".//*[contains(@class,'price') or contains(@class,'amount')]",$card) as $pp){ $price=txt($pp); if($price)break; }
  $cards++;
  echo "  [$cards] $title\n       url:   $href\n       img:   $img\n       price: $price\n";
  if($cards>=8) break;
}
if($cards===0) echo "  (aucune carte de cours individuelle détectée sur /platform/)\n";
echo "  total liens-cours uniques repérés: ".count($seen)."\n";
PHPEOF
php "$PHPX" /tmp/home.html 2>&1 | head -50
echo
echo "== Sliders/sections vedette sur /platform/ (indices) =="
grep -oiE 'class="[^"]*(slider|swiper|owl|featured|popular|top-cours|hero-course)[^"]*"' /tmp/home.html | sort | uniq -c | sort -rn | head -10

echo
echo "########## B) CATEGORIES depuis /platform/courses ##########"
curl -sSL -m 25 -A "$UA" "https://klasyo.org/platform/courses" -o /tmp/cat.html 2>/dev/null
echo "== category (principales) =="
grep -oiE 'href="https://klasyo.org/platform/category/courses/[a-z0-9-]+"[^>]*>[^<]{2,40}' /tmp/cat.html | sed -E 's/.*courses\/([a-z0-9-]+)".*>([^<]+)/  \1  =>  \2/' | sort -u | head -20
echo "== subcategory (sous-cours) =="
grep -oiE 'href="https://klasyo.org/platform/subcategory/courses/[a-z0-9-]+"[^>]*>[^<]{2,40}' /tmp/cat.html | sed -E 's/.*courses\/([a-z0-9-]+)".*>([^<]+)/  \1  =>  \2/' | sort -u | head -30

echo
echo "########## C) Quelle route SCHOOL répond ? ##########"
for U in "https://klasyo.org/school/" "https://klasyo.org/school/login" "https://klasyo.org/school/site" "https://klasyo.org/school/admin" "https://klasyo.org/school/home" "https://klasyo.org/school/public/" "https://klasyo.org/school/dashboard"; do
  read -r CODE FINAL < <(curl -sSL -m 18 -o /tmp/s.html -w '%{http_code} %{url_effective}' -A "$UA" "$U" 2>/dev/null; echo)
  T=$(grep -oiE '<title>[^<]*</title>' /tmp/s.html 2>/dev/null | head -1)
  echo "  $U -> HTTP $CODE (final:$FINAL) $T"
done
rm -f "$PHPX" /tmp/home.html /tmp/cat.html /tmp/s.html
echo
echo "== FIN étape X =="
