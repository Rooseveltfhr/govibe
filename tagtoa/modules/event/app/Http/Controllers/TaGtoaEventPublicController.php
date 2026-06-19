<?php

namespace App\Http\Controllers;

use App\Models\TaGtoaEvOrder;
use App\Models\TaGtoaEvTicket;
use App\Models\TaGtoaEvent;
use App\Services\TaGtoaRevenueService;
use App\Services\TaGtoaTicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * TAGTOA EVENT — pages publiques (vitrine + achat + billet).
 *
 *   GET  /event/{alias}            -> show
 *   POST /event/{alias}/buy        -> buy
 *   GET  /event/order/{reference}  -> order (billets + statut)
 *   GET  /event/ticket/{code}      -> ticket (QR plein écran)
 */
class TaGtoaEventPublicController extends Controller
{
    public function __construct(
        protected TaGtoaTicketService $tickets,
        protected TaGtoaRevenueService $revenue,
    ) {
    }

    public function show(string $alias): View
    {
        $event = TaGtoaEvent::where('alias', $alias)
            ->where('is_published', true)
            ->with(['activeTicketTypes', 'media', 'payPage'])
            ->firstOrFail();

        $event->incrementQuietly('views');

        return view('tagtoa.event.show', ['event' => $event]);
    }

    public function buy(Request $request, string $alias): RedirectResponse
    {
        $event = TaGtoaEvent::where('alias', $alias)->where('is_published', true)->firstOrFail();

        $data = $request->validate([
            'buyer_name'      => ['required', 'string', 'max:120'],
            'buyer_phone'     => ['nullable', 'string', 'max:40'],
            'buyer_email'     => ['nullable', 'email', 'max:120'],
            'qty'             => ['required', 'array'],
            'qty.*'           => ['nullable', 'integer', 'min:0', 'max:50'],
        ]);

        $lines = [];
        foreach ($data['qty'] as $typeId => $qty) {
            if ((int) $qty > 0) {
                $lines[] = ['ticket_type_id' => (int) $typeId, 'qty' => (int) $qty];
            }
        }

        try {
            $order = $this->tickets->createOrder($event, $lines, [
                'name'  => $data['buyer_name'],
                'phone' => $data['buyer_phone'] ?? null,
                'email' => $data['buyer_email'] ?? null,
            ]);
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['qty' => $e->getMessage()]);
        }

        // Événement gratuit => commande déjà payée => commission éventuelle.
        if ($order->isPaid()) {
            $this->accrueCommission($order);
        }

        return redirect()->route('tagtoa.event.order', $order->reference);
    }

    public function order(string $reference): View
    {
        $order = TaGtoaEvOrder::where('reference', $reference)
            ->with(['event.payPage', 'tickets.ticketType'])
            ->firstOrFail();

        return view('tagtoa.event.order', ['order' => $order, 'event' => $order->event]);
    }

    public function ticket(string $code): View
    {
        $ticket = TaGtoaEvTicket::where('code', $code)
            ->with(['event', 'ticketType'])
            ->firstOrFail();

        return view('tagtoa.event.ticket', ['ticket' => $ticket, 'event' => $ticket->event]);
    }

    /** Enregistre la commission plateforme sur une commande payée. */
    protected function accrueCommission(TaGtoaEvOrder $order): void
    {
        $this->revenue->record(
            'event_order',
            $order->id,
            'event',
            (float) $order->total,
            $order->event->tenant_id ?? null,
            $order->currency,
        );
    }
}
