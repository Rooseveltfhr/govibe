<?php

namespace Modules\Tagtoa\App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Models\Hotel\HotelRoom;
use Modules\Tagtoa\App\Services\Audit\AuditService;
use Modules\Tagtoa\App\Services\Hotel\HotelProfileService;
use Modules\Tagtoa\App\Support\Hotel\HotelRoomRules;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA HÔTEL (H2) — gestion des chambres de l'hôtel COURANT.
 *
 * Réservé aux commerces de type « hôtel » : pour tout autre type, ces écrans n'existent pas.
 * Les chambres sont isolées par commerce (BelongsToTenant) : l'identifiant d'une chambre d'un
 * autre hôtel est introuvable (404), jamais « interdit ». Une chambre n'est jamais supprimée :
 * on la désactive (des supports et des demandes y seront rattachés).
 */
class RoomController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if (! ($hotel = $this->hotel())) {
            return $this->pasUnHotel();
        }

        $profil = app(HotelProfileService::class)->for($hotel);

        return view('tagtoa::hotel.rooms', [
            'hotel'  => $hotel,
            'rooms'  => HotelRoom::orderBy('floor')->orderBy('number')->get(),
            'types'  => HotelRoomRules::TYPES,
            'expected' => $profil?->rooms_count,
            'bulkMax'  => HotelRoomRules::BULK_MAX,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! ($hotel = $this->hotel())) {
            return $this->pasUnHotel();
        }

        $data = $request->validate(HotelRoomRules::rules($hotel->id));
        $room = HotelRoom::create(HotelRoomRules::clean($data) + ['tenant_id' => $hotel->id, 'is_active' => true]);

        app(AuditService::class)->log('hotel.room.created', $room, $room->number);

        return back()->with('success', __('Chambre :n ajoutée.', ['n' => $room->number]));
    }

    /** Création en série : de 101 à 120. Les numéros déjà pris sont ignorés, pas écrasés. */
    public function bulk(Request $request): RedirectResponse
    {
        if (! ($hotel = $this->hotel())) {
            return $this->pasUnHotel();
        }

        $data = $request->validate(HotelRoomRules::bulkRules());
        $from = (int) $data['from'];
        $to   = (int) $data['to'];
        if (($to - $from + 1) > HotelRoomRules::BULK_MAX) {
            return back()->withErrors(['to' => __('Au plus :max chambres à la fois.', ['max' => HotelRoomRules::BULK_MAX])])->withInput();
        }

        $existants = HotelRoom::pluck('number')->map(fn ($n) => (string) $n)->all();
        $crees = 0;
        for ($n = $from; $n <= $to; $n++) {
            if (in_array((string) $n, $existants, true)) {
                continue;
            }
            HotelRoom::create(['tenant_id' => $hotel->id, 'number' => (string) $n, 'type' => $data['type'] ?? null, 'is_active' => true]);
            $crees++;
        }

        app(AuditService::class)->log('hotel.room.bulk_created', null, "$from-$to · $crees");

        return back()->with('success', __(':c chambre(s) ajoutée(s) ; les numéros déjà existants ont été ignorés.', ['c' => $crees]));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        if (! ($hotel = $this->hotel())) {
            return $this->pasUnHotel();
        }

        $room = HotelRoom::findOrFail($id);
        $data = $request->validate(HotelRoomRules::rules($hotel->id, $room->id));
        $room->update(HotelRoomRules::clean($data));

        app(AuditService::class)->log('hotel.room.updated', $room, $room->number);

        return back()->with('success', __('Chambre :n mise à jour.', ['n' => $room->number]));
    }

    /** Active ou désactive. Une chambre désactivée n'est plus proposée aux invités. */
    public function toggle(int $id): RedirectResponse
    {
        if (! $this->hotel()) {
            return $this->pasUnHotel();
        }

        $room = HotelRoom::findOrFail($id);
        $room->update(['is_active' => ! $room->is_active]);

        app(AuditService::class)->log($room->is_active ? 'hotel.room.activated' : 'hotel.room.deactivated', $room, $room->number);

        return back()->with('success', $room->is_active
            ? __('Chambre :n activée.', ['n' => $room->number])
            : __('Chambre :n désactivée.', ['n' => $room->number]));
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
