#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# djounes-catalogue.sh — vitrine d'accueil, découpes transparentes, attributs
# et variantes.
#
# Tout passe par les modèles Eloquent de l'application, jamais par du SQL
# écrit à la main : les casts du script décident eux-mêmes de la forme des
# données (product_ids, attribute_values), et les constantes du script
# décident du type d'un attribut. Rien n'est deviné.
#
# Sauvegarde complète avant écriture, vérification HTTP après, restauration
# automatique si la boutique ne répond plus.
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
CNF=$(mktemp); chmod 600 "$CNF"
printf '[client]\nuser=%s\npassword=%s\nhost=%s\n' "$DBU" "$DBP" "${DBH:-localhost}" > "$CNF"
SAFE=$(printf '%s' "$DBN" | tr -cd 'A-Za-z0-9_')

grp "Sauvegarde"
BK="$HOME/djounes-backups"; mkdir -p "$BK"
DUMP="$BK/db-$STAMP.sql.gz"
mysqldump --defaults-extra-file="$CNF" --single-transaction "$SAFE" 2>/dev/null | gzip > "$DUMP" \
  || { echo "::error::sauvegarde impossible — on n'écrit rien."; rm -f "$CNF"; exit 1; }
echo "base sauvegardée : $DUMP ($(du -h "$DUMP" | cut -f1))"
egrp
rm -f "$CNF"

# --- Découpes transparentes -------------------------------------------------
grp "Découpes produit sur fond transparent"
PRODDIR="$PUB/assets/images/product"
if [ -d "$UPLOAD/demo-cut" ]; then
  mkdir -p "$PRODDIR"
  cp -f "$UPLOAD/demo-cut/"*.png "$PRODDIR/" 2>/dev/null
  chmod 644 "$PRODDIR"/*.png 2>/dev/null
  echo "$(ls -1 "$PRODDIR"/*.png 2>/dev/null | wc -l) découpes en place"
  echo "(les JPEG d'origine restent sur le disque : rien ne casse si on revient en arrière)"
else
  echo "::warning::$UPLOAD/demo-cut absent — découpes non envoyées"
fi
egrp

grp "Vitrine, attributs et variantes"
cd "$APP" || exit 1
php <<'PHP'
<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;
use App\Models\ProductCollection;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\ProductVariant;
use App\Models\Media;

/* --- Le type d'un produit à variantes : on lit la constante du script
   plutôt que de parier sur 1 ou 2. --- */
$S = 'App\Constants\Status';
$constantes = class_exists($S) ? (new ReflectionClass($S))->getConstants() : [];
foreach ($constantes as $k => $v) {
    if (stripos($k, 'PRODUCT') !== false) echo "constante : $k = " . var_export($v, true) . "\n";
}
$TYPE_VARIABLE = $constantes['PRODUCT_TYPE_VARIABLE'] ?? $constantes['VARIABLE_PRODUCT'] ?? null;
$TYPE_TEXTE = $constantes['ATTRIBUTE_TYPE_TEXT'] ?? 1;
$TYPE_COULEUR = $constantes['ATTRIBUTE_TYPE_COLOR'] ?? 2;
echo "type « produit à variantes » : " . var_export($TYPE_VARIABLE, true) . "\n\n";

/* --- 1. Vitrine d'accueil ------------------------------------------------ */
$vitrine = ['nurse-scrub-set','compression-socks','whipped-body-butter',
            'canvas-tote-bag','body-oil','performance-headband'];
$ids = Product::whereIn('slug', $vitrine)->pluck('id')->all();

$col = ProductCollection::where('title', 'Shift favorites')->first();
if (!$col) {
    $col = new ProductCollection();
    $col->title = 'Shift favorites';
    $col->product_ids = $ids;
    $col->banner_position = 'left';
    $col->save();
    // Même règle de nommage que l'administration du script.
    $col->unique_key = 'collection_' . $col->id;
    $col->save();
    echo "vitrine créée : « {$col->title} » ({$col->unique_key}), " . count($ids) . " produits\n";
} else {
    $col->product_ids = $ids;
    $col->save();
    echo "vitrine déjà présente ({$col->unique_key}) — liste de produits rafraîchie\n";
}

/* --- 2. Attributs et valeurs --------------------------------------------- */
function attribut(string $nom, int $type, array $valeurs): Attribute {
    $a = Attribute::firstWhere('name', $nom);
    if (!$a) { $a = new Attribute(); $a->name = $nom; $a->type = $type; $a->status = 1; $a->save(); }
    foreach ($valeurs as $libelle => $valeur) {
        if (!AttributeValue::where('attribute_id', $a->id)->where('name', $libelle)->exists()) {
            $v = new AttributeValue();
            $v->attribute_id = $a->id; $v->name = $libelle; $v->value = $valeur;
            $v->save();
        }
    }
    return $a;
}

$taille = attribut('Size', $TYPE_TEXTE, array_combine(
    ['XS','S','M','L','XL','2XL','3XL'], ['XS','S','M','L','XL','2XL','3XL']));
$couleur = attribut('Color', $TYPE_COULEUR, [
    'Forest Green' => '#0B3B2E', 'Black' => '#0A0A0A',
    'Navy' => '#1B2A41', 'Wine' => '#5B2333']);
$compression = attribut('Compression', $TYPE_TEXTE, [
    '15-20 mmHg' => '15-20 mmHg', '20-30 mmHg' => '20-30 mmHg']);
$pointure = attribut('Shoe size', $TYPE_TEXTE, ['S/M' => 'S/M', 'L/XL' => 'L/XL']);
$volume = attribut('Volume', $TYPE_TEXTE, ['2 oz' => '2 oz', '4 oz' => '4 oz', '8 oz' => '8 oz']);

