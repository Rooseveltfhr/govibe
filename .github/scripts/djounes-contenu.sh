#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# djounes-contenu.sh — écrit les textes de marque dans les sections d'accueil
# et les pages de politiques, puis met en place une sauvegarde quotidienne.
#
# Principe de sûreté : le contenu du thème est stocké en JSON dont la forme
# exacte est propre au script acheté. On ne RECONSTRUIT jamais ce JSON — on le
# relit, et on remplace uniquement les clés qui existent déjà et qui portent
# une chaîne. Impossible de casser la structure, même sans la connaître.
# ---------------------------------------------------------------------------
set -uo pipefail

DOM="${DOMAIN:-djounes.com}"
STAMP=$(date +%Y%m%d%H%M%S)
grp() { echo "::group::$*"; }
egrp() { echo "::endgroup::"; }

D="$HOME/domains/$DOM"; PUB="$D/public_html"; APP="$PUB/core"; ENVF="$APP/.env"
[ -f "$APP/artisan" ] || { echo "::error::application introuvable ($APP)"; exit 1; }

val() { grep "^$1=" "$ENVF" 2>/dev/null | head -1 | cut -d= -f2- | tr -d '"'; }
DBN=$(val DB_DATABASE); DBU=$(val DB_USERNAME); DBP=$(val DB_PASSWORD); DBH=$(val DB_HOST)
CNF=$(mktemp); chmod 600 "$CNF"
printf '[client]\nuser=%s\npassword=%s\nhost=%s\n' "$DBU" "$DBP" "${DBH:-localhost}" > "$CNF"
SAFE=$(printf '%s' "$DBN" | tr -cd 'A-Za-z0-9_')

grp "Sauvegarde avant écriture"
BK="$HOME/djounes-backups"; mkdir -p "$BK"
DUMP="$BK/db-$STAMP.sql.gz"
if mysqldump --defaults-extra-file="$CNF" --single-transaction "$SAFE" 2>/dev/null | gzip > "$DUMP"; then
  echo "base sauvegardée : $DUMP ($(du -h "$DUMP" | cut -f1))"
else
  echo "::error::sauvegarde impossible — on n'écrit rien."; rm -f "$CNF"; exit 1
fi
egrp
rm -f "$CNF"

