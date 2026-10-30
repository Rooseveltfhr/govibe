<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA PAY — LA page « Payer » d'une commande (menu, boutique)
|--------------------------------------------------------------------------
| Demandé par le fondateur : « Payer » doit être AUTOMATIQUE dès qu'une
| passerelle est configurée dans l'admin (clés sandbox ou production), et le
| paiement MANUEL est la liste des moyens que le marchand a lui-même renseignés.
|
| Une passerelle n'est offerte que si ses identifiants sont remplis, si le
| fondateur ne l'a pas désactivée, et si elle traite la DEVISE de la commande.
| « J'ai payé » ne paie rien : c'est un message au marchand, qui vérifie.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Menu\Order;
use Modules\Tagtoa\App\Models\Pay\GatewaySetting;
use Modules\Tagtoa\App\Models\Pay\PaymentMethod;
use Modules\Tagtoa\App\Services\Menu\MenuOrderService;
use Modules\Tagtoa\App\Services\Pay\MerchantMethods;
use Modules\Tagtoa\App\Support\GatewayManager;
use Modules\Tagtoa\Tests\TestCase;

class PayOrderPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        GatewayManager::flush();
    }

    private function menuOrder(string $currency = 'HTG', string $tenant = 't-1'): Order
    {
        $menu = Menu::create(['tenant_id' => $tenant, 'name' => 'Chez Rose', 'alias' => 'chez-rose-'.$tenant,
            'currency' => $currency, 'is_active' => true, 'ordering_enabled' => true]);
        $cat = Category::create(['menu_id' => $menu->id, 'name' => 'Plats', 'is_active' => true]);
        $item = Item::create(['menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Griot', 'price' => 350, 'is_available' => true]);

        return app(MenuOrderService::class)->placeOrder($menu, ['items' => [['id' => $item->id, 'qty' => 1]]]);
    }

    private function method(string $tenant = 't-1', array $attrs = []): PaymentMethod
    {
        return PaymentMethod::create(array_merge([
            'payment_page_id' => app(MerchantMethods::class)->library($tenant)->id, 'tenant_id' => $tenant,
            'type' => 'moncash', 'label' => 'MonCash de Rose', 'account_holder' => 'Rose Pierre',
            'account_number' => '3700 1111', 'instructions' => 'Envoyez puis déclarez.', 'is_active' => true,
        ], $attrs));
    }

    private function enableGateways(): void
    {
        config([
            'tagtoa.gateways.moncash.credentials' => ['client_id' => 'cid', 'secret' => 'sec'],
            'tagtoa.gateways.stripe.credentials'  => ['key' => 'pk_test_x', 'secret' => 'sk_test_x'],
        ]);
        GatewayManager::flush();
    }

    private function page(Order $o)
    {
        return $this->get(route('tagtoa.pay.order', ['menu', $o->reference]));
    }

    /* ---------- AUTOMATIQUE : selon les passerelles, la devise, l'admin ---------- */

    public function test_a_configured_gateway_that_handles_the_currency_is_offered(): void
    {
        $this->enableGateways();
        $o = $this->menuOrder('HTG');

        $html = $this->page($o)->assertOk()->getContent();

        $this->assertStringContainsString('Payer avec MonCash', $html);
        $this->assertStringContainsString(route('tagtoa.pay.online.start', ['gateway' => 'moncash', 'type' => 'menu', 'orderId' => $o->id]), $html);
        $this->assertStringNotContainsString('Payer avec Carte bancaire', $html, 'Stripe ne traite pas les gourdes.');
    }

    public function test_a_gateway_that_does_not_handle_the_currency_is_never_offered(): void
    {
        $this->enableGateways();
        // Le moyen « carte » est servi par PayPal ou Stripe au choix du fondateur.
        GatewaySetting::create(['gateway' => 'card', 'is_enabled' => true, 'driver' => 'stripe']);
        $o = $this->menuOrder('USD');

        $html = $this->page($o)->assertOk()->getContent();

        $this->assertStringContainsString('Payer avec Carte bancaire', $html);
        $this->assertStringNotContainsString('Payer avec MonCash', $html, 'MonCash ne traite que la gourde.');
    }

    public function test_a_gateway_without_credentials_is_not_offered(): void
    {
        config(['tagtoa.gateways.moncash.credentials' => ['client_id' => 'cid', 'secret' => null]]);
        GatewayManager::flush();

        $this->page($this->menuOrder('HTG'))->assertOk()->assertDontSee('Payer avec MonCash');
    }

    public function test_a_gateway_the_founder_disabled_is_not_offered(): void
    {
        $this->enableGateways();
        GatewaySetting::create(['gateway' => 'moncash', 'is_enabled' => false]);

        $this->page($this->menuOrder('HTG'))->assertOk()->assertDontSee('Payer avec MonCash');
    }

    /* ---------- MANUEL : ce que le marchand a renseigné ---------- */

    public function test_the_merchants_own_methods_are_listed_with_their_details(): void
    {
        $this->method();
        $o = $this->menuOrder();

        $this->page($o)->assertOk()
            ->assertSee('MonCash de Rose')->assertSee('Rose Pierre')->assertSee('3700 1111')
            ->assertSee('Envoyez puis déclarez.');
    }

    public function test_another_merchants_methods_are_never_shown(): void
    {
        $this->method('t-2', ['label' => 'Compte secret', 'account_number' => '9999 9999']);
        $o = $this->menuOrder('HTG', 't-1');

        $this->page($o)->assertOk()->assertDontSee('Compte secret')->assertDontSee('9999 9999');
    }

    public function test_with_nothing_to_offer_the_page_says_to_pay_at_the_shop_and_the_order_has_no_pay_link(): void
    {
        $o = $this->menuOrder();

        $this->page($o)->assertOk()->assertSee('Payez directement auprès du commerce');
        $this->assertNull(\Modules\Tagtoa\App\Http\Controllers\Pay\OrderPayController::urlFor('menu', $o));
    }

    /* ---------- « J'ai payé » : un message, jamais un paiement ---------- */

    public function test_the_claim_is_recorded_for_the_merchant_but_pays_nothing(): void
    {
        $m = $this->method();
        $o = $this->menuOrder();

        $this->post(route('tagtoa.pay.order.claim', ['menu', $o->reference]), [
            'payment_method_id' => $m->id, 'payer_name' => 'Jean', 'payer_phone' => '3456 7890', 'reference' => 'MC12345',
        ])->assertRedirect(route('tagtoa.pay.order', ['menu', $o->reference]));

        $o = $o->fresh();
        $this->assertFalse($o->isPaid(), 'Une déclaration ne marque jamais la commande payée.');
        $this->assertSame('MC12345', $o->payment_claim['reference']);
        $this->assertSame('MonCash de Rose', $o->payment_claim['method']);
        $this->assertNotNull($o->payment_claimed_at);
    }

    public function test_a_claim_cannot_name_another_merchants_method(): void
    {
        $other = $this->method('t-2');
        $o = $this->menuOrder('HTG', 't-1');

        $this->post(route('tagtoa.pay.order.claim', ['menu', $o->reference]), ['payment_method_id' => $other->id])
            ->assertSessionHasErrors('payment_method_id');
        $this->assertNull($o->fresh()->payment_claim);
    }

    public function test_a_claim_with_an_absurd_phone_is_refused(): void
    {
        $m = $this->method();
        $o = $this->menuOrder();

        $this->post(route('tagtoa.pay.order.claim', ['menu', $o->reference]), ['payment_method_id' => $m->id, 'payer_phone' => '<script>'])
            ->assertSessionHasErrors('payer_phone');
    }

    public function test_a_paid_order_shows_paid_and_accepts_no_claim(): void
    {
        $m = $this->method();
        $o = $this->menuOrder();
        app(MenuOrderService::class)->markPaid($o, 'cash', 'Rose');

        $this->page($o)->assertOk()->assertSee('Cette commande est payée')->assertDontSee('3700 1111');
        $this->post(route('tagtoa.pay.order.claim', ['menu', $o->reference]), ['payment_method_id' => $m->id]);
        $this->assertNull($o->fresh()->payment_claim);
    }

    /* ---------- côté marchand ---------- */

    public function test_the_merchant_sees_the_claim_escaped_in_the_orders_list(): void
    {
        $m = $this->method();
        $o = $this->menuOrder();
        $this->post(route('tagtoa.pay.order.claim', ['menu', $o->reference]), [
            'payment_method_id' => $m->id, 'payer_name' => '<b>Jean</b>', 'reference' => 'MC12345',
        ]);
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-1', 'name' => 'Rose']));

        $html = $this->get(route('tagtoa.menu.dashboard.orders', $o->menu_id))->assertOk()->getContent();

        $this->assertStringContainsString('Le client dit avoir payé', $html);
        $this->assertStringContainsString('MC12345', $html);
        $this->assertStringNotContainsString('<b>Jean</b>', $html);
    }

    /* ---------- routes et liens ---------- */

    public function test_unknown_type_or_reference_is_a_404(): void
    {
        $this->get(route('tagtoa.pay.order', ['menu', 'CMD-NOPE0000']))->assertNotFound();
        $this->get(route('tagtoa.pay.order', ['evenement', 'CMD-NOPE0000']))->assertNotFound();
    }

    public function test_the_tracking_page_offers_pay_now_only_when_there_is_something_to_pay_with(): void
    {
        $o = $this->menuOrder();
        $this->get(route('tagtoa.menu.track', $o->reference))->assertOk()->assertDontSee('Payer maintenant');

        $this->method();
        $this->get(route('tagtoa.menu.track', $o->reference))->assertOk()
            ->assertSee('Payer maintenant')->assertSee(route('tagtoa.pay.order', ['menu', $o->reference]), false);
    }

    public function test_the_order_response_carries_the_unified_pay_page_and_no_gateway_shortcut(): void
    {
        $this->enableGateways();
        $this->method();
        $o = $this->menuOrder();
        $menu = Menu::find($o->menu_id);
        $item = Item::where('menu_id', $menu->id)->first();

        $res = $this->postJson(route('tagtoa.menu.order', $menu->alias), ['items' => [['id' => $item->id, 'qty' => 1]]])->assertOk();

        $this->assertStringContainsString('/pay/order/menu/', $res->json('pay_url'));
        $this->assertNull($res->json('checkout_url'), 'MonCash n\'est plus imposé : le client choisit sur la page Payer.');
    }

    public function test_a_store_order_gets_the_same_page_and_claim_flow(): void
    {
        $m = $this->method();
        $store = \Modules\Tagtoa\App\Models\Store\Store::create([
            'tenant_id' => 't-1', 'name' => 'Boutik Rose', 'alias' => 'boutik-rose', 'currency' => 'HTG', 'is_published' => true,
        ]);
        $o = \Modules\Tagtoa\App\Models\Store\Order::create([
            'store_id' => $store->id, 'tenant_id' => 't-1', 'reference' => \Modules\Tagtoa\App\Models\Store\Order::generateReference(),
            'subtotal' => 500, 'total' => 500, 'currency' => 'HTG', 'status' => 'pending', 'payment_status' => 'pending',
            'customer_name' => 'Jean', 'placed_at' => now(),
        ]);

        $this->get(route('tagtoa.pay.order', ['store', $o->reference]))->assertOk()
            ->assertSee('Boutik Rose')->assertSee('MonCash de Rose')->assertSee(route('tagtoa.store.show', 'boutik-rose'), false);

        $this->post(route('tagtoa.pay.order.claim', ['store', $o->reference]), ['payment_method_id' => $m->id, 'reference' => 'MC777']);
        $o = $o->fresh();
        $this->assertFalse($o->isPaid());
        $this->assertSame('MC777', $o->payment_claim['reference']);
    }
}
