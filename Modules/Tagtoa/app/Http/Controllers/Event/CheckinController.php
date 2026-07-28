<?php

namespace Modules\Tagtoa\App\Http\Controllers\Event;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Event\Event;
use Modules\Tagtoa\App\Services\Event\CheckinService;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA Event — scanner PWA + endpoints scan/sync.
 */
class CheckinController extends Controller
{
    public function __construct(protected CheckinService $service)
    {
    }

    public function scanner(int $id): View
    {
        $event = $this->ownEvent($id);
        $stats = [
            'tickets'    => $event->tickets()->count(),
            'checked_in' => $event->tickets()->where('checked_in', true)->count(),
        ];

        return view('tagtoa::event.scanner', compact('event', 'stats'));
    }

    public function scan(Request $request, int $id): JsonResponse
    {
        $event = $this->ownEvent($id);
        $data = $request->validate([
            'code'        => ['required', 'string', 'max:60'],
            'direction'   => ['nullable', 'in:in,out'],
            'method'      => ['nullable', 'in:qr,nfc,manual'],
            'gate'        => ['nullable', 'string', 'max:40'],
            'client_uuid' => ['nullable', 'string', 'max:64'],
        ]);

        return response()->json($this->service->processScan(
            $event, $data['code'], $data['direction'] ?? 'in', $data['method'] ?? 'qr', $data['gate'] ?? null, $data['client_uuid'] ?? null
        ));
    }

    public function sync(Request $request, int $id): JsonResponse
    {
        $event = $this->ownEvent($id);
        $data = $request->validate(['scans' => ['required', 'array']]);

        return response()->json(['results' => $this->service->sync($event, $data['scans'])]);
    }

    /** Check-in par carte NFC (tap) : UID -> billet -> entrée. */
    public function scanNfc(Request $request, int $id): JsonResponse
    {
        $event = $this->ownEvent($id);
        $data = $request->validate([
            'uid'         => ['required', 'string', 'max:120'],
            'direction'   => ['nullable', 'in:in,out'],
            'gate'        => ['nullable', 'string', 'max:40'],
            'client_uuid' => ['nullable', 'string', 'max:64'],
        ]);

        $code = $this->service->resolveNfcCode($event, $data['uid']);
        if (! $code) {
            return response()->json([
                'valid' => false, 'color' => 'red', 'sound' => 'error',
                'message' => __('Carte non reconnue.'), 'ticket' => null,
            ], 404);
        }

        return response()->json($this->service->processScan(
            $event, $code, $data['direction'] ?? 'in', 'nfc', $data['gate'] ?? null, $data['client_uuid'] ?? null
        ));
    }

    /* ---------------- Rapport d'entrée temps réel ---------------- */

    public function report(int $id): View
    {
        $event = $this->ownEvent($id);

        return view('tagtoa::event.checkin-report', compact('event'));
    }

    /** Stats live (polling JSON) : compteurs + dernières entrées. */
    public function stats(int $id): JsonResponse
    {
        $event = $this->ownEvent($id);

        $tickets = $event->tickets()->count();
        $checkedIn = $event->tickets()->where('checked_in', true)->count();

        $recent = \Modules\Tagtoa\App\Models\Event\Checkin::where('event_id', $event->id)
            ->where('direction', 'in')->with('ticket')
            ->orderByDesc('id')->limit(30)->get()
            ->map(fn ($c) => [
                'name'   => optional($c->ticket)->holder_name ?: __('Participant'),
                'time'   => optional($c->scanned_at)->format('H:i:s'),
                'method' => $c->method,
                'gate'   => $c->gate,
            ]);

        // Entrées par jour (multi-jour : ex. « Entrées Jour 1 / Jour 2 »).
        $days = \Modules\Tagtoa\App\Support\Event\EventDays::list(
            optional($event->starts_at)->toDateString(),
            optional($event->ends_at)->toDateString()
        );
        $counts = \Modules\Tagtoa\App\Models\Event\Checkin::where('event_id', $event->id)
            ->where('direction', 'in')
            ->selectRaw('DATE(scanned_at) as d, COUNT(*) as c')
            ->groupBy('d')->pluck('c', 'd');
        $byDay = [];
        foreach ($days as $i => $d) {
            $byDay[] = ['day' => $d, 'label' => __('Jour').' '.($i + 1), 'count' => (int) ($counts[$d] ?? 0)];
        }

        return response()->json([
            'tickets'    => $tickets,
            'checked_in' => $checkedIn,
            'percent'    => $tickets > 0 ? round($checkedIn * 100 / $tickets) : 0,
            'recent'     => $recent,
            'by_day'     => $byDay,
        ]);
    }

    /* ---------------- Badges QR imprimables ---------------- */

    public function badges(int $id): View
    {
        $event = $this->ownEvent($id);
        $tickets = $event->tickets()->where('status', 1)->with('ticketType')->limit(500)->get();

        return view('tagtoa::event.badges', compact('event', 'tickets'));
    }

    protected function ownEvent(int $id): Event
    {
        return Event::where('tenant_id', Tenant::id())->findOrFail($id);
    }
}
