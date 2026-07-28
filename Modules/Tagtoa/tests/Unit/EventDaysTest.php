<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Support\Event\EventDays;
use PHPUnit\Framework\TestCase;

/**
 * Logique multi-jour du check-in (jours d'un événement, jour effectif, anti
 * double-entrée le même jour).
 */
class EventDaysTest extends TestCase
{
    public function test_single_day_event_has_one_day(): void
    {
        $this->assertSame(['2026-08-14'], EventDays::list('2026-08-14 18:00:00', '2026-08-14 23:00:00'));
    }

    public function test_two_day_festival(): void
    {
        $this->assertSame(['2026-08-14', '2026-08-15'], EventDays::list('2026-08-14', '2026-08-15'));
    }

    public function test_null_end_falls_back_to_start_day(): void
    {
        $this->assertSame(['2026-08-14'], EventDays::list('2026-08-14 20:00:00', null));
    }

    public function test_invalid_or_reversed_range_is_empty(): void
    {
        $this->assertSame([], EventDays::list(null, null));
        $this->assertSame([], EventDays::list('2026-08-15', '2026-08-14'));
    }

    public function test_day_count_is_capped(): void
    {
        $days = EventDays::list('2026-01-01', '2026-12-31');
        $this->assertLessThanOrEqual(EventDays::MAX_DAYS, count($days));
    }

    public function test_resolve_prefers_valid_requested_day(): void
    {
        $days = ['2026-08-14', '2026-08-15'];
        $this->assertSame('2026-08-15', EventDays::resolveDay($days, '2026-08-14', '2026-08-15'));
    }

    public function test_resolve_ignores_invalid_requested_day(): void
    {
        $days = ['2026-08-14', '2026-08-15'];
        // Jour demandé hors événement → on retombe sur today (dans l'événement).
        $this->assertSame('2026-08-14', EventDays::resolveDay($days, '2026-08-14', '2026-09-01'));
    }

    public function test_resolve_uses_first_day_when_today_outside(): void
    {
        $days = ['2026-08-14', '2026-08-15'];
        $this->assertSame('2026-08-14', EventDays::resolveDay($days, '2026-08-20'));
    }

    public function test_resolve_uses_today_when_no_days(): void
    {
        $this->assertSame('2026-08-20', EventDays::resolveDay([], '2026-08-20'));
    }

    public function test_already_entered_detects_same_day(): void
    {
        $this->assertTrue(EventDays::alreadyEntered(['2026-08-14'], '2026-08-14'));
        $this->assertFalse(EventDays::alreadyEntered(['2026-08-14'], '2026-08-15'));
        $this->assertFalse(EventDays::alreadyEntered([], '2026-08-14'));
    }
}
