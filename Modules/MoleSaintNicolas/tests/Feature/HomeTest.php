<?php

namespace Tests\Feature;

use App\Models\Territoire\Arrondissement;
use App\Models\Territoire\Commune;
use App\Models\Territoire\Department;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_shows_the_commune_presentation_between_hero_and_historical_sites(): void
    {
        $department = Department::create(['name' => 'Nord-Ouest', 'slug' => 'nord-ouest']);
        $arrondissement = Arrondissement::create(['department_id' => $department->id, 'name' => 'Môle-Saint-Nicolas']);
        Commune::create([
            'arrondissement_id' => $arrondissement->id,
            'name' => 'Môle-Saint-Nicolas',
            'description' => "Paragraphe un.\n\nParagraphe deux.",
            'population' => 33863,
            'population_year' => 2015,
            'content_status' => 'submitted',
        ]);

        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Paragraphe un.')
            ->assertSee('33 863');

        $content = $response->getContent();
        $heroPos = strpos($content, "porte historique d'Haïti");
        $presentationPos = strpos($content, 'Paragraphe un.');
        $lieuxPos = strpos($content, 'id="lieux-historiques"');

        $this->assertNotFalse($heroPos);
        $this->assertNotFalse($presentationPos);
        $this->assertNotFalse($lieuxPos);
        $this->assertTrue($heroPos < $presentationPos, 'La présentation devrait venir après le hero.');
        $this->assertTrue($presentationPos < $lieuxPos, 'La présentation devrait venir avant "Lieux historiques".');
    }

    public function test_home_page_hides_the_commune_presentation_when_no_description_is_set(): void
    {
        $department = Department::create(['name' => 'Nord-Ouest', 'slug' => 'nord-ouest']);
        $arrondissement = Arrondissement::create(['department_id' => $department->id, 'name' => 'Môle-Saint-Nicolas']);
        Commune::create(['arrondissement_id' => $arrondissement->id, 'name' => 'Môle-Saint-Nicolas']);

        $this->get('/')->assertOk()->assertDontSee('Population :');
    }

    public function test_home_page_hero_has_no_video_layer_when_none_is_configured(): void
    {
        config(['services.home_video.id' => null]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('youtube.com/embed', false);
    }

    public function test_home_page_embeds_the_configured_youtube_video_as_hero_background(): void
    {
        config(['services.home_video.id' => 'dQw4w9WgXcQ']);

        $this->get('/')
            ->assertOk()
            ->assertSee('https://www.youtube.com/embed/dQw4w9WgXcQ', false)
            ->assertSee('autoplay=1&mute=1&loop=1', false);
    }
}
