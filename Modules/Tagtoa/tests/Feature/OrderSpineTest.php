<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Order\Customer;
use Modules\Tagtoa\App\Models\Order\Order;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Services\Menu\MenuOrderService;
use Modules\Tagtoa\App\Services\Pos\PosCatalog;
use Modules\Tagtoa\App\Services\Pos\PosService;
use Modules\Tagtoa\App\Support\Order\Channel;
use Modules\Tagtoa\App\Support\Order\OrderStatus;
use Modules\Tagtoa\Tests\TestCase;

/**
 * La colonne vertébrale : quatre canaux, un seul chiffre d'affaires.
 *
 * Tant que chaque module comptait dans son coin, le marchand additionnait
 * quatre écrans de tête, et ne pouvait pas voir qu'un même client achète chez
 * lui par trois chemins différents.
 */
class OrderSpineTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));
    }

    private function caisse(string $tenantId = 't-1'): Terminal
    {
        return Terminal::firstOrCreate(['tenant_id' => $tenantId, 'name' => 'Caisse'],
            ['currency' => 'HTG', 'is_active' => true]);
    }

    private function vendreAuComptoir(array $payload = [], string $tenantId = 't-1')
    {
        $coca = app(PosCatalog::class)->save($this->caisse($tenantId),
            ['name' => 'Coca', 'price' => 100, 'is_active' => true]);

        return app(PosService::class)->recordSale($this->caisse($tenantId), array_merge([
            'items' => [['ref' => 'pos:'.$coca->id, 'qty' => 1]],
        ], $payload));
    }

    private function menu(string $tenantId = 't-1'): Menu
    {
        return Menu::firstOrCreate(['tenant_id' => $tenantId, 'alias' => 'carte-'.$tenantId],
            ['name' => 'Carte', 'currency' => 'HTG', 'is_active' => true, 'ordering_enabled' => true]);
    }

    private function commanderAuQr(array $client = [], string $tenantId = 't-1')
    {
        $menu = $this->menu($tenantId);
        $cat  = Category::firstOrCreate(['menu_id' => $menu->id, 'name' => 'Plats'], ['is_active' => true]);
        $plat = Item::create(['menu_id' => $menu->id, 'category_id' => $cat->id,
            'name' => 'Griot', 'price' => 350, 'is_available' => true]);

        return app(MenuOrderService::class)->placeOrder($menu, array_merge([
            'items' => [['id' => $plat->id, 'qty' => 1]],
        ], $client));
    }

    /* ------------------------------------------------------------------
       Chaque canal s'inscrit.
       ------------------------------------------------------------------ */

    public function test_a_counter_sale_is_born_finished_and_paid(): void
    {
        // La caisse encaisse en même temps qu'elle vend : elle n'a rien à
        // attendre.
        $this->patron();
        $vente = $this->vendreAuComptoir();

        $ligne = Order::where('source_type', 'pos_sale')->firstOrFail();

        $this->assertSame(Channel::POS, $ligne->channel);
        $this->assertSame($vente->reference, $ligne->reference);
        $this->assertEquals(100.0, (float) $ligne->total);
        $this->assertSame(OrderStatus::COMPLETED, $ligne->status);
        $this->assertSame(OrderStatus::PAID, $ligne->payment_status);
    }

    public function test_a_qr_order_waits_for_its_payment(): void
    {
        // Le QR passe la commande ; le paiement vient après — parfois jamais.
        $this->patron();
        $commande = $this->commanderAuQr();

        $ligne = Order::where('source_type', 'menu_order')->firstOrFail();

        $this->assertSame(Channel::MENU, $ligne->channel);
        $this->assertSame(OrderStatus::PENDING, $ligne->status);
        $this->assertSame(OrderStatus::UNPAID, $ligne->payment_status);
        $this->assertEquals((float) $commande->total, (float) $ligne->total);
    }

    public function test_the_two_channels_add_up_to_one_days_takings(): void
    {
        // Ce que le marchand ne pouvait pas savoir sans additionner deux
        // écrans de tête.
        $this->patron();
        $this->vendreAuComptoir();
        $commande = $this->commanderAuQr();
        app(MenuOrderService::class)->markPaid($commande);

        $this->assertEquals(450.0, (float) Order::revenue()->sum('total'));
        $this->assertSame(2, Order::count());
    }

    /* ------------------------------------------------------------------
       Ce qui compte, et ce qui ne compte pas.
       ------------------------------------------------------------------ */

    public function test_an_unpaid_order_stays_out_of_the_takings(): void
    {
        // Une commande passée mais jamais réglée n'est pas de la recette.
        $this->patron();
        $this->commanderAuQr();

        $this->assertEquals(0.0, (float) Order::revenue()->sum('total'));
        $this->assertSame(1, Order::open()->count(), 'Elle reste à traiter.');
    }

    public function test_marking_paid_moves_it_into_the_takings(): void
    {
        $this->patron();
        $commande = $this->commanderAuQr();

        app(MenuOrderService::class)->markPaid($commande);

        $this->assertSame(OrderStatus::PAID,
            Order::where('source_type', 'menu_order')->firstOrFail()->payment_status);
        $this->assertEquals(350.0, (float) Order::revenue()->sum('total'));
    }

    public function test_the_module_stays_the_master_of_the_status(): void
    {
        // Quand le restaurant marque « prête », la colonne vertébrale suit —
        // sinon le rapport commun montrerait une commande déjà livrée comme
        // encore à préparer.
        $this->patron();
        $commande = $this->commanderAuQr();

        $this->post(route('tagtoa.menu.dashboard.orders.status', $commande->id), ['status' => 'ready'])
            ->assertRedirect();

        $this->assertSame(OrderStatus::READY,
            Order::where('source_type', 'menu_order')->firstOrFail()->status);
    }

    /* ------------------------------------------------------------------
       Le client : facultatif, et c'est normal.
       ------------------------------------------------------------------ */

    public function test_a_counter_sale_without_a_name_is_perfectly_normal(): void
    {
        // La majorité des ventes d'un commerce de quartier sont anonymes.
        // Exiger un nom pour encaisser rendrait la caisse inutilisable.
        $this->patron();
        $this->vendreAuComptoir();

        $ligne = Order::firstOrFail();

        $this->assertNull($ligne->customer_id);
        $this->assertSame('Client de passage', $ligne->who);
        $this->assertSame(0, Customer::count(), 'Aucune fiche vide ne doit être créée.');
    }

    public function test_a_phone_opens_a_record_a_name_alone_does_not(): void
    {
        // Le téléphone identifie un client dans la pratique haïtienne. Un nom
        // seul ne rapproche rien : trois clients peuvent s'appeler Jean.
        $this->patron();

        $this->commanderAuQr(['customer_name' => 'Jean']);
        $this->assertSame(0, Customer::count());

        $this->commanderAuQr(['customer_name' => 'Marie', 'customer_phone' => '3712-4455']);
        $this->assertSame(1, Customer::count());
        $this->assertSame('Marie', Customer::firstOrFail()->name);
    }

    public function test_the_same_customer_written_three_ways_is_one_record(): void
    {
        // « 3712-4455 », « +509 3712 4455 » et « 37124455 » sont le même
        // client. Sans normalisation il aurait trois fiches et aucun historique.
        $this->patron();

        $this->commanderAuQr(['customer_phone' => '3712-4455']);
        $this->commanderAuQr(['customer_phone' => '+509 3712 4455']);
        $this->vendreAuComptoir(['customer_phone' => '37124455']);

        $this->assertSame(1, Customer::count());
        $this->assertSame(3, Customer::firstOrFail()->orders()->count(),
            'Le marchand voit enfin qu\'un même client achète par trois chemins.');
    }

    public function test_a_stray_digit_never_creates_a_record(): void
    {
        $this->patron();
        $this->vendreAuComptoir(['customer_phone' => '37']);

        $this->assertSame(0, Customer::count());
    }

    public function test_a_name_given_later_completes_the_record(): void
    {
        $this->patron();
        $this->commanderAuQr(['customer_phone' => '3712-4455']);
        $this->commanderAuQr(['customer_name' => 'Marie', 'customer_phone' => '3712-4455']);

        $this->assertSame('Marie', Customer::firstOrFail()->name);
    }

    public function test_a_known_name_is_never_erased_by_a_blank(): void
    {
        $this->patron();
        $this->commanderAuQr(['customer_name' => 'Marie', 'customer_phone' => '3712-4455']);
        $this->vendreAuComptoir(['customer_phone' => '3712-4455']);

        $this->assertSame('Marie', Customer::firstOrFail()->name);
    }

    /* ------------------------------------------------------------------
       Ce qui ne doit jamais arriver.
       ------------------------------------------------------------------ */

    public function test_a_replayed_offline_sale_is_never_counted_twice(): void
    {
        // Une caisse hors ligne rejoue ses ventes au retour du réseau. La
        // recette ne doit pas doubler pour autant.
        $this->patron();
        $coca = app(PosCatalog::class)->save($this->caisse(),
            ['name' => 'Coca', 'price' => 100, 'is_active' => true]);

        $payload = ['items' => [['ref' => 'pos:'.$coca->id, 'qty' => 1]], 'client_uuid' => 'abc-123'];

        app(PosService::class)->recordSale($this->caisse(), $payload);
        app(PosService::class)->recordSale($this->caisse(), $payload);

        $this->assertSame(1, Order::count());
        $this->assertEquals(100.0, (float) Order::revenue()->sum('total'));
    }

    public function test_the_neighbour_orders_never_enter_my_takings(): void
    {
        $this->patron('t-2');
        $this->vendreAuComptoir([], 't-2');

        $this->patron('t-1');
        $this->vendreAuComptoir([], 't-1');

        $this->assertSame(1, Order::count(), 'La colonne vertébrale est cloisonnée par commerce.');
        $this->assertEquals(100.0, (float) Order::revenue()->sum('total'));
    }

    public function test_the_neighbour_customers_stay_out_of_my_book(): void
    {
        $this->patron('t-2');
        $this->vendreAuComptoir(['customer_phone' => '3712-4455'], 't-2');

        $this->patron('t-1');

        $this->assertSame(0, Customer::count());
    }

    public function test_a_till_without_a_business_still_sells(): void
    {
        // La colonne de rapport ne doit JAMAIS bloquer l'encaissement : le
        // client a payé, la vente doit exister.
        $caisse = Terminal::create(['tenant_id' => null, 'name' => 'Ancienne caisse', 'currency' => 'HTG']);
        $coca = app(PosCatalog::class)->save($caisse, ['name' => 'Coca', 'price' => 100, 'is_active' => true]);

        $vente = app(PosService::class)->recordSale($caisse, ['items' => [['ref' => 'pos:'.$coca->id, 'qty' => 1]]]);

        $this->assertEquals(100.0, (float) $vente->total);
    }
}
