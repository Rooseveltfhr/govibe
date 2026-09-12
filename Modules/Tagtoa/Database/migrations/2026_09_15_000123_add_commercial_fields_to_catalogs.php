<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA — ce qu'il faut pour savoir si le commerce gagne de l'argent.
 *
 * Beaucoup de petits commerces savent exactement combien ils ont vendu, et pas
 * du tout combien ils ont gagné. Sans prix d'achat, aucune marge, aucun profit,
 * aucun rapport qui serve à décider. C'est la différence entre une caisse qui
 * compte l'argent et un outil de gestion.
 *
 * Les colonnes vont sur les DEUX catalogues, parce que la caisse vend les deux
 * depuis B-3 : un plat du menu a un coût matière comme un article de la caisse
 * a un prix d'achat. La LOGIQUE, elle, n'est écrite qu'une fois
 * (Support/Catalog/Pricing).
 *
 * `cost_price` est nullable et le reste : « 0 » laisserait croire que la marge
 * est totale, alors que la vérité est qu'on ne sait pas encore.
 */
return new class extends Migration
{
    /** Les mêmes champs des deux côtés, pour ne pas diverger. */
    private const TABLES = ['tagtoa_pos_products', 'tagtoa_menu_items'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'cost_price')) {
                    // Nullable : « pas renseigné » n'est pas « gratuit ».
                    $t->decimal('cost_price', 12, 2)->nullable()->after('price');
                }
                if (! Schema::hasColumn($table, 'unit')) {
                    // piece | lb | kg | mamit | gode… — voir Support/Catalog/Pricing.
                    $t->string('unit', 12)->default('piece')->after('cost_price');
                }
                if (! Schema::hasColumn($table, 'low_stock_threshold')) {
                    // Null = le plancher commun s'applique. Une boulangerie qui
                    // vend 200 pains par jour n'alerte pas au même niveau
                    // qu'un bijoutier.
                    // Décimal : un seuil de 2,5 mamit de riz est une phrase
                    // qui a du sens dans une boutique de quartier.
                    $t->decimal('low_stock_threshold', 12, 3)->nullable();
                }
                if (! Schema::hasColumn($table, 'sku')) {
                    // Référence interne du commerce, distincte du code-barres :
                    // elle sert à SES yeux, pas au scanner.
                    $t->string('sku', 60)->nullable();
                }
            });
        }

        // Retrouver un article par sa référence interne, dans son commerce.
        Schema::table('tagtoa_pos_products', function (Blueprint $t) {
            $t->index(['tenant_id', 'sku'], 'tagtoa_pos_products_sku');
        });
        Schema::table('tagtoa_menu_items', function (Blueprint $t) {
            $t->index(['menu_id', 'sku'], 'tagtoa_menu_items_sku');
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_pos_products', fn (Blueprint $t) => $t->dropIndex('tagtoa_pos_products_sku'));
        Schema::table('tagtoa_menu_items', fn (Blueprint $t) => $t->dropIndex('tagtoa_menu_items_sku'));

        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                foreach (['cost_price', 'unit', 'low_stock_threshold', 'sku'] as $colonne) {
                    if (Schema::hasColumn($table, $colonne)) {
                        $t->dropColumn($colonne);
                    }
                }
            });
        }
    }
};
