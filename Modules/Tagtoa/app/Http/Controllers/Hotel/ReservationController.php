<?php

namespace Modules\Tagtoa\App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Models\Hotel\HotelProfile;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Services\Hotel\HotelReservationService as Svc;
use Modules\Tagtoa\App\Support\Hotel\HotelReservationRules as R;

/**
 * TAGTOA HÔTEL (HM-3) — côté INVITÉ : demander une réservation depuis la page publique de l'hôtel, la suivre, l'annuler.
 *
 * Public, sans compte, sans paiement. Le prix et les « inclus » ne viennent JAMAIS de la requête (le service les relit dans
 * la base et les fige). Suivi = référence + secret de 128 bits ; toute erreur de suivi répond la MÊME 404 neutre.
 */
class ReservationController extends Controller
{
    public function __construct(private Svc $reservations)
    {
    }

    public function reserve(Request $request, string $alias): RedirectResponse
    {
        $menu = Menu::where('alias', $alias)->where('is_active', true)->firstOrFail();
        $retour = redirect(route('tagtoa.menu.show', $alias).'#rooms');

        $v = Validator::make($request->all(), R::rules());
        if ($v->fails()) {
            return $retour->withErrors($v, 'reserve')->withInput()->with('reserve_plan', (int) $request->input('plan_id'));
        }
        $data = $v->validated();

        $cleHotel = 'hotel-reserve-all:'.$menu->tenant_id;
        if (RateLimiter::tooManyAttempts($cleHotel, R::PER_HOTEL_HOURLY)) {
            return $retour->withInput()->with('reserve_error', __('Beaucoup de demandes en ce moment. Contactez directement l\'hôtel.'))
                ->with('reserve_plan', (int) $data['plan_id']);
        }

        // Plafond par visiteur (IP) et par hôtel : un script ne peut pas épuiser seul le plafond de l'hôtel.
        $cleIp = 'hotel-reserve-ip:'.$menu->tenant_id.':'.$request->ip();
        if (RateLimiter::tooManyAttempts($cleIp, R::PER_IP_HOURLY)) {
            return $retour->withInput()->with('reserve_error', __('Beaucoup de demandes en ce moment. Contactez directement l\'hôtel.'))
                ->with('reserve_plan', (int) $data['plan_id']);
        }

        $tz = $menu->timezone ?: 'America/Port-au-Prince';
        $r = $this->reservations->create($menu->tenant_id, $data, now($tz));

        if ($r['result'] !== Svc::OK) {
            return $retour->withInput()->with('reserve_plan', (int) $data['plan_id'])->with('reserve_error', match ($r['result']) {
                Svc::BAD_DATE   => __('Choisissez une date valide : aujourd\'hui ou plus tard, dans l\'année qui vient.'),
                Svc::BAD_TIME   => __('Choisissez une heure valide (pas dans le passé).'),
                Svc::BAD_PHONE  => __('Indiquez un numéro de téléphone valide (8 chiffres ou plus).'),
                // Message identique : ne révèle pas si un numéro a déjà des demandes chez cet hôtel.
                Svc::DUPLICATE, Svc::TOO_MANY => __('Votre demande n\'a pas pu être enregistrée. Contactez directement l\'hôtel.'),
                default         => __('Ce tarif n\'est plus disponible. Rechargez la page.'),
            });
        }

        RateLimiter::hit($cleHotel, 3600);
        RateLimiter::hit($cleIp, 3600);

        return $this->entetes(redirect()->route('tagtoa.hotel.reservation.track', [$r['reservation']->reference, $r['secret']]));
    }

    public function track(string $reference, string $secret): Response
    {
        $resa = $this->reservations->forGuest($reference, $secret);
        if (! $resa) {
            return $this->neutre();
        }

        return $this->entetes(response()->view('tagtoa::hotel.reservation-track', [
            'resa' => $resa, 'secret' => $secret,
            'hotel' => Business::find($resa->tenant_id),
            'profile' => HotelProfile::allTenants()->where('tenant_id', $resa->tenant_id)->first(),
        ]));
    }

    public function cancel(string $reference, string $secret): Response|RedirectResponse
    {
        $r = $this->reservations->guestCancel($reference, $secret);
        if ($r['result'] === Svc::NOT_FOUND) {
            return $this->neutre();
        }

        return $this->entetes(redirect()->route('tagtoa.hotel.reservation.track', [$reference, $secret])
            ->with($r['result'] === Svc::OK ? 'guest_ok' : 'guest_error', $r['result'] === Svc::OK
                ? __('Demande annulée.')
                : __('Cette demande est déjà clôturée.')));
    }

    private function neutre(): Response
    {
        return $this->entetes(response()->view('tagtoa::hotel.portal-unavailable', [], 404));
    }

    /** Page de séjour d'un client : jamais mise en cache, jamais indexée, aucune référence sortante. */
    private function entetes(Response|RedirectResponse $r): Response|RedirectResponse
    {
        return $r->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
