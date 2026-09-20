<?php

namespace Tests\Feature;

use App\Models\Photo;
use Database\Seeders\PhotoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_six_real_photos_when_gallery_is_empty(): void
    {
        Storage::fake('public');

        $this->seed(PhotoSeeder::class);

        $this->assertSame(6, Photo::count());
        Photo::all()->each(function (Photo $photo) {
            Storage::disk('public')->assertExists($photo->path);
            $this->assertSame('submitted', $photo->content_status);
        });
    }

    public function test_seeder_does_nothing_when_a_real_photo_already_exists(): void
    {
        Storage::fake('public');
        Photo::create(['path' => 'galerie/deja-la.jpg', 'title' => 'Vraie photo du client']);

        $this->seed(PhotoSeeder::class);

        $this->assertSame(1, Photo::count());
        $this->assertSame('Vraie photo du client', Photo::first()->title);
    }

    public function test_seeder_replaces_leftover_demo_illustrations_with_the_real_photos(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('galerie/demo-1.jpg', 'contenu-bidon');
        Photo::create(['path' => 'galerie/demo-1.jpg', 'title' => 'Illustration', 'category' => 'demo']);

        $this->seed(PhotoSeeder::class);

        $this->assertSame(6, Photo::count());
        $this->assertDatabaseMissing('photos', ['category' => 'demo']);
        Storage::disk('public')->assertMissing('galerie/demo-1.jpg');
    }
}
