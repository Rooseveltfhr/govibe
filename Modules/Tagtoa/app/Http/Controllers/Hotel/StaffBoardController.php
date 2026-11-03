<?php

namespace Modules\Tagtoa\App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Models\Hotel\HotelRoom;
use Modules\Tagtoa\App\Models\Staff\Staff;
use Modules\Tagtoa\App\Services\Audit\AuditService;
use Modules\Tagtoa\App\Services\Hotel\HotelRequestService as Svc;
use Modules\Tagtoa\App\Services\Hotel\HotelReservationService as ResaSvc;
use Modules\Tagtoa\App\Support\Hotel\HotelReservationRules as ResaR;
use Modules\Tagtoa\App\Services\Staff\StaffService;
use Modules\Tagtoa\App\Support\Hotel\HotelRequestRules as R;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA HÔTEL (H6) — l'écran des demandes pour le PERSONNEL (réception, ménage, maintenance).
 *
 * Même principe que l'écran cuisine du Menu : une tablette de l'hôtel reste connectée au compte,
 * l'employé s'identifie par son code, et l'écran lui montre — et ne lui laisse traiter — que les
 * demandes de SON équipe. Le contrôle est fait côté serveur (HotelRequestService), pas seulement
 * dans l'affichage. Le compte TAGTOA reste la vraie frontière d'authentification (voir
 * GuardsStaffAbility) : cet écran restreint ce que l'écran montre et attribue chaque action à
 * une personne ; il ne remplace pas la protection de l'appareil.
 */
class StaffBoardController extends Controller
{
    public function __construct(protected StaffService $staff, protected Svc $requests, protected ResaSvc $reservations)
    {
    }

    public function index(): View|RedirectResponse
    {
        if (! ($hotel = $this->hotel())) {
            return $this->pasUnHotel();
        }
        $me = $this->staff->forHotel($hotel->id);
        if (! $me) {
            return view('tagtoa::hotel.board-login', ['hotel' => $hotel]);
        }

        // Les plus RÉCENTES d'abord : avec les plus anciennes d'abord, 100 demandes oubliées cachaient pour toujours
        // la nouvelle demande d'un invité. Le total ouvert est affiché pour que rien ne soit caché sans le dire.
        $ouvertes = $this->requests->queryFor($hotel->id, $me)->whereIn('status', R::OPEN);
        $totalOuvertes = (clone $ouvertes)->count();
        $requests = (clone $ouvertes)->orderByDesc('id')->limit(100)->get();

        // Réservations (HM-3b) : nouvelles seulement, les plus récentes d'abord ; vide pour ménage et maintenance.
        $resaNew = $this->reservations->queryFor($hotel->id, $me)->where('status', 'new');
        $totalResas = (clone $resaNew)->count();

        return view('tagtoa::hotel.board', [
            'resas'      => (clone $resaNew)->orderByDesc('id')->limit(100)->get(),
            'totalResas' => $totalResas,
            'hotel'    => $hotel,
            'me'       => $me,
            'requests' => $requests,
            'totalOuvertes' => $totalOuvertes,
            'rooms'    => HotelRoom::whereIn('id', $requests->pluck('room_id')->unique())->get()->keyBy('id'),
            'statuses' => R::STATUSES,
            'next'     => R::TRANSITIONS,
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        if (! ($hotel = $this->hotel())) {
            return $this->pasUnHotel();
        }
        $data = $request->validate(['pin' => ['required', 'string', 'max:12']]);

        // Verrou contre la devinette du code : 5 échecs par appareil et par hôtel, puis 5 minutes d'attente.
        // Deux verrous, jamais remis à zéro par une réussite (sinon un employé qui connaît SON code
        // l'utiliserait pour effacer ses essais ratés sur le code d'un gérant) :
        //  - par appareil : 5 échecs puis 5 minutes ;
        //  - par hôtel, toutes adresses confondues : 20 échecs par heure (adresse partagée ou changeante).
        $cle = 'hotel-board-pin:'.$hotel->id.'|'.$request->ip();
        $cleHotel = 'hotel-board-pin-all:'.$hotel->id;
        if (RateLimiter::tooManyAttempts($cle, 5) || RateLimiter::tooManyAttempts($cleHotel, 20)) {
            return back()->withErrors(['pin' => __('Trop d\'essais. Réessayez dans quelques minutes.')]);
        }

        $me = $this->staff->authenticateHotel($hotel->id, $data['pin']);
        if (! $me) {
            RateLimiter::hit($cle, 300);
            RateLimiter::hit($cleHotel, 3600);

            return back()->withErrors(['pin' => __('Code incorrect.')]);
        }

        $request->session()->put('tagtoa_hotel_staff.'.$hotel->id, $me->id);
        $request->session()->regenerate();   // l'identification change de personne : nouvel identifiant de session
        app(AuditService::class)->log('hotel.board.login', null, $me->name);

        return redirect()->route('tagtoa.hotel.board');
    }

    public function logout(Request $request): RedirectResponse
    {
        if ($hotel = $this->hotel()) {
            $request->session()->forget('tagtoa_hotel_staff.'.$hotel->id);
        }

        return redirect()->route('tagtoa.hotel.board');
    }

    public function status(Request $request, int $id): RedirectResponse
    {
        if (! ($hotel = $this->hotel())) {
            return $this->pasUnHotel();
        }
        $me = $this->staff->forHotel($hotel->id);
        if (! $me) {
            return redirect()->route('tagtoa.hotel.board');   // session employé fermée : retour au code
        }
        $data = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', array_keys(R::STATUSES))],
            'reason' => ['nullable', 'string', 'max:'.R::REASON_MAX],
        ]);

        $r = $this->requests->setStatus($hotel->id, $id, $data['status'], $data['reason'] ?? null, $me);
        if ($r['result'] === Svc::NOT_FOUND) {
            abort(404);   // hors de son équipe (ou d'un autre hôtel) : la demande n'existe pas pour lui
        }
        if ($r['result'] !== Svc::OK) {
            return back()->with('error', __('Ce changement de statut n\'est pas possible.'));
        }

        app(AuditService::class)->log('hotel.request.'.$data['status'], $r['request'], $r['request']->reference.' · '.$me->name);

        return back()->with('success', __('Demande :r mise à jour.', ['r' => $r['request']->reference]));
    }

