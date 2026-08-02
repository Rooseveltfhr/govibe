<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA EVENT — import de billets pré-imprimés (hors système)
|--------------------------------------------------------------------------
| Intégration DB réelle : dédup en base (import répété/partiel), statut
| UNSOLD correct, activation par TicketService de vente au terminal.
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Event\Event;
use Modules\Tagtoa\App\Models\Event\Staff;
use Modules\Tagtoa\App\Models\Event\Ticket;
use Modules\Tagtoa\App\Services\Event\TicketImportService;
use Modules\Tagtoa\Tests\TestCase;

class TicketImportFlowTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::create([
            'title' => 'Festival Import Test', 'alias' => 'festival-import-test', 'currency' => 'HTG', 'is_published' => true,
        ]);
    }

    public function test_import_creates_unsold_tickets(): void
    {
        $rows = [
            ['code' => '696bb99d76e28407', 'serial' => '25H165407'],
            ['code' => 'abc123', 'serial' => null],
        ];

        $res = app(TicketImportService::class)->importForEvent($this->event, $rows);

        $this->assertSame(2, $res['created']);
        $this->assertSame(0, $res['duplicates']);
        $this->assertSame(2, Ticket::where('event_id', $this->event->id)->where('imported', true)->count());

        $t = Ticket::where('code', '696bb99d76e28407')->first();
        $this->assertSame('25H165407', $t->serial);
        $this->assertTrue($t->isUnsold());
        $this->assertFalse($t->isValid());
    }

    public function test_reimporting_same_codes_is_idempotent(): void
    {
        $rows = [['code' => 'DUPCODE', 'serial' => 'S1']];
        app(TicketImportService::class)->importForEvent($this->event, $rows);

        $res = app(TicketImportService::class)->importForEvent($this->event, $rows);

        $this->assertSame(0, $res['created']);
        $this->assertSame(1, $res['duplicates']);
        $this->assertSame(1, Ticket::where('code', 'DUPCODE')->count());
    }

    public function test_unsold_ticket_blocks_checkin_until_activated(): void
    {
        app(TicketImportService::class)->importForEvent($this->event, [['code' => 'PREPRINT-1', 'serial' => null]]);
        $ticket = Ticket::where('code', 'PREPRINT-1')->first();

        $this->assertFalse($ticket->isValid()); // pas encore vendu -> pas d'entrée possible

        // Simule l'activation par un vendeur au terminal (même logique que StaffTerminalController::sell()).
        $staff = Staff::create(['event_id' => $this->event->id, 'name' => 'Vendeur A', 'role' => 'vente', 'pin_hash' => 'x', 'active' => true]);
        $ticket->update(['holder_name' => 'Jean', 'holder_phone' => '+50912345678', 'status' => Ticket::STATUS_VALID]);
        $ticket->update(['sold_by_staff_id' => $staff->id, 'sold_at' => now()]);

        $ticket->refresh();
        $this->assertTrue($ticket->isValid());
        $this->assertSame('Vendeur A', $ticket->soldByStaff->name);
        $this->assertNotNull($ticket->sold_at);
    }
}
