<?php

namespace Database\Seeders;

use App\Models\CommunityProject;
use App\Models\ProjectComment;
use Illuminate\Database\Seeder;

/**
 * Un seul projet de démonstration, clairement identifié comme tel — pas un
 * vrai projet communautaire (inconnu, jamais inventé, voir §27 du brief).
 * Sert à montrer le fonctionnement du module (page, statut, commentaires
 * approuvé/en attente) avant que l'équipe n'ajoute de vrais projets, et à
 * être remplacé/supprimé depuis l'admin dès qu'un vrai projet existe.
 */
class CommunityProjectSeeder extends Seeder
{
    public function run(): void
    {
        $project = CommunityProject::firstOrCreate(
            ['slug' => 'projet-de-demonstration'],
            [
                'title' => 'Projet de démonstration',
                'status' => 'en_cours',
                'description' => "Ceci est un projet de démonstration créé pour tester la page et le formulaire de commentaires. Il ne représente aucun projet réel de la commune — à supprimer ou remplacer par un vrai projet depuis l'admin (Admin → Projets).",
                'content_status' => 'needs_review',
                'source_note' => 'Créé à la demande du client pour tester le module (page, statut, modération des commentaires) — pas un fait réel, à supprimer une fois le test terminé.',
            ]
        );

        ProjectComment::firstOrCreate(
            ['community_project_id' => $project->id, 'author_name' => 'Exemple (approuvé)'],
            ['body' => "Ceci est un exemple de commentaire déjà approuvé — visible publiquement.", 'is_approved' => true]
        );

        ProjectComment::firstOrCreate(
            ['community_project_id' => $project->id, 'author_name' => 'Exemple (en attente)'],
            ['body' => "Ceci est un exemple de commentaire en attente de modération — invisible tant qu'un admin ne l'approuve pas dans Admin → Projets → Modérer.", 'is_approved' => false]
        );
    }
}
