<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA EVENT — CS-4 : un billet d'une commande NON payée n'ouvre pas la porte
|--------------------------------------------------------------------------
| TicketService::createOrder() crée les billets VALIDES dès la commande, alors
| que la commande reste « en attente » tant que rien n'est payé, et la page
| de commande affichait déjà le QR. Quelqu'un qui ne paie jamais pouvait donc
| entrer. Décision D1 (défaut du plan) : refusé à la porte, avec un message
| clair. Les billets sans commande (guichet, import, carte encodée) et ceux des
| commandes payées / offertes sont inchangés.
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Event\Checkin;
use Modules\Tagtoa\App\Models\Event\Event;
use Modules\Tagtoa\App\Models\Event\Order;
use Modules\Tagtoa\App\Models\Event\Staff;
use Modules\Tagtoa\App\Models\Event\Ticket;
use Modules\Tagtoa\App\Services\Event\CheckinService;
use Modules\Tagtoa\Tests\TestCase;

class EventUnpaidTicketTest extends TestCase
{
    use RefreshDatabase;

    private function event(): Event
    {
        return Event::create([
            'tenant_id' => 't-1', 'title' => 'Gala', 'alias' => 'gala', 'currency' => 'HTG', 'is_published' => true,
            'starts_at' => now()->subDay()->toDateTimeString(), 'ends_at' => now()->addDay()->toDateTimeString(),
        ]);
    }

    private function ticketOfOrder(Event $e, int $orderStatus, string $code = 'T1'): Ticket
    {
        $o = Order::create(['event_id' => $e->id, 'reference' => Order::generateReference(), 'buyer_name' => 'Marie',
            'total' => 500, 'currency' => 'HTG', 'status' => $orderStatus]);

        return Ticket::create(['event_id' => $e->id, 'order_id' => $o->id, 'code' => $code, 'holder_name' => 'Marie', 'status' => Ticket::STATUS_VALID]);
    }

    public function test_a_ticket_of_a_pending_order_is_refused_at_the_door(): void
    {
        $e = $this->event();
        $t = $this->ticketOfOrder($e, Order::STATUS_PENDING);

        $r = app(CheckinService::class)->processScan($e, 'T1');

        $this->assertFalse($r['valid']);
        $this->assertSame('unpaid', $r['reason']);
        $this->assertSame('red', $r['color']);
        $this->assertSame(0, Checkin::where('ticket_id', $t->id)->count());
        $this->assertFalse((bool) $t->fresh()->checked_in);
    }

    public function test_a_ticket_of_a_cancelled_order_is_refused(): void
    {
        $e = $this->event();
        $this->ticketOfOrder($e, Order::STATUS_CANCELLED);

        $this->assertSame('unpaid', app(CheckinService::class)->processScan($e, 'T1')['reason']);
    }

    public function test_a_ticket_of_a_paid_order_is_admitted(): void
    {
        $e = $this->event();
        $this->ticketOfOrder($e, Order::STATUS_PAID);

        $this->assertTrue(app(CheckinService::class)->processScan($e, 'T1')['valid']);
    }

    public function test_a_ticket_without_an_order_door_sale_or_import_is_admitted(): void
    {
        $e = $this->event();
        Ticket::create(['event_id' => $e->id, 'order_id' => null, 'code' => 'DOOR', 'holder_name' => 'X', 'status' => Ticket::STATUS_VALID]);

        $this->assertTrue(app(CheckinService::class)->processScan($e, 'DOOR')['valid']);
    }

    public function test_paying_after_a_refusal_makes_the_same_ticket_valid(): void
    {
        $e = $this->event();
        $t = $this->ticketOfOrder($e, Order::STATUS_PENDING);
        $svc = app(CheckinService::class);

        $this->assertSame('unpaid', $svc->processScan($e, 'T1')['reason']);
        Order::whereKey($t->order_id)->update(['status' => Order::STATUS_PAID]);

        $this->assertTrue($svc->processScan($e, 'T1')['valid']);
    }

    public function test_an_unpaid_ticket_is_refused_and_acknowledged_by_the_offline_sync_not_retried_forever(): void
    {
        $e = $this->event();
        $this->ticketOfOrder($e, Order::STATUS_PENDING);
        $staff = Staff::create(['event_id' => $e->id, 'name' => 'Agent', 'pin_hash' => bcrypt('1234'), 'role' => 'checkin', 'active' => true]);

        $res = $this->withSession(['tagtoa_ev_staff.'.$e->id => ['id' => $staff->id, 'name' => $staff->name, 'role' => $staff->role]])
            ->postJson(route('tagtoa.event.staff.sync', $e->alias), ['records' => [['client_uuid' => 'u1', 'code' => 'T1', 'at' => now()->toIso8601String()]]])
            ->assertOk()->json('results');

        $this->assertSame('refused', $res[0]['status']);
        $this->assertSame('unpaid', $res[0]['reason']);
        $this->assertTrue($res[0]['ack']);
    }

    public function test_the_public_ticket_page_shows_no_qr_while_unpaid_and_shows_it_once_paid(): void
    {
        $e = $this->event();
        $t = $this->ticketOfOrder($e, Order::STATUS_PENDING, 'ABCD1234');

        $this->get(route('tagtoa.event.ticket', 'ABCD1234'))->assertOk()
            ->assertSee('En attente de paiement')
            ->assertDontSee('<svg', false);

        Order::whereKey($t->order_id)->update(['status' => Order::STATUS_PAID]);

        $this->get(route('tagtoa.event.ticket', 'ABCD1234'))->assertOk()
            ->assertDontSee('En attente de paiement');
    }
}
