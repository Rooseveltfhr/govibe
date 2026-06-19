<?php

namespace App\Http\Controllers;

use App\Models\TaGtoaCommission;
use App\Models\TaGtoaRevenueSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * TAGTOA BILLING — dashboard du modèle de revenu + commissions.
 *
 * Le marchand choisit son modèle (abonnement / commission / les deux) et
 * consulte le journal des commissions prélevées sur ses ventes.
 */
class TaGtoaBillingController extends Controller
{
    public function index(): View
    {
        $tenantId = function_exists('getLogInTenantId') ? getLogInTenantId() : null;
        $setting  = TaGtoaRevenueSetting::resolve($tenantId);

        $commissions = TaGtoaCommission::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->latest()
            ->paginate(20);

        $totals = [
            'gross' => (clone $commissions)->getCollection()->sum('gross_amount'),
            'fees'  => TaGtoaCommission::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->sum('commission_amount'),
            'net'   => TaGtoaCommission::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->sum('net_amount'),
        ];

        return view('tagtoa.billing.index', compact('setting', 'commissions', 'totals'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'revenue_model'      => ['required', 'in:subscription,commission,both'],
            'commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'commission_fixed'   => ['nullable', 'numeric', 'min:0'],
            'currency'           => ['nullable', 'string', 'max:10'],
        ]);

        $tenantId = function_exists('getLogInTenantId') ? getLogInTenantId() : null;

        TaGtoaRevenueSetting::updateOrCreate(
            ['tenant_id' => $tenantId],
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
