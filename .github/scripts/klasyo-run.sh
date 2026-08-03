#!/usr/bin/env bash
# KLASYO — Étape Y : fusionner la section "Un écosystème, deux produits" dans la landing.
# Insertion AVANT #solutions (fallbacks: pricing/why/testimonials). Backup + validation stricte + rollback.
set -uo pipefail
ROOT="$HOME/domains/klasyo.org/public_html"
LAND="$ROOT/index.html"
[ -f "$LAND" ] || { echo "(!) landing introuvable"; exit 0; }

FRAG="$(mktemp /tmp/k2_frag_XXXX.html)"
cat > "$FRAG" <<'K2EOF'
<section id="klasyo-ecosysteme" class="k2-sec">
  <style>
    .k2-sec{--k2-blue:#1d4ed8;--k2-blue-d:#1e3a8a;--k2-green:#16a34a;--k2-green-d:#166534;
      --k2-ink:#0c1524;--k2-muted:#5b6675;--k2-line:#e6ecf7;
      padding:72px 20px;background:linear-gradient(180deg,#f8faff 0%,#eef4ff 100%);
      font-family:'DM Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:var(--k2-ink)}
    .k2-sec *{box-sizing:border-box}
    .k2-wrap{max-width:1160px;margin:0 auto}
    .k2-head{text-align:center;max-width:760px;margin:0 auto 46px}
    .k2-eyebrow{display:inline-flex;align-items:center;gap:8px;font-weight:700;font-size:13px;
      letter-spacing:.6px;text-transform:uppercase;color:var(--k2-blue);
      background:#e7efff;border:1px solid #d3e2ff;padding:7px 15px;border-radius:999px;margin-bottom:18px}
    .k2-head h2{font-family:'Sora',sans-serif;font-weight:800;font-size:clamp(26px,3.6vw,40px);
      line-height:1.14;margin:0 0 14px;letter-spacing:-.5px;color:var(--k2-ink)}
    .k2-head h2 b{background:linear-gradient(120deg,var(--k2-blue),var(--k2-green));
      -webkit-background-clip:text;background-clip:text;color:transparent}
    .k2-head p{font-size:17px;line-height:1.6;color:var(--k2-muted);margin:0}
    .k2-grid{display:grid;grid-template-columns:1fr 1fr;gap:26px}
    .k2-card{position:relative;overflow:hidden;background:#fff;border:1px solid var(--k2-line);
      border-radius:22px;padding:34px 32px;box-shadow:0 18px 44px rgba(20,40,90,.08);
      display:flex;flex-direction:column}
    .k2-card::before{content:"";position:absolute;inset:0 0 auto 0;height:5px}
    .k2-learn::before{background:linear-gradient(90deg,var(--k2-blue),#3b82f6)}
    .k2-school::before{background:linear-gradient(90deg,var(--k2-green),#22c55e)}
    .k2-badge{display:inline-flex;align-items:center;gap:10px;font-weight:700;font-size:13px;
      padding:8px 14px;border-radius:10px;width:fit-content;margin-bottom:18px}
    .k2-learn .k2-badge{color:var(--k2-blue-d);background:#eaf1ff}
    .k2-school .k2-badge{color:var(--k2-green-d);background:#e8f8ee}
    .k2-ic{width:26px;height:26px;display:grid;place-items:center;border-radius:8px;color:#fff}
    .k2-learn .k2-ic{background:linear-gradient(135deg,var(--k2-blue),var(--k2-blue-d))}
    .k2-school .k2-ic{background:linear-gradient(135deg,var(--k2-green),var(--k2-green-d))}
    .k2-ic svg{width:16px;height:16px;stroke:#fff;fill:none;stroke-width:2}
    .k2-card h3{font-family:'Sora',sans-serif;font-size:23px;font-weight:800;margin:0 0 8px;color:var(--k2-ink)}
    .k2-card .k2-desc{font-size:15px;line-height:1.55;color:var(--k2-muted);margin:0 0 20px}
    .k2-feats{list-style:none;padding:0;margin:0 0 22px;display:grid;gap:9px}
    .k2-feats li{display:flex;gap:10px;align-items:flex-start;font-size:14.5px;color:#33404f}
    .k2-feats svg{flex:none;width:18px;height:18px;margin-top:1px;stroke:var(--k2-green);fill:none;stroke-width:2.4}
    .k2-chips-lbl{font-size:12.5px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;
      color:#8592a6;margin:0 0 11px}
    .k2-chips{display:flex;flex-wrap:wrap;gap:9px;margin-bottom:24px}
    .k2-chip{font-size:13.5px;font-weight:600;color:var(--k2-blue-d);text-decoration:none;
      background:#f2f6ff;border:1px solid #dde8ff;padding:8px 14px;border-radius:999px;transition:.15s}
    .k2-chip:hover{background:var(--k2-blue);color:#fff;border-color:var(--k2-blue);transform:translateY(-1px)}
    .k2-actions{margin-top:auto;display:flex;flex-wrap:wrap;gap:12px}
    .k2-btn{display:inline-flex;align-items:center;gap:9px;font-weight:700;font-size:15px;
      text-decoration:none;padding:13px 22px;border-radius:12px;transition:.16s;border:1.6px solid transparent}
    .k2-btn svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2}
    .k2-btn-blue{background:linear-gradient(135deg,var(--k2-blue),var(--k2-blue-d));color:#fff;
      box-shadow:0 12px 26px rgba(29,78,216,.26)}
    .k2-btn-blue:hover{transform:translateY(-2px);box-shadow:0 16px 32px rgba(29,78,216,.34)}
    .k2-btn-green{background:linear-gradient(135deg,var(--k2-green),var(--k2-green-d));color:#fff;
      box-shadow:0 12px 26px rgba(22,163,74,.26)}
    .k2-btn-green:hover{transform:translateY(-2px);box-shadow:0 16px 32px rgba(22,163,74,.34)}
    .k2-btn-ghost{background:#fff;color:var(--k2-blue-d);border-color:#d3e2ff}
    .k2-btn-ghost:hover{background:#f2f6ff}
    .k2-note{text-align:center;margin-top:34px;font-size:14px;color:var(--k2-muted)}
    .k2-note a{color:var(--k2-blue);font-weight:700;text-decoration:none}
    @media(max-width:840px){.k2-grid{grid-template-columns:1fr}.k2-sec{padding:52px 16px}}
  </style>
  <div class="k2-wrap">
    <div class="k2-head">
      <span class="k2-eyebrow">✦ Un écosystème, deux produits</span>
      <h2>Toute l'éducation KLASYO,<br><b>un seul compte, deux tableaux de bord</b></h2>
      <p>KLASYO réunit l'apprentissage en ligne et la gestion d'établissement sous une même marque.
         Choisissez votre espace — vos identifiants fonctionnent partout.</p>
    </div>
    <div class="k2-grid">
      <div class="k2-card k2-learn">
        <span class="k2-badge"><span class="k2-ic"><svg viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.25 3 10l9 3.75L21 10 12 6.25Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6.5 11.5V16c0 .8 2.46 2 5.5 2s5.5-1.2 5.5-2v-4.5"/></svg></span>KLASYO Aprann · LMS</span>
        <h3>Formez-vous, enseignez, certifiez</h3>
        <p class="k2-desc">La marketplace de cours et de formations en ligne : suivez des cours,
           devenez formateur, délivrez des certificats — 100% en français.</p>
        <ul class="k2-feats">
          <li><svg viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12.5 4.5 4.5L19 7"/></svg>Cours en ligne, présentiel &amp; hybride</li>
          <li><svg viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12.5 4.5 4.5L19 7"/></svg>Certificats &amp; espace formateur (revenus)</li>
          <li><svg viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12.5 4.5 4.5L19 7"/></svg>Paiements locaux &amp; internationaux</li>
        </ul>
        <p class="k2-chips-lbl">Explorez par catégorie — accès direct au catalogue</p>
        <div class="k2-chips">
          <a class="k2-chip" href="https://klasyo.org/platform/category/courses/development">Développement</a>
          <a class="k2-chip" href="https://klasyo.org/platform/category/courses/business">Business</a>
          <a class="k2-chip" href="https://klasyo.org/platform/category/courses/design">Design</a>
          <a class="k2-chip" href="https://klasyo.org/platform/category/courses/marketing">Marketing</a>
          <a class="k2-chip" href="https://klasyo.org/platform/category/courses/it-software">IT &amp; Logiciels</a>
          <a class="k2-chip" href="https://klasyo.org/platform/category/courses/finance-accounting">Finance &amp; Compta</a>
          <a class="k2-chip" href="https://klasyo.org/platform/category/courses/personal-development">Dév. personnel</a>
          <a class="k2-chip" href="https://klasyo.org/platform/category/courses/health-fitness">Santé &amp; Bien-être</a>
          <a class="k2-chip" href="https://klasyo.org/platform/category/courses/office-productivity">Bureautique</a>
        </div>
        <div class="k2-actions">
          <a class="k2-btn k2-btn-blue" href="https://klasyo.org/platform/courses">Parcourir les cours
            <svg viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h14m-5-5 5 5-5 5"/></svg></a>
          <a class="k2-btn k2-btn-ghost" href="https://klasyo.org/platform/login">Se connecter</a>
        </div>
      </div>
      <div class="k2-card k2-school">
        <span class="k2-badge"><span class="k2-ic"><svg viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.5 20.5h17M5 20.5V9l7-4.5L19 9v11.5M9.5 20.5V14h5v6.5"/></svg></span>KLASYO Lekòl · Gestion scolaire</span>
        <h3>Gérez toute votre école</h3>
        <p class="k2-desc">Le logiciel complet de gestion d'établissement : élèves, classes, notes,
           présences et paiements — pensé pour les écoles d'Haïti et de la Caraïbe.</p>
        <ul class="k2-feats">
          <li><svg viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12.5 4.5 4.5L19 7"/></svg>Élèves, classes, notes &amp; bulletins</li>
          <li><svg viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12.5 4.5 4.5L19 7"/></svg>Présences, frais de scolarité &amp; reçus</li>
          <li><svg viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12.5 4.5 4.5L19 7"/></svg>Multi-écoles (SaaS) &amp; rôles du personnel</li>
        </ul>
        <p class="k2-chips-lbl">Pour les directions &amp; administrations scolaires</p>
        <div class="k2-chips">
          <span class="k2-chip" style="cursor:default">Inscriptions</span>
          <span class="k2-chip" style="cursor:default">Emplois du temps</span>
          <span class="k2-chip" style="cursor:default">Comptabilité</span>
          <span class="k2-chip" style="cursor:default">Communication parents</span>
        </div>
        <div class="k2-actions">
          <a class="k2-btn k2-btn-green" href="https://klasyo.org/school/public/">Accéder à KLASYO Lekòl
            <svg viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h14m-5-5 5 5-5 5"/></svg></a>
        </div>
      </div>
    </div>
    <p class="k2-note">Un seul identifiant pour les deux espaces. Nouveau sur KLASYO ?
      <a href="https://klasyo.org/platform/login">Créer un compte</a></p>
  </div>
</section>
K2EOF
echo "  fragment: $(wc -c < "$FRAG") octets"

STAMP="$(date +%Y%m%d-%H%M%S)"
BK="$ROOT/index.html.bak-ecosys-$STAMP"
cp -p "$LAND" "$BK" && echo "  backup: $BK"
SZ_BEFORE=$(wc -c < "$LAND")

PHPX="$(mktemp /tmp/k2_ins_XXXX.php)"
cat > "$PHPX" <<'PHPEOF'
<?php
[$_, $landing, $fragfile] = $argv;
$html = file_get_contents($landing);
$frag = file_get_contents($fragfile);

if (strpos($html, 'id="klasyo-ecosysteme"') !== false) {
  fwrite(STDERR, "DEJA PRESENT: section déjà insérée. Aucune écriture.\n"); exit(9);
}
// Anchors candidats (ordre de préférence) : insérer AVANT le tag ouvrant trouvé.
$anchors = [
  '/<(?:section|div)\b[^>]*\bid=["\']solutions["\'][^>]*>/i',
  '/<(?:section|div)\b[^>]*\bid=["\']pricing["\'][^>]*>/i',
  '/<(?:section|div)\b[^>]*\bid=["\']why["\'][^>]*>/i',
  '/<(?:section|div)\b[^>]*\bid=["\']how["\'][^>]*>/i',
  '/<(?:section|div)\b[^>]*\bid=["\']testimonials["\'][^>]*>/i',
];
$done = false; $used = '';
foreach ($anchors as $rx) {
  if (preg_match($rx, $html, $m, PREG_OFFSET_CAPTURE)) {
    $pos = $m[0][1];
    $html = substr($html, 0, $pos) . $frag . "\n" . substr($html, $pos);
    $done = true; $used = $m[0][0];
    break;
  }
}
if (!$done) {
  fwrite(STDERR, "ANCRE INTROUVABLE: aucune section connue. Aucune écriture.\n");
  // aide au diagnostic : lister les id="..." de sections/div de 1er niveau
  if (preg_match_all('/<(?:section|div)\b[^>]*\bid=["\']([a-z0-9_-]+)["\']/i', $html, $mm)) {
    fwrite(STDERR, "  ids présents: ".implode(', ', array_unique($mm[1]))."\n");
  }
  exit(4);
}
file_put_contents($landing, $html);
echo "  INSERE avant: ".trim($used)."\n";
PHPEOF

if php "$PHPX" "$LAND" "$FRAG"; then
  SZ_AFTER=$(wc -c < "$LAND"); DELTA=$((SZ_AFTER - SZ_BEFORE))
  echo "  taille: $SZ_BEFORE -> $SZ_AFTER (Δ=$DELTA)"
  OK=1
  [ "$DELTA" -lt 5000 ] && OK=0
  [ "$DELTA" -gt 40000 ] && OK=0
  [ "$(head -c 15 "$LAND")" = "<!DOCTYPE html>" ] || OK=0
  N=$(grep -c 'id="klasyo-ecosysteme"' "$LAND")
  [ "$N" = "1" ] || OK=0
  # le bouton Connexion recâblé doit toujours être là (non corrompu)
  grep -q 'href="https://klasyo.org/platform/login" class="btn-ghost"' "$LAND" || OK=0
  if [ "$OK" = "1" ]; then
    echo "  VALIDATION OK (section unique, HTML intact, Connexion préservée)"
  else
    echo "  (!) VALIDATION ECHOUEE -> ROLLBACK"; cp -p "$BK" "$LAND"
  fi
else
  echo "  (!) insertion PHP échouée -> ROLLBACK"; cp -p "$BK" "$LAND"
fi
rm -f "$PHPX" "$FRAG"

echo
echo "== Contrôle: la landing sert-elle toujours du HTML et contient la section ? =="
head -c 40 "$LAND"; echo
grep -c 'id="klasyo-ecosysteme"' "$LAND"
grep -oE 'klasyo.org/platform/category/courses/[a-z-]+' "$LAND" | sort -u | wc -l
echo
echo "== Vérif live (HTTP + présence marqueur) =="
BODY="$(curl -sSL -m 25 -A 'Mozilla/5.0 klasyo-check' 'https://klasyo.org/' 2>/dev/null)"
CODE="$(curl -sSL -m 25 -o /dev/null -w '%{http_code}' -A 'Mozilla/5.0 klasyo-check' 'https://klasyo.org/' 2>/dev/null)"
echo "  https://klasyo.org/ -> HTTP $CODE"
printf '%s' "$BODY" | grep -c 'klasyo-ecosysteme'
echo
echo "== FIN étape Y =="
