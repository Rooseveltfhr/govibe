<?php

namespace Tests\Feature;

use App\Models\Territoire\Arrondissement;
use App\Models\Territoire\Commune;
use App\Models\Territoire\Department;
use App\Models\Territoire\Localite;
use App\Models\Territoire\SectionCommunale;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TerritoireTest extends TestCase
{
    use RefreshDatabase;

    private Commune $commune;

    private SectionCommunale $section;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $department = Department::create(['name' => 'Nord-Ouest', 'slug' => 'nord-ouest']);
        $arrondissement = Arrondissement::create([
            'department_id' => $department->id,
            'name' => 'Môle-Saint-Nicolas',
        ]);
        $this->commune = Commune::create([
            'arrondissement_id' => $arrondissement->id,
            'name' => 'Môle-Saint-Nicolas',
            'description' => 'Commune de test.',
        ]);
        $this->section = SectionCommunale::create([
            'commune_id' => $this->commune->id,
            'name' => 'Mare-Rouge',
        ]);
    }

    public function test_territoire_index_lists_communes(): void
    {
        $this->get('/territoire')->assertOk()->assertSee('Môle-Saint-Nicolas');
    }

    public function test_commune_page_lists_its_sections(): void
    {
        $this->get("/territoire/{$this->commune->slug}")
            ->assertOk()
            ->assertSee('Mare-Rouge');
    }

    public function test_localite_can_be_created_under_a_section_communale(): void
    {
        $localite = Localite::create(['section_communale_id' => $this->section->id, 'name' => 'Bassin Bleu']);

        $this->assertTrue($this->section->localites->contains($localite));
    }

    public function test_section_page_shows_placeholder_for_missing_info(): void
    {
        $this->get("/territoire/{$this->commune->slug}/{$this->section->slug}")
            ->assertOk()
            ->assertSee('[Information à compléter]');
    }

    public function test_guest_cannot_manage_communes(): void
    {
        $this->get(route('admin.territoire.communes.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_create_and_delete_a_commune(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $arrondissement = Arrondissement::first();

        $response = $this->actingAs($admin)->post(route('admin.territoire.communes.store'), [
            'arrondissement_id' => $arrondissement->id,
            'name' => 'Baie-de-Henne',
            'content_status' => 'needs_review',
        ]);

        $response->assertRedirect(route('admin.territoire.communes.index'));
        $created = Commune::where('name', 'Baie-de-Henne')->firstOrFail();
        $this->assertSame('baie-de-henne', $created->slug);

        $this->actingAs($admin)
            ->delete(route('admin.territoire.communes.destroy', $created))
            ->assertRedirect(route('admin.territoire.communes.index'));

        $this->assertDatabaseMissing('communes', ['id' => $created->id]);
    }

    public function test_marking_a_section_verified_records_who_and_when(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->put(route('admin.territoire.sections.update', $this->section), [
            'commune_id' => $this->commune->id,
            'name' => $this->section->name,
            'content_status' => 'verified',
        ])->assertRedirect(route('admin.territoire.sections.index'));

        $this->section->refresh();
        $this->assertTrue($this->section->isVerified());
        $this->assertSame($admin->id, $this->section->verified_by);
        $this->assertNotNull($this->section->verified_at);
    }
}
