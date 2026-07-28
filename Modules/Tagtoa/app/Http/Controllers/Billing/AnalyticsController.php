<?php

namespace Modules\Tagtoa\App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Modules\Tagtoa\App\Services\Billing\AnalyticsService;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA — tableau de bord analytique marchand.
 */
class AnalyticsController extends Controller
{
    public function index(): View
    {
        $a = new AnalyticsService(Tenant::id());

        return view('tagtoa::billing.analytics', [
            'revenue'  => $a->revenueByCurrency(),
            'counts'   => $a->counts(),
            'byModule' => $a->ordersByModule(),
            'daily'    => $a->dailyRevenue(14),
            'topItems' => $a->topMenuItems(5),
        ]);
    }
}
