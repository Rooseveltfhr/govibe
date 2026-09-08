<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Territoire\Arrondissement;
use App\Models\Territoire\Commune;
use App\Models\Territoire\Department;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CentreVilleTest extends TestCase
{
    use RefreshDatabase;

    public function test_centre_ville_page_renders_content(): void
    {
        Page::create(['title' => 'Centre-ville', 'slug' => 'centre-ville', 'body' => '<p>Contenu de test.</p>']);

        $this->get(route('centre-ville.index'))
            ->assertOk()
            ->assertSee('Centre-ville')
            ->assertSee('Contenu de test.', escape: false);
    }

    public function test_centre_ville_page_shows_map_when_commune_has_coordinates(): void
    {
        Page::create(['title' => 'Centre-ville', 'slug' => 'centre-ville', 'body' => '<p>x</p>']);
        $department = Department::create(['name' => 'Nord-Ouest', 'slug' => 'nord-ouest']);
        $arrondissement = Arrondissement::create(['department_id' => $department->id, 'name' => 'Môle-Saint-Nicolas']);
        Commune::create([
            'arrondissement_id' => $arrondissement->id,
            'name' => 'Môle-Saint-Nicolas',
            'slug' => 'mole-saint-nicolas',
            'lat' => 19.8047,
            'lng' => -73.3778,
        ]);

        $this->get(route('centre-ville.index'))
            ->assertOk()
            ->assertSee('leaflet', escape: false);
    }

    public function test_missing_centre_ville_page_returns_404(): void
    {
        $this->get(route('centre-ville.index'))->assertNotFound();
    }
}
