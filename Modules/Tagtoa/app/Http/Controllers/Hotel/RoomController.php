<?php

namespace Modules\Tagtoa\App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Models\Hotel\HotelRoom;
use Modules\Tagtoa\App\Services\Audit\AuditService;
use Modules\Tagtoa\App\Models\Stand\Stand;
use Modules\Tagtoa\App\Services\Hotel\HotelProfileService;
use Modules\Tagtoa\App\Services\Hotel\HotelRoomSupportService as Support;
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
            // H4 : les supports de CE commerce, pour afficher leur identifiant imprimé.
            'stands'   => Stand::ofBusiness($hotel->id)->whereIn('id', HotelRoom::whereNotNull('stand_id')->pluck('stand_id'))->get()->keyBy('id'),
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
        // L'aiguillage /s/… garde sa destination en cache : on la vide pour que le scan ne mène plus au portail.
        if ($room->stand_id && ($stand = Stand::find($room->stand_id))) {
            app(\Modules\Tagtoa\App\Services\Stand\StandResolver::class)->forget($stand->public_id);
        }

        app(AuditService::class)->log($room->is_active ? 'hotel.room.activated' : 'hotel.room.deactivated', $room, $room->number);

        return back()->with('success', $room->is_active
            ? __('Chambre :n activée.', ['n' => $room->number])
            : __('Chambre :n désactivée.', ['n' => $room->number]));
    }

    /* ---------- H4 — support QR/NFC de la chambre ---------- */

    public function attachSupport(Request $request, int $id): RedirectResponse
    {
        return $this->support($request, $id, 'attach');
    }

    public function replaceSupport(Request $request, int $id): RedirectResponse
    {
        return $this->support($request, $id, 'replace');
    }

    public function revokeSupport(Request $request, int $id): RedirectResponse
    {
        return $this->support($request, $id, 'revoke');
    }

    private function support(Request $request, int $id, string $action): RedirectResponse
    {
        if (! ($hotel = $this->hotel())) {
            return $this->pasUnHotel();
        }
        // 404 si la chambre n'est pas CELLE de cet hôtel (portée tenant).
        $room = HotelRoom::findOrFail($id);
        $ctx = ['actor_name' => optional($request->user())->name, 'ip' => $request->ip(), 'user_agent' => $request->userAgent()];
        $svc = app(Support::class);

        if ($action === 'revoke') {
            $r = $svc->revoke($hotel->id, $room->id, $ctx);
        } else {
            $data = $request->validate(['code' => ['required', 'string', 'max:64']]);
            $r = $action === 'attach'
                ? $svc->attach($hotel->id, $room->id, $data['code'], $ctx)
                : $svc->replace($hotel->id, $room->id, $data['code'], $ctx);
        }

        if ($r['result'] !== Support::OK) {
            return back()->withErrors(['code' => $this->message($r['result'])])->withInput($request->except('code'));
        }

        app(AuditService::class)->log('hotel.room.support_'.$action, $room, $room->number.($r['stand'] ? ' · '.$r['stand']->public_id : ''));

        return back()->with('success', match ($action) {
            'attach'  => __('Support activé pour la chambre :n.', ['n' => $room->number]),
            'replace' => __('Support remplacé pour la chambre :n ; l\'ancien ne fonctionne plus.', ['n' => $room->number]),
            default   => __('Support de la chambre :n révoqué.', ['n' => $room->number]),
        });
    }

    private function message(string $result): string
    {
        return match ($result) {
            Support::HAS_SUPPORT   => __('Cette chambre a déjà un support. Utilisez « Remplacer ».'),
            Support::NO_SUPPORT    => __('Cette chambre n\'a pas de support.'),
            Support::ROOM_INACTIVE => __('Activez la chambre avant de lui affecter un support.'),
            Support::STAND_IN_USE  => __('Ce support est déjà utilisé ailleurs.'),
            Support::SAME_STAND    => __('C\'est le support déjà en place : scannez un NOUVEAU support.'),
            'stand_bad_code'       => __('Code incorrect. Vérifiez le code gratté sous le panneau.'),
            'stand_too_many'       => __('Trop d\'essais. Réessayez dans quelques minutes.'),
            'stand_no_secret'      => __('Il faut le code gratté sous le panneau, pas seulement le QR du recto.'),
            'stand_unreadable'     => __('Code illisible. Saisissez-le tel qu\'imprimé.'),
            default                => __('Ce support n\'est pas disponible (déjà activé, perdu ou inconnu).'),
        };
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
