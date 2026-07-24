<?php

namespace Modules\Tagtoa\App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Modules\Tagtoa\App\Services\Billing\SuperAdminService;

/**
 * TAGTOA SUPER-ADMIN — tableau de bord plateforme (fondateur, rôle super_admin).
 *
 * Vue CROSS-TENANT de tout TAGTOA : revenu global, commissions, abonnements,
 * top marchands, revenu par module. Réservé au super_admin (garde route).
 * Lecture seule — aucune action destructive ici.
 */
class DashboardController extends Controller
{
    public function __construct(protected SuperAdminService $svc)
    {
    }

    public function index(): View
    {
        return view('tagtoa::superadmin.index', [
            'totals'         => $this->svc->totals(),
            'revenue'        => $this->svc->revenueByCurrency(),
            'commission'     => $this->svc->commissionByStatus(),
            'byModule'       => $this->svc->revenueByModule(),
            'topMerchants'   => $this->svc->topMerchants(),
            'plans'          => $this->svc->planCounts(),
            'merchants'      => $this->svc->merchants(),
        ]);
    }
}
