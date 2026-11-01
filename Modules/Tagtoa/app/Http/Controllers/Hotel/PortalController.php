<?php

namespace Modules\Tagtoa\App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Models\Hotel\HotelProfile;
use Modules\Tagtoa\App\Models\Hotel\HotelRoom;
use Modules\Tagtoa\App\Models\Hotel\HotelService;
use Modules\Tagtoa\App\Models\Stand\Stand;
use Modules\Tagtoa\App\Support\Stand\StandId;
use Modules\Tagtoa\App\Support\Stand\StandState;

/**
 * TAGTOA HÔTEL (H4) — la page publique derrière le QR/NFC d'une chambre.
 *
 * Route PUBLIQUE : la portée tenant est ouverte par conception. Le commerce est donc résolu
 * EXPLICITEMENT depuis le support (jamais depuis un état ambiant), puis chaque maillon est
 * revérifié À CHAQUE REQUÊTE, sans cache : support → chambre → hôtel. Un support révoqué, remplacé,
 * suspendu, perdu, d'un lot rappelé, une chambre désactivée ou un commerce qui n'est plus un hôtel
 * donnent TOUS la même réponse neutre (404) : on n'apprend rien en sondant des identifiants.
 *
 * La page ne montre que ce qu'un client de l'hôtel voit déjà à la réception : nom de l'hôtel,
 * numéro de la chambre, contact de la réception, services proposés. Aucun identifiant interne,
 * aucune donnée d'invité, aucune autre chambre.
 */
class PortalController extends Controller
{
    public function show(string $standId): Response
    {
        $contexte = $this->resoudre($standId);

        return $contexte === null
            ? $this->neutre()
            : $this->entetes(response()->view('tagtoa::hotel.portal', $contexte));
    }

    /** @return array<string,mixed>|null null = indisponible, sans dire pourquoi. */
    private function resoudre(string $standId): ?array
    {
        $id = StandId::normalizeId($standId);
        if (! StandId::isValidId($id)) {
            return null;
        }

        $stand = Stand::with('batch')->byPublicId($id)->first();
        if (! $stand
            || $stand->target_module !== 'hotel_room'
            || $stand->tenant_id === null
            || $stand->digital_state !== StandState::ACTIVE
            || in_array($stand->physical_state, StandState::PHYSICAL_CLOSED, true)
            || ($stand->batch && $stand->batch->isRecalled())) {
            return null;
        }

        $tenant = $stand->tenant_id;

        $chambre = HotelRoom::allTenants()->where('tenant_id', $tenant)
            ->where('stand_id', $stand->id)->where('is_active', true)->first();
        $hotel = $chambre ? Business::find($tenant) : null;
        if (! $chambre || ! $hotel || $hotel->type !== 'hotel' || $hotel->is_active === false) {
            return null;
        }

        return [
            'hotel'    => $hotel,
            'room'     => $chambre,
            'profile'  => HotelProfile::allTenants()->where('tenant_id', $tenant)->first(),
            'services' => HotelService::allTenants()->where('tenant_id', $tenant)->where('is_active', true)
                ->orderBy('sort')->orderBy('id')->get(),
        ];
    }

    private function neutre(): Response
    {
        return $this->entetes(response()->view('tagtoa::hotel.portal-unavailable', [], 404));
    }

    /** Page de séjour d'un client : jamais mise en cache, jamais indexée, aucune référence sortante. */
    private function entetes(Response $r): Response
    {
        return $r->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
