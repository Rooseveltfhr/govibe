<?php

namespace Modules\Tagtoa\App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Models\Hotel\HotelReservation;
use Modules\Tagtoa\App\Services\Audit\AuditService;
use Modules\Tagtoa\App\Services\Hotel\HotelReservationService as Svc;
use Modules\Tagtoa\App\Support\Hotel\HotelReservationRules as R;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA HÔTEL (HM-3) — côté HÔTEL : voir les demandes de réservation de l'hôtel COURANT, les confirmer ou les refuser.
 *
 * Réservé au type « hôtel » et isolé par commerce (l'identifiant d'une demande d'un autre hôtel est introuvable, 404).
 * Rien ne se supprime. Aucun paiement : la confirmation prévient l'invité sur sa page de suivi.
 */
class ReservationAdminController extends Controller
{
    private const PER_PAGE = 50;

    public function __construct(private Svc $reservations)
    {
    }

    public function index(Request $request): View|RedirectResponse
    {
        if (! ($hotel = $this->hotel())) {
            return $this->pasUnHotel();
        }

        $filtre = $request->query('status');
        $filtre = is_string($filtre) && isset(R::STATUSES[$filtre]) ? $filtre : null;

        $q = HotelReservation::query();
        if ($filtre) {
            $q->where('status', $filtre);
        }
        // Les nouvelles d'abord, puis par date d'arrivée.
        $liste = $q->orderByRaw("CASE status WHEN 'new' THEN 0 WHEN 'confirmed' THEN 1 ELSE 2 END")
            ->orderBy('starts_on')->orderByDesc('id')->limit(self::PER_PAGE)->get();

        return view('tagtoa::hotel.reservations', [
            'hotel'    => $hotel,
            'liste'    => $liste,
            'filtre'   => $filtre,
            'statuses' => R::STATUSES,
            'compte'   => HotelReservation::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->all(),
            'max'      => self::PER_PAGE,
        ]);
    }

    public function status(Request $request, int $id): RedirectResponse
    {
        if (! ($hotel = $this->hotel())) {
            return $this->pasUnHotel();
        }

        $data = $request->validate([
            'to'     => ['required', 'string', 'in:confirmed,refused'],
            'reason' => ['nullable', 'string', 'max:'.R::REASON_MAX],
        ]);

        $r = $this->reservations->setStatus($hotel->id, $id, $data['to'], $data['reason'] ?? null);
        if ($r['result'] === Svc::NOT_FOUND) {
            abort(404);
        }
        if ($r['result'] !== Svc::OK) {
            return back()->with('error', __('Cette demande ne peut plus passer à cet état.'));
        }

        app(AuditService::class)->log('hotel.reservation.'.$data['to'], $r['reservation'], $r['reservation']->reference);

        return back()->with('success', $data['to'] === 'confirmed' ? __('Réservation confirmée.') : __('Demande refusée.'));
    }

    /* ----------------------------------------------------------------- */

    /** Le commerce courant, s'il est de type hôtel et appartient au compte connecté. */
    private function hotel(): ?Business
    {
        $b = Business::where('account_id', Tenant::account())->whereKey(Tenant::id())->first();

        return $b && $b->type === 'hotel' ? $b : null;
    }

    private function pasUnHotel(): RedirectResponse
    {
        return redirect()->route('tagtoa.hub')->with('error', __('Cette section est réservée aux hôtels. Choisissez le type « Hôtel » dans votre commerce.'));
    }
}
