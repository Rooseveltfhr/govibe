<?php

namespace Database\Seeders;

use App\Models\Post;
use Illuminate\Database\Seeder;

/**
 * Article de lancement — annonce la plateforme elle-même (fait vérifiable,
 * contrairement à une actualité locale qui serait inventée).
 */
class PostSeeder extends Seeder
{
    public function run(): void
    {
        Post::firstOrCreate(
            ['slug' => 'bienvenue-sur-molesaintnicolas-com'],
            [
                'title' => 'Bienvenue sur molesaintnicolas.com',
                'excerpt' => 'La nouvelle plateforme dédiée à Môle-Saint-Nicolas est en ligne, et se construit module par module.',
                'body' => <<<'HTML'
                    <p>
                        molesaintnicolas.com rassemble désormais en un seul endroit l'histoire, le
                        territoire et l'offre touristique de Môle-Saint-Nicolas. La plateforme est
                        volontairement construite module par module : chaque section s'enrichit
                        progressivement, avec un contenu vérifié plutôt que deviné.
                    </p>
                    <p>
                        Un hôtel, un restaurant ou un lieu que vous connaissez n'apparaît pas encore ?
                        L'équipe éditoriale locale complète le contenu en continu — revenez bientôt.
                    </p>
                    HTML,
                'published_at' => now(),
                'content_status' => 'needs_review',
                'source_note' => 'Article de lancement rédigé par l\'équipe technique.',
            ]
        );
    }
}
