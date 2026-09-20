<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_shows_a_placeholder_when_no_video_is_configured(): void
    {
        config(['services.home_video.id' => null]);

        $this->get('/')
            ->assertOk()
            ->assertSee('[Vidéo à ajouter — lien YouTube à fournir]');
    }

    public function test_home_page_embeds_the_configured_youtube_video(): void
    {
        config(['services.home_video.id' => 'dQw4w9WgXcQ']);

        $this->get('/')
            ->assertOk()
            ->assertSee('https://www.youtube.com/embed/dQw4w9WgXcQ', false);
    }
}
