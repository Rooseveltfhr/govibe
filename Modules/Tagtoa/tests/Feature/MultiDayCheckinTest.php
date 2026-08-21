<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA EVENT — check-in sur un événement multi-jours (festival 2 jours)
|--------------------------------------------------------------------------
| Le même billet doit : (1) entrer jour 1 avec un message qui précise le
| jour courant, (2) être bloqué s'il rescanne le même jour, (3) entrer à
| nouveau jour 2 avec un message mis à jour — sans créer de doublon de billet.
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Tagtoa\App\Models\Event\Event;
use Modules\Tagtoa\App\Models\Event\Ticket;
use Modules\Tagtoa\App\Services\Event\CheckinService;
use Modules\Tagtoa\Tests\TestCase;

class MultiDayCheckinTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_two_day_pass_allows_entry_each_day_with_day_number_in_message(): void
    {
        $event = Event::create([
            'title' => 'Festival Multi-Jour', 'alias' => 'festival-multi-jour', 'currency' => 'HTG',
            'is_published' => true, 'starts_at' => '2026-08-14 18:00:00', 'ends_at' => '2026-08-15 23:00:00',
        ]);
        $ticket = Ticket::create([
            'event_id' => $event->id, 'code' => 'TCKMULTI1', 'holder_name' => 'Jean', 'status' => Ticket::STATUS_VALID,
        ]);
        $svc = app(CheckinService::class);

        Carbon::setTestNow('2026-08-14 19:00:00');
        $day1 = $svc->processScan($event, $ticket->code);
        $this->assertTrue($day1['valid']);
        $this->assertStringContainsString('jour 1/2', $day1['message']);

        $again = $svc->processScan($event, $ticket->code);
        $this->assertFalse($again['valid']);
        $this->assertStringContainsString('Déjà entré', $again['message']);

        Carbon::setTestNow('2026-08-15 12:00:00');
        $day2 = $svc->processScan($event, $ticket->code);
        $this->assertTrue($day2['valid']);
        $this->assertStringContainsString('jour 2/2', $day2['message']);

        $this->assertSame(1, Ticket::where('event_id', $event->id)->count()); // pas de doublon créé
    }

    public function test_single_day_event_keeps_simple_welcome_message(): void
    {
        $event = Event::create([
            'title' => 'Concert Un Soir', 'alias' => 'concert-un-soir', 'currency' => 'HTG',
            'is_published' => true, 'starts_at' => '2026-08-14 20:00:00', 'ends_at' => '2026-08-14 23:00:00',
        ]);
        $ticket = Ticket::create([
            'event_id' => $event->id, 'code' => 'TCKSINGLE1', 'holder_name' => 'Marie', 'status' => Ticket::STATUS_VALID,
        ]);

        Carbon::setTestNow('2026-08-14 20:30:00');
        $res = app(CheckinService::class)->processScan($event, $ticket->code);

        $this->assertTrue($res['valid']);
        $this->assertSame('Bienvenue!', $res['message']);
    }
}
