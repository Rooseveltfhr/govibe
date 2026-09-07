<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_index_lists_events(): void
    {
        Event::create(['title' => 'Fête patronale', 'description' => 'Description.', 'starts_at' => now()->addWeek()]);

        $this->get(route('evenements.index'))
            ->assertOk()
            ->assertSee('Fête patronale');
    }

    public function test_past_event_is_marked_as_passed(): void
    {
        Event::create(['title' => 'Ancien événement', 'description' => 'Description.', 'starts_at' => now()->subWeek()]);

        $this->get(route('evenements.index'))
            ->assertOk()
            ->assertSee('Passé');
    }

    public function test_show_page_renders_event_details(): void
    {
        $event = Event::create([
            'title' => 'Fête patronale',
            'description' => 'Description complète.',
            'location' => 'Place publique',
            'starts_at' => now()->addWeek(),
        ]);

        $this->get(route('evenements.show', $event->slug))
            ->assertOk()
            ->assertSee('Fête patronale')
            ->assertSee('Place publique')
            ->assertSee('Description complète.');
    }

    public function test_guest_cannot_manage_events(): void
    {
        $this->get(route('admin.evenements.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_create_an_event(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->post(route('admin.evenements.store'), [
            'title' => 'Fête patronale',
            'description' => 'Description.',
            'starts_at' => now()->addWeek()->format('Y-m-d\TH:i'),
            'content_status' => 'needs_review',
        ])->assertRedirect(route('admin.evenements.index'));

        $this->assertDatabaseHas('events', ['title' => 'Fête patronale', 'slug' => 'fete-patronale']);
    }
}
