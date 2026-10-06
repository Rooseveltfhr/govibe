<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA POS — pos/settings.blade.php rejoint la fondation partagée
|--------------------------------------------------------------------------
| L'un des trois exemples NOMMÉS par le commit de fondation
| (« pos/settings, pos/lots, stand/reseller… ») : cet écran redéfinissait sa
| propre classe de champ (.ic) et sa propre grille .pf (même largeur que la
| fondation, minmax(130px,1fr), mais une deuxième définition du même
| composant quand même) au lieu des .inp/.sel partagés. Le libellé
| « Activité » dupliquait aussi en ligne le style que la classe .lbl
| partagée porte déjà.
|
| Migration markup/CSS seule : aucun champ, aucune validation, aucune
| logique serveur n'a changé (voir SettingsController@update/
| @updateBusinessType, non touchés).
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Support\Tenant;
use Modules\Tagtoa\Tests\TestCase;

class PosSettingsFormLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function ecran(): string
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-1', 'name' => 'Roosevelt']));
        Tenant::flush();
        Terminal::create(['tenant_id' => 't-1', 'name' => 'Caisse 1', 'currency' => 'HTG', 'is_active' => true]);

        return $this->get(route('tagtoa.pos.settings'))->assertOk()->getContent();
    }

    public function test_the_poste_form_uses_the_shared_input_and_select_classes_not_the_local_ic_one(): void
    {
        $html = $this->ecran();

        $this->assertStringContainsString('<input class="inp" name="name"', $html);
        $this->assertStringContainsString('<select class="sel" name="currency">', $html);
        // Préfixe précis (guillemet fermant) : évite tout faux positif avec
        // une autre classe qui commencerait aussi par « ic ».
        $this->assertStringNotContainsString('class="ic"', $html);
        $this->assertStringNotContainsString('.ic{width:100%', $html);
    }

    public function test_the_business_type_dropdown_uses_the_shared_select_class(): void
    {
        $html = $this->ecran();

        $this->assertStringContainsString('<select class="sel" name="type" required>', $html);
    }

    public function test_the_activity_label_no_longer_duplicates_the_shared_lbl_class_inline(): void
    {
        $html = $this->ecran();

        $this->assertStringContainsString('<label class="lbl">'.__('Activité').'</label>', $html);
    }

    /** La fondation partagée déclare déjà .pf{display:grid…} une fois sur
     *  CHAQUE page (layouts/dashboard.blade.php) — cet écran ne doit plus en
     *  porter une SECONDE, locale et redondante. */
    public function test_the_pf_grid_is_declared_once_by_the_shared_foundation_not_a_second_time_locally(): void
    {
        $html = $this->ecran();

        $this->assertSame(1, substr_count($html, '.pf{display:grid'));
    }

    public function test_the_poste_name_still_spans_two_columns_via_the_shared_w2_helper(): void
    {
        $html = $this->ecran();

        $this->assertStringContainsString('<div class="w2">', $html);
        // La RÈGLE .pf .w2{grid-column:span 2} reste légitimement dans le
        // <style> — c'est le style EN LIGNE qui ne doit plus exister.
        $this->assertStringNotContainsString('style="grid-column:span 2"', $html);
    }
}
