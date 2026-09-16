<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA EVENT — contrôle d'accès par porte
|--------------------------------------------------------------------------
| Un type de billet peut restreindre les portes où il est accepté
| (TicketType::allowed_gates). Vide/absent = aucune restriction, le
| comportement de TOUJOURS avant cette fonctionnalité.
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Event\Event;
use Modules\Tagtoa\App\Models\Event\Ticket;
use Modules\Tagtoa\App\Models\Event\TicketType;
use Modules\Tagtoa\App\Services\Event\CheckinService;
use Modules\Tagtoa\Tests\TestCase;

class EventGateAccessTest extends TestCase
{
    use RefreshDatabase;

    private function event(): Event
    {
        return Event::create(['title' => 'Gala', 'alias' => 'gala-'.random_int(1000, 9999), 'currency' => 'HTG', 'is_published' => true]);
    }

    public function test_un_billet_sans_restriction_entre_par_nimporte_quelle_porte(): void
    {
        $event = $this->event();
        $type = $event->ticketTypes()->create(['name' => 'Standard', 'price' => 0, 'is_active' => true]);
        $ticket = Ticket::create(['event_id' => $event->id, 'ticket_type_id' => $type->id, 'code' => 'GATE1', 'status' => Ticket::STATUS_VALID]);

        $res = app(CheckinService::class)->processScan($event, $ticket->code, 'in', 'qr', 'PORTE-INCONNUE');

        $this->assertTrue($res['valid']);
    }

    public function test_un_billet_restreint_est_refuse_a_une_autre_porte(): void
    {
        $event = $this->event();
        $type = $event->ticketTypes()->create(['name' => 'VIP', 'price' => 0, 'is_active' => true, 'allowed_gates' => ['VIP']]);
        $ticket = Ticket::create(['event_id' => $event->id, 'ticket_type_id' => $type->id, 'code' => 'GATE2', 'status' => Ticket::STATUS_VALID]);

        $res = app(CheckinService::class)->processScan($event, $ticket->code, 'in', 'qr', 'A');

        $this->assertFalse($res['valid']);
        $this->assertStringContainsString('pas valable à cette porte', $res['message']);
    }

    public function test_un_billet_restreint_entre_a_la_bonne_porte(): void
    {
        $event = $this->event();
        $type = $event->ticketTypes()->create(['name' => 'VIP', 'price' => 0, 'is_active' => true, 'allowed_gates' => ['VIP']]);
        $ticket = Ticket::create(['event_id' => $event->id, 'ticket_type_id' => $type->id, 'code' => 'GATE3', 'status' => Ticket::STATUS_VALID]);

        $res = app(CheckinService::class)->processScan($event, $ticket->code, 'in', 'qr', 'VIP');

        $this->assertTrue($res['valid']);
    }

    public function test_la_comparaison_de_porte_ignore_la_casse(): void
    {
        $event = $this->event();
        $type = $event->ticketTypes()->create(['name' => 'VIP', 'price' => 0, 'is_active' => true, 'allowed_gates' => ['VIP']]);
        $ticket = Ticket::create(['event_id' => $event->id, 'ticket_type_id' => $type->id, 'code' => 'GATE4', 'status' => Ticket::STATUS_VALID]);

        $res = app(CheckinService::class)->processScan($event, $ticket->code, 'in', 'qr', 'vip');

        $this->assertTrue($res['valid']);
    }

    public function test_une_porte_non_renseignee_ne_bloque_jamais(): void
    {
        $event = $this->event();
        $type = $event->ticketTypes()->create(['name' => 'VIP', 'price' => 0, 'is_active' => true, 'allowed_gates' => ['VIP']]);
        $ticket = Ticket::create(['event_id' => $event->id, 'ticket_type_id' => $type->id, 'code' => 'GATE5', 'status' => Ticket::STATUS_VALID]);

        $res = app(CheckinService::class)->processScan($event, $ticket->code, 'in', 'qr', null);

        $this->assertTrue($res['valid']);
    }

    public function test_allows_gate_est_pure_et_directement_testable(): void
    {
        $type = new TicketType(['allowed_gates' => ['A', 'B']]);

        $this->assertTrue($type->allowsGate('A'));
        $this->assertTrue($type->allowsGate('b'));
        $this->assertFalse($type->allowsGate('C'));
        $this->assertTrue($type->allowsGate(null));

        $sansRestriction = new TicketType(['allowed_gates' => null]);
        $this->assertTrue($sansRestriction->allowsGate('nimporte-quoi'));
    }
}
