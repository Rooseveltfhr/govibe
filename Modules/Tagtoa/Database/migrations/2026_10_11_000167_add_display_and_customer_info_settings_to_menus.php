<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deux réglages d'affichage/commande, vus sur la maquette assistant :
 *
 * - `show_images` : masque les photos d'articles sur le menu public (texte
 *   seul) — défaut `true`, puisque les photos s'affichaient déjà sans ce
 *   réglage ; l'ajouter ne doit rien changer pour les menus déjà en service.
 * - `require_customer_info` : exige le nom et le téléphone du client avant
 *   de pouvoir commander — défaut `false`, car c'est l'INVERSE : ces champs
 *   sont optionnels depuis toujours (MenuOrderService), les rendre
 *   obligatoires par défaut romprait la commande de tous les menus déjà en
 *   service tant que leur propriétaire n'a pas vu ce nouveau réglage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_menus', function (Blueprint $table) {
            $table->boolean('show_images')->default(true)->after('table_ordering_enabled');
            $table->boolean('require_customer_info')->default(false)->after('show_images');
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_menus', function (Blueprint $table) {
            $table->dropColumn(['show_images', 'require_customer_info']);
        });
    }
};
