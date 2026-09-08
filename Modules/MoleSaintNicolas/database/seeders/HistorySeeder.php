<?php

namespace Database\Seeders;

use App\Models\Histoire\HistoricalEvent;
use App\Models\Histoire\HistoricalFigure;
use App\Models\Histoire\HistoricalPeriod;
use Illuminate\Database\Seeder;

/**
 * Chronologie de Môle-Saint-Nicolas, sourcée (Wikipedia + sources touristiques
 * consultées 09/2026) mais volontairement PRUDENTE : la baie a bien été visitée
 * et nommée par Christophe Colomb en 1492/1494 (fait largement corroboré),
 * mais l'affirmation selon laquelle le fort "La Navidad" aurait été construit
 * ICI (plutôt que près de l'actuel Limonade/En Bas Saline, localisation
 * généralement retenue par l'historiographie) n'est PAS reprise ici — elle
 * apparaît sur des sites touristiques mais pas dans les sources académiques
 * consultées. À valider avec un historien avant de la publier comme "vérifiée".
 * Tout est seedé en needs_review (brief §27) : ce sont des faits sourcés,
 * pas du contenu inventé, mais aucun humain de l'équipe ne les a encore
 * confirmés sur place.
 */
class HistorySeeder extends Seeder
{
    public function run(): void
    {
        $columbusSource = 'Wikipedia "Môle-Saint-Nicolas" + sources touristiques (consulté 09/2026). '
            .'Le passage de Colomb en 1492 et son retour en 1494 sont largement corroborés ; '
            .'la construction du fort "La Navidad" à cet endroit précis est une affirmation '
            .'discutée (l\'historiographie situe généralement La Navidad près de l\'actuel '
            .'Limonade) — ne pas publier comme "vérifié" sans confirmation d\'un historien.';

        $frenchSource = 'Wikipedia "Môle-Saint-Nicolas Arrondissement" (consulté 09/2026) : '
            .'cession de 1697 (Traité de Ryswick), fondation du village en 1764, colons '
            .'acadiens (1764) puis allemands (1766), fortifications donnant le surnom '
            .'"Gibraltar des Caraïbes". À vérifier avant publication comme "vérifié".';

        $arrival = HistoricalPeriod::firstOrCreate(
            ['slug' => 'arrivee-christophe-colomb'],
            [
                'name' => 'Arrivée de Christophe Colomb',
                'start_year' => 1492,
                'end_year' => 1494,
                'display_order' => 1,
                'description' => "Lors de son premier voyage, Christophe Colomb ancre dans la baie en décembre 1492 et la nomme en l'honneur de Saint Nicolas. Il y revient en avril 1494, lors de son second voyage.",
                'content_status' => 'needs_review',
                'source_note' => $columbusSource,
            ]
        );

        $corsairs = HistoricalPeriod::firstOrCreate(
            ['slug' => 'repaire-espagnol-flibustier'],
            [
                'name' => 'Repaire espagnol et flibustier',
                'start_year' => 1500,
                'end_year' => 1697,
                'display_order' => 2,
                'description' => "Pendant plus d'un siècle, la présence espagnole reste limitée ; la baie sert d'abri à des navires, corsaires et pirates profitant de sa protection naturelle.",
                'content_status' => 'needs_review',
                'source_note' => $columbusSource,
            ]
        );

        $french = HistoricalPeriod::firstOrCreate(
            ['slug' => 'mole-saint-nicolas-francais'],
            [
                'name' => 'Môle-Saint-Nicolas français — le "Gibraltar des Caraïbes"',
                'start_year' => 1697,
                'end_year' => 1804,
                'display_order' => 3,
                'description' => "La France obtient la partie occidentale de l'île en 1697. Après la guerre de Sept Ans, elle fortifie la baie : fondation du village en 1764, colons acadiens puis allemands, forts et batteries qui vaudront à Môle-Saint-Nicolas le surnom de \"Gibraltar des Caraïbes\".",
                'content_status' => 'needs_review',
                'source_note' => $frenchSource,
            ]
        );

        HistoricalPeriod::firstOrCreate(
            ['slug' => 'depuis-lindependance'],
            [
                'name' => "Depuis l'indépendance",
                'display_order' => 4,
                'description' => null,
                'content_status' => 'needs_review',
                'source_note' => 'Aucune recherche historique complémentaire menée pour cette période — à compléter par l\'équipe éditoriale.',
            ]
        );

        HistoricalEvent::firstOrCreate(
            ['slug' => 'colomb-ancre-baie-1492'],
            [
                'historical_period_id' => $arrival->id,
                'title' => 'Christophe Colomb ancre dans la baie et la nomme',
                'happened_on' => '1492-12-06',
                'description' => 'Lors de son premier voyage, Colomb mouille dans la baie et lui donne le nom de Saint Nicolas.',
                'content_status' => 'needs_review',
                'source_note' => $columbusSource,
            ]
        );

        HistoricalEvent::firstOrCreate(
            ['slug' => 'retour-colomb-1494'],
            [
                'historical_period_id' => $arrival->id,
                'title' => 'Retour de Christophe Colomb',
                'happened_on' => '1494-04-29',
                'description' => 'Colomb revient dans la baie lors de son second voyage, quelques jours avant la découverte de la Jamaïque.',
                'content_status' => 'needs_review',
                'source_note' => $columbusSource,
            ]
        );

        HistoricalEvent::firstOrCreate(
            ['slug' => 'traite-de-ryswick'],
            [
                'historical_period_id' => $french->id,
                'title' => "Cession de la partie occidentale de l'île à la France (Traité de Ryswick)",
                'circa_year' => 1697,
                'description' => "L'Espagne cède la partie occidentale d'Hispaniola à la France.",
                'content_status' => 'needs_review',
                'source_note' => $frenchSource,
            ]
        );

        HistoricalEvent::firstOrCreate(
            ['slug' => 'fondation-village-1764'],
            [
                'historical_period_id' => $french->id,
                'title' => 'Fondation du village du Môle Saint-Nicolas par la France',
                'circa_year' => 1764,
                'description' => 'La France fonde un village au plan en damier, qui deviendra le centre-ville actuel.',
                'content_status' => 'needs_review',
                'source_note' => $frenchSource,
            ]
        );

        HistoricalEvent::firstOrCreate(
            ['slug' => 'arrivee-colons-acadiens-1764'],
            [
                'historical_period_id' => $french->id,
                'title' => 'Arrivée des colons acadiens',
                'circa_year' => 1764,
                'description' => "Des Acadiens fuyant la proscription anglaise s'installent comme premiers habitants permanents du village.",
                'content_status' => 'needs_review',
                'source_note' => $frenchSource,
            ]
        );

        HistoricalEvent::firstOrCreate(
            ['slug' => 'arrivee-colons-allemands-1766'],
            [
                'historical_period_id' => $french->id,
                'title' => 'Arrivée des colons allemands',
                'circa_year' => 1766,
                'description' => 'Des colons allemands rejoignent les Acadiens déjà installés.',
                'content_status' => 'needs_review',
                'source_note' => $frenchSource,
            ]
        );

        HistoricalFigure::firstOrCreate(
            ['slug' => 'christophe-colomb'],
            [
                'historical_period_id' => $arrival->id,
                'name' => 'Christophe Colomb',
                'bio' => "Navigateur génois au service de la couronne espagnole. Lors de son premier voyage transatlantique (1492), sa flotte mouille dans la baie de Môle-Saint-Nicolas, qu'il nomme en l'honneur de Saint Nicolas ; il y revient en 1494.",
                'content_status' => 'needs_review',
                'source_note' => $columbusSource,
            ]
        );

        HistoricalEvent::firstOrCreate(
            ['slug' => 'depuis-lindependance-a-completer'],
            [
                'historical_period_id' => HistoricalPeriod::where('slug', 'depuis-lindependance')->first()?->id,
                'title' => '[Information à compléter]',
                'description' => 'Événements de la période post-indépendance à documenter (XIXe–XXe siècle : rôle stratégique de la baie, tentatives de bail naval, développement du centre-ville, etc.).',
                'content_status' => 'needs_review',
                'source_note' => 'Placeholder — aucune recherche menée, à remplacer ou supprimer par l\'équipe éditoriale.',
            ]
        );
    }
}
