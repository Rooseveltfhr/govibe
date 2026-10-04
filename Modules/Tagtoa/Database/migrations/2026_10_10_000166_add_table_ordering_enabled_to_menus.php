<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permet au commerce de désactiver la commande par table QR/NFC sans
 * supprimer ses tables — un QR mal imprimé ou une table qu'on retire
 * temporairement du service ne doit pas obliger à effacer la configuration.
 * Défaut `true` : la vérification de table fonctionnait déjà sans ce
 * réglage (dès qu'une Table existait) — l'ajouter ne doit RIEN désactiver
 * pour les menus déjà en service, même convention que `ordering_enabled`/
 * `show_prices`/`is_active` sur cette même table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_menus', function (Blueprint $table) {
            $table->boolean('table_ordering_enabled')->default(true)->after('service_types');
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_menus', function (Blueprint $table) {
            $table->dropColumn('table_ordering_enabled');
        });
    }
};
