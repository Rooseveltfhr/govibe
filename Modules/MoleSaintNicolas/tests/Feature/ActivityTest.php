<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_index_lists_activities(): void
    {
        Activity::create(['title' => 'Randonnée au Fort', 'description' => 'Description.']);

        $this->get(route('explorer.index'))
            ->assertOk()
            ->assertSee('Randonnée au Fort');
    }

    public function test_show_page_renders_whatsapp_link_when_present(): void
    {
        $activity = Activity::create([
            'title' => 'Plongée',
            'description' => 'Description.',
            'whatsapp' => '+509 1234 5678',
        ]);

        $this->get(route('explorer.show', $activity->slug))
            ->assertOk()
            ->assertSee('https://wa.me/50912345678', escape: false);
    }

    public function test_show_page_without_contact_shows_placeholder(): void
    {
        $activity = Activity::create(['title' => 'Pêche', 'description' => 'Description.']);

        $this->get(route('explorer.show', $activity->slug))
            ->assertOk()
            ->assertSee('[Information à compléter — coordonnées de contact]');
    }

    public function test_guest_cannot_manage_activities(): void
    {
        $this->get(route('admin.explorer.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_create_an_activity(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->post(route('admin.explorer.store'), [
            'title' => 'Randonnée au Fort',
            'description' => 'Description.',
            'content_status' => 'needs_review',
        ])->assertRedirect(route('admin.explorer.index'));

        $this->assertDatabaseHas('activities', ['title' => 'Randonnée au Fort', 'slug' => 'randonnee-au-fort']);
    }
}
