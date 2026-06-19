<?php

namespace App\Http\Controllers;

use App\Models\TaGtoaEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * TAGTOA EVENT — dashboard organisateur (CRUD + commandes + analytics + export).
 */
class TaGtoaEventController extends Controller
{
    public function index(): View
    {
        $events = TaGtoaEvent::withCount(['tickets', 'orders'])->latest()->paginate(15);

        return view('tagtoa.event.dashboard.index', compact('events'));
    }

    public function create(): View
    {
        return view('tagtoa.event.dashboard.form', [
            'event'  => new TaGtoaEvent(),
            'vcards' => $this->ownerVcards(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateEvent($request);

        $event = new TaGtoaEvent($data);
        $event->tenant_id = function_exists('getLogInTenantId') ? getLogInTenantId() : null;
        $event->alias     = $data['alias'] ?: TaGtoaEvent::generateAlias($data['title']);
        $event->save();

        if ($request->hasFile('cover')) {
            $event->addMediaFromRequest('cover')->toMediaCollection('event-cover');
        }

        return redirect()->route('tagtoa.event.dashboard.edit', $event->id)
            ->with('success', __('Événement créé.'));
    }

    public function edit(int $id): View
    {
        $event = TaGtoaEvent::with(['ticketTypes', 'saleItems', 'media'])->findOrFail($id);

        return view('tagtoa.event.dashboard.form', [
            'event'  => $event,
            'vcards' => $this->ownerVcards(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $event = TaGtoaEvent::findOrFail($id);
        $data  = $this->validateEvent($request, $event->id);

        $data['alias'] = $data['alias'] ?: $event->alias;
        $event->update($data);

        if ($request->hasFile('cover')) {
            $event->clearMediaCollection('event-cover');
            $event->addMediaFromRequest('cover')->toMediaCollection('event-cover');
        }

        $this->syncTicketTypes($event, $request);

        return back()->with('success', __('Événement mis à jour.'));
    }

    public function orders(int $id): View
    {
        $event  = TaGtoaEvent::findOrFail($id);
        $orders = $event->orders()->withCount('tickets')->latest()->paginate(20);

        $analytics = [
            'revenue'    => $event->orders()->where('status', 1)->sum('total'),
            'tickets'    => $event->tickets()->count(),
            'checked_in' => $event->tickets()->where('checked_in', true)->count(),
            'orders'     => $event->orders()->count(),
        ];

        return view('tagtoa.event.dashboard.orders', compact('event', 'orders', 'analytics'));
    }

    /** Export CSV des commandes. */
    public function exportOrders(int $id): StreamedResponse
    {
        $event = TaGtoaEvent::findOrFail($id);

        return Response::streamDownload(function () use ($event) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['reference', 'buyer_name', 'buyer_phone', 'total', 'currency', 'status', 'paid_at']);
            $event->orders()->orderBy('id')->chunk(200, function ($chunk) use ($out) {
                foreach ($chunk as $o) {
                    fputcsv($out, [$o->reference, $o->buyer_name, $o->buyer_phone, $o->total, $o->currency, $o->status, $o->paid_at]);
                }
            });
            fclose($out);
        }, "event-{$event->alias}-orders.csv", ['Content-Type' => 'text/csv']);
    }

    /* ----------------------------------------------------------------- helpers */

    protected function syncTicketTypes(TaGtoaEvent $event, Request $request): void
    {
        $rows = $request->input('ticket_types', []);
        $keep = [];

        foreach ($rows as $i => $row) {
            if (empty($row['name'])) {
                continue;
            }
            $attrs = [
                'name'           => $row['name'],
                'price'          => (float) ($row['price'] ?? 0),
                'quantity'       => ($row['quantity'] ?? '') === '' ? null : (int) $row['quantity'],
                'is_active'      => ! empty($row['is_active']),
                'sort'           => (int) ($row['sort'] ?? $i),
            ];
            $type = ! empty($row['id']) ? $event->ticketTypes()->whereKey($row['id'])->first() : null;
            $type ? $type->update($attrs) : $type = $event->ticketTypes()->create($attrs);
            $keep[] = $type->id;
        }

        $event->ticketTypes()->whereNotIn('id', $keep ?: [0])->delete();
    }

    protected function validateEvent(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'vcard_id'     => ['nullable', 'integer', 'exists:vcards,id'],
            'title'        => ['required', 'string', 'max:160'],
            'alias'        => ['nullable', 'string', 'max:120', 'alpha_dash',
                               'unique:tagtoa_ev_events,alias' . ($ignoreId ? ',' . $ignoreId : '')],
            'type'         => ['nullable', 'string', 'max:30'],
            'description'  => ['nullable', 'string', 'max:2000'],
            'venue'        => ['nullable', 'string', 'max:160'],
            'address'      => ['nullable', 'string', 'max:255'],
            'starts_at'    => ['nullable', 'date'],
            'ends_at'      => ['nullable', 'date'],
            'currency'     => ['nullable', 'string', 'max:10'],
            'is_free'      => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
            'pay_page_id'  => ['nullable', 'integer'],
            'cover'        => ['nullable', 'image', 'max:4096'],
        ]);
    }

    protected function ownerVcards()
    {
        return \App\Models\Vcard::query()->orderBy('name')->get(['id', 'name']);
    }
}
