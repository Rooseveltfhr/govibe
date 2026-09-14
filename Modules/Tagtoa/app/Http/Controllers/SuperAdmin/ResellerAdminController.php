<?php

namespace Modules\Tagtoa\App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Stand\Reseller;
use Modules\Tagtoa\App\Models\Stand\Stand;
use Modules\Tagtoa\App\Models\Stand\StandBatch;
use Modules\Tagtoa\App\Services\Audit\AuditService;
use Modules\Tagtoa\App\Services\Stand\ResellerService;
use Modules\Tagtoa\App\Support\Stand\StandState;

/**
 * TAGTOA SMART STAND — le réseau de distribution, vu du fondateur.
 *
 * Il crée les revendeurs et leur affecte des CARTONS — c'est-à-dire des plages.
 * On n'affecte pas trente stands choisis un par un : on envoie le carton 41–80.
 * Faire autrement obligerait à cocher trente cases pour décrire un geste qui
 * en est un seul.
 *
 * ⚠️ Cet écran ne montre pas non plus les codes d'activation. Le fondateur les
 * a, mais ailleurs : dans le fichier de frappe, produit une fois, en 0600, et
 * détruit après tirage. Les remettre dans une page web les rendrait
 * consultables depuis n'importe quel navigateur resté ouvert.
 */
class ResellerAdminController extends Controller
{
    public function __construct(protected ResellerService $service)
    {
    }

    public function index(): View
    {
        $resellers = Reseller::orderBy('name')->get();

        // L'inventaire de chacun, en UNE requête plutôt qu'une par revendeur :
        // à cinquante revendeurs, la page ferait cinquante allers-retours.
        $compte = Stand::where('holder_type', Stand::HOLDER_RESELLER)
            ->selectRaw('holder_id, physical_state, COUNT(*) as n')
            ->groupBy('holder_id', 'physical_state')
            ->get()
            ->groupBy('holder_id');

        return view('tagtoa::superadmin.resellers', [
            'resellers' => $resellers,
            'compte'    => $compte,
            'batches'   => StandBatch::orderByDesc('id')->get(['id', 'code', 'range_start', 'range_end']),
            'etats'     => StandState::PHYSICAL_LABELS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            // Le commerce QUI EST ce revendeur. Unique : un commerce ne peut
            // pas être deux revendeurs, sinon son stock se dédoublerait.
            'business_id'    => ['required', 'string', 'max:64', 'unique:tagtoa_stand_resellers,business_id'],
            'name'           => ['required', 'string', 'max:120'],
            'contact_phone'  => ['nullable', 'string', 'max:40'],
            'zone'           => ['nullable', 'string', 'max:80'],
            'commission_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes'          => ['nullable', 'string', 'max:255'],
        ]);

        Reseller::create($data + ['is_active' => true]);

        return back()->with('success', __('Revendeur « :nom » créé.', ['nom' => $data['name']]));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'name'           => ['required', 'string', 'max:120'],
            'contact_phone'  => ['nullable', 'string', 'max:40'],
            'zone'           => ['nullable', 'string', 'max:80'],
            'commission_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes'          => ['nullable', 'string', 'max:255'],
            'is_active'      => ['nullable', 'boolean'],
        ]);

        $r = Reseller::findOrFail($id);
        $r->update($data + ['is_active' => $request->boolean('is_active')]);

        // Désactiver un revendeur lui FERME la console immédiatement (elle
        // relit `is_active` à chaque requête) — mais ne lui reprend pas son
        // stock : les cartons sont chez lui, et le prétendre autrement rendrait
        // l'inventaire faux.
        app(AuditService::class)->log('stand.reseller_updated', null,
            $r->name.($r->is_active ? '' : ' — désactivé'));

        return back()->with('success', __('Revendeur mis à jour.'));
    }

    /** Affecte un carton — une plage — à un revendeur. */
    public function allocate(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'batch_id' => ['required', 'integer'],
            'from'     => ['required', 'integer', 'min:1'],
            'to'       => ['required', 'integer', 'min:1'],
        ]);

        $reseller = Reseller::findOrFail($id);
        abort_unless(StandBatch::whereKey($data['batch_id'])->exists(), 404);

        $r = $this->service->allocate($reseller, (int) $data['batch_id'],
            (int) $data['from'], (int) $data['to'], [
                'actor_name' => optional($request->user())->name,
                'ip'         => $request->ip(),
            ]);

        if ($r['result'] !== ResellerService::OK) {
            return back()->with('error', __(
                'Aucun stand affecté. Cette plage est vide, déjà affectée, ou dépasse :max unités.',
                ['max' => ResellerService::MAX_PLAGE]
            ));
        }

        app(AuditService::class)->log('stand.allocated', null,
            $reseller->name.' — '.$r['count'].' stands');

        return back()->with('success', trans_choice(
            '{1}:count stand affecté à :nom.|[2,*]:count stands affectés à :nom.',
            $r['count'], ['count' => $r['count'], 'nom' => $reseller->name]
        ));
    }
}
