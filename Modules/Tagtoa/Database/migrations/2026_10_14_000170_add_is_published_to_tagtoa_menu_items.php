<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA MENU — « publié » vs « brouillon », distinct de `is_available`.
 *
 * `is_available` dit si un produit déjà en ligne se vend maintenant (une
 * chambre réservée, un plat épuisé) ; il reste visible sur la vitrine, grisé.
 * `is_published` dit si le produit est prêt à être montré AU CLIENT — un
 * marchand qui commence sa fiche (photo pas encore ajoutée, prix pas encore
 * sûr) doit pouvoir l'enregistrer sans qu'elle apparaisse déjà en public.
 *
 * Par défaut TRUE : tout produit déjà en base a déjà traversé cette étape
 * sans elle — le considérer publié ne change rien à ce que voit un client
 * aujourd'hui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_menu_items', function (Blueprint $table) {
            $table->boolean('is_published')->default(true)->after('is_available');
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_menu_items', function (Blueprint $table) {
            $table->dropColumn('is_published');
        });
    }
};
