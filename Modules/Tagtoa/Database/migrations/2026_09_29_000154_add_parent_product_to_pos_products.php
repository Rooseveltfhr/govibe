<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA POS — un « verre » se vend à partir d'une « bouteille ».
 *
 * Un bar tient son stock en bouteilles, mais vend au verre ou au shot. Sans
 * lien entre les deux, le marchand devait créer deux articles indépendants
 * et vendre un verre ne décrémentait jamais la bouteille dont il sortait
 * réellement.
 *
 * `parent_product_id` : SANS contrainte de clé étrangère, comme
 * `category_id`/`supplier_id` déjà dans cette table — supprimer l'article
 * parent ne doit jamais bloquer la vente d'un article qui le référence ; le
 * cas est traité côté service (parent introuvable = rien à décrémenter).
 * `units_per_parent` : combien d'unités de l'article courant fait UNE unité
 * du parent (25 verres pour 1 bouteille).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_pos_products', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_product_id')->nullable()->after('category_id');
            $table->decimal('units_per_parent', 10, 3)->nullable()->after('parent_product_id');
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_pos_products', function (Blueprint $table) {
            $table->dropColumn(['parent_product_id', 'units_per_parent']);
        });
    }
};
