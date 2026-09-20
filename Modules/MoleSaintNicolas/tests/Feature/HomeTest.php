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

    public function test_home_page_shows_the_commune_presentation_between_hero_and_map(): void
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
        $cartePos = strpos($content, 'id="carte"');

        $this->assertNotFalse($heroPos);
        $this->assertNotFalse($presentationPos);
        $this->assertNotFalse($cartePos);
        $this->assertTrue($heroPos < $presentationPos, 'La présentation devrait venir après le hero.');
        $this->assertTrue($presentationPos < $cartePos, 'La présentation devrait venir avant la carte.');
    }

    public function test_home_page_only_shows_the_map_between_presentation_and_gallery(): void
    {
        // "Lieux historiques" reste un libellé du menu (nav) — on vérifie donc
        // l'absence des sections elles-mêmes par leur ancre, pas du texte
        // visible qui existe légitimement ailleurs sur la page.
        $this->get('/')
            ->assertOk()
            ->assertDontSee('id="lieux-historiques"', false)
            ->assertDontSee('id="territoire"', false)
            ->assertDontSee('id="sejour"', false)
            ->assertDontSee('id="restaurants"', false)
            ->assertDontSee('id="actualites"', false)
            ->assertDontSee('id="centre-ville"', false)
            ->assertDontSee('id="explorer"', false)
            ->assertDontSee('id="evenements"', false)
            ->assertSee('id="carte"', false);
    }

    public function test_home_page_gallery_slideshow_shows_every_uploaded_photo(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $photos = collect(['galerie/un.jpg', 'galerie/deux.jpg', 'galerie/trois.jpg'])
            ->map(fn ($path) => \App\Models\Photo::create(['path' => $path]));

        $response = $this->get('/');

        $response->assertOk()->assertSee('id="galerie"', false);
        $photos->each(fn ($photo) => $response->assertSee($photo->url, false));
    }

    public function test_home_page_hides_the_gallery_section_when_there_are_no_photos(): void
    {
        $this->get('/')->assertOk()->assertDontSee('id="galerie"', false);
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
