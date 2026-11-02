<?php

namespace Modules\Tagtoa\App\Services\Hotel;

use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Models\Hotel\HotelProfile;
use Modules\Tagtoa\App\Models\Hotel\HotelRoom;
use Modules\Tagtoa\App\Models\Hotel\HotelService;
use Modules\Tagtoa\App\Models\Stand\Stand;
use Modules\Tagtoa\App\Support\Stand\StandId;
use Modules\Tagtoa\App\Support\Stand\StandState;

/**
 * TAGTOA HÔTEL — du support public à la chambre et à l'hôtel, REVÉRIFIÉ à chaque appel.
 *
 * Le commerce est résolu EXPLICITEMENT depuis le support (jamais depuis un état ambiant : le
 * contexte public n'a pas de commerce courant). Aucun cache : un support révoqué, remplacé,
 * suspendu, perdu, d'un lot rappelé, une chambre désactivée ou un commerce qui n'est plus un hôtel
 * donnent TOUS `null`, sans dire pourquoi.
 */
class HotelPortalResolver
{
    /** @return array{stand:Stand, room:HotelRoom, hotel:Business}|null */
    public function resolve(?string $standId): ?array
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

        $room = HotelRoom::allTenants()->where('tenant_id', $stand->tenant_id)
            ->where('stand_id', $stand->id)->where('is_active', true)->first();
        $hotel = $room ? Business::find($stand->tenant_id) : null;
        if (! $room || ! $hotel || $hotel->type !== 'hotel' || $hotel->is_active === false) {
            return null;
        }

        return ['stand' => $stand, 'room' => $room, 'hotel' => $hotel];
    }

    /** Contexte complet de la page : + profil et services actifs du MÊME commerce. */
    public function page(?string $standId): ?array
    {
        $c = $this->resolve($standId);
        if ($c === null) {
            return null;
        }
        $tenant = $c['stand']->tenant_id;

        return $c + [
            'profile'  => HotelProfile::allTenants()->where('tenant_id', $tenant)->first(),
            'services' => HotelService::allTenants()->where('tenant_id', $tenant)->where('is_active', true)
                ->orderBy('sort')->orderBy('id')->get(),
        ];
    }
}
