<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA Stand — stand/reseller.blade.php rejoint la fondation partagée
|--------------------------------------------------------------------------
| Dernier des trois exemples NOMMÉS par le commit de fondation
| (« pos/settings, pos/lots, stand/reseller… ») : le formulaire « Déclarer
| une vente » redéfinissait sa propre grille de champs courts (.pf) et sa
| propre classe de champ (.ic) au lieu des .inp/.sel partagés.
|
| Cet écran avait un piège de plus : les TROIS carrés d'icône des tuiles
| statistiques réutilisaient aussi la classe .ic — pas comme un champ, mais
| pour sa bordure et son padding. Un simple renommage global aurait changé
| leur rendu ; ils portent donc leur propre classe locale (.icon-box)
| plutôt que de disparaître avec .ic.
|
| Migration markup/CSS seule : aucun champ, aucune validation, aucune
| logique serveur n'a changé (voir ResellerController@index/@sell, non
| touchés).
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Stand\Reseller;
use Modules\Tagtoa\App\Services\Stand\StandMinter;
use Modules\Tagtoa\Tests\TestCase;

class StandResellerFormLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function ecran(): string
    {
        app(StandMinter::class)->mint('TAGTOA-2026-001', 2);
        Reseller::create([
            'business_id' => 't-1', 'name' => 'Chez Wilner',
            'zone' => 'Cap-Haïtien', 'commission_pct' => 15, 'is_active' => true,
        ]);
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-1', 'name' => 'Wilner']));

        return $this->get(route('tagtoa.reseller.index'))->assertOk()->getContent();
    }

    public function test_the_sale_form_uses_the_shared_input_class_not_the_local_ic_one(): void
    {
        $html = $this->ecran();

        $this->assertStringContainsString('<input class="inp" id="pid"', $html);
        $this->assertStringContainsString('<input class="inp" id="bn"', $html);
        $this->assertStringContainsString('<input class="inp" id="bp"', $html);
        $this->assertStringContainsString('<input class="inp" name="q"', $html);
        // Préfixe précis (guillemet fermant) : évite tout faux positif avec
        // une autre classe qui commencerait aussi par « ic » (ex. .icon-box).
        $this->assertStringNotContainsString('class="ic"', $html);
        $this->assertStringNotContainsString('.ic{width:100%', $html);
    }

    public function test_the_form_uses_the_shared_short_fields_grid_not_a_local_one(): void
    {
        $html = $this->ecran();

        $this->assertStringContainsString('<div class="pf">', $html);
        // La fondation partagée déclare déjà .pf{display:grid…} une fois sur
        // CHAQUE page — cet écran ne doit plus en porter une SECONDE, locale.
        $this->assertSame(1, substr_count($html, '.pf{display:grid'));
    }

    public function test_the_stat_tiles_keep_their_own_icon_box_class_instead_of_disappearing_with_ic(): void
    {
        $html = $this->ecran();

        $this->assertSame(3, substr_count($html, 'class="icon-box"'),
            'Les trois carrés d\'icône des tuiles statistiques doivent garder leur rendu via .icon-box.');
    }
}
