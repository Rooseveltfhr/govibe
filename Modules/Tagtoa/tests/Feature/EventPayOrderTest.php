<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA PAY × EVENT — la commande de billetterie paie comme le menu et la boutique
|--------------------------------------------------------------------------
| Avant : le bouton « Payer » de la page de commande menait à la page de
| paiement générique (une preuve), qui ne marquait JAMAIS la commande payée. Et
| la table des commandes Event n'a pas de colonne tenant_id : la transaction de
| paiement, la commission et les moyens du marchand étaient cherchés pour
| « aucun commerce ».
|
| Maintenant : la page « Payer » unifiée (passerelles automatiques + moyens
| manuels de l'organisateur), « J'ai payé » qui ne paie rien, et un règlement
| passerelle qui marque la commande payée sous le BON commerce.
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Billing\Commission;
use Modules\Tagtoa\App\Models\Billing\RevenueSetting;
use Modules\Tagtoa\App\Models\Event\Event;
use Modules\Tagtoa\App\Models\Event\Order;
use Modules\Tagtoa\App\Models\Event\Ticket;
use Modules\Tagtoa\App\Models\Event\TicketType;
use Modules\Tagtoa\App\Models\Pay\PayTransaction;
use Modules\Tagtoa\App\Models\Pay\PaymentMethod;
use Modules\Tagtoa\App\Services\Event\CheckinService;
use Modules\Tagtoa\App\Services\Event\TicketService;
use Modules\Tagtoa\App\Services\Pay\CheckoutService;
use Modules\Tagtoa\App\Services\Pay\MerchantMethods;
use Modules\Tagtoa\App\Support\GatewayManager;
use Modules\Tagtoa\App\Support\Gateways\GatewayDriver;
use Modules\Tagtoa\Tests\TestCase;

class EventPaidGateway implements GatewayDriver
{
    public function createPayment(PayTransaction $txn): ?string { return null; }

    public function verify(PayTransaction $txn): string { return 'paid'; }
}

class EventCheckout extends CheckoutService
{
    protected function driver(string $gateway): ?GatewayDriver
    {
        return new EventPaidGateway();
    }
}

class EventPayOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        GatewayManager::flush();
    }

    private function paidEvent(string $tenant = 't-1'): Event
    {
        return Event::create([
            'tenant_id' => $tenant, 'title' => 'Gala Rose', 'alias' => 'gala-'.$tenant, 'currency' => 'HTG', 'is_published' => true,
            'starts_at' => now()->subHour()->toDateTimeString(), 'ends_at' => now()->addDay()->toDateTimeString(),
        ]);
    }

    private function order(Event $e): Order
    {
        $type = TicketType::create(['event_id' => $e->id, 'name' => 'Standard', 'price' => 500, 'currency' => 'HTG', 'is_active' => true, 'quota' => 100]);

        return app(TicketService::class)->createOrder($e, [['ticket_type_id' => $type->id, 'qty' => 1]], ['name' => 'Marie', 'phone' => '+50937000000']);
    }

    private function method(string $tenant): PaymentMethod
    {
        return PaymentMethod::create([
            'payment_page_id' => app(MerchantMethods::class)->library($tenant)->id, 'tenant_id' => $tenant,
            'type' => 'moncash', 'label' => 'MonCash Gala', 'account_holder' => 'Rose', 'account_number' => '3700 1111',
            'instructions' => 'Envoyez puis déclarez.', 'is_active' => true,
        ]);
    }

    private function enableMonCash(): void
    {
        config(['tagtoa.gateways.moncash.credentials' => ['client_id' => 'cid', 'secret' => 'sec']]);
        GatewayManager::flush();
    }

    public function test_an_event_order_belongs_to_the_tenant_of_its_event(): void
    {
        $o = $this->order($this->paidEvent('t-7'));

        $this->assertSame('t-7', $o->tenant_id);
    }

    public function test_the_unified_pay_page_serves_an_event_order_with_the_organisers_methods(): void
    {
        $o = $this->order($this->paidEvent());
        $this->method('t-1');

        $this->get(route('tagtoa.pay.order', ['event', $o->reference]))
            ->assertOk()->assertSee('Gala Rose')->assertSee('MonCash Gala');
    }

    public function test_the_pay_page_never_shows_another_organisers_methods(): void
    {
        $o = $this->order($this->paidEvent('t-1'));
        $this->method('t-2');

        $this->get(route('tagtoa.pay.order', ['event', $o->reference]))
            ->assertOk()->assertDontSee('MonCash Gala');
    }

    public function test_a_configured_gateway_is_offered_for_an_event_order(): void
    {
        $this->enableMonCash();
        $o = $this->order($this->paidEvent());

        $this->get(route('tagtoa.pay.order', ['event', $o->reference]))
            ->assertOk()->assertSee(route('tagtoa.pay.online.start', ['gateway' => 'moncash', 'type' => 'event', 'orderId' => $o->id]), false);
    }

    public function test_i_paid_pays_nothing_it_only_tells_the_organiser(): void
    {
        $o = $this->order($this->paidEvent());
        $m = $this->method('t-1');

        $this->post(route('tagtoa.pay.order.claim', ['event', $o->reference]), [
            'payment_method_id' => $m->id, 'payer_name' => 'Marie', 'payer_phone' => '37000000', 'reference' => 'MC-123',
        ])->assertRedirect();

        $o->refresh();
        $this->assertSame(Order::STATUS_PENDING, $o->status, 'Une déclaration ne paie RIEN.');
        $this->assertSame('MC-123', $o->payment_claim['reference']);
        $this->assertNotNull($o->payment_claimed_at);
    }

    public function test_a_method_of_another_organiser_cannot_be_claimed(): void
    {
        $o = $this->order($this->paidEvent('t-1'));
        $foreign = $this->method('t-2');

        $this->post(route('tagtoa.pay.order.claim', ['event', $o->reference]), ['payment_method_id' => $foreign->id])
            ->assertSessionHasErrors('payment_method_id');

        $this->assertNull($o->fresh()->payment_claimed_at);
    }

    public function test_the_organiser_sees_the_claim_on_the_pending_order(): void
    {
        $e = $this->paidEvent();
        $o = $this->order($e);
        $m = $this->method('t-1');
        $this->post(route('tagtoa.pay.order.claim', ['event', $o->reference]), ['payment_method_id' => $m->id, 'reference' => 'MC-999']);

        $this->be(new \Illuminate\Auth\GenericUser(['id' => 1, 'tenant_id' => 't-1']));
        $this->get(route('tagtoa.event.dashboard.orders', $e->id))->assertOk()
            ->assertSee('Dit avoir payé')->assertSee('MC-999')->assertSee('Encaisser');
    }

    public function test_the_order_page_links_to_the_unified_pay_page_when_something_is_offered(): void
    {
        $o = $this->order($this->paidEvent());
        $this->method('t-1');

        $this->get(route('tagtoa.event.order', $o->reference))->assertOk()
            ->assertSee(route('tagtoa.pay.order', ['event', $o->reference]), false);
    }

    public function test_a_gateway_settlement_marks_the_order_paid_under_the_right_tenant_with_commission_and_the_ticket_then_enters(): void
    {
        RevenueSetting::create(['tenant_id' => null, 'revenue_model' => RevenueSetting::MODEL_COMMISSION,
            'commission_percent' => 5, 'commission_fixed' => 0, 'currency' => 'HTG', 'is_active' => true]);
        $e = $this->paidEvent('t-9');
        $o = $this->order($e);
        $ticket = Ticket::where('order_id', $o->id)->first();
        $checkin = app(CheckinService::class);
        $this->assertSame('unpaid', $checkin->processScan($e, $ticket->code)['reason']);

        $txn = PayTransaction::create(['tenant_id' => $o->tenant_id, 'gateway' => 'moncash', 'reference' => PayTransaction::generateReference(),
            'order_type' => 'event', 'order_id' => $o->id, 'amount' => (float) $o->total, 'currency' => 'HTG', 'status' => PayTransaction::STATUS_PENDING]);

        $this->assertTrue((new EventCheckout())->confirmOrFail($txn->reference));

        $this->assertSame(Order::STATUS_PAID, $o->fresh()->status);
        $this->assertSame('t-9', $txn->fresh()->tenant_id);
        $this->assertSame('t-9', Commission::where('source_type', 'event_order')->value('tenant_id'), 'La commission doit porter le commerce de l\'événement.');
        $this->assertTrue($checkin->processScan($e, $ticket->code)['valid'], 'Payé : le billet ouvre la porte.');
    }

    public function test_starting_an_online_payment_records_the_event_tenant_on_the_transaction(): void
    {
        $this->enableMonCash();
        $o = $this->order($this->paidEvent('t-4'));

        (new CheckoutService())->start('event', $o->id, 'moncash');

        $this->assertSame('t-4', PayTransaction::where('order_type', 'event')->where('order_id', $o->id)->value('tenant_id'));
    }

    public function test_an_online_topup_of_a_platform_card_with_no_tenant_still_settles(): void
    {
        // Carte officielle TAGTOA : sans émetteur (tenant null). La colonne tenant_id du
        // grand livre est NOT NULL : l'écriture échouait, le règlement était annulé, le
        // client avait payé à la passerelle et la carte n'était jamais créditée.
        $card = \Modules\Tagtoa\App\Models\Card\CardAccount::create(['tenant_id' => null, 'uid_hash' => hash('sha256', 'u1'),
            'code' => 'TG-1', 'currency' => 'HTG', 'balance_minor' => 0, 'status' => 'active']);
        $txn = PayTransaction::create(['tenant_id' => null, 'gateway' => 'moncash', 'reference' => PayTransaction::generateReference(),
            'order_type' => 'card_topup', 'order_id' => $card->id, 'amount' => 500, 'currency' => 'HTG', 'status' => PayTransaction::STATUS_PENDING]);

        $this->assertTrue((new EventCheckout())->confirmOrFail($txn->reference));

        $this->assertSame(PayTransaction::STATUS_PAID, $txn->fresh()->status);
        $this->assertGreaterThan(0, $card->fresh()->balance_minor, 'La carte doit être créditée.');
    }

    public function test_a_cancelled_event_order_can_no_longer_be_paid_or_claimed(): void
    {
        $this->enableMonCash();
        $o = $this->order($this->paidEvent());
        $m = $this->method('t-1');
        Order::whereKey($o->id)->update(['status' => Order::STATUS_CANCELLED]);

        $this->get(route('tagtoa.pay.order', ['event', $o->reference]))->assertNotFound();
        $this->post(route('tagtoa.pay.order.claim', ['event', $o->reference]), ['payment_method_id' => $m->id])->assertNotFound();
        $this->assertNull((new CheckoutService())->start('event', $o->id, 'moncash'));
        $this->assertSame(0, PayTransaction::where('order_id', $o->id)->count());
    }
}
