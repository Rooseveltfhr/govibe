<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le livreur assigné à une commande LIVRAISON, et les deux horodatages de son
 * trajet. `courier_id` pointe sur `tagtoa_staff` (le même annuaire que la
 * caisse/cuisine — un livreur est un employé de plus, pas un concept séparé) ;
 * `nullOnDelete` plutôt que `cascadeOnDelete` : retirer un livreur de l'équipe
 * ne doit jamais effacer l'historique de ses livraisons passées.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_menu_orders', function (Blueprint $table) {
            $table->foreignId('courier_id')->nullable()->after('delivery_zone_label')
                ->constrained('tagtoa_staff')->nullOnDelete();
            $table->timestamp('courier_assigned_at')->nullable()->after('courier_id');
            $table->timestamp('picked_up_at')->nullable()->after('courier_assigned_at');
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_menu_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('courier_id');
            $table->dropColumn(['courier_assigned_at', 'picked_up_at']);
        });
    }
};
