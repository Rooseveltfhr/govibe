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

    protected function ownEvent(int $id): Event
    {
        return Event::where('tenant_id', Tenant::id())->findOrFail($id);
    }
}
