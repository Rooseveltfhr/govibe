<?php

namespace Modules\Tagtoa\App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Billing\Commission;
use Modules\Tagtoa\App\Models\Billing\RevenueSetting;
use Modules\Tagtoa\App\Support\Tenant;

class BillingController extends Controller
{
    public function index(): View
    {
        $tenantId = Tenant::id();
        $setting  = RevenueSetting::resolve($tenantId);

        $q = Commission::when($tenantId, fn ($x) => $x->where('tenant_id', $tenantId));
        $totals = [
            'gross' => (clone $q)->sum('gross_amount'),
            'fees'  => (clone $q)->sum('commission_amount'),
            'net'   => (clone $q)->sum('net_amount'),
        ];
        $commissions = $q->latest()->paginate(15);

        return view('tagtoa::billing.index', compact('setting', 'commissions', 'totals'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'revenue_model'      => ['required', 'in:subscription,commission,both'],
            'commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'commission_fixed'   => ['nullable', 'numeric', 'min:0'],
            'currency'           => ['nullable', 'string', 'max:10'],
        ]);

        RevenueSetting::updateOrCreate(
            ['tenant_id' => Tenant::id()],
            [
                'revenue_model'      => $data['revenue_model'],
                'commission_percent' => $data['commission_percent'] ?? 0,
                'commission_fixed'   => $data['commission_fixed'] ?? 0,
                'currency'           => $data['currency'] ?? 'HTG',
                'is_active'          => true,
            ]
        );

        return back()->with('success', __('Modèle de revenu mis à jour.'));
    }
}
