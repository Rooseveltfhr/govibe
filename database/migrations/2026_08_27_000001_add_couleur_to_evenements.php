<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chaque événement porte sa propre couleur d'accent. Le rouge de marque reste
 * la valeur par défaut ; un événement peut s'en écarter sans toucher au code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evenements', function (Blueprint $table) {
            $table->string('couleur', 7)->default('#DC2626')->after('whatsapp_group_url');
        });

        // FEMINOI : vert, et l'événement se tient aux Gonaïves.
        DB::table('evenements')
            ->where('slug', 'feminoi')
            ->update([
                'couleur'    => '#16A34A',
                'lieu'       => 'Gonaïves, Haïti',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('evenements', function (Blueprint $table) {
            $table->dropColumn('couleur');
        });
    }
};
