<?php

namespace Database\Seeders;

use App\Models\Histoire\HistoricalSite;
use Illuminate\Database\Seeder;

/**
 * Un seul lieu, largement documenté (cf. note EstablishmentSeeder qui le
 * cite déjà comme repère), est seedé à titre de démonstration — sans
 * coordonnées GPS précises inventées : à confirmer par l'équipe sur place.
 */
class HistoricalSiteSeeder extends Seeder
{
    public function run(): void
    {
        HistoricalSite::firstOrCreate(
            ['slug' => 'fort-valliere'],
            [
                'name' => 'Fort Vallière',
                'category' => 'Fort',
                'description' => 'Fort historique de Môle-Saint-Nicolas, à proximité du littoral. [Information à compléter — historique détaillé, période de construction, état actuel]',
                'content_status' => 'needs_review',
                'source_note' => 'Cité comme repère par plusieurs sources touristiques locales (dont TripAdvisor). Coordonnées GPS et détails historiques non confirmés — à vérifier sur place avant publication comme "vérifié".',
            ]
        );
    }
}
