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

    public function test_seeder_creates_five_demo_photos_when_gallery_is_empty(): void
    {
        Storage::fake('public');

        $this->seed(PhotoSeeder::class);

        $this->assertSame(5, Photo::count());
        Photo::all()->each(function (Photo $photo) {
            Storage::disk('public')->assertExists($photo->path);
            $this->assertSame('needs_review', $photo->content_status);
        });
    }

    public function test_seeder_does_nothing_when_a_photo_already_exists(): void
    {
        Storage::fake('public');
        Photo::create(['path' => 'galerie/deja-la.jpg', 'title' => 'Vraie photo du client']);

        $this->seed(PhotoSeeder::class);

        $this->assertSame(1, Photo::count());
        $this->assertSame('Vraie photo du client', Photo::first()->title);
    }
}
