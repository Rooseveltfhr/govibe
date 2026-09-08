<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * La formation de l'affiche, créée par migration pour que l'URL de la
     * publicité fonctionne dès la mise en ligne. Tout est ensuite modifiable
     * depuis l'ERP, et les prochaines sessions s'y créent sans migration.
     */
    public function up(): void
    {
        $maintenant = now();

        DB::table('sessions_formation')->updateOrInsert(
            ['slug' => 'formation-ai'],
            [
                'titre' => 'Grande Formation AI',
                'sous_titre' => 'Ultra-pratique',
                'description' => "Cinq modules pour produire avec l'intelligence artificielle : "
                    .'écrire de bons prompts, créer un logo, faire un shooting photo, restaurer une image '
                    .'et générer des vidéos. Rien de théorique — vous repartez avec vos propres réalisations.',
                'modules' => json_encode([
                    'Prompt Engineering',
                    'Création Logo Pro avec AI',
                    'Shooting Photo avec AI',
                    'Restauration Image',
                    'Générer des Vidéos Virales avec AI',
                ], JSON_UNESCAPED_UNICODE),

                'prix' => 2500,
                'devise' => 'HTG',

                'modes' => json_encode(['presentiel', 'online'], JSON_UNESCAPED_UNICODE),
                'lieu_presentiel' => 'Local GOVIBE — #16, Ruelle Sajous',
                'precision_online' => 'Depuis votre téléphone ou votre ordinateur',

                'date_texte' => '19 septembre',
                'date_debut' => '2026-09-19',
                'heure_texte' => null,

                'places_limitees' => true,
                'max_participants' => null,

                'whatsapp_contact' => '50933151550',
                'couleur' => '#DC2626',
                'inscriptions_ouvertes' => true,
                'actif' => true,
                'ordre' => 1,
                'created_at' => $maintenant,
                'updated_at' => $maintenant,
            ]
        );
    }

    public function down(): void
    {
        DB::table('sessions_formation')->where('slug', 'formation-ai')->delete();
    }
};