    /** Réservation (HM-3b) : confirmer ou refuser, réservé patron, gérant, réception ; toute autre personne → 404 neutre. */
    public function reservationStatus(Request $request, int $id): RedirectResponse
    {
        if (! ($hotel = $this->hotel())) {
            return $this->pasUnHotel();
        }
        $me = $this->staff->forHotel($hotel->id);
        if (! $me) {
            return redirect()->route('tagtoa.hotel.board');
        }
        if (! ResaR::staffMaySee($me->role, $me->hotel_team)) {
            abort(404);
        }
        $data = $request->validate([
            'to'     => ['required', 'string', 'in:confirmed,refused'],
            'reason' => ['nullable', 'string', 'max:'.ResaR::REASON_MAX],
        ]);

        $r = $this->reservations->setStatus($hotel->id, $id, $data['to'], $data['reason'] ?? null);
        if ($r['result'] === ResaSvc::NOT_FOUND) {
            abort(404);
        }
        if ($r['result'] !== ResaSvc::OK) {
            return back()->with('error', __('Cette demande ne peut plus passer à cet état.'));
        }

        app(AuditService::class)->log('hotel.reservation.'.$data['to'], $r['reservation'], $r['reservation']->reference.' · '.$me->name);

        return back()->with('success', $data['to'] === 'confirmed' ? __('Réservation confirmée.') : __('Demande refusée.'));
    }

    /** Léger : pastille + son. Réservé à l'employé identifié, limité à SON équipe. */
    public function alerts(): JsonResponse
    {
        $hotel = $this->hotel();
        $me = $hotel ? $this->staff->forHotel($hotel->id) : null;
        if (! $me) {
            return response()->json(['latest_id' => 0, 'pending' => 0], 401);
        }
        $q = $this->requests->queryFor($hotel->id, $me);
        $resa = $this->reservations->queryFor($hotel->id, $me)->where('status', 'new');

        return response()->json([
            'resa_latest_id' => (int) ((clone $resa)->max('id') ?? 0),
            'resa_pending'   => (clone $resa)->count(),
            // Sur les demandes OUVERTES seulement, comme l'écran : sur toutes, une demande récente annulée ou
            // terminée faisait sonner et recharger la tablette toutes les 15 secondes, indéfiniment.
            'latest_id' => (int) ((clone $q)->whereIn('status', R::OPEN)->max('id') ?? 0),
            'pending'   => (clone $q)->where('status', 'new')->count(),
        ]);
    }

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
