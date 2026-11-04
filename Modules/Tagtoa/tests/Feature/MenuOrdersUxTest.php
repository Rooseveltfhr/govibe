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
    public function test_the_primary_button_is_the_next_step_of_the_cycle(array $attrs, ?string $next, ?string $label): void
    {
        $step = (new Order($attrs))->nextStep();

        $this->assertSame($next, $step['status'] ?? null);
        $this->assertSame($label, $step['label'] ?? null);
    }

    public static function cycle(): array
    {
        $paid = ['payment_status' => 'paid'];

        return [
            'pending'                  => [['status' => 'pending', 'order_type' => 'dine_in'], 'confirmed', 'Confirmer'],
            'confirmed'                => [['status' => 'confirmed', 'order_type' => 'dine_in'], 'preparing', 'Envoyer en cuisine'],
            'preparing'                => [['status' => 'preparing', 'order_type' => 'pickup'], 'ready', 'Marquer prête'],
            'ready sur place payée'    => [['status' => 'ready', 'order_type' => 'dine_in'] + $paid, 'completed', 'Servie'],
            'ready emporter payée'     => [['status' => 'ready', 'order_type' => 'pickup'] + $paid, 'completed', 'Remise au client'],
            'ready sur place IMPAYÉE'  => [['status' => 'ready', 'order_type' => 'dine_in'], null, null],
            'ready livraison+livreur'  => [['status' => 'ready', 'order_type' => 'delivery', 'courier_id' => 7], 'picked_up', 'Récupérée par le livreur'],
            'ready livraison SANS'     => [['status' => 'ready', 'order_type' => 'delivery'], null, null],
            'en route payée'           => [['status' => 'picked_up', 'order_type' => 'delivery'] + $paid, 'completed', 'Livrée'],
            'en route IMPAYÉE'         => [['status' => 'picked_up', 'order_type' => 'delivery'], null, null],
            'terminée'                 => [['status' => 'completed', 'order_type' => 'dine_in'], null, null],
            'annulée'                  => [['status' => 'cancelled', 'order_type' => 'dine_in'], null, null],
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
        $this->order(['status' => 'ready', 'order_type' => 'dine_in', 'payment_status' => 'paid', 'payment_method' => 'cash']);
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
        $this->assertSame(0, substr_count($this->strip($this->page()), 'data-pay-form'));
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

    public function test_the_navigation_is_left_intact_and_the_badge_sits_on_one_tab_only(): void
    {
        $html = $this->get(route('tagtoa.menu.dashboard.orders', $this->menu->id))->assertOk()->getContent();

        $this->assertStringContainsString('.tabs a[href$="/tagtoa/orders"]', $html);
        $this->assertStringNotContainsString('.tabs a[href$="/tagtoa/menu"], ', $html, 'La pastille n\'est plus posée deux fois (Menu ET Commandes).');
        $this->assertStringNotContainsString('.subnav{display:none}', $html, 'La barre du haut (Produits, Cuisine, Livraison…) n\'est pas masquée : ces écrans n\'ont pas d\'autre accès en un geste.');
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

    public function test_age_label_boundaries_between_minutes_and_hours(): void
    {
        $this->assertSame('il y a 59 min', Order::ageLabel(59));
        $this->assertSame('il y a 1 h', Order::ageLabel(60), '60 minutes = 1 h, pas « 60 min ».');
        $this->assertSame('il y a 23 h', Order::ageLabel(1439));
        $this->assertSame('il y a 1 j', Order::ageLabel(1440));
    }

    public function test_a_one_letter_search_is_ignored_instead_of_matching_everything_or_nothing(): void
    {
        $this->order(['customer_name' => 'Zoe']);
        $this->order(['customer_name' => 'Yann']);
        $html = $this->page('?q=z');

        $this->assertStringContainsString('Zoe', $html);
        $this->assertStringContainsString('Yann', $html, 'Une seule lettre ne filtre pas (trop large) : la liste reste complète.');
    }

    public function test_processed_orders_stay_visible_under_the_chips_which_only_talk_about_what_is_left_to_do(): void
    {
        $this->order(['customer_name' => 'Nouvelle Zed', 'status' => 'pending']);
        $this->order(['customer_name' => 'Deja Traitee', 'status' => 'completed']);

        $html = $this->page('?f=new');
        $this->assertStringContainsString('Nouvelle Zed', $html);
        $this->assertStringContainsString('Deja Traitee', $html, 'Le filtre « Nouveau » ne vide pas la liste des commandes traitées.');
    }

    public function test_pagination_links_keep_the_filter_and_the_search(): void
    {
        for ($i = 0; $i < 22; $i++) {
            $this->order(['order_type' => 'delivery', 'customer_name' => 'Livreur '.$i]);
        }
        $html = $this->page('?f=delivery&q=livreur');

        $this->assertMatchesRegularExpression('#page=2[^"]*#', $html);
        $this->assertMatchesRegularExpression('#href="[^"]*(f=delivery[^"]*q=livreur|q=livreur[^"]*f=delivery)[^"]*page=2|href="[^"]*page=2[^"]*(f=delivery[^"]*q=livreur|q=livreur[^"]*f=delivery)#', html_entity_decode($html));
    }

    public function test_the_alert_button_wires_see_the_order_and_the_meta_text_is_dark_enough(): void
    {
        $html = $this->page();
        $this->assertStringContainsString('onclick="voirCommande()"', $html, '« Voir la commande » mène à la commande (et coupe le son).');
        $this->assertStringContainsString('.ord-meta{color:#4b5563', $html, 'Le texte secondaire des cartes passe AA (7,5:1).');
    }

    /* ---------- relecture indépendante : règles financières et livraison ---------- */

    public function test_an_unpaid_ready_order_shows_collect_first_instead_of_a_finish_button(): void
    {
        $this->order(['status' => 'ready', 'order_type' => 'dine_in']);
        $html = $this->strip($this->page());

        $this->assertStringContainsString('Encaissez d\'abord pour terminer', str_replace('&#039;', "'", $html));
        $this->assertStringNotContainsString('Servie', $html, 'Pas de « Servie » : terminer sans encaisser ferait sortir la commande de « À encaisser » et perdre le revenu.');
        $this->assertSame(1, substr_count($html, 'data-pay-form'), 'Les pastilles d\'encaissement restent là.');
    }

    public function test_after_collecting_the_finish_button_appears(): void
    {
        $o = $this->order(['status' => 'ready', 'order_type' => 'pickup']);
        $this->post(route('tagtoa.menu.dashboard.orders.paid', $o->id), ['payment_method' => 'cash'])->assertSessionHasNoErrors();

        $html = $this->strip($this->page());
        $this->assertStringContainsString('Remise au client', $html);
    }

    public function test_a_ready_delivery_without_a_courier_offers_to_assign_one_not_to_mark_it_picked_up(): void
    {
        $this->order(['status' => 'ready', 'order_type' => 'delivery']);
        $html = $this->strip($this->page());

        $this->assertStringContainsString('Assigner un livreur', $html);
        $this->assertStringNotContainsString('Récupérée par le livreur', explode('<details class="ord-more">', $html)[0]);
    }

    public function test_set_status_refuses_picked_up_without_a_courier_but_accepts_it_with_one_and_stamps_the_time(): void
    {
        $sans = $this->order(['status' => 'ready', 'order_type' => 'delivery']);
        $this->post(route('tagtoa.menu.dashboard.orders.status', $sans->id), ['status' => 'picked_up'])->assertSessionHas('error');
        $this->assertSame('ready', $sans->fresh()->status);

        $staff = \Modules\Tagtoa\App\Models\Staff\Staff::create(['tenant_id' => 't-1', 'name' => 'Livreur', 'role' => 'courier', 'pin_hash' => 'x', 'is_active' => true]);
        $avec = $this->order(['status' => 'ready', 'order_type' => 'delivery', 'courier_id' => $staff->id]);
        $this->post(route('tagtoa.menu.dashboard.orders.status', $avec->id), ['status' => 'picked_up'])->assertSessionHasNoErrors();
        $this->assertSame('picked_up', $avec->fresh()->status);
        $this->assertNotNull($avec->fresh()->picked_up_at);
    }

    public function test_a_cancelled_order_cannot_be_collected_server_side(): void
    {
        $o = $this->order(['status' => 'cancelled']);
        $this->post(route('tagtoa.menu.dashboard.orders.paid', $o->id), ['payment_method' => 'cash'])->assertSessionHas('error');
        $this->assertFalse($o->fresh()->isPaid());
    }

    public function test_a_second_collect_click_says_so_and_writes_no_second_audit_line(): void
    {
        $o = $this->order();
        $this->post(route('tagtoa.menu.dashboard.orders.paid', $o->id), ['payment_method' => 'cash'])->assertSessionHas('success');
        $avant = \Illuminate\Support\Facades\DB::table('tagtoa_audit_logs')->where('action', 'order.paid')->count();

        $this->post(route('tagtoa.menu.dashboard.orders.paid', $o->id), ['payment_method' => 'moncash'])->assertSessionHas('success', 'Déjà encaissée.');

        $this->assertSame($avant, \Illuminate\Support\Facades\DB::table('tagtoa_audit_logs')->where('action', 'order.paid')->count());
        $this->assertSame('cash', $o->fresh()->payment_method, 'Le moyen du premier encaissement n\'est pas écrasé.');
    }

    public function test_search_wildcards_are_literal_on_every_database(): void
    {
        $this->order(['customer_name' => '100% Joseph']);
        $this->order(['customer_name' => 'Autre Client']);

        $this->assertStringContainsString('100% Joseph', $this->page('?q=100%25'));
        $this->assertStringNotContainsString('Autre Client', $this->page('?q=100%25'), '« % » est un caractère, pas un joker.');
        $this->assertStringNotContainsString('100% Joseph', $this->page('?q=!!'), '« ! » (notre caractère d\'échappement) est lui aussi littéral.');
    }

    public function test_the_feed_gives_the_server_clock_and_the_page_uses_it_for_ages(): void
    {
        $json = $this->getJson(route('tagtoa.menu.dashboard.orders.feed', $this->menu->id))->assertOk()->json();
        $this->assertEqualsWithDelta(now()->timestamp, $json['now'], 5);

        $html = $this->page();
        $this->assertStringContainsString('decalage', $html, 'L\'âge ne dépend pas de l\'horloge du téléphone.');
        $this->assertStringContainsString('PROTEGE_MS = 45000', $html, 'Une pastille ou un « ⋯ » abandonnés ne figent pas la liste indéfiniment.');
        $this->assertStringContainsString("window.addEventListener('pageshow', syncPay)", $html);
    }

    public function test_voir_la_commande_leaves_a_filtered_view_for_the_full_list(): void
    {
        $html = $this->page('?f=delivery');
        $this->assertStringContainsString('window.location.href = window.location.pathname', $html);
    }

    public function test_alert_banners_use_a_dark_background_for_white_text(): void
    {
        $page = $this->page();
        $this->assertStringContainsString('background:#14532d', $page);
        $this->assertStringNotContainsString('background:var(--blue);color:#fff;border-radius:14px', $page, 'Blanc sur le vert de marque : 2,7:1, illisible.');
    }

    public function test_touch_targets_are_at_least_44px(): void
    {
        $html = $this->page();
        $this->assertGreaterThanOrEqual(3, substr_count($html, 'min-height:44px'));
    }

    public function test_every_new_label_is_translated_in_english_creole_and_spanish(): void
    {
        $labels = ['Envoyer en cuisine', 'Marquer prête', 'Remise au client', 'Servie', 'Livrée', 'Récupérée par le livreur', 'Assigner un livreur',
            'Encaissez d\'abord pour terminer', 'Corriger le statut', 'Appliquer', 'Plus d\'actions', 'Annuler la commande', 'Annuler cette commande ?',
            'Se déconnecter ?', 'Moyen de paiement', 'Autre', 'Tout', 'Nouveau', 'À encaisser', 'Filtrer les commandes', 'Nom ou téléphone…',
            'Rechercher', 'Aujourd\'hui', 'Hier', 'Voir la commande', 'Arrêter le son', 'Déjà encaissée.', 'à l\'instant', 'il y a :n min', 'il y a :n h', 'il y a :n j'];
        foreach (['en', 'ht', 'es'] as $lang) {
            $d = json_decode(file_get_contents(__DIR__.'/../../resources/lang/'.$lang.'.json'), true);
            foreach ($labels as $k) {
                $this->assertArrayHasKey($k, $d, "$lang : « $k » non traduit");
                $this->assertNotSame('', trim($d[$k]));
            }
            $this->assertStringContainsString(':n', $d['il y a :n min'], 'Le nombre reste un paramètre.');
        }
    }

    public function test_the_collect_first_note_never_applies_to_a_delivery_waiting_for_a_courier(): void
    {
        $this->assertFalse((new Order(['status' => 'ready', 'order_type' => 'delivery']))->awaitsPaymentToFinish(), 'Elle attend un livreur, pas un encaissement.');
        $this->assertTrue((new Order(['status' => 'ready', 'order_type' => 'delivery']))->awaitsCourier());
        $this->assertTrue((new Order(['status' => 'ready', 'order_type' => 'dine_in']))->awaitsPaymentToFinish());
        $this->assertTrue((new Order(['status' => 'picked_up', 'order_type' => 'delivery']))->awaitsPaymentToFinish());
        $this->assertFalse((new Order(['status' => 'ready', 'order_type' => 'dine_in', 'payment_status' => 'paid']))->awaitsPaymentToFinish());
        $this->assertFalse((new Order(['status' => 'cancelled', 'order_type' => 'dine_in']))->awaitsPaymentToFinish());
    }

    public function test_the_stale_protection_really_expires(): void
    {
        $this->assertStringContainsString('if (Date.now() - touche > PROTEGE_MS) return false;', $this->page());
    }

    public function test_the_global_alert_banner_is_dark_too(): void
    {
        $this->assertStringContainsString('background:#14532d;color:#fff;border-radius:14px;padding:12px 14px', $this->page());
    }
}