grp "Textes de marque"
DB_DATABASE="$DBN" DB_USERNAME="$DBU" DB_PASSWORD="$DBP" DB_HOST="${DBH:-localhost}" php <<'PHP'
<?php
$pdo = new PDO(
    sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', getenv('DB_HOST'), getenv('DB_DATABASE')),
    getenv('DB_USERNAME'), getenv('DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

/* Textes candidats par section. On n'écrit une valeur que si la clé existe
   déjà dans le JSON du thème et qu'elle contient une chaîne : la structure
   reste exactement celle que le script attend. */
$H  = 'Built for the 12-hour shift';
$SUB= 'Scrubs that move with you, and body care for after the shift. Made for the people who keep everyone else standing.';

$copy = [
  'banner.content' => [
    'heading' => $H, 'title' => $H, 'sub_heading' => $SUB, 'subheading' => $SUB,
    'short_description' => $SUB, 'description' => $SUB,
    'button_name' => 'Shop scrubs', 'button_text' => 'Shop scrubs',
  ],
  'feature.content' => [
    'heading' => 'Why nurses choose DJOUNES', 'title' => 'Why nurses choose DJOUNES',
    'short_description' => 'Free shipping over $75, 30-day returns, and fabrics tested through back-to-back shifts.',
    'description' => 'Free shipping over $75, 30-day returns, and fabrics tested through back-to-back shifts.',
  ],
  'services.content' => [
    'heading' => 'From clock-in to clock-out', 'title' => 'From clock-in to clock-out',
    'short_description' => 'What you wear during the shift, and what your skin needs after it.',
    'description' => 'What you wear during the shift, and what your skin needs after it.',
  ],
  'featured_categories.content' => [
    'heading' => 'Shop by category', 'title' => 'Shop by category',
    'short_description' => 'Scrubs and apparel, body care, and the accessories that carry it all.',
    'description' => 'Scrubs and apparel, body care, and the accessories that carry it all.',
  ],
  'top_selling_products.content' => [
    'heading' => 'Shift favorites', 'title' => 'Shift favorites',
    'short_description' => 'The pieces our customers reorder.',
    'description' => 'The pieces our customers reorder.',
  ],
  'featured_brands.content' => [
    'heading' => 'DJOUNES', 'title' => 'DJOUNES',
    'short_description' => 'One brand, made for healthcare.',
    'description' => 'One brand, made for healthcare.',
  ],
  'subscribe.content' => [
    'heading' => 'Get 15% off your first order', 'title' => 'Get 15% off your first order',
    'short_description' => 'Join the list for new drops, restocks and shift-day tips. No spam, unsubscribe anytime.',
    'description' => 'Join the list for new drops, restocks and shift-day tips. No spam, unsubscribe anytime.',
    'button_name' => 'Subscribe', 'button_text' => 'Subscribe',
  ],
  'newsletter.content' => [
    'heading' => 'Get 15% off your first order', 'title' => 'Get 15% off your first order',
    'short_description' => 'Join the list for new drops, restocks and shift-day tips.',
    'description' => 'Join the list for new drops, restocks and shift-day tips.',
  ],
  'about_us.content' => [
    'heading' => 'Built for the 12-hour shift', 'title' => 'Built for the 12-hour shift',
    'short_description' => 'DJOUNES makes what a healthcare worker actually needs across one long day.',
    'description' => "DJOUNES is an American brand built around one day: the 12-hour shift.\n\nWe started with a simple observation. The people who care for everyone else spend twelve hours in clothes that pull in the wrong places, on their feet until their legs ache, washing their hands until the skin cracks. So we make both halves of that day. Scrubs, compression socks, underwear and headbands for the hours on the floor. Body butter, oil and soap for the hours after.\n\nEvery piece is tested the only way that counts, by people who work those shifts.",
  ],
  'quote.content' => [
    'heading' => 'Built for the 12-hour shift', 'title' => 'Built for the 12-hour shift',
    'short_description' => 'What you wear during the shift, and what your skin needs after it.',
    'description' => 'What you wear during the shift, and what your skin needs after it.',
  ],
  'footer.content' => [
    'short_description' => 'DJOUNES makes scrubs, compression socks and body care for healthcare workers in the United States.',
    'description' => 'DJOUNES makes scrubs, compression socks and body care for healthcare workers in the United States.',
    'copyright_text' => '© ' . date('Y') . ' DJOUNES. All rights reserved.',
  ],
  'contact_page.content' => [
    'heading' => 'Talk to us', 'title' => 'Talk to us',
    'short_description' => 'Questions about sizing, an order or a return? We answer within one business day.',
    'description' => 'Questions about sizing, an order or a return? We answer within one business day.',
  ],
  'faq_page.content' => [
    'heading' => 'Questions we hear often', 'title' => 'Questions we hear often',
    'short_description' => 'Sizing, shipping, returns and care.',
    'description' => 'Sizing, shipping, returns and care.',
  ],
  'seo.data' => [
    'meta_title' => 'DJOUNES — Scrubs, Compression Socks & Body Care for Nurses',
    'meta_description' => 'Scrubs, compression socks, underwear and body care built for the 12-hour shift. Free US shipping over $75. 30-day returns.',
    'meta_keywords' => 'scrubs, nursing scrubs, compression socks, nurse apparel, body butter, healthcare uniforms',
  ],
];

/* Éléments répétés : listes d'arguments et de services. */
$elements = [
  'feature.element' => [
    ['Free shipping over $75', 'On every order shipped inside the United States.'],
    ['30-day returns',         'Wrong size? Send it back within 30 days.'],
    ['Made for healthcare',    'Fabrics tested through back-to-back shifts.'],
    ['Secure checkout',        'Encrypted payment by card or PayPal.'],
  ],
  'services.element' => [
    ['Scrubs & apparel',   'Scrubs, underwear, compression socks and headbands.'],
    ['Body care',          'Whipped body butter, oil and handmade soap.'],
    ['Accessories',        'The tote that carries the whole shift.'],
    ['Support that answers','Real people, one business day.'],
  ],
  'policy_pages.element' => [
    ['Shipping Policy',
     "Orders are processed within 1 to 2 business days, Monday through Friday, excluding US holidays. Standard shipping arrives in 3 to 5 business days, Express in 1 to 2. Shipping is free on orders over \$75 shipped inside the United States.\n\nYou receive a tracking number by email as soon as your parcel leaves our facility. We currently ship within the United States only.\n\nIf a parcel is marked delivered and you have not received it, contact us within 7 days and we will open an inquiry with the carrier."],
    ['Return & Refund Policy',
     "Unworn, unwashed items in their original packaging can be returned within 30 days of delivery. Body care products can only be returned unopened, for hygiene reasons.\n\nTo start a return, email us with your order number. We will send a return label. Once the parcel is received and inspected, the refund is issued to the original payment method within 5 to 7 business days.\n\nExchanges for a different size are free on the first exchange. Sale items are final."],
    ['Privacy Policy',
     "We collect only what an order requires: name, shipping address, email, phone number and payment confirmation. Card details are handled by our payment provider and never stored on our servers.\n\nYour email is used for order updates, and for our newsletter only if you subscribed. You can unsubscribe from any email in one click. We never sell or rent your data.\n\nTo access, correct or delete your data, write to us and we will act within 30 days."],
  ],
];

$sel = $pdo->prepare('SELECT id, data_values FROM frontends WHERE data_keys = ? ORDER BY id');
$upd = $pdo->prepare('UPDATE frontends SET data_values = ? WHERE id = ?');

/** Remplace uniquement les clés existantes portant une chaîne. */
function applique(&$obj, array $map, array &$journal, string $ctx): void {
    foreach ($map as $k => $v) {
        if (is_object($obj) && property_exists($obj, $k) && is_string($obj->$k)) {
            $obj->$k = $v; $journal[] = "$ctx.$k";
        } elseif (is_array($obj) && array_key_exists($k, $obj) && is_string($obj[$k])) {
            $obj[$k] = $v; $journal[] = "$ctx.$k";
        }
    }
}

$journal = []; $ignores = [];
foreach ($copy as $section => $map) {
    $sel->execute([$section]);
    $rows = $sel->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) { $ignores[] = $section; continue; }
    foreach ($rows as $row) {
        $data = json_decode($row['data_values']);
        if ($data === null) { $ignores[] = "$section (JSON illisible)"; continue; }
        applique($data, $map, $journal, $section);
        $upd->execute([json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $row['id']]);
    }
}

/* Les éléments sont ordonnés : on affecte le n-ième texte à la n-ième ligne. */
foreach ($elements as $section => $liste) {
    $sel->execute([$section]);
    $rows = $sel->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) { $ignores[] = $section; continue; }
    foreach (array_values($rows) as $i => $row) {
        if (!isset($liste[$i])) break;
        [$titre, $texte] = $liste[$i];
        $data = json_decode($row['data_values']);
        if ($data === null) continue;
        applique($data, [
            'title' => $titre, 'heading' => $titre, 'name' => $titre,
            'short_description' => $texte, 'description' => $texte, 'details' => $texte,
            'content' => $texte,
        ], $journal, $section . '[' . $i . ']');
        $upd->execute([json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $row['id']]);
    }
}

