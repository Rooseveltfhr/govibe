<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Le sous-titre de FEMINOI mentionne désormais l'innovation.
 * Le champ reste modifiable depuis l'ERP.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('evenements')
            ->where('slug', 'feminoi')
            ->update([
                'sous_titre' => "Forum sur l'employabilité, l'innovation et les opportunités des infirmières",
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('evenements')
            ->where('slug', 'feminoi')
            ->update([
                'sous_titre' => "Forum sur l'employabilité et les opportunités des infirmières",
                'updated_at' => now(),
            ]);
    }
};
