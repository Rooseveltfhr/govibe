<?php

namespace Modules\Tagtoa\App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Billing\PlanDefinition;
use Modules\Tagtoa\App\Services\Audit\AuditService;
use Modules\Tagtoa\App\Services\Billing\PlanService;

/**
 * TAGTOA SUPER-ADMIN — édition des forfaits (prix + limites), fondateur uniquement.
 * Les valeurs saisies surchargent la config `tagtoa.plans` (table tagtoa_plans).
 */
class PlanController extends Controller
{
    /** Fonctionnalités limitables (clés de `limits`). */
    protected const FEATURES = ['site', 'menu', 'pay', 'links', 'loyalty', 'event', 'pos', 'booking', 'staff', 'store'];

    public function index(): View
    {
        return view('tagtoa::superadmin.plans', [
            'plans'    => PlanService::effectivePlans(),
            'features' => self::FEATURES,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plans'                  => ['required', 'array'],
            'plans.*.label'          => ['nullable', 'string', 'max:60'],
            'plans.*.price'          => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'plans.*.limits'         => ['nullable', 'array'],
            'plans.*.limits.*'       => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $sort = 0;
        foreach ($data['plans'] as $key => $row) {
            $key = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $key));
            if ($key === '') {
                continue;
            }

            // Limites : champ vide => null (illimité) ; "0" => bloqué ; N => max.
            $limits = [];
            foreach (self::FEATURES as $f) {
                $v = $row['limits'][$f] ?? null;
                $limits[$f] = ($v === null || $v === '') ? null : (int) $v;
            }

            PlanDefinition::updateOrCreate(
                ['plan_key' => $key],
                [
                    'label'     => ($row['label'] ?? '') !== '' ? $row['label'] : ucfirst($key),
                    'price'     => ($row['price'] ?? '') === '' ? null : (float) $row['price'],
                    'limits'    => $limits,
                    'sort'      => $sort++,
                    'is_active' => true,
                ]
            );
        }

        PlanService::flush();
        app(AuditService::class)->log('plan.config_updated', null, implode(', ', array_keys($data['plans'])));

        return back()->with('success', __('Forfaits mis à jour.'));
    }
}
