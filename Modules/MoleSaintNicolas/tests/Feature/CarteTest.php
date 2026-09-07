<?php

namespace Tests\Feature;

use App\Models\Etablissements\Establishment;
use App\Models\Histoire\HistoricalSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarteTest extends TestCase
{
    use RefreshDatabase;

    public function test_carte_page_renders_with_no_coordinates(): void
    {
        $this->get(route('carte.index'))
            ->assertOk()
            ->assertSee('aucune coordonnée confirmée');
    }

    public function test_carte_page_includes_markers_for_geolocated_entities(): void
    {
        HistoricalSite::create(['name' => 'Fort Vallière', 'lat' => 19.8, 'lng' => -73.37]);
        Establishment::create(['type' => 'hotel', 'name' => 'Boukan Guinguette', 'lat' => 19.81, 'lng' => -73.38]);

        // @json() échappe l'unicode ("è" -> è) dans le <script> : on
        // vérifie le marker via son slug (ASCII, présent dans le href) plutôt
        // que le libellé accentué, qui n'apparaît jamais tel quel dans le HTML.
        $this->get(route('carte.index'))
            ->assertOk()
            ->assertSee('fort-valliere', escape: false)
            ->assertSee('boukan-guinguette', escape: false);
    }
}
