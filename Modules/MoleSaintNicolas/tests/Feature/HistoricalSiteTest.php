<?php

namespace Tests\Feature;

use App\Models\Histoire\HistoricalSite;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoricalSiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_index_lists_sites_and_placeholder_for_missing_description(): void
    {
        HistoricalSite::create(['name' => 'Fort Vallière', 'category' => 'Fort']);

        $this->get(route('lieux-historiques.index'))
            ->assertOk()
            ->assertSee('Fort Vallière')
            ->assertSee('[Information à compléter]');
    }

    public function test_show_page_renders_map_when_coordinates_present(): void
    {
        $site = HistoricalSite::create([
            'name' => 'Fort Vallière',
            'lat' => 19.8047,
            'lng' => -73.3778,
        ]);

        $this->get(route('lieux-historiques.show', $site->slug))
            ->assertOk()
            ->assertSee('Fort Vallière')
            ->assertSee('leaflet', escape: false);
    }

    public function test_show_page_without_coordinates_shows_placeholder(): void
    {
        $site = HistoricalSite::create(['name' => 'Lieu sans coordonnées']);

        $this->get(route('lieux-historiques.show', $site->slug))
            ->assertOk()
            ->assertSee('[Coordonnées GPS à compléter]');
    }

    public function test_unknown_site_returns_404(): void
    {
        $this->get('/lieux-historiques/inconnu')->assertNotFound();
    }

    public function test_guest_cannot_manage_sites(): void
    {
        $this->get(route('admin.histoire.sites.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_create_a_site(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->post(route('admin.histoire.sites.store'), [
            'name' => 'Fort Vallière',
            'content_status' => 'needs_review',
        ])->assertRedirect(route('admin.histoire.sites.index'));

        $this->assertDatabaseHas('historical_sites', ['name' => 'Fort Vallière', 'slug' => 'fort-valliere']);
    }
}
