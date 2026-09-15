<?php

namespace Modules\Core\Http\Controllers\Admin;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Agents\Models\AgentOrder;
use Modules\Agents\Models\AgentPayment;
use Modules\Agents\Templates\AgentTemplateRegistry;

/**
 * Kòmand kliyan yo: lis, dosye, estati, nòt entèn, peman.
 */
class OrderController extends Controller
{
    public function __construct(private readonly AgentTemplateRegistry $templates) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(AgentOrder::STATUSES)],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $orders = AgentOrder::query()
            ->withCount('payments')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['q'] ?? null, function ($query, $term) {
                // Twa chan moun nan chèche: referans, non biznis, WhatsApp.
                $query->where(function ($inner) use ($term) {
                    $inner->where('reference', 'like', "%{$term}%")
                        ->orWhere('business_name', 'like', "%{$term}%")
                        ->orWhere('whatsapp', 'like', "%{$term}%");
                });
            })
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('core::admin.orders.index', [
            'orders' => $orders,
            'status' => $filters['status'] ?? null,
            'q' => $filters['q'] ?? null,
        ]);
    }

    public function show(AgentOrder $order): View
    {
        return view('core::admin.orders.show', [
            'order' => $order->load('payments'),
            'template' => $this->templates->get($order->sector),
        ]);
    }

    public function update(Request $request, AgentOrder $order): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(AgentOrder::STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $order->update($data);

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('status', __('Dossier mis à jour.'));
    }

    /**
     * Ajoute yon peman.
     *
     * Montan an antre an inite majè (goud, dola) paske se konsa moun nan
     * ekri l; nou sere l an inite minè. Yon `* 100` sou yon flotan bay
     * 4999 pou 49.99 — nou pase pa yon chèn epi nou wonn yon sèl fwa.
     */
    public function storePayment(Request $request, AgentOrder $order): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'currency' => ['required', Rule::in(['HTG', 'USD'])],
            'method' => ['required', Rule::in(AgentPayment::METHODS)],
            'reference' => ['nullable', 'string', 'max:120'],
            'received_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $order->payments()->create([
            ...$data,
            'amount_minor' => (int) round(((float) $data['amount']) * 100),
        ]);

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('status', __('Paiement enregistré.'));
    }

    public function destroyPayment(AgentOrder $order, AgentPayment $payment): RedirectResponse
    {
        // Yon peman ki pa pou dosye sa a pa dwe ka efase depi isit la.
        abort_unless($payment->agent_order_id === $order->id, 404);

        $payment->delete();

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('status', __('Paiement supprimé.'));
    }
}
