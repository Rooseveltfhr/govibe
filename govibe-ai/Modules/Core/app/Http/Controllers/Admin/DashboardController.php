<?php

namespace Modules\Core\Http\Controllers\Admin;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Modules\Agents\Models\Agent;
use Modules\Agents\Models\AgentOrder;
use Modules\Agents\Models\AgentPayment;
use Modules\AIProvider\Enums\Capability;
use Modules\AIProvider\Registry\ProviderRegistry;

/**
 * Premye ekran panèl la.
 *
 * Li pa montre volim: li montre sa ki mande yon aksyon. Yon kònè ki di
 * « 38 kòmand » pa di w anyen; « 4 kòmand pèsonn pa reponn » di w sa pou fè.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly ProviderRegistry $providers) {}

    public function index(): View
    {
        $byStatus = AgentOrder::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        // `pluck` sou yon agrega: pa gen pwopriyete envante sou modèl la.
        $paid = AgentPayment::query()
            ->selectRaw('currency, sum(amount_minor) as total')
            ->groupBy('currency')
            ->pluck('total', 'currency')
            ->map(static fn ($total): int => (int) $total)
            ->all();

        return view('core::admin.dashboard', [
            'byStatus' => $byStatus,
            'newOrders' => AgentOrder::query()->where('status', 'nouvo')->latest()->limit(8)->get(),
            'agentCount' => Agent::query()->count(),
            'paid' => $paid,
            'chatProviders' => $this->providers->configuredFor(Capability::Chat),
            'voiceProviders' => $this->providers->configuredFor(Capability::Speech),
        ]);
    }
}
