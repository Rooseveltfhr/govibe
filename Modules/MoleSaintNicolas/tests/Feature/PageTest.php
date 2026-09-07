<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_about_page_renders_seeded_content(): void
    {
        Page::create([
            'title' => 'À propos de la plateforme',
            'slug' => 'a-propos',
            'body' => '<p>Contenu de test.</p>',
        ]);

        $this->get(route('pages.about'))
            ->assertOk()
            ->assertSee('À propos de la plateforme')
            ->assertSee('Contenu de test.', escape: false);
    }

    public function test_legal_page_renders_seeded_content(): void
    {
        Page::create([
            'title' => 'Mentions légales',
            'slug' => 'mentions-legales',
            'body' => '<p>[Information à compléter]</p>',
        ]);

        $this->get(route('pages.legal'))
            ->assertOk()
            ->assertSee('Mentions légales')
            ->assertSee('[Information à compléter]');
    }

    public function test_about_page_missing_returns_404(): void
    {
        $this->get(route('pages.about'))->assertNotFound();
    }

    public function test_navigation_and_footer_link_to_about_page(): void
    {
        Page::create(['title' => 'À propos de la plateforme', 'slug' => 'a-propos', 'body' => '<p>x</p>']);

        $this->get('/')
            ->assertOk()
            ->assertSee(route('pages.about'), escape: false);
    }

    public function test_guest_cannot_manage_pages(): void
    {
        $this->get(route('admin.pages.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_create_and_edit_a_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->post(route('admin.pages.store'), [
            'title' => 'Nouvelle page',
            'body' => '<p>Contenu</p>',
            'content_status' => 'needs_review',
        ])->assertRedirect(route('admin.pages.index'));

        $page = Page::where('title', 'Nouvelle page')->firstOrFail();
        $this->assertSame('nouvelle-page', $page->slug);

        $this->actingAs($admin)->put(route('admin.pages.update', $page), [
            'title' => 'Nouvelle page',
            'slug' => $page->slug,
            'body' => '<p>Contenu modifié</p>',
            'content_status' => 'verified',
        ])->assertRedirect(route('admin.pages.index'));

        $page->refresh();
        $this->assertTrue($page->isVerified());
        $this->assertSame($admin->id, $page->verified_by);
    }
}
