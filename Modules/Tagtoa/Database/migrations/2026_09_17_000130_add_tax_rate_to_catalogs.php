<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA — le taux propre à un article.
 *
 * Tous les articles ne subissent pas le même sort : le riz et les médicaments
 * sont souvent exonérés là où l'alcool est plein tarif. Un taux unique par
 * commerce obligerait à facturer une taxe sur ce qui n'en doit pas.
 *
 * NULL = « suit le commerce », et c'est le cas de l'immense majorité.
 * 0 = exonéré, une décision explicite du marchand. Les distinguer compte :
 * sans cela, régler le commerce à 10 % taxerait d'un coup tout ce que le
 * marchand avait volontairement sorti de l'assiette.
 */
return new class extends Migration
{
    private const TABLES = ['tagtoa_pos_products', 'tagtoa_menu_items'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'tax_rate')) {
                    $t->decimal('tax_rate', 6, 3)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (Schema::hasColumn($table, 'tax_rate')) {
                    $t->dropColumn('tax_rate');
                }
            });
        }
    }
};
