<?php

namespace Modules\Tagtoa\App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Tagtoa\App\Services\Hotel\HotelPortalResolver;
use Modules\Tagtoa\App\Services\Hotel\HotelRequestService as Svc;
use Modules\Tagtoa\App\Support\Hotel\HotelPortalToken;
use Modules\Tagtoa\App\Support\Hotel\HotelRequestRules;

/**
 * TAGTOA HÔTEL (H4/H5) — la page publique derrière le QR/NFC d'une chambre, les demandes de
 * l'invité et leur suivi.
 *
 * Route PUBLIQUE : la portée tenant est ouverte par conception. Le commerce est résolu
 * EXPLICITEMENT depuis le support (HotelPortalResolver), puis chaque maillon est revérifié À CHAQUE
 * REQUÊTE, sans cache. Tous les cas indisponibles donnent la MÊME réponse neutre (404).
 *
 * Créer une demande exige : le support valide, un jeton récent émis avec la page, et passe par les
 * plafonds du service (une demande ouverte par service, 5 par chambre). Le suivi se fait par
 * référence + secret de 128 bits (HotelRequestService), indépendant du support : une demande en
 * cours reste suivie même si le support est remplacé pendant le séjour.
 *
 * La page ne montre que ce qu'un client de l'hôtel voit déjà à la réception : jamais d'identifiant
 * interne, de donnée d'un autre invité, ni d'identité du personnel.
 */
class PortalController extends Controller
{
    public function __construct(protected HotelPortalResolver $portal, protected Svc $requests)
    {
    }

    public function show(string $standId): Response
    {
        $ctx = $this->portal->page($standId);
        if ($ctx === null) {
            return $this->neutre();
        }

        return $this->entetes(response()->view('tagtoa::hotel.portal', $ctx + [
            'standId'     => $ctx['stand']->public_id,
            'token'       => HotelPortalToken::issue($ctx['stand']->public_id),
            'requestable' => HotelRequestRules::REQUESTABLE_KINDS,
        ]));
    }

    /** Crée une demande. Redirige vers le suivi de l'invité. */
    public function request(Request $request, string $standId): Response|RedirectResponse
    {
        $ctx = $this->portal->resolve($standId);
        if ($ctx === null) {
            return $this->neutre();
        }

        $data = $request->validate([
            'service_id' => ['required', 'integer', 'min:1'],
            'note'       => ['nullable', 'string', 'max:'.HotelRequestRules::NOTE_MAX],
            'tk'         => ['required', 'string', 'max:100'],
        ]);

        $retour = redirect()->route('tagtoa.hotel.portal', $ctx['stand']->public_id);

        if (! HotelPortalToken::verify($ctx['stand']->public_id, $data['tk'])) {
            return $this->entetes($retour->with('guest_error', __('Cette page a expiré. Rechargez-la puis recommencez.')));
        }

        $r = $this->requests->create($ctx['stand']->tenant_id, $ctx['room']->id, (int) $data['service_id'], $data['note'] ?? null);

        if ($r['result'] !== Svc::OK) {
            return $this->entetes($retour->with('guest_error', match ($r['result']) {
                Svc::DUPLICATE        => __('Cette demande a déjà été transmise à la réception.'),
                Svc::TOO_MANY_OPEN    => __('Plusieurs demandes sont déjà en cours pour cette chambre. Contactez la réception.'),
                default               => __('Ce service n\'est pas disponible. Contactez la réception.'),
            }));
        }

        return $this->entetes(redirect()->route('tagtoa.hotel.track', [$r['request']->reference, $r['secret']]));
    }

    public function track(string $reference, string $secret): Response
    {
        $req = $this->requests->forGuest($reference, $secret);
        if (! $req) {
            return $this->neutre();
        }
        $ctx = $this->contexteDe($req);

        return $this->entetes(response()->view('tagtoa::hotel.track', [
            'req' => $req, 'secret' => $secret, 'hotel' => $ctx['hotel'], 'room' => $ctx['room'], 'profile' => $ctx['profile'],
        ]));
    }

    public function cancel(string $reference, string $secret): Response|RedirectResponse
    {
        $r = $this->requests->guestCancel($reference, $secret);
        if ($r['result'] === Svc::NOT_FOUND) {
            return $this->neutre();
        }

        return $this->entetes(redirect()->route('tagtoa.hotel.track', [$reference, $secret])
            ->with($r['result'] === Svc::OK ? 'guest_ok' : 'guest_error', $r['result'] === Svc::OK
                ? __('Demande annulée.')
                : __('Cette demande a déjà été prise en compte : contactez la réception pour l\'annuler.')));
    }

    /** Hôtel, chambre et profil d'une demande, résolus par SON commerce (pas par le support). */
    private function contexteDe($req): array
    {
        return [
            'hotel'   => \Modules\Tagtoa\App\Models\Business\Business::find($req->tenant_id),
            'room'    => \Modules\Tagtoa\App\Models\Hotel\HotelRoom::allTenants()->where('tenant_id', $req->tenant_id)->whereKey($req->room_id)->first(),
            'profile' => \Modules\Tagtoa\App\Models\Hotel\HotelProfile::allTenants()->where('tenant_id', $req->tenant_id)->first(),
        ];
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
