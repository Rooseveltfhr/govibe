<?php

namespace Modules\Tagtoa\App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Billing\Subscription;
use Modules\Tagtoa\App\Services\Billing\PlanService;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA — abonnement marchand (forfait) : aperçu, usage vs limites, changement.
 */
class PlanController extends Controller
{
    public function __construct(protected PlanService $plans)
    {
    }

    public function index(): View
    {
        $tid = Tenant::id();
        $current = $this->plans->planKey($tid);

        $usage = [];
        foreach ($this->plans->features() as $f) {
            $usage[$f] = ['used' => $this->plans->usage($tid, $f), 'limit' => $this->plans->limit($tid, $f)];
        }

        return view('tagtoa::billing.plan', [
            'plans'   => (array) config('tagtoa.plans', []),
            'current' => $current,
            'usage'   => $usage,
        ]);
    }

    public function subscribe(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', Rule::in(array_keys((array) config('tagtoa.plans', [])))],
        ]);

        // Self-service pour l'instant (le paiement du forfait viendra avec PAY auto).
        Subscription::updateOrCreate(
            ['tenant_id' => Tenant::id()],
            ['plan' => $data['plan'], 'status' => 'active', 'started_at' => now()]
        );

        return back()->with('success', __('Forfait mis à jour.'));
    }
}
