<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_index_lists_only_published_posts(): void
    {
        Post::create(['title' => 'Article publié', 'body' => '<p>x</p>', 'published_at' => now()->subDay()]);
        Post::create(['title' => 'Brouillon', 'body' => '<p>x</p>', 'published_at' => null]);
        Post::create(['title' => 'Futur', 'body' => '<p>x</p>', 'published_at' => now()->addWeek()]);

        $this->get(route('actualites.index'))
            ->assertOk()
            ->assertSee('Article publié')
            ->assertDontSee('Brouillon')
            ->assertDontSee('Futur');
    }

    public function test_unpublished_post_show_page_returns_404(): void
    {
        $post = Post::create(['title' => 'Brouillon', 'body' => '<p>x</p>', 'published_at' => null]);

        $this->get(route('actualites.show', $post->slug))->assertNotFound();
    }

    public function test_published_post_show_page_renders_body(): void
    {
        $post = Post::create([
            'title' => 'Bienvenue',
            'body' => '<p>Contenu de test.</p>',
            'published_at' => now()->subHour(),
        ]);

        $this->get(route('actualites.show', $post->slug))
            ->assertOk()
            ->assertSee('Bienvenue')
            ->assertSee('Contenu de test.', escape: false);
    }

    public function test_guest_cannot_manage_posts(): void
    {
        $this->get(route('admin.posts.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_create_and_publish_a_post(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->post(route('admin.posts.store'), [
            'title' => 'Nouvel article',
            'body' => '<p>Contenu</p>',
            'published_at' => now()->subMinute()->format('Y-m-d\TH:i'),
            'content_status' => 'needs_review',
        ])->assertRedirect(route('admin.posts.index'));

        $post = Post::where('title', 'Nouvel article')->firstOrFail();
        $this->assertTrue($post->isPublished());

        $this->get(route('actualites.show', $post->slug))->assertOk();
    }
}
