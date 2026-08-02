<?php

namespace Modules\Tagtoa\App\Http\Controllers\Event;

use App\Http\Controllers\Controller;
use Modules\Tagtoa\App\Support\EnforcesPlan;
use App\Models\Vcard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Event\Event;
use Modules\Tagtoa\App\Models\Event\Ticket;
use Modules\Tagtoa\App\Models\Pay\PaymentPage;
use Modules\Tagtoa\App\Services\Audit\AuditService;
use Modules\Tagtoa\App\Services\Event\TicketImportService;
use Modules\Tagtoa\App\Support\Tenant;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * TAGTOA Event — dashboard organisateur (CRUD + commandes + analytics + export).
 */
class DashboardController extends Controller
{
    use EnforcesPlan;

    public function index(): View
    {
        $events = Event::where('tenant_id', Tenant::id())->withCount(['tickets', 'orders'])->latest()->paginate(12);

        return view('tagtoa::event.index', compact('events'));
    }

    public function create(): View
    {
        return view('tagtoa::event.form', ['event' => new Event(), 'vcards' => $this->vcards(), 'payPages' => $this->payPages()]);
    }

    public function store(Request $request): RedirectResponse
    {
        
        if ($r = $this->planGuard('event')) {
            return $r;
        }
$data = $this->validateEvent($request);
        $event = new Event($data);
        $event->tenant_id = Tenant::id();
        $event->alias = ($data['alias'] ?? null) ?: Event::generateAlias($data['title']);
        if ($request->hasFile('cover')) {
            $event->cover_path = $request->file('cover')->store('tagtoa/event-covers', 'public');
        }
        if ($request->hasFile('logo')) {
            $event->logo_path = $request->file('logo')->store('tagtoa/event-logos', 'public');
        }
        $event->save();
        $this->syncTypes($event, $request); // capture les types de billets + prix dès la création

        return redirect()->route('tagtoa.event.dashboard.edit', $event->id)->with('success', __('Événement créé.'));
    }

    public function edit(int $id): View
    {
        $event = $this->own($id, ['ticketTypes']);

        return view('tagtoa::event.form', ['event' => $event, 'vcards' => $this->vcards(), 'payPages' => $this->payPages()]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $event = $this->own($id);
        $data = $this->validateEvent($request, $event->id);
        $data['alias'] = ($data['alias'] ?? null) ?: $event->alias;
        if ($request->hasFile('cover')) {
            $data['cover_path'] = $request->file('cover')->store('tagtoa/event-covers', 'public');
        }
        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('tagtoa/event-logos', 'public');
        }
        $event->update($data);
        $this->syncTypes($event, $request);

        return back()->with('success', __('Événement mis à jour.'));
    }

    public function orders(int $id): View
    {
        $event = $this->own($id);
        $orders = $event->orders()->withCount('tickets')->latest()->paginate(20);
        $analytics = [
            'revenue'    => $event->orders()->where('status', 1)->sum('total'),
            'tickets'    => $event->tickets()->count(),
            'checked_in' => $event->tickets()->where('checked_in', true)->count(),
            'orders'     => $event->orders()->count(),
        ];

        return view('tagtoa::event.orders', compact('event', 'orders', 'analytics'));
    }

    /** Encaisse une commande en attente (preuve manuelle validée hors-ligne). */
    public function markOrderPaid(Request $request, int $id, int $orderId): RedirectResponse
    {
        $event = $this->own($id);
        $order = $event->orders()->findOrFail($orderId);

        if (! $order->isPaid()) {
            app(\Modules\Tagtoa\App\Services\Event\TicketService::class)->markPaid($order, 'manual');
            app(\Modules\Tagtoa\App\Services\Billing\RevenueService::class)
                ->record('event_order', $order->id, 'event', (float) $order->total, $event->tenant_id, $order->currency);
            app(\Modules\Tagtoa\App\Services\Audit\AuditService::class)
                ->log('event_order_paid', $order, $order->reference.' — '.$order->total.' '.$order->currency);
        }

        return back()->with('success', __('Commande encaissée.'));
    }

