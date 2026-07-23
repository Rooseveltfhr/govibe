<?php

namespace Modules\Tagtoa\App\Http\Controllers\Event;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Event\Event;
use Modules\Tagtoa\App\Models\Event\Order;
use Modules\Tagtoa\App\Models\Event\Ticket;
use Modules\Tagtoa\App\Models\Event\WalletTxn;
use Modules\Tagtoa\App\Services\Billing\RevenueService;
use Modules\Tagtoa\App\Services\Event\TicketService;

/**
 * TAGTOA Event — vitrine publique, achat, commande, billet.
 */
class PublicController extends Controller
{
    public function __construct(protected TicketService $tickets, protected RevenueService $revenue)
    {
    }

    /** Vitrine publique : TOUS les événements publiés (annuaire découverte). */
    public function index(): View
    {
        $events = Event::where('is_published', true)
            ->where(function ($q) {
                // Masque les événements clairement terminés (fin < hier).
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()->subDay());
            })
            ->with('activeTicketTypes')
            ->orderByRaw('CASE WHEN starts_at IS NULL THEN 1 ELSE 0 END, starts_at ASC')
            ->paginate(24);

        return view('tagtoa::event.directory', compact('events'));
    }

    public function show(string $alias): View
    {
        $event = Event::where('alias', $alias)->where('is_published', true)
            ->with('activeTicketTypes')->firstOrFail();
        $event->incrementQuietly('views');

        return view('tagtoa::event.show', ['event' => $event]);
    }

    /** Reçu public d'une transaction wallet (achat/recharge), par référence. */
    public function walletReceipt(string $reference): View
    {
        $txn = WalletTxn::where('reference', $reference)->firstOrFail();
        $event = $txn->event_id ? Event::find($txn->event_id) : null;
        $vendor = $txn->destAccount; // pour un achat : le stand

        return view('tagtoa::event.wallet-receipt', compact('txn', 'event', 'vendor'));
    }

    public function buy(Request $request, string $alias): RedirectResponse
    {
        $event = Event::where('alias', $alias)->where('is_published', true)->firstOrFail();

        $data = $request->validate([
            'buyer_name'  => ['required', 'string', 'max:120'],
            'buyer_phone' => ['nullable', 'string', 'max:40'],
            'buyer_email' => ['nullable', 'email', 'max:120'],
            'qty'         => ['required', 'array'],
            'qty.*'       => ['nullable', 'integer', 'min:0', 'max:50'],
        ]);

        $lines = [];
        foreach ($data['qty'] as $typeId => $qty) {
            if ((int) $qty > 0) {
                $lines[] = ['ticket_type_id' => (int) $typeId, 'qty' => (int) $qty];
            }
        }

        try {
            $order = $this->tickets->createOrder($event, $lines, [
                'name' => $data['buyer_name'], 'phone' => $data['buyer_phone'] ?? null, 'email' => $data['buyer_email'] ?? null,
            ]);
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['qty' => $e->getMessage()]);
        }

        if ($order->isPaid()) {
            $this->revenue->record('event_order', $order->id, 'event', (float) $order->total, $event->tenant_id, $order->currency);
        }

        $this->notifyBuyer($event, $order);

        return redirect()->route('tagtoa.event.order', $order->reference);
    }

    public function order(string $reference): View
    {
        $order = Order::where('reference', $reference)->with(['event.payPage', 'tickets.ticketType'])->firstOrFail();

        return view('tagtoa::event.order', ['order' => $order, 'event' => $order->event]);
    }

    public function ticket(string $code): View
    {
        $ticket = Ticket::where('code', $code)->with(['event', 'ticketType'])->firstOrFail();

        return view('tagtoa::event.ticket', ['ticket' => $ticket, 'event' => $ticket->event]);
    }

    /**
     * Message de confirmation au participant après l'achat en ligne
     * (WhatsApp + e-mail, opt-in/tolérant — ne bloque jamais la commande).
     */
    protected function notifyBuyer(Event $event, Order $order): void
    {
        try {
            $svc = app(\Modules\Tagtoa\App\Services\Notifications\NotificationService::class);
            $url = route('tagtoa.event.order', $order->reference);
            $status = $order->isPaid()
                ? __('Vos billets sont confirmés — présentez le QR à l\'entrée.')
                : __('Finalisez le paiement pour valider vos billets.');
            $body = __('Merci :name! Commande :ref pour :event. :status Vos billets : :url', [
                'name'   => $order->buyer_name,
                'ref'    => $order->reference,
                'event'  => $event->title,
                'status' => $status,
                'url'    => $url,
            ]);

            if ($order->buyer_phone) {
                $svc->push(['channels' => ['whatsapp'], 'phone' => $order->buyer_phone, 'subject' => $event->title, 'body' => $body]);
            }
            if ($order->buyer_email) {
                $svc->push(['channels' => ['email'], 'email' => $order->buyer_email, 'subject' => __('Vos billets').' — '.$event->title, 'body' => $body]);
            }
        } catch (\Throwable $e) {
            if (function_exists('report')) {
                report($e);
            }
        }
    }
}
