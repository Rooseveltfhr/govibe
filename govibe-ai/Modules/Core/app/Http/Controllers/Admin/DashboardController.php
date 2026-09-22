<?php

namespace Modules\Core\Http\Controllers\Admin;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Modules\Agents\Models\Agent;
use Modules\Agents\Models\AgentOrder;
use Modules\Agents\Models\AgentPayment;
use Modules\AIProvider\Enums\Capability;
use Modules\AIProvider\Registry\ProviderRegistry;
use Modules\Core\Support\Settings;

/**
 * Premye ekran panèl la.
 *
 * Li pa montre volim: li montre sa ki mande yon aksyon. Yon kònè ki di
 * « 38 kòmand » pa di w anyen; « 4 kòmand pèsonn pa reponn » di w sa pou fè.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly ProviderRegistry $providers,
        private readonly Settings $settings,
    ) {}

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

        $chatProviders = $this->providers->configuredFor(Capability::Chat);
        $voiceProviders = $this->providers->configuredFor(Capability::Speech);
        $paymentReady = $this->settings->hasSecret('moncash_client_secret')
            || $this->settings->get('bank_htg') !== null
            || $this->settings->get('bank_usd') !== null;

        // Sa yon panèl pwofesyonèl montre an premye: sa ki bloke ajan yo
        // travay, pa yon chif vid. Yon lis tach vid se yon platfòm ki pare.
        $setup = [
            [
                'done' => $chatProviders !== [],
                'label' => __('Ajouter une clé IA'),
                'hint' => __('Sans elle, les agents ne peuvent pas répondre.'),
                'route' => route('admin.settings'),
            ],
            [
                'done' => $voiceProviders !== [],
                'label' => __('Ajouter une clé de voix'),
                'hint' => __('Optionnel : sans elle, les agents ne parlent pas.'),
                'route' => route('admin.settings'),
            ],
            [
                'done' => $paymentReady,
                'label' => __('Configurer le paiement'),
                'hint' => __('MonCash ou virement bancaire, pour les clients qui paient une commande.'),
                'route' => route('admin.payments'),
            ],
        ];

        return view('core::admin.dashboard', [
            'byStatus' => $byStatus,
            'newOrders' => AgentOrder::query()->where('status', 'nouvo')->latest()->limit(8)->get(),
            'agentCount' => Agent::query()->count(),
            'paid' => $paid,
            'chatProviders' => $chatProviders,
            'voiceProviders' => $voiceProviders,
            'setup' => $setup,
            'paymentReady' => $paymentReady,
        ]);
    }
}
