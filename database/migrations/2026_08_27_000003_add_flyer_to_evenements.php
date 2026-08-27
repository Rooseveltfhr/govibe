<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Visuel de l'événement, affiché dans l'en-tête à côté du titre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evenements', function (Blueprint $table) {
            $table->string('flyer')->nullable()->after('couleur');
        });

        // Le flyer de FEMINOI est livré avec le dépôt ; la date vient du visuel.
        DB::table('evenements')
            ->where('slug', 'feminoi')
            ->update([
                'flyer'      => 'images/evenements/feminoi-2026.jpg',
                'date_debut' => '2026-10-16',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('evenements', function (Blueprint $table) {
            $table->dropColumn('flyer');
        });
    }
};
