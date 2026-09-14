<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA POS — la ligne qui donne envie.
 *
 * Le menu digital a une description par article ; le catalogue de la caisse
 * n'en avait pas. Or les deux alimentent désormais la MÊME vitrine — la carte
 * produit que voit le client attablé comme le caissier au comptoir — et une
 * carte sans un mot de description n'est qu'un prix posé sous une photo.
 *
 * Courte à dessein : deux lignes sur la carte, pas un paragraphe que personne
 * ne lit et qui pousse le prix hors de l'écran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_pos_products', function (Blueprint $t) {
            if (! Schema::hasColumn('tagtoa_pos_products', 'description')) {
                $t->string('description', 160)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_pos_products', function (Blueprint $t) {
            if (Schema::hasColumn('tagtoa_pos_products', 'description')) {
                $t->dropColumn('description');
            }
        });
    }
};