echo count($journal) . " champ(s) mis à jour :\n";
foreach (array_chunk($journal, 4) as $ligne) echo '  ' . implode(' · ', $ligne) . "\n";
if ($ignores) echo "\nSections absentes ou illisibles (ignorées) : " . implode(', ', $ignores) . "\n";

/* Coupon de bienvenue — inséré seulement si la table s'y prête et qu'il n'existe pas. */
try {
    $cols = array_column($pdo->query('SHOW COLUMNS FROM coupons')->fetchAll(PDO::FETCH_ASSOC), 'Field');
    if (!in_array('coupon_code', $cols, true)) {
        echo "\nCoupon non créé : colonne « coupon_code » absente.\n";
        echo "Colonnes réelles : " . implode(', ', $cols) . "\n";
    } elseif ($pdo->query("SELECT COUNT(*) FROM coupons WHERE coupon_code = 'FIRST15'")->fetchColumn()) {
        echo "\nCoupon FIRST15 : déjà présent — inchangé.\n";
    } else {
        $v = ['coupon_name' => 'Welcome 15%', 'coupon_code' => 'FIRST15',
              'discount_type' => 1, 'coupon_amount' => 15,
              'minimum_spend' => 0, 'maximum_spend' => 0,
              'usage_limit_per_coupon' => 0, 'usage_limit_per_user' => 1,
              'exclude_sale_items' => 0, 'status' => 1,
              'expired_at' => date('Y-m-d H:i:s', strtotime('+1 year')),
              'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')];
        $v = array_intersect_key($v, array_flip($cols));
        $pdo->prepare(sprintf('INSERT INTO coupons (%s) VALUES (%s)',
            implode(',', array_keys($v)), implode(',', array_fill(0, count($v), '?'))))
            ->execute(array_values($v));
        echo "\nCoupon FIRST15 créé : 15 pour cent, une utilisation par client, valable un an.\n";
        echo "À vérifier dans l'administration : le sens de discount_type (pourcentage ou montant fixe) est propre au script.\n";
    }
} catch (Throwable $e) {
    echo "\nCoupon non créé (schéma différent) : " . $e->getMessage() . "\n";
}
PHP
egrp

