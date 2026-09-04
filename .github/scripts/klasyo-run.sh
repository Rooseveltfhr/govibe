#!/usr/bin/env bash
# KLASYO — MODULE B : injecter la section dynamique "cours par catégorie + populaires + nouveautés"
# (données live via l'API JSON same-origin) dans la landing klasyo.org. Backup + validation + rollback + vérif live.
set -uo pipefail
ROOT="$HOME/domains/klasyo.org/public_html"
LAND="$ROOT/index.html"
[ -f "$LAND" ] || { echo "(!) landing introuvable"; exit 0; }

FRAG="$(mktemp /tmp/kc_frag_XXXX.html)"
cat > "$FRAG" <<'KCEOF'
<section id="klasyo-courses-live" class="kc-sec">
  <style>
    .kc-sec{--kc-blue:#1d4ed8;--kc-blue-d:#1e3a8a;--kc-green:#16a34a;--kc-ink:#0c1524;--kc-muted:#5b6675;--kc-line:#e6ecf7;
      padding:64px 20px;background:#ffffff;font-family:'DM Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:var(--kc-ink)}
    .kc-sec *{box-sizing:border-box}
    .kc-wrap{max-width:1180px;margin:0 auto}
    .kc-head{text-align:center;max-width:720px;margin:0 auto 34px}
    .kc-eyebrow{display:inline-block;font-weight:700;font-size:12.5px;letter-spacing:.6px;text-transform:uppercase;
      color:var(--kc-blue);background:#eaf1ff;border:1px solid #d3e2ff;padding:6px 14px;border-radius:999px;margin-bottom:14px}
    .kc-head h2{font-family:'Sora',sans-serif;font-weight:800;font-size:clamp(24px,3.2vw,36px);line-height:1.15;margin:0 0 10px}
    .kc-head p{font-size:16px;color:var(--kc-muted);margin:0;line-height:1.55}
    .kc-scroll{display:flex;gap:16px;overflow-x:auto;scroll-snap-type:x mandatory;padding:6px 2px 16px;-webkit-overflow-scrolling:touch}
    .kc-scroll::-webkit-scrollbar{height:8px}.kc-scroll::-webkit-scrollbar-thumb{background:#d7e0f2;border-radius:999px}
    .kc-cats{margin-bottom:14px}
    .kc-cat{flex:0 0 auto;scroll-snap-align:start;display:flex;flex-direction:column;align-items:center;gap:10px;
      width:132px;padding:18px 12px;border:1.4px solid var(--kc-line);border-radius:16px;text-decoration:none;color:var(--kc-ink);
      background:#f9fbff;transition:.15s}
    .kc-cat:hover{border-color:var(--kc-blue);box-shadow:0 10px 24px rgba(29,78,216,.12);transform:translateY(-2px)}
    .kc-cat-ic{width:56px;height:56px;border-radius:14px;background:#eaf1ff;display:grid;place-items:center;overflow:hidden}
    .kc-cat-ic img{width:34px;height:34px;object-fit:contain}
    .kc-cat-name{font-size:13.5px;font-weight:600;text-align:center;line-height:1.3}
    .kc-block{margin-top:26px}
    .kc-block-h{display:flex;align-items:baseline;justify-content:space-between;margin-bottom:6px}
    .kc-block-h h3{font-family:'Sora',sans-serif;font-size:20px;font-weight:800;margin:0}
    .kc-block-h a{color:var(--kc-blue);font-weight:700;font-size:14px;text-decoration:none}
    .kc-card{flex:0 0 auto;scroll-snap-align:start;width:250px;border:1.4px solid var(--kc-line);border-radius:16px;overflow:hidden;
      text-decoration:none;color:var(--kc-ink);background:#fff;transition:.15s;display:flex;flex-direction:column}
    .kc-card:hover{border-color:var(--kc-blue);box-shadow:0 12px 26px rgba(20,40,90,.12);transform:translateY(-2px)}
    .kc-thumb{aspect-ratio:16/9;background:#eef3fb;overflow:hidden}
    .kc-thumb img{width:100%;height:100%;object-fit:cover}
    .kc-body{padding:13px 14px 15px}
    .kc-title{font-weight:700;font-size:14.5px;line-height:1.35;margin-bottom:6px;
      display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:39px}
    .kc-inst{font-size:12.5px;color:var(--kc-muted);margin-bottom:8px}
    .kc-meta{display:flex;align-items:center;justify-content:space-between}
    .kc-price{font-weight:800;color:var(--kc-blue-d);font-size:15px}
    .kc-old{color:#9aa6b8;text-decoration:line-through;font-weight:600;font-size:12.5px;margin-right:4px}
    .kc-rate{font-size:12.5px;color:#b45309;font-weight:700}
    .kc-empty{width:100%;text-align:center;color:var(--kc-muted);font-size:14.5px;background:#f7faff;border:1px dashed #cfdcf5;
      border-radius:14px;padding:22px 18px}
    .kc-empty a{color:var(--kc-blue);font-weight:700;text-decoration:none}
    @media(max-width:560px){.kc-card{width:220px}}
  </style>

  <div class="kc-wrap">
    <div class="kc-head">
      <span class="kc-eyebrow">Formations KLASYO</span>
      <h2>Explorez nos cours par catégorie</h2>
      <p>Cliquez sur une catégorie ou un cours pour entrer directement dans la plateforme d'apprentissage.</p>
    </div>

    <div id="kc-cats" class="kc-cats kc-scroll" aria-label="Catégories de cours"></div>

    <div class="kc-block">
      <div class="kc-block-h"><h3>Cours populaires</h3><a href="https://klasyo.org/platform/courses">Tout voir →</a></div>
      <div id="kc-pop" class="kc-row kc-scroll"></div>
    </div>

    <div class="kc-block">
      <div class="kc-block-h"><h3>Nouveautés</h3><a href="https://klasyo.org/platform/courses">Tout voir →</a></div>
      <div id="kc-new" class="kc-row kc-scroll"></div>
    </div>
  </div>

  <script>
  (function(){
    var API="https://klasyo.org/platform/api";
    var PBASE="https://klasyo.org/platform";
    function esc(s){ return String(s==null?"":s).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;"); }
    function node(tag,cls){ var e=document.createElement(tag); if(cls)e.className=cls; return e; }
    function thumbOf(c){ return c.image_url || (c.image ? PBASE+"/"+c.image : ""); }
    function instOf(c){
      if(c.user && c.user.name) return c.user.name;
      if(c.instructor){ return ((c.instructor.first_name||"")+" "+(c.instructor.last_name||"")).trim(); }
      if(c.organization){ return ((c.organization.first_name||"")+" "+(c.organization.last_name||"")).trim(); }
      return "";
    }
    function priceHtml(c){
      var p=Number(c.price||0), o=Number(c.old_price||0);
      if(!p) return "Gratuit";
      return (o&&o>p ? '<span class="kc-old">'+o+'</span>' : '')+p;
    }
    function courseCard(c){
      var a=node("a","kc-card"); a.href=PBASE+"/course-details/"+(c.slug||"");
      var t=thumbOf(c), inst=instOf(c);
      var rate=c.average_rating?('★ '+Number(c.average_rating).toFixed(1)):"";
      a.innerHTML=
        '<div class="kc-thumb">'+(t?'<img loading="lazy" src="'+esc(t)+'" alt="'+esc(c.title)+'">':'')+'</div>'+
        '<div class="kc-body"><div class="kc-title">'+esc(c.title)+'</div>'+
        (inst?'<div class="kc-inst">'+esc(inst)+'</div>':'')+
        '<div class="kc-meta"><span class="kc-price">'+priceHtml(c)+'</span>'+
        (rate?'<span class="kc-rate">'+rate+'</span>':'')+'</div></div>';
      return a;
    }
    function catCard(c){
      var a=node("a","kc-cat"); a.href=PBASE+"/category/courses/"+(c.slug||"");
      var im=c.image_url||"";
      a.innerHTML='<span class="kc-cat-ic">'+(im?'<img loading="lazy" src="'+esc(im)+'" alt="'+esc(c.name)+'">':'')+'</span>'+
        '<span class="kc-cat-name">'+esc(c.name)+'</span>';
      return a;
    }
    function getJSON(u){ return fetch(u,{headers:{Accept:"application/json"}}).then(function(r){return r.json();}); }
    function fill(id, cards, emptyHtml){
      var box=document.getElementById(id); if(!box)return;
      box.innerHTML="";
      if(cards&&cards.length){ cards.forEach(function(c){box.appendChild(c);}); }
      else { var e=node("div","kc-empty"); e.innerHTML=emptyHtml; box.appendChild(e); }
    }
    getJSON(API+"/home/category-course").then(function(j){
      var arr=(j&&j.data)||[];
      fill("kc-cats", arr.map(catCard), 'Catégories bientôt disponibles. <a href="'+PBASE+'/courses">Voir le catalogue →</a>');
    }).catch(function(){ fill("kc-cats",[], 'Voir toutes les catégories dans <a href="'+PBASE+'/courses">le catalogue →</a>'); });

    getJSON(API+"/home/courses").then(function(j){
      var arr=(j&&j.data&&j.data.topCourse)||[];
      fill("kc-pop", arr.map(courseCard), 'Les cours populaires apparaîtront ici dès les premières inscriptions. <a href="'+PBASE+'/courses">Explorer le catalogue →</a>');
    }).catch(function(){ fill("kc-pop",[], 'Explorer <a href="'+PBASE+'/courses">le catalogue →</a>'); });

    getJSON(API+"/courses-list").then(function(j){
      var d=(j&&j.data)||{}; var arr=d.courses||d.topCourse||[];
      fill("kc-new", arr.map(courseCard), 'Aucun cours publié pour le moment. <a href="'+PBASE+'/login">Devenez formateur et publiez le premier →</a>');
    }).catch(function(){ fill("kc-new",[], 'Devenez formateur sur <a href="'+PBASE+'/login">KLASYO →</a>'); });
  })();
  </script>
</section>
KCEOF
echo "  fragment: $(wc -c < "$FRAG") octets"

STAMP="$(date +%Y%m%d-%H%M%S)"
BK="$ROOT/index.html.bak-courseslive-$STAMP"
cp -p "$LAND" "$BK" && echo "  backup: $BK"
SZ_BEFORE=$(wc -c < "$LAND")

PHPX="$(mktemp /tmp/kc_ins_XXXX.php)"
cat > "$PHPX" <<'PHPEOF'
<?php
[$_, $landing, $fragfile] = $argv;
$html = file_get_contents($landing);
$frag = file_get_contents($fragfile);
if (strpos($html,'id="klasyo-courses-live"')!==false){ fwrite(STDERR,"DEJA PRESENT\n"); exit(9); }
$anchors=[
  '/<(?:section|div)\b[^>]*\bid=["\']solutions["\'][^>]*>/i',
  '/<(?:section|div)\b[^>]*\bid=["\']pricing["\'][^>]*>/i',
  '/<(?:section|div)\b[^>]*\bid=["\']why["\'][^>]*>/i',
];
foreach($anchors as $rx){
  if(preg_match($rx,$html,$m,PREG_OFFSET_CAPTURE)){
    $pos=$m[0][1];
    $html=substr($html,0,$pos).$frag."\n".substr($html,$pos);
    file_put_contents($landing,$html);
    echo "  INSERE avant: ".trim($m[0][0])."\n"; exit(0);
  }
}
fwrite(STDERR,"ANCRE INTROUVABLE\n"); exit(4);
PHPEOF

if php "$PHPX" "$LAND" "$FRAG"; then
  SZ_AFTER=$(wc -c < "$LAND"); DELTA=$((SZ_AFTER-SZ_BEFORE))
  echo "  taille: $SZ_BEFORE -> $SZ_AFTER (Δ=$DELTA)"
  OK=1
  [ "$DELTA" -lt 4000 ] && OK=0
  [ "$DELTA" -gt 40000 ] && OK=0
  [ "$(head -c 15 "$LAND")" = "<!DOCTYPE html>" ] || OK=0
  [ "$(grep -c 'id=\"klasyo-courses-live\"' "$LAND")" = "1" ] || OK=0
  grep -q 'id="klasyo-ecosysteme"' "$LAND" || OK=0     # section précédente intacte
  grep -q 'href="https://klasyo.org/platform/login" class="btn-ghost"' "$LAND" || OK=0  # bouton Connexion intact
  if [ "$OK" = "1" ]; then
    echo "  VALIDATION OK"
  else
    echo "  (!) VALIDATION ECHOUEE -> ROLLBACK"; cp -p "$BK" "$LAND"
  fi
else
  echo "  (!) insertion échouée -> ROLLBACK"; cp -p "$BK" "$LAND"
fi
rm -f "$PHPX" "$FRAG"

echo
echo "== Vérif live (HTTP + présence marqueur) =="
CODE="$(curl -sSL -m 25 -o /dev/null -w '%{http_code}' -A 'Mozilla/5.0 klasyo-check' 'https://klasyo.org/' 2>/dev/null)"
echo "  https://klasyo.org/ -> HTTP $CODE"
curl -sSL -m 25 -A 'Mozilla/5.0 klasyo-check' 'https://klasyo.org/' 2>/dev/null | grep -c 'klasyo-courses-live'
echo
echo "== FIN MODULE B =="
