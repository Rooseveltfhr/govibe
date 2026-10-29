<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA LINKS / SITE — deux défauts des pages publiques (audit sécurité)
|--------------------------------------------------------------------------
| 1. Désactiver une page de liens ne désactivait QUE /links/{alias} : chacun
|    de ses liens restait joignable un par un via /links/go/{id}, qui ne
|    regardait que le drapeau du lien, jamais celui de sa page.
| 2. Les photos de galerie passaient par Storage::url() en dur dans les
|    vues — le disque par DÉFAUT, qui en production est `local` (privé, sans
|    URL publique). Toutes les images de galerie étaient donc cassées, alors
|    que logo et couverture, eux, passaient bien par le disque `public`.
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Links\Link;
use Modules\Tagtoa\App\Models\Links\LinkPage;
use Modules\Tagtoa\App\Models\Site\Site;
use Modules\Tagtoa\Tests\TestCase;

class LinksAndSitePublicTest extends TestCase
{
    use RefreshDatabase;

    private function pageWithLink(bool $pageActive): Link
    {
        $page = LinkPage::create([
            'tenant_id' => null, 'alias' => 'ma-page-'.random_int(1000, 9999),
            'title' => 'Ma page', 'is_active' => $pageActive,
        ]);

        return $page->links()->create([
            'label' => 'Mon site', 'url' => 'https://example.test', 'is_active' => true,
        ]);
    }

    public function test_a_link_of_an_active_page_still_redirects(): void
    {
        $link = $this->pageWithLink(true);

        $this->get(route('tagtoa.links.go', $link->id))
            ->assertRedirect('https://example.test');
        $this->assertSame(1, (int) $link->fresh()->clicks);
    }

    public function test_deactivating_a_page_also_closes_each_of_its_links(): void
    {
        // Le cœur du correctif : le marchand qui retire sa page croyait
        // avoir tout fermé. Chaque lien restait pourtant joignable.
        $link = $this->pageWithLink(false);

        $this->get(route('tagtoa.links.go', $link->id))->assertNotFound();
        $this->assertSame(0, (int) $link->fresh()->clicks);
    }

    public function test_gallery_images_use_the_public_disk_even_when_it_is_not_the_default(): void
    {
        // Reproduit la production : disque par défaut `local`, galerie
        // servie depuis le disque `public`.
        config([
            'filesystems.default' => 'local',
            'filesystems.disks.public.url' => 'https://tagtoa-test.example/storage',
        ]);

        $site = Site::create([
            'tenant_id' => null, 'alias' => 'ma-vitrine', 'name' => 'Ma vitrine',
            'gallery' => ['tagtoa/site-gallery/photo.jpg'],
        ]);

        $this->assertSame(
            ['https://tagtoa-test.example/storage/tagtoa/site-gallery/photo.jpg'],
            $site->gallery_urls
        );
    }

    public function test_an_empty_gallery_yields_no_urls(): void
    {
        $site = Site::create(['tenant_id' => null, 'alias' => 'vide', 'name' => 'Vide']);

        $this->assertSame([], $site->gallery_urls);
    }
}
