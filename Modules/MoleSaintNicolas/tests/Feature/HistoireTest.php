<?php

namespace Tests\Feature;

use App\Models\Histoire\HistoricalEvent;
use App\Models\Histoire\HistoricalFigure;
use App\Models\Histoire\HistoricalPeriod;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoireTest extends TestCase
{
    use RefreshDatabase;

    private HistoricalPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->period = HistoricalPeriod::create([
            'name' => 'Arrivée de Christophe Colomb',
            'start_year' => 1492,
            'end_year' => 1494,
        ]);
    }

    public function test_histoire_page_lists_periods_and_placeholders_missing_content(): void
    {
        $this->get('/histoire')
            ->assertOk()
            ->assertSee('Arrivée de Christophe Colomb')
            ->assertSee('[Information à compléter]');
    }

    public function test_events_and_figures_appear_under_their_period(): void
    {
        HistoricalEvent::create([
            'historical_period_id' => $this->period->id,
            'title' => 'Colomb ancre dans la baie',
            'circa_year' => 1492,
        ]);
        HistoricalFigure::create([
            'historical_period_id' => $this->period->id,
            'name' => 'Christophe Colomb',
        ]);

        $this->get('/histoire')
            ->assertOk()
            ->assertSee('Colomb ancre dans la baie')
            ->assertSee('Christophe Colomb');
    }

    public function test_guest_cannot_manage_periods(): void
    {
        $this->get(route('admin.histoire.periods.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_create_event_without_slug_and_it_is_generated_from_title(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->post(route('admin.histoire.events.store'), [
            'historical_period_id' => $this->period->id,
            'title' => 'Retour de Christophe Colomb',
            'circa_year' => 1494,
            'content_status' => 'needs_review',
        ])->assertRedirect(route('admin.histoire.events.index'));

        $event = HistoricalEvent::where('title', 'Retour de Christophe Colomb')->firstOrFail();
        $this->assertSame('retour-de-christophe-colomb', $event->slug);
    }

    public function test_marking_a_figure_verified_records_who_and_when(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $figure = HistoricalFigure::create(['historical_period_id' => $this->period->id, 'name' => 'Christophe Colomb']);

        $this->actingAs($admin)->put(route('admin.histoire.figures.update', $figure), [
            'name' => $figure->name,
            'content_status' => 'verified',
        ])->assertRedirect(route('admin.histoire.figures.index'));

        $figure->refresh();
        $this->assertTrue($figure->isVerified());
        $this->assertSame($admin->id, $figure->verified_by);
        $this->assertNotNull($figure->verified_at);
    }

    public function test_admin_can_delete_a_period(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->delete(route('admin.histoire.periods.destroy', $this->period))
            ->assertRedirect(route('admin.histoire.periods.index'));

        $this->assertDatabaseMissing('historical_periods', ['id' => $this->period->id]);
    }
}
