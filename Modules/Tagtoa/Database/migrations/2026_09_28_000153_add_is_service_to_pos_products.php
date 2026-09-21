<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA POS — un article peut être un SERVICE, sans existence physique.
 *
 * Un hôtel vend des nuitées, une clinique des consultations, un spa des
 * soins : rien de tout cela ne se compte en stock, ne se scanne, ni ne
 * s'alerte sous un seuil. Le formulaire produit posait pourtant ces trois
 * champs pour tout article, sans distinction — `sells_products`/
 * `sells_services` existait déjà sur `Business`, mais seulement comme
 * libellé affiché (getSellsLabelAttribute), jamais pour adapter l'écran.
 *
 * Une simple case à cocher par article, pas une restriction : un commerce
 * mixte (une pharmacie qui loue aussi du matériel médical) peut avoir les
 * deux à la fois.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_pos_products', function (Blueprint $table) {
            $table->boolean('is_service')->default(false)->after('unit');
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_pos_products', function (Blueprint $table) {
            $table->dropColumn('is_service');
        });
    }
};
