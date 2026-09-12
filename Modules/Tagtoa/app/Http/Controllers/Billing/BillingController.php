<?php

namespace Modules\Tagtoa\App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Billing\Commission;
use Modules\Tagtoa\App\Models\Billing\RevenueSetting;
use Modules\Tagtoa\App\Support\Tenant;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BillingController extends Controller
{
    public function index(): View
    {
        $tenantId = Tenant::id();
        $setting  = RevenueSetting::resolve($tenantId);

        // Relevé par devise : brut, commission, net, à régler, réglé.
        $summary = $this->base($tenantId)->selectRaw(
            'currency, COUNT(*) AS n, '
            .'SUM(gross_amount) AS gross, SUM(commission_amount) AS fees, SUM(net_amount) AS net, '
            .'SUM(CASE WHEN status = '.Commission::STATUS_ACCRUED.' THEN commission_amount ELSE 0 END) AS accrued, '
            .'SUM(CASE WHEN status = '.Commission::STATUS_SETTLED.' THEN commission_amount ELSE 0 END) AS settled'
        )->groupBy('currency')->get();

        $accruedCount = (clone $this->base($tenantId))->where('status', Commission::STATUS_ACCRUED)->count();
        $commissions  = $this->base($tenantId)->latest()->paginate(15);

        return view('tagtoa::billing.index', compact('setting', 'commissions', 'summary', 'accruedCount'));
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

        app(\Modules\Tagtoa\App\Services\Audit\AuditService::class)->log('billing.updated');

        return back()->with('success', __('Modèle de revenu mis à jour.'));
    }

    /** Marque toutes les commissions « à régler » comme réglées (settlement). */
    public function settle(): RedirectResponse
    {
        // Écriture de masse : on refuse net plutôt que d'écrire sans savoir sur
        // quel commerce. base() protégerait déjà, mais un refus explicite dit au
        // marchand ce qui se passe au lieu de lui annoncer « 0 réglée ».
        $tenantId = Tenant::id();
        abort_if($tenantId === null || $tenantId === '', 403, __('Commerce introuvable.'));

        $n = $this->base($tenantId)
            ->where('status', Commission::STATUS_ACCRUED)
            ->update(['status' => Commission::STATUS_SETTLED, 'settled_at' => now()]);

        app(\Modules\Tagtoa\App\Services\Audit\AuditService::class)->log('billing.settled', null, (string) $n);

        return back()->with('success', __('Commissions réglées.').' ('.$n.')');
    }

    /** Export CSV du relevé de commissions. */
    public function export(): StreamedResponse
    {
        $tenantId = Tenant::id();
        $filename = 'tagtoa-commissions-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($tenantId) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['reference', 'module', 'gross', 'commission', 'net', 'currency', 'status', 'created_at', 'settled_at']);
            $this->base($tenantId)->orderBy('id')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $c) {
                    fputcsv($out, [
                        $c->source_type.'#'.$c->source_id,
                        $c->module,
                        $c->gross_amount,
                        $c->commission_amount,
                        $c->net_amount,
                        $c->currency,
                        Commission::STATUS_META[$c->status]['label'] ?? $c->status,
                        optional($c->created_at)->format('Y-m-d H:i'),
                        optional($c->settled_at)->format('Y-m-d H:i'),
                    ]);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Requête de base, TOUJOURS limitée à un commerce.
     *
     * Elle échouait en OUVERT : l'ancien `when($tenantId, …)` retirait purement
     * et simplement le filtre quand l'identifiant du commerce était absent
     * (session expirée, tâche en console, helper Biztap indisponible). Le relevé
     * affichait alors les commissions de TOUTE la plateforme, l'export CSV les
     * téléchargeait, et le règlement les marquait réglées d'un seul coup.
     *
     * Sans identifiant, la requête ne renvoie donc plus rien. Un écran vide se
     * remarque et se corrige ; des chiffres faux, non.
     */
    protected function base(?string $tenantId)
    {
        if ($tenantId === null || $tenantId === '') {
            return Commission::query()->whereRaw('1 = 0');
        }

        return Commission::query()->where('tenant_id', $tenantId);
    }
}
