<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA EVENT — invités VIP (billets offerts, hors panier)
|--------------------------------------------------------------------------
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Event\Event;
use Modules\Tagtoa\App\Models\Event\Order;
use Modules\Tagtoa\App\Models\Event\Ticket;
use Modules\Tagtoa\App\Services\Event\CheckinService;
use Modules\Tagtoa\App\Services\Event\TicketService;
use Modules\Tagtoa\Tests\TestCase;

class EventVipGuestTest extends TestCase
{
    use RefreshDatabase;

    private function patron(string $tenantId = 't-1'): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Roosevelt']));
    }

    private function event(string $tenantId = 't-1'): Event
    {
        return Event::create(['tenant_id' => $tenantId, 'title' => 'Gala', 'alias' => 'gala-'.random_int(1000, 9999), 'currency' => 'HTG', 'is_published' => true]);
    }

    public function test_inviter_un_invite_emet_un_billet_gratuit_sans_toucher_le_chiffre_daffaires(): void
    {
        $event = $this->event();
        $type = $event->ticketTypes()->create(['name' => 'VIP', 'price' => 100, 'is_active' => true, 'is_vip' => true, 'quantity' => 5]);

        $order = app(TicketService::class)->inviteGuest($event, $type, ['name' => 'Wilner', 'phone' => '38112345']);

        $this->assertTrue($order->isInvite());
        $this->assertTrue($order->isPaid());
        $this->assertEquals(0, $order->total);
        $this->assertSame(1, $order->tickets()->count());
        $this->assertSame(1, $type->fresh()->sold);
    }

    public function test_inviter_respecte_le_stock_du_type_de_billet(): void
    {
        $event = $this->event();
        $type = $event->ticketTypes()->create(['name' => 'VIP', 'price' => 100, 'is_active' => true, 'quantity' => 1]);
        app(TicketService::class)->inviteGuest($event, $type, ['name' => 'Premier']);

        $this->expectException(\RuntimeException::class);
        app(TicketService::class)->inviteGuest($event, $type, ['name' => 'Deuxième']);
    }

    public function test_un_billet_vip_annonce_vip_au_scan(): void
    {
        $event = $this->event();
        $type = $event->ticketTypes()->create(['name' => 'VIP', 'price' => 0, 'is_active' => true, 'is_vip' => true]);
        $ticket = Ticket::create(['event_id' => $event->id, 'ticket_type_id' => $type->id, 'code' => 'VIPX1', 'status' => Ticket::STATUS_VALID]);

        $res = app(CheckinService::class)->processScan($event, $ticket->code);

        $this->assertStringContainsString('VIP', $res['message']);
    }

    public function test_un_billet_standard_nannonce_pas_vip(): void
    {
        $event = $this->event();
        $type = $event->ticketTypes()->create(['name' => 'Standard', 'price' => 0, 'is_active' => true]);
        $ticket = Ticket::create(['event_id' => $event->id, 'ticket_type_id' => $type->id, 'code' => 'STDX1', 'status' => Ticket::STATUS_VALID]);

        $res = app(CheckinService::class)->processScan($event, $ticket->code);

        $this->assertStringNotContainsString('VIP', $res['message']);
    }

    public function test_lecran_dinvitation_est_cloisonne_par_commerce(): void
    {
        $this->patron('t-1');
        $autre = $this->event('t-2');

        $this->get(route('tagtoa.event.dashboard.guests', $autre->id))->assertNotFound();
    }

    public function test_inviter_via_lecran_cree_le_billet(): void
    {
        $this->patron();
        $event = $this->event();
        $type = $event->ticketTypes()->create(['name' => 'VIP', 'price' => 100, 'is_active' => true]);

        $response = $this->post(route('tagtoa.event.dashboard.guests.invite', $event->id), [
            'ticket_type_id' => $type->id,
            'name'           => 'Journaliste',
        ]);

        $response->assertRedirect();
        $this->assertSame(1, Order::where('event_id', $event->id)->where('source', Order::SOURCE_INVITE)->count());
    }
}
