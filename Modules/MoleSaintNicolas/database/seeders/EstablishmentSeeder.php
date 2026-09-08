<?php

namespace Database\Seeders;

use App\Models\Etablissements\Establishment;
use Illuminate\Database\Seeder;

/**
 * Contrairement aux seeders Territoire/Histoire (faits encyclopédiques),
 * les établissements touristiques (nom, contact, prix) sont des informations
 * commerciales qui changent et qu'il serait dangereux d'inventer ou de deviner
 * — un mauvais numéro de téléphone nuit directement à un visiteur. Un seul
 * établissement, réellement documenté (TripAdvisor + site officiel
 * boukanguinguette.com, consultés 09/2026), est seedé à titre de démonstration.
 * Tout le reste doit être saisi par l'équipe via l'admin, jamais deviné.
 */
class EstablishmentSeeder extends Seeder
{
    public function run(): void
    {
        Establishment::firstOrCreate(
            ['slug' => 'boukan-guinguette'],
            [
                'type' => 'hotel',
                'name' => 'Boukan Guinguette',
                'description' => 'Bungalows avec accès direct à une plage de sable blanc, snorkeling sur les récifs à proximité, restaurant sur place. À environ 5 minutes à pied du Fort Vallière.',
                'amenities' => 'Restaurant sur place, accès direct à la plage',
                'content_status' => 'needs_review',
                'source_note' => 'TripAdvisor ("Boukan Guinguette", Mole-Saint-Nicolas) + site officiel boukanguinguette.com, consultés 09/2026. Coordonnées (téléphone, prix) non trouvées de manière fiable — à compléter/vérifier par l\'équipe avant publication comme "vérifié".',
            ]
        );
    }
}
