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
