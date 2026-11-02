<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| Formulaires compacts : devise + prix sur UNE ligne, horaires simplifiés
|--------------------------------------------------------------------------
| Sur téléphone, chaque champ d'un `.row` prenait toute la largeur : une paire « devise + prix »
| occupait deux lignes. `.duo` (devise étroite + prix large) et `.row.pair` gardent les champs courts
| côte à côte. Les horaires : un seul horaire + jours en pastilles par défaut, détail par jour en option ;
| les champs réellement envoyés (`hours[jour][open|close|closed]`) ne changent pas.
| Le rendu réel (même ligne à 390 px, recopie des horaires) a été vérifié dans un navigateur.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Support\Tenant;
use Modules\Tagtoa\Tests\TestCase;

class FormsCompactLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function menu(): Menu
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-1', 'name' => 'Rose']));
        Tenant::flush();

        return Menu::create(['tenant_id' => 't-1', 'name' => 'Lakay', 'alias' => 'lakay', 'currency' => 'HTG', 'is_active' => true, 'ordering_enabled' => true]);
    }

    public function test_the_item_form_puts_currency_and_price_in_one_row_with_the_add_link_below(): void
    {
        $m = $this->menu();
        $html = $this->get(route('tagtoa.menu.dashboard.items.create', $m->id))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<div class="duo"[^>]*>\s*<div>\s*<label class="lbl">Devise<\/label>\s*<select name="item_currency".*?<label class="lbl">Prix \*<\/label>\s*<input name="item_price"/s', $html);
        // le lien « Ajouter une devise » est SOUS la ligne, pas entre la devise et le prix
        $this->assertGreaterThan(strpos($html, 'name="item_price"'), strpos($html, '+ Ajouter une devise'));
        $this->assertStringContainsString('inputmode="decimal"', $html);
    }

    public function test_the_shared_css_defines_the_compact_components(): void
    {
        $m = $this->menu();
        $html = $this->get(route('tagtoa.menu.dashboard.items.create', $m->id))->assertOk()->getContent();

        $this->assertStringContainsString('.row.pair>*{min-width:0}', $html);
        $this->assertStringContainsString('.duo{display:grid;grid-template-columns:minmax(100px,30%) 1fr', $html);
    }

    public function test_the_menu_form_offers_a_simple_schedule_and_keeps_the_real_field_names(): void
    {
        $m = $this->menu();
        $html = $this->get(route('tagtoa.menu.dashboard.edit', $m->id))->assertOk()->getContent();

        $this->assertStringContainsString('id="hoursSimple"', $html);
        $this->assertStringContainsString('id="hsOpen"', $html);
        foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $j) {
            $this->assertStringContainsString('data-day="'.$j.'"', $html);
            foreach (['open', 'close', 'closed'] as $k) {
                $this->assertStringContainsString('name="hours['.$j.']['.$k.']"', $html, "Champ hours[$j][$k] manquant.");
            }
        }
        $this->assertStringContainsString('Horaires différents selon les jours', $html);
    }

    public function test_saving_the_schedule_exactly_as_the_simple_view_posts_it(): void
    {
        $m = $this->menu();
        // Ce qu'envoie le navigateur après « 09:00–17:00, sans samedi ni dimanche » : jours fermés = case cochée, heures désactivées (non envoyées).
        $post = ['name' => 'Lakay', 'currency' => 'HTG', 'show_hours' => '1', 'timezone' => 'America/Port-au-Prince', 'hours' => [
            'mon' => ['open' => '09:00', 'close' => '17:00'], 'tue' => ['open' => '09:00', 'close' => '17:00'],
            'wed' => ['open' => '09:00', 'close' => '17:00'], 'thu' => ['open' => '09:00', 'close' => '17:00'],
            'fri' => ['open' => '09:00', 'close' => '17:00'], 'sat' => ['closed' => '1'], 'sun' => ['closed' => '1'],
        ]];

        $this->put(route('tagtoa.menu.dashboard.update', $m->id), $post)->assertSessionHasNoErrors();

        $h = $m->fresh()->hours;
        $this->assertSame(['open' => '09:00', 'close' => '17:00'], $h['fri']);
        $this->assertNull($h['sat']);
        $this->assertNull($h['sun']);
    }
}
