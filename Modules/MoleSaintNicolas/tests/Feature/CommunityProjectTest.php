<?php

namespace Tests\Feature;

use App\Models\CommunityProject;
use App\Models\ProjectComment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityProjectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_index_lists_projects(): void
    {
        CommunityProject::create(['title' => 'Réfection de la route', 'status' => 'en_cours', 'description' => 'Description.']);

        $this->get(route('projets.index'))
            ->assertOk()
            ->assertSee('Réfection de la route');
    }

    public function test_show_page_lists_only_approved_comments(): void
    {
        $project = CommunityProject::create(['title' => 'Puits communautaire', 'status' => 'termine', 'description' => 'Description.']);
        ProjectComment::create(['community_project_id' => $project->id, 'author_name' => 'Jean', 'body' => 'Avis approuvé', 'is_approved' => true]);
        ProjectComment::create(['community_project_id' => $project->id, 'author_name' => 'Pierre', 'body' => 'Avis en attente', 'is_approved' => false]);

        $this->get(route('projets.show', $project->slug))
            ->assertOk()
            ->assertSee('Avis approuvé')
            ->assertDontSee('Avis en attente');
    }

    public function test_visitor_can_submit_a_comment_and_it_is_not_approved_by_default(): void
    {
        $project = CommunityProject::create(['title' => 'École communautaire', 'status' => 'planifie', 'description' => 'Description.']);

        $this->post(route('projets.comments.store', $project->slug), [
            'author_name' => 'Marie',
            'body' => 'Bonne initiative !',
        ])->assertRedirect(route('projets.show', $project->slug));

        $comment = ProjectComment::where('body', 'Bonne initiative !')->firstOrFail();
        $this->assertFalse($comment->is_approved);

        // Pas encore visible publiquement tant qu'il n'est pas approuvé.
        $this->get(route('projets.show', $project->slug))->assertDontSee('Bonne initiative !');
    }

    public function test_comment_requires_author_name_and_body(): void
    {
        $project = CommunityProject::create(['title' => 'Projet', 'status' => 'planifie', 'description' => 'Description.']);

        $this->post(route('projets.comments.store', $project->slug), [])
            ->assertSessionHasErrors(['author_name', 'body']);
    }

    public function test_guest_cannot_manage_projects(): void
    {
        $this->get(route('admin.projets.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_create_a_project(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->post(route('admin.projets.store'), [
            'title' => 'Marché communautaire',
            'status' => 'planifie',
            'description' => 'Construction d\'un nouveau marché.',
            'content_status' => 'needs_review',
        ])->assertRedirect(route('admin.projets.index'));

        $this->assertDatabaseHas('community_projects', ['title' => 'Marché communautaire', 'slug' => 'marche-communautaire']);
    }

    public function test_guest_cannot_moderate_comments(): void
    {
        $this->get(route('admin.projets.comments.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_approve_and_delete_comments(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $project = CommunityProject::create(['title' => 'Projet', 'status' => 'planifie', 'description' => 'Description.']);
        $comment = ProjectComment::create(['community_project_id' => $project->id, 'author_name' => 'Jean', 'body' => 'Avis', 'is_approved' => false]);

        $this->actingAs($admin)->put(route('admin.projets.comments.approve', $comment))
            ->assertRedirect();

        $this->assertTrue($comment->refresh()->is_approved);

        $this->actingAs($admin)->delete(route('admin.projets.comments.destroy', $comment))
            ->assertRedirect();

        $this->assertDatabaseMissing('project_comments', ['id' => $comment->id]);
    }
}
