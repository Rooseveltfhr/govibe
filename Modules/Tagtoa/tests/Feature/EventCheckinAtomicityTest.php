<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA EVENT — CS-3 : l'admission d'un billet est décidée sous verrou
|--------------------------------------------------------------------------
| Avant : « déjà entré aujourd'hui ? » était lu AVANT la transaction. Deux
| portes scannant le même billet à la même seconde lisaient toutes deux « non »,
| puis écrivaient chacune une entrée : deux personnes, un billet.
|
| Maintenant le billet est verrouillé (SELECT … FOR UPDATE) et la décision est
| prise APRÈS, sur ce que la première requête a écrit. SQLite ignore les
| verrous de ligne : le test simule donc, par un point d'accroche appelé juste
| après l'obtention du verrou, l'entrée qu'une autre porte a validée PENDANT
| l'attente — c'est exactement ce que le verrou garantit de voir en production.
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Tagtoa\App\Models\Event\Checkin;
use Modules\Tagtoa\App\Models\Event\Event;
use Modules\Tagtoa\App\Models\Event\Ticket;
use Modules\Tagtoa\App\Services\Event\CheckinService;
use Modules\Tagtoa\Tests\TestCase;

class RacingCheckin extends CheckinService
{
    /** @var callable|null */
    public $whileWaitingForLock = null;
    public int $notified = 0;

    protected function beforeTicketLock(Ticket $locked): void
    {
        if ($this->whileWaitingForLock) {
            ($this->whileWaitingForLock)($locked);
            $this->whileWaitingForLock = null;
        }
    }

    protected function notifyEntry(Event $event, Ticket $ticket): void
    {
        $this->notified++;
    }
}

class EventCheckinAtomicityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function event(array $extra = []): Event
    {
        return Event::create(array_merge([
            'tenant_id' => 't-1', 'title' => 'Gala', 'alias' => 'gala', 'currency' => 'HTG', 'is_published' => true,
            'starts_at' => now()->subDay()->toDateTimeString(), 'ends_at' => now()->addDay()->toDateTimeString(),
        ], $extra));
    }

    private function ticket(Event $e, string $code = 'T1'): Ticket
    {
        return Ticket::create(['event_id' => $e->id, 'code' => $code, 'holder_name' => 'Jean', 'status' => Ticket::STATUS_VALID]);
    }

    private function otherGateAdmits(Ticket $t, ?string $uuid = null): callable
    {
        return function () use ($t, $uuid) {
            Checkin::create(['event_id' => $t->event_id, 'ticket_id' => $t->id, 'direction' => 'in', 'method' => 'qr',
                'client_uuid' => $uuid, 'scanned_at' => now()]);
            Ticket::whereKey($t->id)->update(['checked_in' => true, 'checked_in_at' => now()]);
        };
    }

    public function test_an_entry_committed_by_another_door_while_waiting_for_the_lock_is_seen_and_refused(): void
    {
        $e = $this->event();
        $t = $this->ticket($e);
        $svc = new RacingCheckin(app(\Modules\Tagtoa\App\Services\Notifications\NotificationService::class), app(\Modules\Tagtoa\App\Services\Event\EventNotifier::class));
        $svc->whileWaitingForLock = $this->otherGateAdmits($t);

        $r = $svc->processScan($e, 'T1');

        $this->assertFalse($r['valid']);
        $this->assertSame('duplicate_today', $r['reason']);
        $this->assertSame(1, Checkin::where('ticket_id', $t->id)->count(), 'Deux entrées pour un seul billet.');
        $this->assertSame(0, $svc->notified, 'Le refus ne doit pas notifier une entrée.');
    }

    public function test_a_replayed_uuid_is_answered_already_synced_even_when_it_is_also_a_same_day_duplicate(): void
    {
        $e = $this->event();
        $t = $this->ticket($e);
        $svc = new RacingCheckin(app(\Modules\Tagtoa\App\Services\Notifications\NotificationService::class), app(\Modules\Tagtoa\App\Services\Event\EventNotifier::class));

        $this->assertTrue($svc->processScan($e, 'T1', 'in', 'qr', null, 'uuid-1')['valid']);
        $replay = $svc->processScan($e, 'T1', 'in', 'qr', null, 'uuid-1');

        $this->assertTrue($replay['valid'], 'Le rejeu d\'un scan réussi est un succès, pas un refus.');
        $this->assertSame(1, Checkin::where('ticket_id', $t->id)->count());
    }

    public function test_the_same_uuid_validated_by_the_other_door_during_the_wait_is_a_replay_not_a_duplicate(): void
    {
        $e = $this->event();
        $t = $this->ticket($e);
        $svc = new RacingCheckin(app(\Modules\Tagtoa\App\Services\Notifications\NotificationService::class), app(\Modules\Tagtoa\App\Services\Event\EventNotifier::class));
        $svc->whileWaitingForLock = $this->otherGateAdmits($t, 'uuid-9');

        $r = $svc->processScan($e, 'T1', 'in', 'qr', null, 'uuid-9');

        $this->assertTrue($r['valid']);
        $this->assertSame(1, Checkin::where('ticket_id', $t->id)->count());
    }

    public function test_a_ticket_voided_while_waiting_for_the_lock_is_refused(): void
    {
        $e = $this->event();
        $t = $this->ticket($e);
        $svc = new RacingCheckin(app(\Modules\Tagtoa\App\Services\Notifications\NotificationService::class), app(\Modules\Tagtoa\App\Services\Event\EventNotifier::class));
        $svc->whileWaitingForLock = fn () => Ticket::whereKey($t->id)->update(['status' => Ticket::STATUS_VOID]);

        $r = $svc->processScan($e, 'T1');

        $this->assertFalse($r['valid']);
        $this->assertSame('void', $r['reason']);
        $this->assertSame(0, Checkin::where('ticket_id', $t->id)->count());
    }

    public function test_a_ticket_deleted_while_waiting_for_the_lock_is_not_found_not_a_crash(): void
    {
        $e = $this->event();
        $t = $this->ticket($e);
        $svc = new RacingCheckin(app(\Modules\Tagtoa\App\Services\Notifications\NotificationService::class), app(\Modules\Tagtoa\App\Services\Event\EventNotifier::class));
        $svc->whileWaitingForLock = fn () => Ticket::whereKey($t->id)->delete();

        $r = $svc->processScan($e, 'T1');

        $this->assertFalse($r['valid']);
        $this->assertSame('not_found', $r['reason']);
    }

    public function test_a_multi_day_pass_is_still_admitted_on_day_two(): void
    {
        $e = $this->event(['starts_at' => now()->toDateString().' 08:00:00', 'ends_at' => now()->addDay()->toDateString().' 23:00:00']);
        $this->ticket($e);
        $svc = new RacingCheckin(app(\Modules\Tagtoa\App\Services\Notifications\NotificationService::class), app(\Modules\Tagtoa\App\Services\Event\EventNotifier::class));

        Carbon::setTestNow(now()->setTime(10, 0));
        $this->assertTrue($svc->processScan($e, 'T1')['valid']);
        $this->assertFalse($svc->processScan($e, 'T1')['valid'], 'Même jour : refusé.');

        Carbon::setTestNow(now()->addDay()->setTime(10, 0));
        $this->assertTrue($svc->processScan($e, 'T1')['valid'], 'Jour 2 : admis.');
    }

    public function test_a_ticket_of_another_event_is_never_admitted(): void
    {
        $e1 = $this->event();
        $e2 = $this->event(['alias' => 'autre', 'title' => 'Autre']);
        $this->ticket($e2, 'FOREIGN');
        $svc = new RacingCheckin(app(\Modules\Tagtoa\App\Services\Notifications\NotificationService::class), app(\Modules\Tagtoa\App\Services\Event\EventNotifier::class));

        $r = $svc->processScan($e1, 'FOREIGN');

        $this->assertFalse($r['valid']);
        $this->assertSame('not_found', $r['reason']);
    }

    public function test_exit_without_entry_is_still_refused_and_exit_after_entry_works(): void
    {
        $e = $this->event();
        $this->ticket($e);
        $svc = new RacingCheckin(app(\Modules\Tagtoa\App\Services\Notifications\NotificationService::class), app(\Modules\Tagtoa\App\Services\Event\EventNotifier::class));

        $this->assertSame('not_yet_in', $svc->processScan($e, 'T1', 'out')['reason']);
        $this->assertTrue($svc->processScan($e, 'T1', 'in')['valid']);
        $this->assertTrue($svc->processScan($e, 'T1', 'out')['valid']);
    }
}