grp "Vidage des caches"
( cd "$APP" && php artisan optimize:clear 2>&1 | head -5 ) || true
egrp

# --- Sauvegarde quotidienne -------------------------------------------------
grp "Sauvegarde automatique quotidienne"
mkdir -p "$HOME/bin"
cat > "$HOME/bin/djounes-backup.sh" <<'BAK'
#!/usr/bin/env bash
# Sauvegarde quotidienne de djounes.com : base + fichiers envoyés.
# Conserve 14 jours. Installé par djounes-contenu.sh.
set -uo pipefail
DOM="djounes.com"
ENVF="$HOME/domains/$DOM/public_html/core/.env"
BK="$HOME/djounes-backups"; mkdir -p "$BK"
val() { grep "^$1=" "$ENVF" 2>/dev/null | head -1 | cut -d= -f2- | tr -d '"'; }
CNF=$(mktemp); chmod 600 "$CNF"
printf '[client]\nuser=%s\npassword=%s\nhost=%s\n' "$(val DB_USERNAME)" "$(val DB_PASSWORD)" "$(val DB_HOST)" > "$CNF"
mysqldump --defaults-extra-file="$CNF" --single-transaction "$(val DB_DATABASE)" 2>/dev/null \
  | gzip > "$BK/auto-db-$(date +%Y%m%d).sql.gz"
rm -f "$CNF"
# Images envoyées : ce que la base ne contient pas.
tar czf "$BK/auto-files-$(date +%Y%m%d).tar.gz" \
  -C "$HOME/domains/$DOM/public_html" assets/images 2>/dev/null
find "$BK" -name 'auto-*' -mtime +14 -delete 2>/dev/null
BAK
chmod +x "$HOME/bin/djounes-backup.sh"

if crontab -l 2>/dev/null | grep -q djounes-backup; then
  echo "tâche cron déjà en place"
else
  ( crontab -l 2>/dev/null; echo "17 3 * * * $HOME/bin/djounes-backup.sh >/dev/null 2>&1" ) | crontab - \
    && echo "tâche cron ajoutée : sauvegarde tous les jours à 3h17"
fi
crontab -l 2>/dev/null | grep djounes-backup
echo "-- première exécution immédiate --"
"$HOME/bin/djounes-backup.sh" && ls -1t "$HOME/djounes-backups" | head -4
egrp

grp "Vérification"
for p in "" "categories" "products" "contact" "about-us" "faq"; do
  echo "https://$DOM/$p → $(curl -sS -o /dev/null -m 25 -w '%{http_code}' "https://$DOM/$p" 2>/dev/null)"
done
egrp
echo "Terminé. Sauvegarde préalable : $DUMP"
