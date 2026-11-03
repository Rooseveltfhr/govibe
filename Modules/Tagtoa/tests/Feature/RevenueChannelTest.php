<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA REVENU (R0-b) — pas de commission sur ce que TAGTOA ne peut pas vérifier
|--------------------------------------------------------------------------
| Avant : toute vente « payée » générait une commission, y compris les espèces et les paiements que le marchand
| confirmait lui-même à la main (rien de vérifiable à facturer — le marchand déclarait sa propre dette).
| Maintenant : seulement une passerelle en ligne CONFIRMÉE ou un débit de carte TAGTOA (notre grand livre). Un appelant
| qui ne déclare pas son canal ne génère AUCUNE commission, et un test interdit d'en ajouter un sans canal.
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Billing\Commission;
use Modules\Tagtoa\App\Models\Billing\RevenueSetting;
use Illuminate\Auth\GenericUser;
use Modules\Tagtoa\App\Models\Event\Event;
use Modules\Tagtoa\App\Models\Event\TicketType;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Models\Store\Store;
use Modules\Tagtoa\App\Services\Billing\RevenueService as Rev;
use Modules\Tagtoa\App\Services\Card\CardWalletService;
use Modules\Tagtoa\App\Services\Event\TicketService;
use Modules\Tagtoa\App\Services\Menu\MenuOrderService;
use Modules\Tagtoa\App\Services\Pos\PosService;
use Modules\Tagtoa\App\Support\Tenant;
use Modules\Tagtoa\App\Services\Store\StoreOrderService;
use Modules\Tagtoa\App\Models\Pay\PayTransaction;
use Modules\Tagtoa\App\Models\Pay\PaymentMethod;
use Modules\Tagtoa\App\Models\Pay\PaymentPage;
use Modules\Tagtoa\App\Services\Pay\CheckoutService;
use Modules\Tagtoa\App\Support\Gateways\GatewayDriver;
use Modules\Tagtoa\Tests\TestCase;

class RevenueChannelPaidGateway implements GatewayDriver
{
    public function createPayment(PayTransaction $txn): ?string { return null; }

    public function verify(PayTransaction $txn): string { return 'paid'; }
}

class RevenueChannelCheckout extends CheckoutService
{
    protected function driver(string $gateway): ?GatewayDriver
    {
        return new RevenueChannelPaidGateway();
    }
}

class RevenueChannelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RevenueSetting::create(['tenant_id' => null, 'revenue_model' => RevenueSetting::MODEL_COMMISSION,
            'commission_percent' => 5, 'commission_fixed' => 0, 'currency' => 'HTG', 'is_active' => true]);
        // Dans une requête connectée, l'isolation par commerce masque le paramètre global : il faut aussi celui du commerce,
        // sinon un test de « pas de commission » réussirait pour la mauvaise raison (aucun paramètre trouvé).
        RevenueSetting::allTenants()->create(['tenant_id' => 't-1', 'revenue_model' => RevenueSetting::MODEL_COMMISSION,
            'commission_percent' => 5, 'commission_fixed' => 0, 'currency' => 'HTG', 'is_active' => true]);
    }

    private function enregistrer(string $canal, int $id = 1)
    {
        return app(Rev::class)->record('menu_order', $id, 'menu', 1000, 't-1', 'HTG', $canal);
    }

    /* ---------- le service ---------- */

    public function test_only_a_verified_gateway_or_a_card_ledger_debit_is_commissioned(): void
    {
        $this->assertNotNull($this->enregistrer(Rev::CHANNEL_ONLINE_VERIFIED, 1));
        $this->assertNotNull($this->enregistrer(Rev::CHANNEL_LEDGER, 2));
        foreach ([Rev::CHANNEL_CASH, Rev::CHANNEL_MANUAL, Rev::CHANNEL_UNKNOWN, 'inconnu-xyz', ''] as $i => $canal) {
            $this->assertNull($this->enregistrer($canal, 10 + $i), "« $canal » ne doit rien facturer.");
        }
        $this->assertSame(2, Commission::allTenants()->count());
        $this->assertSame('50.00', Commission::allTenants()->where('source_id', 1)->value('commission_amount'));
    }

    public function test_a_caller_that_forgets_its_channel_charges_nothing(): void
    {
        $this->assertNull(app(Rev::class)->record('menu_order', 5, 'menu', 1000, 't-1', 'HTG'));
        $this->assertSame(0, Commission::allTenants()->count());
    }

    public function test_a_verified_sale_is_still_commissioned_once_even_if_recorded_twice(): void
    {
        $this->enregistrer(Rev::CHANNEL_ONLINE_VERIFIED, 1);
        $this->enregistrer(Rev::CHANNEL_ONLINE_VERIFIED, 1);
        $this->enregistrer(Rev::CHANNEL_CASH, 1);   // un second canal sur la même source ne crée ni n'annule rien

        $this->assertSame(1, Commission::allTenants()->count());
    }

    public function test_the_percentage_rules_are_unchanged_for_a_verified_sale(): void
    {
        RevenueSetting::query()->update(['commission_percent' => 2.5, 'commission_fixed' => 10]);

        $c = $this->enregistrer(Rev::CHANNEL_ONLINE_VERIFIED, 1);

        $this->assertSame('35.00', (string) $c->commission_amount);   // 1000 × 2,5 % + 10
        $this->assertSame('965.00', (string) $c->net_amount);
    }

    /* ---------- Menu ---------- */

    private function commandeMenu()
    {
        $menu = Menu::create(['vcard_id' => 77, 'tenant_id' => 't1', 'name' => 'Lounge', 'alias' => 'lounge-'.uniqid(), 'currency' => 'HTG', 'is_active' => true, 'ordering_enabled' => true]);
        $cat = Category::create(['menu_id' => $menu->id, 'name' => 'Boissons', 'sort' => 0, 'is_active' => true]);
        $item = Item::create(['menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Jus', 'price' => 200, 'is_available' => true]);

        return app(MenuOrderService::class)->placeOrder($menu, ['items' => [['id' => $item->id, 'qty' => 3]], 'tip' => 30, 'customer_phone' => '+509 3456 7890']);
    }

    public function test_a_menu_order_cashed_by_hand_pays_no_commission_whatever_the_method(): void
    {
        foreach (['cash', 'moncash', 'natcash', 'card', 'transfer', 'other'] as $methode) {
            $order = $this->commandeMenu();
            app(MenuOrderService::class)->markPaid($order->fresh(), $methode, 'Rose');
            $this->assertSame('paid', $order->fresh()->payment_status, $methode);
        }
        $this->assertSame(0, Commission::allTenants()->where('source_type', 'menu_order')->count());
    }

    public function test_a_menu_order_settled_by_a_confirmed_gateway_pays_commission_on_the_subtotal_only(): void
    {
        $order = $this->commandeMenu();

        app(MenuOrderService::class)->markPaid($order->fresh(), 'moncash', null, true);

        $c = Commission::allTenants()->where('source_type', 'menu_order')->sole();
        $this->assertSame('600.00', (string) $c->gross_amount, 'Hors pourboire.');
        $this->assertSame('30.00', (string) $c->commission_amount);
    }

    /* ---------- Boutique ---------- */

    private function commandeBoutique()
    {
        $s = Store::allTenants()->create(['tenant_id' => 't-1', 'name' => 'Boutik', 'alias' => 'boutik-'.uniqid(), 'currency' => 'HTG', 'is_published' => true]);
        $p = $s->products()->create(['name' => 'Radio', 'price' => 400, 'currency' => 'HTG', 'is_available' => true]);

        return app(StoreOrderService::class)->placeOrder($s, ['items' => [['id' => $p->id, 'qty' => 1]], 'customer_name' => 'Jean', 'customer_phone' => '34567890']);
    }

    public function test_a_store_order_marked_paid_by_hand_pays_no_commission_but_a_gateway_one_does(): void
    {
        $manuelle = $this->commandeBoutique();
        app(StoreOrderService::class)->markPaid($manuelle);
        $this->assertSame(0, Commission::allTenants()->where('source_type', 'store_order')->count());

        $enLigne = $this->commandeBoutique();
        app(StoreOrderService::class)->markPaid($enLigne, true);
        $this->assertSame(1, Commission::allTenants()->where('source_type', 'store_order')->count());
    }

    /* ---------- passerelle confirmée : la commission reste due ---------- */

    private function reglerParPasserelle(string $type, int $id, float $montant, string $tenant, array $meta = []): void
    {
        $txn = PayTransaction::create(['tenant_id' => $tenant, 'gateway' => 'moncash', 'reference' => PayTransaction::generateReference(),
            'order_type' => $type, 'order_id' => $id, 'amount' => $montant, 'currency' => 'HTG', 'status' => PayTransaction::STATUS_PENDING, 'meta' => $meta ?: null]);

        $this->assertTrue((new RevenueChannelCheckout())->confirmOrFail($txn->reference));
    }

    public function test_a_confirmed_gateway_payment_still_commissions_menu_store_and_pay_page_sales(): void
    {
        $menu = $this->commandeMenu();
        $this->reglerParPasserelle('menu', $menu->id, (float) $menu->total, 't1');
        $this->assertSame(1, Commission::allTenants()->where('source_type', 'menu_order')->count(), 'Menu en ligne.');

        $boutique = $this->commandeBoutique();
        $this->reglerParPasserelle('store', $boutique->id, (float) $boutique->total, 't-1');
        $this->assertSame(1, Commission::allTenants()->where('source_type', 'store_order')->count(), 'Boutique en ligne.');

        $page = PaymentPage::create(['tenant_id' => 't-1', 'alias' => 'page-'.uniqid(), 'title' => 'Don', 'default_currency' => 'HTG', 'is_active' => true]);
        $methode = PaymentMethod::create(['payment_page_id' => $page->id, 'type' => 'moncash', 'account_number' => '+509 3712 4408', 'is_active' => true]);
        $this->reglerParPasserelle('pay_page', $page->id, 250, 't-1', ['method_id' => $methode->id]);
        $this->assertSame(1, Commission::allTenants()->where('source_type', 'pay_page')->count(), 'Page de paiement en ligne.');
    }

    /* ---------- carte TAGTOA (grand livre), billetterie, caisse ---------- */

    private function commandeEvenement(): \Modules\Tagtoa\App\Models\Event\Order
    {
        $e = Event::create(['tenant_id' => 't-1', 'title' => 'Gala', 'alias' => 'gala-'.uniqid(), 'currency' => 'HTG', 'is_published' => true,
            'starts_at' => now()->subHour()->toDateTimeString(), 'ends_at' => now()->addDay()->toDateTimeString()]);
        $type = TicketType::create(['event_id' => $e->id, 'name' => 'Standard', 'price' => 500, 'currency' => 'HTG', 'is_active' => true, 'quota' => 100]);

        return app(TicketService::class)->createOrder($e, [['ticket_type_id' => $type->id, 'qty' => 1]], ['name' => 'Marie', 'phone' => '+50937000000']);
    }

    private function carte(float $solde = 2000)
    {
        $svc = app(CardWalletService::class);
        $card = $svc->issue('UID-'.uniqid(), ['currency' => 'HTG', 'pin' => '4321', 'code' => 'TG-'.strtoupper(substr(uniqid(), -6))]);
        $svc->topUp($card, $solde, ['reference' => 'seed-'.uniqid()]);

        return $card->fresh();
    }

    public function test_a_card_debit_is_commissioned_because_it_is_written_in_our_own_ledger(): void
    {
        $card = $this->carte();

        $res = app(CardWalletService::class)->charge($card, 400, '4321', ['tenant_id' => 't-1', 'module' => 'pay', 'reference' => 'r0-'.uniqid()]);

        $this->assertTrue($res['ok']);
        $c = Commission::allTenants()->where('source_type', 'card_charge')->sole();
        $this->assertSame('20.00', (string) $c->commission_amount);
    }

    public function test_an_event_order_paid_with_a_card_is_commissioned_once_and_a_manual_confirmation_is_not(): void
    {
        $carte = $this->carte();
        $payee = $this->commandeEvenement();
        $this->post(route('tagtoa.event.order.card', $payee->reference), ['card_code' => $carte->code, 'pin' => '4321']);
        $this->assertTrue($payee->fresh()->isPaid());
        $this->assertSame(1, Commission::allTenants()->where('source_type', 'event_order')->count(), 'Carte : grand livre TAGTOA.');

        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-1', 'name' => 'Rose']));
        Tenant::flush();
        $manuelle = $this->commandeEvenement();
        $this->post(route('tagtoa.event.dashboard.orders.paid', [$manuelle->event_id, $manuelle->id]));
        $this->assertTrue($manuelle->fresh()->isPaid());
        $this->assertSame(1, Commission::allTenants()->where('source_type', 'event_order')->count(), 'Confirmée à la main : aucune commission en plus.');
    }

    public function test_a_till_sale_pays_no_commission(): void
    {
        $t = Terminal::create(['tenant_id' => 't-1', 'name' => 'Caisse', 'currency' => 'HTG', 'is_active' => true]);

        $sale = app(PosService::class)->recordSale($t, ['client_uuid' => 'r0-1', 'items' => [['name' => 'Article', 'price' => 300, 'qty' => 1]]]);

        $this->assertNotNull($sale->id);
        $this->assertSame(0, Commission::allTenants()->where('source_type', 'pos_sale')->count());
    }

    /* ---------- garde-fou : tout appelant déclare son canal ---------- */

    public function test_every_commission_call_site_declares_its_channel(): void
    {
        $racine = dirname(__DIR__, 2).'/app';
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($racine, \FilesystemIterator::SKIP_DOTS));
        $appels = 0;
        $sansCanal = [];
        foreach ($it as $f) {
            if ($f->getExtension() !== 'php' || str_ends_with($f->getPathname(), 'Billing/RevenueService.php')) {
                continue;
            }
            $src = file_get_contents($f->getPathname());
            if (! preg_match_all('/(?:revenue|RevenueService::class\))\s*->record\(([^;]*);/s', $src, $m)) {
                continue;
            }
            foreach ($m[1] as $instruction) {
                $appels++;
                if (! str_contains($instruction, 'CHANNEL_')) {
                    $sansCanal[] = str_replace($racine.'/', '', $f->getPathname()).' : '.substr(trim($instruction), 0, 60);
                }
            }
        }

        $this->assertGreaterThanOrEqual(10, $appels, 'Le garde-fou doit voir les appels existants (sinon il ne garde rien).');
        $this->assertSame([], $sansCanal, 'Tout appel à RevenueService::record() doit déclarer son canal (CHANNEL_*).');
    }
}
