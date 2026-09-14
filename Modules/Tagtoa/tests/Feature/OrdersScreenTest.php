<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Order\Order;
use Modules\Tagtoa\App\Support\Order\Channel;
use Modules\Tagtoa\App\Support\Order\OrderStatus;
use Modules\Tagtoa\Tests\TestCase;

/**
 * L'écran des commandes — la colonne vertébrale enfin visible.
 *
 * Elle existait sans écran depuis l'étape 02 : chaque module montrait ses
 * propres commandes, et le marchand ouvrait quatre écrans pour savoir ce qu'il
 * avait vendu dans la journée. C'est précisément ce que la colonne vertébrale
 * devait éviter.
 */
class OrdersScreenTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));
    }

    private function commande(array $attrs = []): Order
    {
        return Order::create(array_merge([
            'tenant_id'      => 't-1',
            'channel'        => Channel::POS,
            'source_type'    => 'pos_sale',
            'source_id'      => random_int(1, 999999),
            'reference'      => 'REF-'.random_int(1000, 9999),
            'subtotal'       => 100, 'discount' => 0, 'tax_base' => 100,
            'tax_total'      => 0, 'total' => 100, 'currency' => 'HTG',
            'status'         => OrderStatus::COMPLETED,
            'payment_status' => OrderStatus::PAID,
            'placed_at'      => now(),
        ], $attrs));
    }

    public function test_the_four_channels_meet_on_one_screen(): void
    {
        $this->patron();
        $this->commande(['channel' => Channel::POS,   'reference' => 'CAISSE-1']);
        $this->commande(['channel' => Channel::MENU,  'reference' => 'MENU-1']);
        $this->commande(['channel' => Channel::EVENT, 'reference' => 'EVENT-1']);
        $this->commande(['channel' => Channel::LINK,  'reference' => 'LIEN-1']);

        $this->get(route('tagtoa.orders.index'))->assertOk()
            ->assertSee('CAISSE-1')->assertSee('MENU-1')
            ->assertSee('EVENT-1')->assertSee('LIEN-1');
    }

    public function test_it_never_shows_the_neighbour_s_sales(): void
    {
        // Le cloisonnement est automatique (BelongsToTenant) : c'est le trait
        // qui empêche qu'un filtre oublié montre les ventes du voisin.
        $this->patron('t-1');
        $this->commande(['tenant_id' => 't-1', 'reference' => 'A-MOI']);
        Order::withoutGlobalScopes()->create([
            'tenant_id' => 't-2', 'channel' => Channel::POS, 'source_type' => 'pos_sale',
            'source_id' => 4242, 'reference' => 'AU-VOISIN', 'subtotal' => 900,
            'discount' => 0, 'tax_base' => 900, 'tax_total' => 0, 'total' => 900,
            'currency' => 'HTG', 'status' => OrderStatus::COMPLETED,
            'payment_status' => OrderStatus::PAID, 'placed_at' => now(),
        ]);

        $this->get(route('tagtoa.orders.index'))->assertOk()
            ->assertSee('A-MOI')->assertDontSee('AU-VOISIN');
    }

    public function test_to_serve_gathers_everything_that_is_not_finished(): void
    {
        // « À servir » n'est pas un statut : c'est TOUT ce qui n'est pas fini.
        // Lister statut par statut ferait manquer ceux qu'on ne pense pas à
        // cocher — et une commande oubliée en cuisine est un client perdu.
        $this->patron();
        $this->commande(['status' => OrderStatus::PENDING,   'reference' => 'RECUE']);
        $this->commande(['status' => OrderStatus::PREPARING, 'reference' => 'EN-CUISINE']);
        $this->commande(['status' => OrderStatus::READY,     'reference' => 'PRETE']);
        $this->commande(['status' => OrderStatus::COMPLETED, 'reference' => 'SERVIE']);
        $this->commande(['status' => OrderStatus::CANCELLED, 'reference' => 'ANNULEE']);

        $this->get(route('tagtoa.orders.index', ['status' => 'open']))->assertOk()
            ->assertSee('RECUE')->assertSee('EN-CUISINE')->assertSee('PRETE')
            ->assertDontSee('SERVIE')->assertDontSee('ANNULEE');
    }

    public function test_it_filters_by_channel(): void
    {
        $this->patron();
        $this->commande(['channel' => Channel::POS,  'reference' => 'CAISSE-9']);
        $this->commande(['channel' => Channel::MENU, 'reference' => 'MENU-9']);

        $this->get(route('tagtoa.orders.index', ['channel' => Channel::MENU]))->assertOk()
            ->assertSee('MENU-9')->assertDontSee('CAISSE-9');
    }

    public function test_an_invented_filter_shows_everything_rather_than_nothing(): void
    {
        // Un paramètre bricolé dans l'URL ne doit pas vider l'écran : le
        // marchand croirait avoir perdu ses ventes.
        $this->patron();
        $this->commande(['reference' => 'BIEN-LA']);

        $this->get(route('tagtoa.orders.index', ['channel' => 'inventé', 'status' => 'nimporte']))
            ->assertOk()->assertSee('BIEN-LA');
    }

    public function test_it_finds_an_order_by_reference_or_phone(): void
    {
        $this->patron();
        $this->commande(['reference' => 'CMD-777', 'customer_phone' => '50931234567']);
        $this->commande(['reference' => 'CMD-888']);

        $this->get(route('tagtoa.orders.index', ['q' => '777']))->assertOk()
            ->assertSee('CMD-777')->assertDontSee('CMD-888');

        $this->get(route('tagtoa.orders.index', ['q' => '31234567']))->assertOk()
            ->assertSee('CMD-777');
    }

    public function test_an_anonymous_customer_is_the_norm_not_a_gap(): void
    {
        // Le client de passage est le cas NORMAL en Haïti : la plupart ne
        // donnent pas leur nom. L'écran ne doit pas afficher un vide comme
        // s'il manquait quelque chose.
        $this->patron();
        $this->commande(['customer_name' => null]);

        $this->get(route('tagtoa.orders.index'))->assertOk()->assertSee('Client de passage');
    }

    public function test_the_day_s_total_ignores_what_was_cancelled(): void
    {
        // Compter une commande annulée dans le chiffre d'affaires ferait
        // déclarer au marchand un revenu qu'il n'a pas encaissé.
        $this->patron();
        $this->commande(['total' => 500, 'status' => OrderStatus::COMPLETED]);
        $this->commande(['total' => 300, 'status' => OrderStatus::CANCELLED]);
        $this->commande(['total' => 200, 'status' => OrderStatus::REFUNDED]);

        $this->get(route('tagtoa.orders.index'))->assertOk()->assertSee('500');
    }

    public function test_the_total_does_not_change_when_you_filter(): void
    {
        // Un chiffre d'affaires qui bouge quand on filtre par canal n'est plus
        // un chiffre d'affaires : c'est un sous-total qui se fait passer pour lui.
        $this->patron();
        $this->commande(['channel' => Channel::POS,  'total' => 400]);
        $this->commande(['channel' => Channel::MENU, 'total' => 600]);

        $sansFiltre = $this->get(route('tagtoa.orders.index'))->assertOk()->getContent();
        $avecFiltre = $this->get(route('tagtoa.orders.index', ['channel' => Channel::POS]))
            ->assertOk()->getContent();

        foreach ([$sansFiltre, $avecFiltre] as $html) {
            $this->assertStringContainsString('1,000', str_replace("\u{a0}", ' ', $html));
        }
    }

    public function test_an_empty_screen_says_so_calmly(): void
    {
        $this->patron();

        $this->get(route('tagtoa.orders.index'))->assertOk()
            ->assertSee('Aucune commande');
    }
}
