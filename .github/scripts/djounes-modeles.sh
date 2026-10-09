#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# djounes-modeles.sh — inspection EN LECTURE SEULE des modèles Eloquent.
#
# On amorce l'application elle-même plutôt que de lire le schéma : les casts,
# les relations et les constantes de statut disent la forme exacte attendue
# (collection.product_ids, product_variants.attribute_values, attributes.type),
# là où le SQL brut ne montre qu'un « varchar(255) » à deviner.
# ---------------------------------------------------------------------------
set -uo pipefail
DOM="${DOMAIN:-djounes.com}"
APP="$HOME/domains/$DOM/public_html/core"
[ -f "$APP/artisan" ] || { echo "::error::application introuvable"; exit 1; }

echo "::group::Modèles, casts et relations"
cd "$APP" || exit 1
php <<'PHP'
<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$classes = [
    'App\Models\ProductCollection',
    'App\Models\Attribute',
    'App\Models\AttributeValue',
    'App\Models\ProductVariant',
    'App\Models\Product',
];

foreach ($classes as $c) {
    echo "=== $c ===\n";
    if (!class_exists($c)) { echo "  (classe absente)\n\n"; continue; }
    $m = new $c;
    $r = new ReflectionClass($c);

    $casts = $m->getCasts();
    echo "  casts    : " . (($casts) ? json_encode($casts) : '(aucun)') . "\n";
    echo "  fillable : " . (($m->getFillable()) ? implode(',', $m->getFillable()) : '(vide)') . "\n";

    // Relations = méthodes publiques sans argument déclarées par la classe.
    $rel = [];
    foreach ($r->getMethods(ReflectionMethod::IS_PUBLIC) as $meth) {
        if ($meth->class !== $c || $meth->getNumberOfParameters() > 0) continue;
        if (in_array($meth->name, ['__construct'], true)) continue;
        $rel[] = $meth->name;
    }
    echo "  méthodes : " . implode(', ', $rel) . "\n";

    foreach ($r->getConstants() as $k => $v) {
        echo "  const    : $k = " . var_export($v, true) . "\n";
    }
    echo "\n";
}

// Le type d'un attribut est un tinyint : les constantes de statut disent
// souvent ce que valent 1 et 2 (texte / couleur).
echo "=== constantes de statut liées aux attributs ===\n";
if (class_exists('App\Constants\Status')) {
    foreach ((new ReflectionClass('App\Constants\Status'))->getConstants() as $k => $v) {
        if (stripos($k, 'ATTRIBUTE') !== false || stripos($k, 'COLOR') !== false
            || stripos($k, 'TEXT') !== false || stripos($k, 'VARIANT') !== false) {
            echo "  $k = " . var_export($v, true) . "\n";
        }
    }
} else {
    echo "  (App\\Constants\\Status absent)\n";
}

// Où les sections de collection vont chercher leurs données.
echo "\n=== résolution d'une section collection ===\n";
$f = __DIR__.'/resources/views/templates/basic/sections/collection.blade.php';
echo file_exists($f) ? implode('', array_slice(file($f), 0, 8)) : "(section absente)\n";
foreach (['app/Http/Helpers/helpers.php'] as $h) {
    $src = @file_get_contents(__DIR__.'/'.$h);
    if ($src && preg_match('/function\s+getCollection.*?\n\}/s', $src, $mm)) {
        echo "--- helper getCollection ---\n" . $mm[0] . "\n";
    }
}
PHP
echo "::endgroup::"

echo "::group::Comment la section collection est appelée"
grep -rn "collection" "$APP/resources/views/templates/basic/home.blade.php" 2>/dev/null | head -10
grep -rn "unique_key" "$APP/app" "$APP/resources/views/templates/basic" 2>/dev/null \
  | sed "s|$APP/||" | head -12
echo "::endgroup::"
echo "Terminé."
