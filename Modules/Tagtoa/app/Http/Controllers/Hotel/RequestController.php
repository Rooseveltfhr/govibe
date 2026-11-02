<?php

namespace Modules\Tagtoa\App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Models\Hotel\HotelRequest;
use Modules\Tagtoa\App\Models\Hotel\HotelRoom;
use Modules\Tagtoa\App\Services\Audit\AuditService;
use Modules\Tagtoa\App\Services\Hotel\HotelRequestService as Svc;
use Modules\Tagtoa\App\Support\Hotel\HotelRequestRules as R;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA HÔTEL (H5) — les demandes des invités, côté hôtel (liste et traitement).
 * Les rôles du personnel (ménage, maintenance…) et les alertes arrivent avec H6.
 */
class RequestController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if (! ($hotel = $this->hotel())) {
            return redirect()->route('tagtoa.hub')->with('error', __('Cette section est réservée aux hôtels. Choisissez le type « Hôtel » dans votre commerce.'));
        }

        $filtre = $request->query('statut') === 'toutes' ? 'toutes' : 'ouvertes';
        $q = HotelRequest::query()->orderByRaw("CASE WHEN status IN ('new','acknowledged','in_progress') THEN 0 ELSE 1 END")->latest('id');
        if ($filtre === 'ouvertes') {
            $q->whereIn('status', R::OPEN);
        }

        $requests = $q->paginate(30)->withQueryString();

        return view('tagtoa::hotel.requests', [
            'hotel'    => $hotel,
            'requests' => $requests,
            'rooms'    => HotelRoom::whereIn('id', $requests->pluck('room_id')->unique())->get()->keyBy('id'),
            'filtre'   => $filtre,
            'statuses' => R::STATUSES,
            'next'     => R::TRANSITIONS,
        ]);
    }

    public function status(Request $request, int $id): RedirectResponse
    {
        if (! ($hotel = $this->hotel())) {
            return redirect()->route('tagtoa.hub');
        }
        // 404 si la demande n'est pas celle de CET hôtel (portée tenant).
        $req = HotelRequest::findOrFail($id);
        $data = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', array_keys(R::STATUSES))],
            'reason' => ['nullable', 'string', 'max:'.R::REASON_MAX],
        ]);

        $r = app(Svc::class)->setStatus($hotel->id, $req->id, $data['status'], $data['reason'] ?? null);
        if ($r['result'] !== Svc::OK) {
            return back()->with('error', __('Ce changement de statut n\'est pas possible.'));
        }

        app(AuditService::class)->log('hotel.request.'.$data['status'], $req, $req->reference);

        return back()->with('success', __('Demande :r mise à jour.', ['r' => $req->reference]));
    }

    /** Clôture en une fois les demandes cochées (parasites, oubliées) : refusées avec un motif standard. */
    public function bulk(Request $request): RedirectResponse
    {
        if (! ($hotel = $this->hotel())) {
            return redirect()->route('tagtoa.hub');
        }
        $data = $request->validate([
            'ids'   => ['required', 'array', 'min:1', 'max:'.R::BULK_MAX],
            'ids.*' => ['integer', 'min:1'],
        ]);

        $svc = app(Svc::class);
        $ok = 0;
        // Seules les demandes de CET hôtel (portée tenant + filtre explicite du service) peuvent être touchées.
        foreach (HotelRequest::whereIn('id', $data['ids'])->pluck('id') as $id) {
            if ($svc->setStatus($hotel->id, (int) $id, 'rejected', __('Clôturée par l\'hôtel'))['result'] === Svc::OK) {
                $ok++;
            }
        }
        app(AuditService::class)->log('hotel.request.bulk_closed', null, (string) $ok);

        return back()->with('success', __(':n demande(s) clôturée(s).', ['n' => $ok]));
    }

    private function hotel(): ?Business
    {
        $b = Business::where('account_id', Tenant::account())->whereKey(Tenant::id())->first();

        return $b && $b->type === 'hotel' ? $b : null;
    }
}
