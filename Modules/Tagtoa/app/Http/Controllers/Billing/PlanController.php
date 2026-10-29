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
            'plans'   => PlanService::effectivePlans(),
            'current' => $current,
            'usage'   => $usage,
        ]);
    }

    public function subscribe(Request $request): RedirectResponse
    {
        $plans = PlanService::effectivePlans();
        $data = $request->validate([
            'plan' => ['required', Rule::in(array_keys($plans))],
        ]);
        $plan = $data['plan'];

        // « Sur devis » = prix NULL. Surtout pas `?? 0` : null devenait 0, le
        // forfait passait pour gratuit et s'activait sans date de fin — tout
        // illimité, cartes NFC comprises, pour rien. Un forfait sans prix se
        // négocie, il ne se prend pas en libre-service.
        $rawPrice = $plans[$plan]['price'] ?? null;
        if ($rawPrice === null) {
            return back()->with('error', __('Ce forfait est sur devis. Contactez-nous pour y souscrire.'));
        }

        // Un forfait désactivé par l'administrateur ne se souscrit plus (les
        // abonnements déjà en cours ne sont pas touchés).
        if (($plans[$plan]['is_active'] ?? true) === false) {
            return back()->with('error', __('Ce forfait n\'est plus disponible.'));
        }

        $price = (float) $rawPrice;

        // Forfait PAYANT + passerelle active → paiement avant activation.
        if ($price > 0) {
            $url = app(\Modules\Tagtoa\App\Services\Pay\CheckoutService::class)
                ->startSubscription(Tenant::id(), $plan);
            if ($url) {
                return redirect()->away($url); // le forfait s'active au retour de paiement (confirm)
            }
            // Pas de passerelle configurée : on n'accorde pas un forfait payant gratuitement.
            return back()->with('error', __('Le paiement en ligne n\'est pas encore activé. Contactez-nous pour souscrire.'));
        }

        // Forfait gratuit → self-service immédiat.
        Subscription::updateOrCreate(
            ['tenant_id' => Tenant::id()],
            ['plan' => $plan, 'status' => 'active', 'started_at' => now(), 'expires_at' => null]
        );

        app(\Modules\Tagtoa\App\Services\Audit\AuditService::class)->log('plan.changed', null, $plan);

        return back()->with('success', __('Forfait mis à jour.'));
    }
}
