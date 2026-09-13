<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Roosevelt : le contact de la Grande Formation AI passe au numéro de
     * vérification, celui où arrivent déjà les inscriptions.
     *
     * Deux numéros différents sur la même formation obligeaient l'équipe à
     * suivre deux conversations pour une seule personne.
     */
    public function up(): void
    {
        if (! Schema::hasTable('sessions_formation')) {
            return;
        }

        DB::table('sessions_formation')
            ->where('slug', 'formation-ai')
            ->update([
                'whatsapp_contact' => '50933988754',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('sessions_formation')) {
            return;
        }

        DB::table('sessions_formation')
            ->where('slug', 'formation-ai')
            ->update([
                'whatsapp_contact' => '50933151550',
                'updated_at' => now(),
            ]);
    }
};