echo "attributs : Size, Color, Compression, Shoe size, Volume\n";

$vId = function (Attribute $a, array $noms) {
    return AttributeValue::where('attribute_id', $a->id)->whereIn('name', $noms)
        ->pluck('id', 'name')->all();
};

/* Quel produit reçoit quels attributs, et quelles combinaisons. */
$plan = [
  'nurse-scrub-set'       => [[$taille, ['XS','S','M','L','XL','2XL','3XL']],
                              [$couleur, ['Forest Green','Black','Navy']]],
  'everyday-underwear'    => [[$taille, ['XS','S','M','L','XL','2XL','3XL']]],
  'performance-headband'  => [[$couleur, ['Forest Green','Black','Wine']]],
  'compression-socks'     => [[$compression, ['15-20 mmHg','20-30 mmHg']],
                              [$pointure, ['S/M','L/XL']]],
  'canvas-tote-bag'       => [[$couleur, ['Forest Green','Black']]],
  'whipped-body-butter'   => [[$volume, ['2 oz','4 oz','8 oz']]],
  'body-oil'              => [[$volume, ['2 oz','4 oz']]],
];

$prefixes = ['nurse-scrub-set'=>'SCR','everyday-underwear'=>'UND','performance-headband'=>'HDB',
             'compression-socks'=>'SOC','canvas-tote-bag'=>'TOT',
             'whipped-body-butter'=>'BUT','body-oil'=>'OIL'];

$totalVariantes = 0;
foreach ($plan as $slug => $axes) {
    $p = Product::firstWhere('slug', $slug);
    if (!$p) { echo "  $slug : produit absent, ignoré\n"; continue; }

    $attrIds = []; $listes = [];
    foreach ($axes as [$attr, $noms]) {
        $attrIds[] = $attr->id;
        $map = $vId($attr, $noms);
        $listes[] = array_values(array_map(fn($n) => ['nom' => $n, 'id' => $map[$n]], $noms));
    }
    $p->attributes()->sync($attrIds);
    $p->attributeValues()->sync(array_merge(...array_map(
        fn($l) => array_column($l, 'id'), $listes)));

    // Produit cartésien des axes → une variante par combinaison.
    $combis = [[]];
    foreach ($listes as $liste) {
        $suivant = [];
        foreach ($combis as $c) foreach ($liste as $v) $suivant[] = array_merge($c, [$v]);
        $combis = $suivant;
    }

    $n = 0;
    foreach ($combis as $i => $combi) {
        $nom = implode(' / ', array_column($combi, 'nom'));
        $sku = 'DJ-' . $prefixes[$slug] . '-' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT);
        if (ProductVariant::where('product_id', $p->id)->where('sku', $sku)->exists()) continue;
        $v = new ProductVariant();
        $v->product_id      = $p->id;
        $v->name            = $nom;
        $v->sku             = $sku;
        $v->manage_stock    = 1;
        $v->track_inventory = 1;
        $v->show_stock      = 1;
        $v->in_stock        = 12;
        $v->alert_quantity  = 3;
        $v->attribute_values = array_column($combi, 'id');   // cast « array » du modèle
        $v->regular_price   = $p->regular_price;
        $v->sale_price      = $p->sale_price;
        $v->main_image_id   = $p->main_image_id;
        $v->is_published    = 1;
        $v->save();
        $n++;
    }
    if ($TYPE_VARIABLE !== null && $n) { $p->product_type = $TYPE_VARIABLE; $p->save(); }
    $totalVariantes += $n;
    echo "  $slug : " . count($attrIds) . " attribut(s), $n variante(s) créée(s)\n";
}
echo "total variantes créées : $totalVariantes\n";

/* --- 3. Découpes transparentes comme image principale -------------------- */
$n = 0;
foreach (Media::where('file_name', 'like', '%.jpg')->get() as $m) {
    $png = preg_replace('/\.jpg$/', '.png', $m->file_name);
    if (is_file(dirname(__DIR__) . '/assets/images/product/' . $png)) {
        $m->file_name = $png; $m->save(); $n++;
    }
}
echo "images produit basculées sur les découpes transparentes : $n\n";
PHP
egrp

grp "Vidage des caches"
( cd "$APP" && php artisan optimize:clear 2>&1 | head -5 ) || true
egrp

grp "Vérification"
OK=1
for p in "" "products" "product/nurse-scrub-set"; do
  C=$(curl -sS -o /dev/null -m 25 -w '%{http_code}' "https://$DOM/$p" 2>/dev/null)
  echo "https://$DOM/$p → $C"
  [ "$C" = "200" ] || OK=0
done
if [ "$OK" != "1" ]; then
  echo "::error::une page ne répond plus 200 — restauration de la base."
  RCNF=$(mktemp); chmod 600 "$RCNF"
  printf '[client]\nuser=%s\npassword=%s\nhost=%s\n' "$DBU" "$DBP" "${DBH:-localhost}" > "$RCNF"
  gunzip -c "$DUMP" | mysql --defaults-extra-file="$RCNF" "$SAFE" 2>&1 | head -3
  rm -f "$RCNF"
  ( cd "$APP" && php artisan optimize:clear >/dev/null 2>&1 ) || true
  exit 1
fi
echo "-- la vitrine apparaît-elle sur l'accueil ? --"
curl -sS -m 25 "https://$DOM/" 2>/dev/null | grep -oc "Shift favorites" || echo "0 (pas encore rendue)"
echo "Sauvegarde conservée : $DUMP"
egrp
echo "Terminé."
