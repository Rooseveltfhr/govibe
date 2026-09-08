<?php

namespace Tests\Feature;

use App\Models\Photo;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Storage::fake('public');
    }

    public function test_galerie_page_shows_placeholder_when_empty(): void
    {
        $this->get(route('galerie.index'))
            ->assertOk()
            ->assertSee('aucune photo ajoutée pour');
    }

    public function test_galerie_page_lists_uploaded_photos(): void
    {
        Photo::create(['title' => 'Coucher de soleil', 'path' => 'galerie/test.jpg', 'category' => 'plage']);

        $this->get(route('galerie.index'))
            ->assertOk()
            ->assertSee('Coucher de soleil');
    }

    public function test_guest_cannot_manage_the_gallery(): void
    {
        $this->get(route('admin.galerie.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_upload_a_photo(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $file = UploadedFile::fake()->image('plage.jpg', 800, 600);

        $this->actingAs($admin)->post(route('admin.galerie.store'), [
            'image' => $file,
            'title' => 'Plage de sable blanc',
            'content_status' => 'needs_review',
        ])->assertRedirect(route('admin.galerie.index'));

        $photo = Photo::where('title', 'Plage de sable blanc')->firstOrFail();
        Storage::disk('public')->assertExists($photo->path);
    }

    public function test_upload_rejects_non_image_files(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $this->actingAs($admin)->post(route('admin.galerie.store'), [
            'image' => $file,
            'content_status' => 'needs_review',
        ])->assertSessionHasErrors('image');
    }

    public function test_admin_can_delete_a_photo_and_its_file(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Storage::disk('public')->put('galerie/test.jpg', 'fake-content');
        $photo = Photo::create(['path' => 'galerie/test.jpg']);

        $this->actingAs($admin)->delete(route('admin.galerie.destroy', $photo))
            ->assertRedirect(route('admin.galerie.index'));

        $this->assertDatabaseMissing('photos', ['id' => $photo->id]);
        Storage::disk('public')->assertMissing('galerie/test.jpg');
    }
}
