<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA — chez qui cet article est acheté.
 *
 * Le fournisseur habituel de l'article, pré-rempli au moment de saisir une
 * réception. Nullable et sans contrainte : un fournisseur désactivé ou effacé
 * ne doit jamais empêcher de vendre.
 */
return new class extends Migration
{
    private const TABLES = ['tagtoa_pos_products', 'tagtoa_menu_items'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'supplier_id')) {
                    $t->unsignedBigInteger('supplier_id')->nullable()->index();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (Schema::hasColumn($table, 'supplier_id')) {
                    $t->dropColumn('supplier_id');
                }
            });
        }
    }
};
