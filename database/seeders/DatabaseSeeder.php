<?php

namespace Database\Seeders;

use App\Models\Formation;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Administrateur GOVIBE',
            'email' => env('ADMIN_EMAIL', 'govibeht@gmail.com'),
            'password' => bcrypt(env('ADMIN_PASSWORD', 'admin@govibe2024')),
            'is_admin' => true,
        ]);

        $formations = [
            [
                'nom' => 'Marketing Digital & Réseaux Sociaux',
                'description' => 'Apprenez les fondamentaux du marketing digital, la gestion des réseaux sociaux et les stratégies de croissance en ligne.',
                'date_debut' => '2024-02-01',
                'date_fin' => '2024-02-28',
                'lieu' => 'Port-au-Prince',
                'whatsapp_link' => 'https://chat.whatsapp.com/govibe-marketing',
                'max_participants' => 50,
                'active' => true,
            ],
            [
                'nom' => 'Développement Web (HTML, CSS, JavaScript)',
                'description' => 'Formation complète en développement web front-end. Créez des sites modernes et responsives.',
                'date_debut' => '2024-03-01',
                'date_fin' => '2024-03-31',
                'lieu' => 'Cap-Haïtien',
                'whatsapp_link' => 'https://chat.whatsapp.com/govibe-devweb',
                'max_participants' => 30,
                'active' => true,
            ],
            [
                'nom' => 'Entrepreneuriat & Business Plan',
                'description' => 'Développez votre idée d\'entreprise et créez un business plan solide pour attirer des investisseurs.',
                'date_debut' => '2024-04-01',
                'date_fin' => '2024-04-30',
                'lieu' => 'Les Cayes',
                'whatsapp_link' => 'https://chat.whatsapp.com/govibe-entrepreneuriat',
                'max_participants' => 40,
                'active' => true,
            ],
            [
                'nom' => 'Design Graphique (Canva & Photoshop)',
                'description' => 'Maîtrisez les outils de design pour créer des visuels professionnels pour votre entreprise.',
                'date_debut' => '2024-05-01',
                'date_fin' => '2024-05-31',
                'lieu' => 'Pétion-Ville',
                'whatsapp_link' => 'https://chat.whatsapp.com/govibe-design',
                'max_participants' => 35,
                'active' => true,
            ],
            [
                'nom' => 'Gestion de Projet & Leadership',
                'description' => 'Développez vos compétences en gestion de projet et leadership pour diriger des équipes efficacement.',
                'date_debut' => '2024-06-01',
                'date_fin' => '2024-06-30',
                'lieu' => 'Port-au-Prince',
                'whatsapp_link' => 'https://chat.whatsapp.com/govibe-gestion',
                'max_participants' => 45,
                'active' => true,
            ],
        ];

        foreach ($formations as $formation) {
            Formation::create($formation);
        }
    }
}