    public function exportOrders(int $id): StreamedResponse
    {
        $event = $this->own($id);

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

    /* ---------------- Import de billets pré-imprimés (hors système) ---------------- */

    public function ticketsImport(int $id): View
    {
        $event = $this->own($id);
        $stats = [
            'imported' => $event->tickets()->where('imported', true)->count(),
            'unsold'   => $event->tickets()->where('imported', true)->where('status', Ticket::STATUS_UNSOLD)->count(),
            'sold'     => $event->tickets()->where('imported', true)->where('status', Ticket::STATUS_VALID)->count(),
        ];
        $pending = $event->tickets()->where('imported', true)->where('status', Ticket::STATUS_UNSOLD)
            ->orderByDesc('id')->paginate(50);

        return view('tagtoa::event.tickets-import', compact('event', 'stats', 'pending'));
    }

    public function ticketsImportStore(Request $request, int $id): RedirectResponse
    {
        $event = $this->own($id);
        $data = $request->validate(['file' => ['required', 'file', 'max:2048']]);

        $parsed = TicketImportService::parseCsv(file_get_contents($data['file']->getRealPath()));
        $res = app(TicketImportService::class)->importForEvent($event, $parsed['rows']);

        app(AuditService::class)->log('event_tickets_imported', $event, $res['created'].' billets importés — '.$event->title);

        return back()->with('success', __(':n billets importés (:d doublons ignorés, :s lignes invalides).', [
            'n' => $res['created'], 'd' => $res['duplicates'], 's' => $parsed['skipped'],
        ]));
    }

    /**
     * Enregistre UN billet pré-imprimé par scan caméra (pas de liste numérique
     * disponible — l'organisateur scanne chaque carte physique une à une).
     * Idempotent : rescanner un code déjà enregistré ne crée pas de doublon.
     */
    public function ticketsScanImport(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $event = $this->own($id);
        $data = $request->validate([
            'code'   => ['required', 'string', 'max:64'],
            'serial' => ['nullable', 'string', 'max:40'],
        ]);

        $code = trim($data['code']);
        $existing = $event->tickets()->where('code', $code)->first();
        if ($existing) {
            return response()->json(['ok' => true, 'status' => 'duplicate', 'code' => $code]);
        }

        app(TicketImportService::class)->importForEvent($event, [
            ['code' => $code, 'serial' => ! empty($data['serial']) ? trim($data['serial']) : null],
        ]);

        return response()->json(['ok' => true, 'status' => 'created', 'code' => $code]);
    }

    /** Retire un billet pré-imprimé importé PAR ERREUR (jamais s'il a déjà été vendu). */
    public function ticketsDestroy(int $id, int $ticketId): RedirectResponse
    {
        $event = $this->own($id);
        $ticket = $event->tickets()->where('imported', true)->where('status', Ticket::STATUS_UNSOLD)->findOrFail($ticketId);
        $ticket->delete();

        return back()->with('success', __('Billet retiré de la liste d\'import.'));
    }

    /* helpers */
    protected function own(int $id, array $with = []): Event
    {
        return Event::with($with)->where('tenant_id', Tenant::id())->findOrFail($id);
    }

    protected function syncTypes(Event $event, Request $request): void
    {
        $rows = $request->input('ticket_types', []);
        $keep = [];
        foreach ($rows as $i => $row) {
            if (empty($row['name'])) {
                continue;
            }
            $compare = ($row['compare_at_price'] ?? '') === '' ? null : (float) $row['compare_at_price'];
            $price = (float) ($row['price'] ?? 0);
            $attrs = [
                'name'             => $row['name'],
                'price'            => $price,
                // Prix barré valide seulement s'il est supérieur au prix courant (sinon pas de « fausse » réduction).
                'compare_at_price' => ($compare !== null && $compare > $price) ? $compare : null,
                'quantity'         => ($row['quantity'] ?? '') === '' ? null : (int) $row['quantity'],
                'is_active'        => ! empty($row['is_active']),
                'sort'             => (int) ($row['sort'] ?? $i),
            ];
            $t = ! empty($row['id']) ? $event->ticketTypes()->whereKey($row['id'])->first() : null;
            $t ? $t->update($attrs) : $t = $event->ticketTypes()->create($attrs);
            $keep[] = $t->id;
        }
        $event->ticketTypes()->whereNotIn('id', $keep ?: [0])->delete();
    }

    protected function validateEvent(Request $request, ?int $ignoreId = null): array
    {
        $ownVcardIds = $this->vcards()->pluck('id')->all();
        $ownPayIds   = $this->payPages()->pluck('id')->all();

        return $request->validate([
            'vcard_id'     => ['nullable', 'integer', Rule::in($ownVcardIds)],
            'title'        => ['required', 'string', 'max:160'],
            'alias'        => ['nullable', 'string', 'max:120', 'alpha_dash', 'unique:tagtoa_ev_events,alias'.($ignoreId ? ','.$ignoreId : '')],
            'type'         => ['nullable', 'string', 'max:30'],
            'checkin_mode' => ['nullable', Rule::in(['qr', 'nfc', 'both'])], // mode de billet : en ligne (QR) / carte NFC / les deux
            'description'  => ['nullable', 'string', 'max:2000'],
            'venue'        => ['nullable', 'string', 'max:160'],
            'address'      => ['nullable', 'string', 'max:255'],
            'starts_at'    => ['nullable', 'date'],
            'ends_at'      => ['nullable', 'date'],
            'currency'     => ['nullable', 'string', 'max:10'],
            'is_free'      => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
            'pay_page_id'  => ['nullable', 'integer', Rule::in($ownPayIds)],
            'cover'        => ['nullable', 'image', 'max:4096'],
            'logo'         => ['nullable', 'image', 'max:4096'],
        ]);
    }

    protected function vcards()
    {
        try {
            return Vcard::query()->where('tenant_id', Tenant::id())->orderBy('name')->get(['id', 'name']);
        } catch (\Throwable $e) {
            return collect();
        }
    }

    protected function payPages()
    {
        return PaymentPage::where('tenant_id', Tenant::id())->get(['id', 'title', 'alias']);
    }
}
