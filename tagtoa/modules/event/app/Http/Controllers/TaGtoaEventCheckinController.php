<?php

namespace App\Http\Controllers;

use App\Models\TaGtoaEvent;
use App\Services\TaGtoaCheckinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * TAGTOA EVENT — scanner de check-in (PWA) + endpoints de scan/sync.
 *
 *   GET  /tagtoa/event/{id}/scanner   -> scanner (auth)
 *   POST /tagtoa/event/{id}/scan      -> scan unitaire (JSON)
 *   POST /tagtoa/event/{id}/sync      -> sync lot offline (JSON)
 */
class TaGtoaEventCheckinController extends Controller
{
    public function __construct(protected TaGtoaCheckinService $service)
    {
    }

    public function scanner(int $id): View
    {
        $event = TaGtoaEvent::findOrFail($id);

        $stats = [
            'tickets'    => $event->tickets()->count(),
            'checked_in' => $event->tickets()->where('checked_in', true)->count(),
        ];

        return view('tagtoa.event.dashboard.scanner', compact('event', 'stats'));
    }

    public function scan(Request $request, int $id): JsonResponse
    {
        $event = TaGtoaEvent::findOrFail($id);

        $data = $request->validate([
            'code'        => ['required', 'string', 'max:60'],
            'direction'   => ['nullable', 'in:in,out'],
            'method'      => ['nullable', 'in:qr,nfc,manual'],
            'gate'        => ['nullable', 'string', 'max:40'],
            'client_uuid' => ['nullable', 'string', 'max:64'],
        ]);

        $result = $this->service->processScan(
            $event,
            $data['code'],
            $data['direction'] ?? 'in',
            $data['method'] ?? 'qr',
            $data['gate'] ?? null,
            $data['client_uuid'] ?? null,
        );

        return response()->json($result);
    }

    public function sync(Request $request, int $id): JsonResponse
    {
        $event = TaGtoaEvent::findOrFail($id);

        $data = $request->validate([
            'scans'              => ['required', 'array'],
            'scans.*.code'       => ['required', 'string', 'max:60'],
            'scans.*.direction'  => ['nullable', 'in:in,out'],
            'scans.*.client_uuid'=> ['nullable', 'string', 'max:64'],
        ]);

        return response()->json(['results' => $this->service->sync($event, $data['scans'])]);
    }
}
