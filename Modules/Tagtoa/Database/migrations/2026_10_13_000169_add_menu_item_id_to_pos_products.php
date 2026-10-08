<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA POS — quel article de Menu un produit POS a été synchronisé depuis
 * (voir MenuProductSync). Pas de clé étrangère, même choix que category_id/
 * parent_product_id/supplier_id sur cette même table : supprimer un article
 * de Menu ne doit jamais bloquer ni casser le produit POS déjà synchronisé —
 * il continue de vivre, simplement détaché (voir le commentaire dans
 * MenuProductSync).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_pos_products', function (Blueprint $table) {
            $table->unsignedBigInteger('menu_item_id')->nullable()->after('tenant_id');
            $table->index(['tenant_id', 'menu_item_id']);
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_pos_products', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'menu_item_id']);
            $table->dropColumn('menu_item_id');
        });
    }
};
