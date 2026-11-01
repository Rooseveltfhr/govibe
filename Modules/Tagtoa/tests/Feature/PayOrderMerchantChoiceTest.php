<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA PAY (PAY-1) — la page « Payer » respecte les choix du marchand
|--------------------------------------------------------------------------
| Avant : (1) l'interrupteur du marchand ne commandait pas les boutons automatiques (MonCash
| décoché restait proposé) ; (2) un moyen « automatique » coché apparaissait comme une carte
| sans coordonnées avec « J'ai payé » : un client pouvait déclarer un paiement MonCash qui
| n'avait jamais eu lieu ; (3) un numéro « (509) 3456-7890 » était refusé sans explication.
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Menu\Category;
use Modules\Tagtoa\App\Models\Menu\Item;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Menu\Order;
use Modules\Tagtoa\App\Models\Pay\PayTransaction;
use Modules\Tagtoa\App\Models\Pay\PaymentMethod;
use Modules\Tagtoa\App\Services\Menu\MenuOrderService;
use Modules\Tagtoa\App\Services\Pay\CheckoutService;
use Modules\Tagtoa\App\Services\Pay\MerchantMethods;
use Modules\Tagtoa\App\Support\GatewayManager;
use Modules\Tagtoa\Tests\TestCase;

class PayOrderMerchantChoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['tagtoa.gateways.moncash.credentials' => ['client_id' => 'cid', 'secret' => 'sec']]);
        GatewayManager::flush();
    }

    private function menuOrder(string $tenant = 't-1'): Order
    {
        $menu = Menu::create(['tenant_id' => $tenant, 'name' => 'Chez Rose', 'alias' => 'chez-rose-'.$tenant,
            'currency' => 'HTG', 'is_active' => true, 'ordering_enabled' => true]);
        $cat = Category::create(['menu_id' => $menu->id, 'name' => 'Plats', 'is_active' => true]);
        $item = Item::create(['menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Griot', 'price' => 350, 'is_available' => true]);

        return app(MenuOrderService::class)->placeOrder($menu, ['items' => [['id' => $item->id, 'qty' => 1]]]);
    }

    private function method(string $type, bool $active = true, string $tenant = 't-1', array $attrs = []): PaymentMethod
    {
        return PaymentMethod::create(array_merge([
            'payment_page_id' => app(MerchantMethods::class)->library($tenant)->id, 'tenant_id' => $tenant,
            'type' => $type, 'label' => $type.' de Rose', 'is_active' => $active,
        ], $attrs));
    }

    private function page(Order $o)
    {
        return $this->get(route('tagtoa.pay.order', ['menu', $o->reference]));
    }

    private function startUrl(Order $o): string
    {
        return route('tagtoa.pay.online.start', ['gateway' => 'moncash', 'type' => 'menu', 'orderId' => $o->id]);
    }

    /* ---------- l'interrupteur du marchand commande les boutons automatiques ---------- */

    public function test_a_merchant_who_never_configured_it_still_offers_the_founders_gateway(): void
    {
        $this->page($this->menuOrder())->assertOk()->assertSee('Payer avec MonCash');
    }

    public function test_a_merchant_who_unticked_moncash_does_not_offer_it(): void
    {
        $this->method('moncash', false);
        $o = $this->menuOrder();

        $this->page($o)->assertOk()->assertDontSee('Payer avec MonCash');
        $this->assertNull(\Modules\Tagtoa\App\Http\Controllers\Pay\OrderPayController::urlFor('menu', $o), 'Rien à proposer : pas de lien « Payer ».');
    }

    public function test_a_merchant_who_ticked_moncash_offers_it(): void
    {
        $this->method('moncash', true);

        $this->page($this->menuOrder())->assertOk()->assertSee('Payer avec MonCash');
    }

    public function test_an_unticked_gateway_cannot_be_started_by_typing_the_address(): void
    {
        $this->method('moncash', false);
        $o = $this->menuOrder();

        $this->get($this->startUrl($o))->assertRedirect(route('tagtoa.pay.result'));
        $this->assertSame(0, PayTransaction::count(), 'Aucune transaction créée.');
    }

    public function test_another_merchants_switch_does_not_affect_mine(): void
    {
        $this->method('moncash', false, 't-2');

        $this->page($this->menuOrder('t-1'))->assertOk()->assertSee('Payer avec MonCash');
    }

    /* ---------- un moyen automatique n'est pas une carte « J'ai payé » ---------- */

    public function test_a_working_automatic_method_is_not_shown_as_a_declarable_card(): void
    {
        $m = $this->method('moncash', true);
        $o = $this->menuOrder();

        $this->page($o)->assertOk()->assertSee('Payer avec MonCash')->assertDontSee('Payer par un autre moyen')->assertDontSee('moncash de Rose');

        $this->post(route('tagtoa.pay.order.claim', ['menu', $o->reference]), ['payment_method_id' => $m->id])
            ->assertSessionHasErrors('payment_method_id');
        $this->assertNull($o->fresh()->payment_claim, 'On ne déclare pas un paiement MonCash : on paie par la passerelle.');
    }

    public function test_an_automatic_method_the_gateway_cannot_serve_stays_declarable_with_proof(): void
    {
        config(['tagtoa.gateways.moncash.credentials' => ['client_id' => null, 'secret' => null]]);
        GatewayManager::flush();
        $m = $this->method('moncash', true, 't-1', ['account_number' => '3700 1111']);
        $o = $this->menuOrder();

        $this->page($o)->assertOk()->assertSee('3700 1111')->assertDontSee('Payer avec MonCash');
        $this->post(route('tagtoa.pay.order.claim', ['menu', $o->reference]), ['payment_method_id' => $m->id, 'reference' => 'MC1'])
            ->assertSessionHasNoErrors();
        $this->assertFalse($o->fresh()->isPaid(), 'Une déclaration ne paie rien.');
    }

    public function test_a_manual_method_is_still_declarable(): void
    {
        $m = $this->method('natcash', true, 't-1', ['account_number' => '4400 2222']);
        $o = $this->menuOrder();

        $this->page($o)->assertOk()->assertSee('4400 2222');
        $this->post(route('tagtoa.pay.order.claim', ['menu', $o->reference]), ['payment_method_id' => $m->id])->assertSessionHasNoErrors();
        $this->assertNotNull($o->fresh()->payment_claimed_at);
    }

    /* ---------- le téléphone et les erreurs ---------- */

    /** @dataProvider phones */
    public function test_a_phone_typed_in_a_natural_way_is_accepted_and_stored_clean(string $typed, string $stored): void
    {
        $m = $this->method('natcash', true, 't-1', ['account_number' => '4400 2222']);
        $o = $this->menuOrder();

        $this->post(route('tagtoa.pay.order.claim', ['menu', $o->reference]), ['payment_method_id' => $m->id, 'payer_phone' => $typed])
            ->assertSessionHasNoErrors();
        $this->assertSame($stored, $o->fresh()->payment_claim['payer_phone']);
    }

    public static function phones(): array
    {
        return [
            'parenthèses' => ['(509) 3456-7890', '50934567890'],
            'plus et points' => ['+509 3456.7890', '+50934567890'],
            'simple' => ['3456 7890', '34567890'],
        ];
    }

    /** @dataProvider badPhones */
    public function test_a_bad_phone_is_refused_with_a_visible_message(string $typed): void
    {
        $m = $this->method('natcash', true, 't-1', ['account_number' => '4400 2222']);
        $o = $this->menuOrder();

        $this->post(route('tagtoa.pay.order.claim', ['menu', $o->reference]), ['payment_method_id' => $m->id, 'payer_phone' => $typed])
            ->assertSessionHasErrors('payer_phone');
        $this->assertNull($o->fresh()->payment_claim);

        $html = $this->followingRedirects()->from(route('tagtoa.pay.order', ['menu', $o->reference]))
            ->post(route('tagtoa.pay.order.claim', ['menu', $o->reference]), ['payment_method_id' => $m->id, 'payer_phone' => $typed])->getContent();
        $this->assertStringContainsString('role="alert"', $html, 'Le message d\'erreur est affiché en haut du formulaire.');
    }

    public static function badPhones(): array
    {
        return [['<script>'], ['abc'], ['12'], [str_repeat('9', 25)]];
    }

    public function test_the_claim_route_allows_a_shared_mobile_network(): void
    {
        $m = $this->method('natcash', true, 't-1', ['account_number' => '4400 2222']);
        $o = $this->menuOrder();

        for ($i = 0; $i < 12; $i++) {
            $this->post(route('tagtoa.pay.order.claim', ['menu', $o->reference]), ['payment_method_id' => $m->id])->assertStatus(302);
        }
    }
}
