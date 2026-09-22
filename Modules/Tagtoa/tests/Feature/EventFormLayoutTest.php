<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA EVENT — le formulaire cesse de se contredire lui-même
|--------------------------------------------------------------------------
| Signalé avec des captures d'écran mobiles : des champs trop grands, mal
| alignés, ou coupés sur plusieurs lignes. Dans ce formulaire précisément :
| le haut groupait Type/Devise et Début/Fin côte à côte, mais Lieu, Adresse,
| Cover et Logo — exactement le même genre de paire courte — restaient
| empilés un par ligne ; un `<div></div>` vide gaspillait la moitié d'une
| ligne sur ordinateur ; et la ligne « type de billet » (5 champs) n'avait
| aucune grille, donc se brisait n'importe où sous 390px.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\Tests\TestCase;

class EventFormLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function ecran(): string
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-1', 'name' => 'Roosevelt']));

        return $this->get(route('tagtoa.event.dashboard.create'))->assertOk()->getContent();
    }

    public function test_venue_and_address_sit_side_by_side_like_the_other_short_field_pairs(): void
    {
        $html = $this->ecran();

        $this->assertMatchesRegularExpression(
            '/<div class="row">\s*<div><label class="lbl">Lieu<\/label><input class="inp" name="venue"[^>]*><\/div>\s*'.
            '<div><label class="lbl">Adresse<\/label><input class="inp" name="address"/s',
            $html
        );
    }

    public function test_cover_and_logo_sit_side_by_side(): void
    {
        $html = $this->ecran();

        $this->assertMatchesRegularExpression(
            '/<div class="row">\s*<div>\s*<label class="lbl">Cover<\/label><input class="inp" type="file" name="cover"/s',
            $html
        );
        $this->assertStringContainsString('name="logo"', $html);
    }

    public function test_no_empty_spacer_div_is_left_to_fake_alignment(): void
    {
        $html = $this->ecran();

        $this->assertStringNotContainsString('<div></div>', $html);
    }

    public function test_the_ticket_type_row_uses_the_shared_short_fields_grid(): void
    {
        $html = $this->ecran();

        $this->assertStringContainsString('<div class="ttrow card"', $html);
        $this->assertStringContainsString('<div class="pf">', $html);
        // Plus de largeurs ad hoc par champ : c'est la grille qui décide.
        $this->assertStringNotContainsString('style="max-width:150px"', $html);
        $this->assertStringNotContainsString('style="max-width:100px"', $html);
    }

    public function test_every_toggle_in_the_ticket_type_row_carries_visible_text(): void
    {
        $html = $this->ecran();

        preg_match('/<template id="tttpl">(.*?)<\/template>/s', $html, $m);
        $this->assertNotEmpty($m, 'Le template #tttpl est introuvable.');
        $tpl = $m[1];

        $this->assertStringNotContainsString('title="Actif"', $tpl);
        $this->assertMatchesRegularExpression('/is_active][^>]*checked>\s*Actif/', $tpl);
        $this->assertMatchesRegularExpression('/is_vip][^>]*>\s*VIP/', $tpl);
    }
}
