<?php

namespace Modules\Agents\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Agents\Models\AgentOrder;
use Modules\Agents\Templates\AgentTemplateRegistry;

/**
 * Kòmande yon ajan.
 *
 * Rezon paj sa a egziste: tout machann pa gen tan (ni anvi) ranpli yon fòm
 * konesans. Sa yo mande yon ekspè fè travay la. Fòm nan pa mande anyen li
 * pa bezwen — non biznis lan ak yon WhatsApp ase pou nou rele moun nan.
 */
class OrderController extends Controller
{
    public function __construct(private readonly AgentTemplateRegistry $templates) {}

    public function create(?string $sector = null): View
    {
        if ($sector !== null && ! $this->templates->has($sector)) {
            abort(404);
        }

        return view('agents::orders.create', [
            'templates' => $this->templates->all(),
            'selected' => $sector,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sector' => ['required', 'string', Rule::in(array_map(
                static fn ($t) => $t->sector,
                $this->templates->all(),
            ))],
            'business_name' => ['required', 'string', 'max:160'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            // WhatsApp obligatwa: se sèl chemen ki mache toutbon an Ayiti.
            'whatsapp' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:160'],
            'mode' => ['required', Rule::in([AgentOrder::MODE_EXPERT, AgentOrder::MODE_SELF])],
            'channels' => ['nullable', 'array'],
            'channels.*' => [Rule::in(AgentOrder::CHANNELS)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $order = AgentOrder::create([
            ...$data,
            'reference' => AgentOrder::newReference(),
            'channels' => array_values($data['channels'] ?? ['whatsapp']),
            'status' => 'nouvo',
        ]);

        return redirect()->route('orders.show', $order->reference);
    }

    /**
     * Konfimasyon an pa montre yon lis kòmand: li montre YON kòmand, epi
     * referans lan pa yon nimewo ki swiv (1, 2, 3…) ki ta kite nenpòt moun
     * li kòmand tout lòt moun.
     */
    public function show(string $reference): View
    {
        $order = AgentOrder::query()->where('reference', $reference)->firstOrFail();

        return view('agents::orders.show', [
            'order' => $order,
            'template' => $this->templates->get($order->sector),
        ]);
    }
}
