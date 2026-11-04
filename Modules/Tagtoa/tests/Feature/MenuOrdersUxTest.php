<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA MENU — l'écran des commandes du marchand (UX-1)
|--------------------------------------------------------------------------
| Une seule action principale par carte (l'étape suivante), annuler/corriger dans « ⋯ », paiement en trois pastilles,
| total qui ne ment pas sur le paiement, ancienneté de la commande, filtres + recherche, une seule navigation, contraste.
*/

use Carbon\Carbon;
use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Menu\Order;
use Modules\Tagtoa\App\Support\DashboardModules;
use Modules\Tagtoa\Tests\TestCase;

class MenuOrdersUxTest extends TestCase
{
    use RefreshDatabase;

    private Menu $menu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->menu = Menu::create(['tenant_id' => 't-1', 'name' => 'Chez Rose', 'alias' => 'chez-rose', 'currency' => 'HTG', 'is_active' => true, 'ordering_enabled' => true]);
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-1', 'name' => 'Rose']));
    }

    private function order(array $over = []): Order
    {
        static $n = 0;
        $n++;

        return Order::create(array_merge([
            'tenant_id' => 't-1', 'menu_id' => $this->menu->id, 'reference' => 'CMD-UX'.str_pad((string) $n, 5, '0', STR_PAD_LEFT),
            'status' => 'pending', 'payment_status' => 'unpaid', 'order_type' => 'dine_in', 'currency' => 'HTG',
            'subtotal' => 700, 'total' => 700, 'customer_name' => 'Client '.$n, 'customer_phone' => '509'.str_pad((string) $n, 8, '0', STR_PAD_LEFT),
            'placed_at' => now(),
        ], $over));
    }

    private function page(string $qs = ''): string
    {
        return $this->get(route('tagtoa.menu.dashboard.orders', $this->menu->id).$qs)->assertOk()->getContent();
    }

    private function strip(string $html): string
    {
        return preg_replace('#<style.*?</style>|<script.*?</script>#s', '', $html);
    }

    /* ---------- une action principale : l'étape suivante ---------- */

    /** @dataProvider cycle */
    public function test_the_primary_button_is_the_next_step_of_the_cycle(string $status, string $type, ?string $next, ?string $label): void
    {
        $o = new Order(['status' => $status, 'order_type' => $type]);
        $step = $o->nextStep();

        $this->assertSame($next, $step['status'] ?? null);
        $this->assertSame($label, $step['label'] ?? null);
    }

    public static function cycle(): array
    {
        return [
            'pending'          => ['pending', 'dine_in', 'confirmed', 'Confirmer'],
            'confirmed'        => ['confirmed', 'dine_in', 'preparing', 'Envoyer en cuisine'],
            'preparing'        => ['preparing', 'pickup', 'ready', 'Marquer prête'],
            'ready sur place'  => ['ready', 'dine_in', 'completed', 'Servie'],
            'ready emporter'   => ['ready', 'pickup', 'completed', 'Remise au client'],
            'ready livraison'  => ['ready', 'delivery', 'picked_up', 'Récupérée par le livreur'],
            'en route'         => ['picked_up', 'delivery', 'completed', 'Livrée'],
            'terminée'         => ['completed', 'dine_in', null, null],
            'annulée'          => ['cancelled', 'dine_in', null, null],
        ];
    }

    public function test_an_already_confirmed_order_no_longer_offers_a_button_called_confirm(): void
    {
        $this->order(['status' => 'confirmed']);
        $html = $this->strip($this->page());

        $this->assertStringContainsString('Envoyer en cuisine', $html);
        $this->assertStringNotContainsString('fa-rotate"></i> Confirmer', $html, 'L\'ancien bouton « Confirmer » + icône refresh a disparu.');
        $this->assertSame(1, substr_count($html, 'class="btn btn-p ord-next"'));
    }

    public function test_the_next_step_button_really_advances_the_order(): void
    {
        $o = $this->order(['status' => 'pending']);
        $this->post(route('tagtoa.menu.dashboard.orders.status', $o->id), ['status' => $o->nextStep()['status']])->assertRedirect();
        $this->assertSame('confirmed', $o->fresh()->status);
    }

    public function test_cancel_and_status_correction_live_in_the_more_menu_with_a_confirmation(): void
    {
        $this->order();
        $html = $this->strip($this->page());

        $this->assertStringContainsString('<details class="ord-more">', $html);
        $this->assertMatchesRegularExpression('#<details class="ord-more">.*Corriger le statut.*Annuler la commande.*</details>#s', $html);
        $this->assertStringContainsString('Annuler cette commande ?', $html, 'Annuler demande confirmation.');
        $this->assertStringNotContainsString('<option value="pending" selected', explode('<details class="ord-more">', $html)[0], 'Plus de liste de statuts à côté du geste de tous les jours.');
    }

    public function test_a_finished_order_has_no_primary_button(): void
    {
        $this->order(['status' => 'ready', 'order_type' => 'dine_in']);
        $o2 = $this->order(['status' => 'completed']);   // traitée : ligne compacte, pas de carte
        $html = $this->strip($this->page());

        $this->assertSame(1, substr_count($html, 'class="btn btn-p ord-next"'));
        $this->assertStringNotContainsString('data-order="'.$o2->id.'"', $html);
    }

    /* ---------- paiement ---------- */

    public function test_paying_requires_choosing_one_of_the_chips_server_side_too(): void
    {
        $o = $this->order();
        $this->post(route('tagtoa.menu.dashboard.orders.paid', $o->id))->assertSessionHasErrors('payment_method');
        $this->assertFalse($o->fresh()->isPaid());

        $this->post(route('tagtoa.menu.dashboard.orders.paid', $o->id), ['payment_method' => 'natcash'])->assertSessionHasNoErrors();
        $this->assertTrue($o->fresh()->isPaid());
    }

    public function test_a_paid_order_shows_no_payment_chips(): void
    {
        $this->order(['payment_status' => 'paid', 'payment_method' => 'cash']);
        $this->assertSame(0, substr_count($this->page(), 'data-pay-form'));
    }

    /* ---------- couleur du total ---------- */

    public function test_the_total_is_green_only_when_paid_and_orange_when_unpaid(): void
    {
        $this->order(['customer_name' => 'Paye', 'payment_status' => 'paid', 'payment_method' => 'cash', 'status' => 'confirmed']);
        $this->order(['customer_name' => 'Pas paye']);
        $html = $this->strip($this->page());

        $this->assertSame(1, substr_count($html, 'class="ord-total paid"'));
        $this->assertSame(1, substr_count($html, 'class="ord-total unpaid"'));
        $this->assertStringNotContainsString('color:var(--blue)', explode('ord-card', $html, 2)[1] ?? '', 'Le total n\'est plus bleu/vert par défaut.');
    }

    /* ---------- ancienneté ---------- */

    public function test_age_labels_and_the_fifteen_minute_threshold(): void
    {
        $this->assertSame("à l'instant", Order::ageLabel(0));
        $this->assertSame('il y a 12 min', Order::ageLabel(12));
        $this->assertSame('il y a 3 h', Order::ageLabel(185));
        $this->assertSame('il y a 2 j', Order::ageLabel(2 * 1440 + 5));

        $this->assertFalse((new Order(['status' => 'pending', 'placed_at' => now()->subMinutes(15)]))->isLate(), '15 min pile n\'est pas encore en retard.');
        $this->assertTrue((new Order(['status' => 'pending', 'placed_at' => now()->subMinutes(16)]))->isLate());
        $this->assertFalse((new Order(['status' => 'completed', 'placed_at' => now()->subHours(5)]))->isLate(), 'Une commande terminée n\'est jamais en retard.');
    }

    public function test_the_card_shows_how_long_ago_and_turns_red_after_fifteen_minutes(): void
    {
        $this->order(['customer_name' => 'Recent', 'placed_at' => now()->subMinutes(3)]);
        $this->order(['customer_name' => 'Ancien', 'placed_at' => now()->subMinutes(40)]);
        $html = $this->strip($this->page());

        $this->assertStringContainsString('il y a 3 min', $html);
        $this->assertStringContainsString('il y a 40 min', $html);
        $this->assertSame(1, substr_count($html, 'class="ord-age late"'), 'Seule la commande de 40 min est rouge.');
        $this->assertSame(1, substr_count($html, 'class="ord-age "'));
    }

    /* ---------- mode de service ---------- */

    public function test_service_mode_has_an_icon_and_its_own_colour(): void
    {
        $this->order(['order_type' => 'dine_in']);
        $this->order(['order_type' => 'pickup']);
        $this->order(['order_type' => 'delivery']);
        $html = $this->strip($this->page());

        foreach (['fa-utensils', 'fa-bag-shopping', 'fa-motorcycle'] as $icon) {
            $this->assertStringContainsString('class="fa-solid '.$icon.'"', $html);
        }
        $this->assertStringContainsString('color:#6b21a8', $html, 'La livraison (coursier requis) se distingue des deux autres.');
    }

    /* ---------- filtres, regroupement, recherche ---------- */

    public function test_filters_show_only_what_they_say(): void
    {
        $this->order(['customer_name' => 'Zorro', 'status' => 'pending']);
        $this->order(['customer_name' => 'EnCuisine', 'status' => 'preparing', 'payment_status' => 'paid', 'payment_method' => 'cash']);
        $this->order(['customer_name' => 'Livree', 'order_type' => 'delivery', 'status' => 'confirmed']);

        $this->assertStringContainsString('Zorro', $this->page('?f=new'));
        $this->assertStringNotContainsString('EnCuisine', $this->page('?f=new'));
        $this->assertStringNotContainsString('EnCuisine', $this->page('?f=unpaid'), 'Payée : pas « à encaisser ».');
        $this->assertStringContainsString('Livree', $this->page('?f=delivery'));
        $this->assertStringNotContainsString('Zorro', $this->page('?f=delivery'));
        $this->assertStringContainsString('EnCuisine', $this->page(), 'Sans filtre : tout.');
        $this->assertStringContainsString('Zorro', $this->page('?f=bogus'), 'Un filtre inconnu est ignoré.');
    }

    public function test_search_by_name_or_phone_and_wildcards_are_not_wildcards(): void
    {
        $this->order(['customer_name' => 'Marie Joseph', 'customer_phone' => '50937001122']);
        $this->order(['customer_name' => 'Paul', 'customer_phone' => '50948889999']);

        $this->assertStringContainsString('Marie Joseph', $this->page('?q=marie'));
        $this->assertStringNotContainsString('Paul', $this->page('?q=marie'));
        $this->assertStringContainsString('Paul', $this->page('?q=8889'));
        $this->assertStringNotContainsString('Marie Joseph', $this->page('?q=8889'));
        $this->assertStringNotContainsString('Paul', $this->page('?q=%25%25'), 'Un « % » tapé ne correspond pas à tout.');
        $this->assertStringNotContainsString('Marie Joseph', $this->page('?q=_____'), 'Un « _ » tapé ne correspond pas à tout.');
    }

    public function test_search_also_finds_a_processed_order_from_yesterday(): void
    {
        $this->order(['customer_name' => 'Hier Termine', 'status' => 'completed', 'placed_at' => now()->subDay()]);
        $html = $this->page('?q=hier');
        $this->assertStringContainsString('Hier Termine', $html);
    }

    public function test_orders_are_grouped_under_today_and_yesterday(): void
    {
        $this->order(['customer_name' => 'DuJour', 'placed_at' => now()]);
        $this->order(['customer_name' => 'DHier', 'placed_at' => now()->subDay()->setTime(12, 0)]);
        $html = $this->strip($this->page());

        $this->assertMatchesRegularExpression("#Aujourd'hui.*DuJour.*Hier.*DHier#s", str_replace('&#039;', "'", $html));
    }

    public function test_filters_never_leak_another_merchants_orders(): void
    {
        $autre = Menu::create(['tenant_id' => 't-2', 'name' => 'Autre', 'alias' => 'autre', 'currency' => 'HTG', 'is_active' => true, 'ordering_enabled' => true]);
        Order::create(['tenant_id' => 't-2', 'menu_id' => $autre->id, 'reference' => 'CMD-AUTRE001', 'status' => 'pending', 'payment_status' => 'unpaid',
            'order_type' => 'dine_in', 'currency' => 'HTG', 'subtotal' => 1, 'total' => 1, 'customer_name' => 'Espion', 'placed_at' => now()]);
        $this->order(['customer_name' => 'Espion Local']);

        $html = $this->page('?q=espion');
        $this->assertStringContainsString('Espion Local', $html);
        $this->assertStringNotContainsString('CMD-AUTRE001', $html);
    }

    public function test_the_feed_keeps_the_filter_and_returns_chip_counts(): void
    {
        $this->order(['status' => 'pending']);
        $this->order(['status' => 'confirmed', 'order_type' => 'delivery']);

        $json = $this->getJson(route('tagtoa.menu.dashboard.orders.feed', $this->menu->id).'?f=delivery')->assertOk()->json();
        $this->assertSame(['all' => 2, 'new' => 1, 'unpaid' => 2, 'delivery' => 1], $json['counts']);
        $this->assertStringContainsString('fa-motorcycle', $json['html']);
        $this->assertStringNotContainsString('Confirmer</button>', $json['html'], 'La commande « nouvelle » est filtrée hors du fil livraison.');
    }

    public function test_the_page_wires_the_feed_with_the_current_filter_and_protects_a_half_made_choice(): void
    {
        $html = $this->page('?f=unpaid');
        $this->assertStringContainsString('window.location.search', $html);
        $this->assertStringContainsString('input[name=payment_method]:checked, details.ord-more[open]', $html, 'Un moyen choisi ou un « ⋯ » ouvert n\'est pas écrasé par le rafraîchissement.');
        $this->assertStringContainsString('aria-current="true"', $html);
    }

    /* ---------- une seule navigation, une seule pastille ---------- */

    public function test_the_menu_orders_screen_lights_only_the_commandes_tab(): void
    {
        $this->assertSame('orders', DashboardModules::bottomActive('tagtoa/menu/orders'));
        $this->assertSame('menu', DashboardModules::bottomActive('tagtoa/menu'), 'Le reste du menu garde son onglet.');
        $this->assertSame('menu', DashboardModules::bottomActive('tagtoa/menu/items'));
    }

    public function test_only_one_badge_and_one_navigation_on_the_orders_screen(): void
    {
        $html = $this->get(route('tagtoa.menu.dashboard.orders', $this->menu->id))->assertOk()->getContent();

        $this->assertStringContainsString('.tabs a[href$="/tagtoa/orders"]', $html);
        $this->assertStringNotContainsString('.tabs a[href$="/tagtoa/menu"], ', $html, 'La pastille n\'est plus posée deux fois (Menu ET Commandes).');
        $this->assertStringContainsString('@media(max-width:860px){ .subnav{display:none} }', $html, 'Sur téléphone, la barre du bas suffit.');
    }

    /* ---------- accessibilité ---------- */

    public function test_muted_grey_meets_contrast_and_logout_asks_confirmation(): void
    {
        $html = $this->get(route('tagtoa.menu.dashboard.orders', $this->menu->id))->assertOk()->getContent();

        $this->assertStringNotContainsString('--muted:#8a8a8a', $html, 'Le gris #8a8a8a (3,4:1 sur blanc) est illisible au soleil.');
        $this->assertMatchesRegularExpression('/--muted:#5b6068/', $html);
        // La route « logout » est celle de l'application hôte (absente de l'hôte de test) : on lit donc le gabarit.
        $layout = file_get_contents(__DIR__.'/../../resources/views/layouts/dashboard.blade.php');
        $this->assertStringContainsString("onsubmit=\"return confirm(@js(__('Se déconnecter ?')))\"", $layout);
        $this->assertStringContainsString('aria-label="{{ __(\'Se déconnecter\') }}"', $layout);
    }

    public function test_contrast_ratio_of_the_new_greys_is_at_least_aa(): void
    {
        $lum = function (string $hex): float {
            $c = array_map(fn ($v) => ($v /= 255) <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4, sscanf(ltrim($hex, '#'), '%02x%02x%02x'));

            return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
        };
        $ratio = fn (string $fg) => (1.05) / ($lum($fg) + 0.05);

        $this->assertGreaterThanOrEqual(4.5, $ratio('#5b6068'));
        $this->assertGreaterThanOrEqual(4.5, $ratio('#4b5563'));
        $this->assertGreaterThanOrEqual(4.5, $ratio('#c2410c'));
        $this->assertLessThan(4.5, $ratio('#8a8a8a'), 'Le point de départ ne passait pas.');
    }

    /* ---------- l'alerte ---------- */

    public function test_the_global_alert_offers_to_see_the_order_and_puts_the_badge_on_one_tab(): void
    {
        $html = $this->get(route('tagtoa.menu.dashboard.orders', $this->menu->id))->assertOk()->getContent();
        $this->assertStringContainsString('id="ordAlertGo"', $html);
        $this->assertStringContainsString('Voir la commande', $html);
    }
}
