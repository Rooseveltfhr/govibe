<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Pages statiques de départ (à propos, mentions légales). Contenu rédigé
 * sans inventer de faits organisationnels/juridiques non confirmés — les
 * champs identitaires précis (raison sociale, adresse légale...) restent
 * `[Information à compléter]` jusqu'à validation par le client (brief §27).
 */
class PageSeeder extends Seeder
{
    public function run(): void
    {
        Page::firstOrCreate(
            ['slug' => 'a-propos'],
            [
                'title' => 'À propos de la plateforme',
                'meta_description' => "Ce qu'est molesaintnicolas.com, sa mission et comment son contenu est vérifié.",
                'body' => <<<'HTML'
                    <p>
                        <strong>molesaintnicolas.com</strong> rassemble en un seul endroit l'histoire,
                        le territoire, le patrimoine et l'offre touristique de Môle-Saint-Nicolas,
                        dans le Nord-Ouest d'Haïti — un lieu souvent présenté comme le point où
                        Christophe Colomb aurait accosté en 1492.
                    </p>

                    <h2>Notre mission</h2>
                    <p>
                        Donner une vitrine claire et fiable à la commune : pour les visiteurs qui
                        préparent un séjour, pour la diaspora qui garde un lien avec le Môle, et
                        pour les hôtels, restaurants et bars locaux qui veulent être trouvés.
                    </p>

                    <h2>Un contenu vérifié progressivement</h2>
                    <p>
                        La plateforme est construite module par module. Chaque page affiche un badge
                        de statut :
                    </p>
                    <ul>
                        <li><strong>Vérifié</strong> — confirmé par l'équipe éditoriale locale.</li>
                        <li><strong>Soumis</strong> — en attente de vérification.</li>
                        <li><strong>À vérifier</strong> — contenu initial à confirmer sur place.</li>
                    </ul>
                    <p>
                        Une information manquante est toujours affichée comme
                        « [Information à compléter] » plutôt que devinée ou inventée.
                    </p>

                    <h2>Qui gère cette plateforme</h2>
                    <p>[Information à compléter — organisation/porteur de projet à confirmer]</p>
                    HTML,
                'content_status' => 'needs_review',
                'source_note' => 'Contenu de lancement rédigé par l\'équipe technique — à valider par le client avant de marquer "vérifié".',
            ]
        );

        Page::firstOrCreate(
            ['slug' => 'mentions-legales'],
            [
                'title' => 'Mentions légales',
                'meta_description' => 'Éditeur, hébergement, propriété intellectuelle et données personnelles de molesaintnicolas.com.',
                'body' => <<<'HTML'
                    <h2>Éditeur du site</h2>
                    <p>[Information à compléter — raison sociale / nom de l'organisation éditrice, adresse]</p>

                    <h2>Hébergement</h2>
                    <p>Le site est hébergé par un prestataire tiers. [Information à compléter — coordonnées complètes de l'hébergeur]</p>

                    <h2>Propriété intellectuelle</h2>
                    <p>
                        Les textes, photos et données présentés sur ce site sont la propriété de leurs
                        auteurs respectifs ou sont utilisés avec l'autorisation nécessaire. Toute
                        reproduction sans accord préalable est interdite.
                    </p>

                    <h2>Données personnelles</h2>
                    <p>
                        Les informations transmises via les formulaires de réservation (nom, contact,
                        dates de séjour) sont utilisées uniquement pour traiter la demande auprès de
                        l'établissement concerné et ne sont pas revendues à des tiers.
                    </p>

                    <h2>Contact</h2>
                    <p>[Information à compléter — email/téléphone de contact officiel]</p>
                    HTML,
                'content_status' => 'needs_review',
                'source_note' => 'Modèle standard de mentions légales — les champs identitaires doivent être complétés et validés par le client.',
            ]
        );
    }
}
