<?php

namespace Modules\Tagtoa\App\Services\Billing;

use Modules\Tagtoa\App\Models\Billing\Commission;
use Modules\Tagtoa\App\Models\Billing\Subscription;

/**
 * TAGTOA SUPER-ADMIN — agrégation CROSS-TENANT (vue plateforme du fondateur).
 *
 * Contrairement aux services marchands (scopés par Tenant::id()), celui-ci
 * agrège TOUS les tenants : revenu global, commissions, abonnements, top
 * marchands. Lecture seule. Réservé au rôle super_admin (garde au niveau route).
 */
class SuperAdminService
{
    /** Revenu brut + commission par devise (toutes commissions non annulées). */
    public function revenueByCurrency(): array
    {
        return Commission::where('status', '!=', Commission::STATUS_VOID)
            ->selectRaw('currency, SUM(gross_amount) AS gross, SUM(commission_amount) AS commission, COUNT(*) AS n')
            ->groupBy('currency')->get()
            ->map(fn ($r) => [
                'currency'   => $r->currency,
                'gross'      => (float) $r->gross,
                'commission' => (float) $r->commission,
                'count'      => (int) $r->n,
            ])->all();
    }

    /** Commissions à régler vs réglées, par devise. */
    public function commissionByStatus(): array
    {
        return Commission::selectRaw('currency, status, SUM(commission_amount) AS amount')
            ->whereIn('status', [Commission::STATUS_ACCRUED, Commission::STATUS_SETTLED])
            ->groupBy('currency', 'status')->get()
            ->map(fn ($r) => [
                'currency' => $r->currency,
                'status'   => (int) $r->status,
                'label'    => $r->status === Commission::STATUS_SETTLED ? 'Réglé' : 'À régler',
                'amount'   => (float) $r->amount,
            ])->all();
    }

    /** Revenu brut par module (menu, store, event, booking…). */
    public function revenueByModule(): array
    {
        return Commission::where('status', '!=', Commission::STATUS_VOID)
            ->selectRaw('module, SUM(gross_amount) AS gross, COUNT(*) AS n')
            ->groupBy('module')->orderByDesc('gross')->get()
            ->map(fn ($r) => ['module' => $r->module, 'gross' => (float) $r->gross, 'count' => (int) $r->n])
            ->all();
    }

    /** Top marchands par revenu brut cumulé. */
    public function topMerchants(int $limit = 10): array
    {
        return Commission::where('status', '!=', Commission::STATUS_VOID)
            ->selectRaw('tenant_id, SUM(gross_amount) AS gross, COUNT(*) AS n')
            ->groupBy('tenant_id')->orderByDesc('gross')->limit($limit)->get()
            ->map(fn ($r) => ['tenant_id' => $r->tenant_id, 'gross' => (float) $r->gross, 'count' => (int) $r->n])
            ->all();
    }

    /** Répartition des abonnements par forfait. */
    public function planCounts(): array
    {
        $counts = Subscription::selectRaw('plan, status, COUNT(*) AS n')
            ->groupBy('plan', 'status')->get();

        $out = [];
        foreach (array_keys((array) config('tagtoa.plans', [])) as $plan) {
            $out[$plan] = ['plan' => $plan, 'active' => 0, 'total' => 0];
        }
        foreach ($counts as $c) {
            $out[$c->plan] ??= ['plan' => $c->plan, 'active' => 0, 'total' => 0];
            $out[$c->plan]['total'] += (int) $c->n;
            if ($c->status === 'active') {
                $out[$c->plan]['active'] += (int) $c->n;
            }
        }

        return array_values($out);
    }

    /** Liste des marchands (abonnements) avec forfait + statut, paginable. */
    public function merchants(int $perPage = 25)
    {
        return Subscription::orderByDesc('started_at')->paginate($perPage);
    }

    /** Compteurs de tête : marchands, abonnements actifs. */
    public function totals(): array
    {
        $tenants = Subscription::distinct('tenant_id')->count('tenant_id');
        $active = Subscription::where('status', 'active')->count();

        return ['merchants' => $tenants, 'active_subscriptions' => $active];
    }
}
