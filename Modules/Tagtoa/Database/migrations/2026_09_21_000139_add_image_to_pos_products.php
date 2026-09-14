<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA POS — la photo de l'article.
 *
 * Le bouton de caisse n'avait qu'un emoji et une couleur. Un emoji suffit pour
 * « 🍔 burger » ; il ne distingue pas trois plats de riz, deux marques d'eau ou
 * quatre tailles de la même bière — et c'est précisément là que le caissier se
 * trompe de bouton, en pleine affluence.
 *
 * L'emoji RESTE : il ne coûte rien, il ne se charge pas, et il reste la
 * meilleure option pour un marchand qui n'a pas de photos. La photo se
 * superpose quand elle existe.
 *
 * Nullable et sans contrainte : une image manquante, un disque plein ou un
 * fichier effacé ne doivent jamais empêcher de vendre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_pos_products', function (Blueprint $t) {
            if (! Schema::hasColumn('tagtoa_pos_products', 'image_path')) {
                $t->string('image_path')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_pos_products', function (Blueprint $t) {
            if (Schema::hasColumn('tagtoa_pos_products', 'image_path')) {
                $t->dropColumn('image_path');
            }
        });
    }
};
