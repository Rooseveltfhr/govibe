<?php

namespace Database\Seeders;

use App\Models\Territoire\Arrondissement;
use App\Models\Territoire\Commune;
use App\Models\Territoire\Department;
use App\Models\Territoire\SectionCommunale;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Structure administrative réelle de Môle-Saint-Nicolas (source : Wikipedia,
 * articles "Môle-Saint-Nicolas Arrondissement" et "Môle-Saint-Nicolas",
 * consultés en 09/2026). Tout est seedé en `needs_review` : ce sont des
 * faits sourcés, pas du contenu inventé, mais aucun humain de l'équipe ne
 * les a encore vérifiés sur place — voir docs/molesaintnicolas §7 et §27.
 */
class TerritorySeeder extends Seeder
{
    public function run(): void
    {
        $source = 'Wikipedia — "Môle-Saint-Nicolas Arrondissement" / "Môle-Saint-Nicolas" (consulté 09/2026). À vérifier sur place avant publication comme "vérifié".';

        $department = Department::firstOrCreate(['slug' => 'nord-ouest'], ['name' => 'Nord-Ouest']);

        $arrondissement = Arrondissement::firstOrCreate(
            ['slug' => 'mole-saint-nicolas'],
            [
                'department_id' => $department->id,
                'name' => 'Môle-Saint-Nicolas',
                'area_km2' => 1115,
                'population' => 245590,
                'population_year' => 2015,
            ]
        );

        $communes = [
            'mole-saint-nicolas' => 'Môle-Saint-Nicolas',
            'baie-de-henne' => 'Baie-de-Henne',
            'bombardopolis' => 'Bombardopolis',
            'jean-rabel' => 'Jean-Rabel',
        ];

        foreach ($communes as $slug => $name) {
            Commune::firstOrCreate(
                ['slug' => $slug],
                [
                    'arrondissement_id' => $arrondissement->id,
                    'name' => $name,
                    'content_status' => 'needs_review',
                    'source_note' => $source,
                ]
            );
        }

        $moleCommune = Commune::where('slug', 'mole-saint-nicolas')->firstOrFail();

        // Coordonnées du bourg (fait géographique public, pas une donnée
        // commerciale) — seedées une seule fois, jamais réécrites après (un
        // ajustement fait depuis l'admin ne doit pas être écrasé au prochain
        // déploiement, voir remote-deploy.sh qui relance ce seeder à chaque fois).
        if ($moleCommune->lat === null) {
            $moleCommune->update([
                'lat' => 19.8047,
                'lng' => -73.3778,
                'source_note' => $moleCommune->source_note.' Coordonnées approximatives du bourg — à affiner.',
            ]);
        }

        // Présentation, limites administratives, histoire et population : texte
        // fourni directement par le client (Roosevelt) en conversation — traité
        // comme "vérifié" (source directe du porteur du projet), pas comme le
        // contenu Wikipedia générique ci-dessus. Coupe-file idempotent classique
        // (jamais réécrit si déjà rempli, pour ne pas écraser un futur ajustement
        // fait depuis l'admin).
        if ($moleCommune->description === null) {
            $moleCommune->update([
                'description' => <<<'TEXT'
                    La commune du Môle-Saint-Nicolas pourrait même être considérée comme la première ville d'Haïti. Elle est actuellement composée du centre-ville du Môle et de ses trois sections communales : Côtes-de-Fer (1), Mare-Rouge (2) et Damé (3). Elle est bornée au nord par l'océan Atlantique, à l'ouest par la mer des Caraïbes, à l'est par la commune de Bombardopolis, au sud-est par la commune de Baie-de-Henne, et au sud et au sud-ouest par la commune de Jean-Rabel. Avant l'arrivée des Européens, Môle-Saint-Nicolas était habitée par des populations amérindiennes, principalement des Tainos, qui occupaient une grande partie de l'île d'Ayiti, appelée plus tard Hispaniola par les Européens.

                    Cette région d'Haïti est là où Christophe Colomb a mis pied pour la première fois, lors de son premier voyage, le 6 décembre 1492. Cette période a marqué un tournant dans l'histoire de la commune du Môle-Saint-Nicolas et de l'île d'Haïti, avec notamment la colonisation du pays par les Européens, l'extermination de la population autochtone et l'arrivée des esclaves noirs en Haïti.

                    Vers 1764, les Français fondent officiellement la ville du Môle-Saint-Nicolas.

                    Après l'indépendance d'Haïti en 1804, Môle-Saint-Nicolas devient une partie du nouvel État haïtien, et en 1821, elle devient officiellement une commune.

                    Actuellement, la population de la commune du Môle-Saint-Nicolas est estimée à plus de 33 000 habitants, puisqu'elle était de 33 863 habitants lors du dernier recensement officiel de référence de l'Institut Haïtien de Statistique et d'Informatique (IHSI), en 2015.
                    TEXT,
                'population' => 33863,
                'population_year' => 2015,
                // "submitted" (pas "verified") : le contenu vient directement du
                // client, mais l'attribution verified_by/verified_at attend son
                // clic explicite depuis /admin/territoire/communes (cf. HasContentStatus).
                'content_status' => 'submitted',
                'source_note' => 'Contenu fourni directement par le client (Roosevelt), porteur du projet — population sourcée IHSI 2015.',
            ]);
        }

        foreach (['Côtes de Fer', 'Mare-Rouge', 'Damé'] as $name) {
            SectionCommunale::firstOrCreate(
                ['commune_id' => $moleCommune->id, 'slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'content_status' => 'needs_review',
                    'source_note' => $source,
                ]
            );
        }
    }
}
