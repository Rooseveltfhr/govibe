<?php

namespace Modules\Tagtoa\App\Http\Controllers\Stand;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Stand\Stand;
use Modules\Tagtoa\App\Services\Stand\StandResolver;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA SMART STAND — les stands du commerce, vus du patron.
 *
 * Le modèle n'a PAS de portée automatique par commerce : un stand non réclamé
 * n'appartient à personne, et l'isoler le rendrait introuvable au scan. Le
 * cloisonnement s'écrit donc explicitement ici, à chaque requête — c'est le
 * prix de l'exemption, et il se paie en toutes lettres.
 */
class StandController extends Controller
{
    public function __construct(protected StandResolver $resolver)
    {
    }

    public function index(): View
    {
        return view('tagtoa::stand.index', [
            'stands' => Stand::ofBusiness(Tenant::id())->orderBy('public_id')->get(),
        ]);
    }

    /**
     * Nomme l'emplacement d'un stand : « Table 05 », « Comptoir », « Chambre 12 ».
     *
     * Un libellé, pas une entité : il n'a ni cycle de vie ni relations, et une
     * table dédiée ajouterait une jointure à la requête la plus fréquente.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'location_label' => ['nullable', 'string', 'max:60'],
            'target_module'  => ['nullable', 'string', 'in:menu,links,pay'],
        ]);

        $stand = $this->own($id);

        $stand->update([
            'location_label' => $data['location_label'] ?? null,
            'target_module'  => $data['target_module'] ?? $stand->target_module,
        ]);

        // Sans cet oubli-là, l'ancienne destination continuerait d'être servie
        // une heure durant — c'est-à-dire le menu d'hier au client d'aujourd'hui.
        $this->resolver->forget($stand->public_id);

        return back()->with('success', __('Stand mis à jour.'));
    }

    /** Un stand de CE commerce, ou 404. Jamais un find() nu. */
    private function own(int $id): Stand
    {
        return Stand::ofBusiness(Tenant::id())->whereKey($id)->firstOrFail();
    }
}
