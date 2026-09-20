<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

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
