<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA LINKS — les clics existaient déjà (Link::clicks, incrémenté à
| chaque redirection), mais n'étaient affichés NULLE PART : ni sur l'écran
| « Vos pages de liens », ni sur l'écran d'édition d'une page. Un marchand
| n'avait aucun moyen de savoir si ses liens servaient vraiment.
|--------------------------------------------------------------------------
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Links\Link;
use Modules\Tagtoa\App\Models\Links\LinkPage;
use Modules\Tagtoa\Tests\TestCase;

class LinkClicksTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));
    }

    private function page(string $tenant = 't-1'): LinkPage
    {
        return LinkPage::create([
            'tenant_id' => $tenant, 'alias' => 'moi-'.random_int(1000, 9999), 'title' => 'Moi', 'is_active' => true,
        ]);
    }

    public function test_un_clic_sur_le_lien_public_incremente_son_compteur(): void
    {
        $page = $this->page();
        $link = $page->links()->create(['label' => 'Instagram', 'url' => 'https://instagram.com/x', 'is_active' => true]);

        $this->get(route('tagtoa.links.go', $link->id))->assertRedirect('https://instagram.com/x');

        $this->assertSame(1, $link->fresh()->clicks);
    }

    public function test_lecran_des_pages_affiche_le_total_des_clics(): void
    {
        $this->patron();
        $page = $this->page();
        $page->links()->create(['label' => 'A', 'url' => 'https://a.com', 'is_active' => true, 'clicks' => 12]);
        $page->links()->create(['label' => 'B', 'url' => 'https://b.com', 'is_active' => true, 'clicks' => 8]);

        $this->get(route('tagtoa.links.dashboard.index'))
            ->assertOk()
            ->assertSee('20'); // 12 + 8
    }

    public function test_lecran_dedition_expose_le_clic_de_chaque_lien(): void
    {
        $this->patron();
        $page = $this->page();
        $page->links()->create(['label' => 'A', 'url' => 'https://a.com', 'is_active' => true, 'clicks' => 7]);

        $this->get(route('tagtoa.links.dashboard.edit', $page->id))
            ->assertOk()
            // Les clics voyagent dans le JSON injecté pour le JS du formulaire.
            ->assertSee('"clicks":7', false);
    }

    public function test_une_page_sans_clic_naffiche_rien_de_trompeur(): void
    {
        $this->patron();
        $page = $this->page();

        $this->get(route('tagtoa.links.dashboard.index'))
            ->assertOk()
            ->assertSee('0');
    }
}
