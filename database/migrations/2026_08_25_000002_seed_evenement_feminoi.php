<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Crée le premier événement pour que son URL soit utilisable dès la mise en
 * ligne, sans passer par l'ERP. Les suivants se créent depuis l'ERP.
 */
return new class extends Migration
{
    public function up(): void
    {
        // updateOrInsert plutôt qu'insert : la migration reste rejouable et
        // n'écrase pas un événement déjà retouché depuis l'ERP.
        DB::table('evenements')->updateOrInsert(
            ['slug' => 'feminoi'],
            [
                'titre'      => 'FEMINOI',
                'sous_titre' => "Forum sur l'employabilité et les opportunités des infirmières",
                'description' => "FEMINOI réunit les infirmières et les professionnelles de la santé autour "
                    . "des débouchés du secteur : employabilité, évolution de carrière, formation continue, "
                    . "entrepreneuriat en santé et opportunités à l'international. Une journée d'échanges "
                    . "avec des intervenants du milieu, des institutions et des recruteurs.",
                'lieu'       => 'Port-au-Prince, Haïti',
                'whatsapp_group_url' => 'https://chat.whatsapp.com/Ca2Yh8VZcojIiy8AaLgvnJ?s=cl&p=a&ilr=4',
                'actif'      => true,
                'inscriptions_ouvertes' => true,
                'ordre'      => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('evenements')->where('slug', 'feminoi')->delete();
    }
};
