<?php

namespace Modules\Tagtoa\App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Models\Hotel\HotelRatePlan;
use Modules\Tagtoa\App\Models\Hotel\HotelRoomType;
use Modules\Tagtoa\App\Services\Audit\AuditService;
use Modules\Tagtoa\App\Services\Currency\CurrencyRateService;
use Modules\Tagtoa\App\Support\Hotel\HotelRoomTypeRules as R;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA HÔTEL (HM-2) — types de chambre et plans tarifaires (nuit / moment) de l'hôtel COURANT.
 *
 * Mêmes garanties que les chambres et les services : réservé au type « hôtel », isolé par commerce (l'identifiant d'un
 * type ou d'un plan d'un autre hôtel est introuvable, 404), jamais supprimé (désactivé). Aucune réservation ici :
 * la page publique du menu AFFICHE ces prix.
 */
class RoomTypeController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if (! ($hotel = $this->hotel())) {
            return $this->pasUnHotel();
        }

        return view('tagtoa::hotel.room-types', [
            'hotel'      => $hotel,
            'types'      => HotelRoomType::with('plans')->orderBy('sort')->orderBy('id')->get(),
            'devises'    => $this->devises($hotel),
            'inclusions' => R::INCLUSIONS,
            'hours'      => R::BLOCK_HOURS,
            'kinds'      => R::KINDS,
            'maxTypes'   => R::MAX_TYPES,
        ]);
    }

    public function storeType(Request $request): RedirectResponse
    {
        if (! ($hotel = $this->hotel())) {
            return $this->pasUnHotel();
        }
        $data = R::cleanType($request->validate(R::typeRules()));

        // Le plafond se vérifie sous verrou (ligne du commerce) : deux onglets ne passent pas tous deux le contrôle.
        $type = DB::transaction(function () use ($hotel, $data) {
            Business::whereKey($hotel->id)->lockForUpdate()->first();
            if (HotelRoomType::count() >= R::MAX_TYPES) {
                return null;
            }

            return HotelRoomType::create($data + ['tenant_id' => $hotel->id, 'is_active' => true, 'sort' => (int) HotelRoomType::max('sort') + 1]);
        });
        if (! $type) {
            return back()->with('error', __('Au plus :max types de chambre par hôtel.', ['max' => R::MAX_TYPES]));
        }
        app(AuditService::class)->log('hotel.roomtype.created', $type, $type->name);

        return back()->with('success', __('Type « :n » ajouté.', ['n' => $type->name]));
    }

    public function updateType(Request $request, int $id): RedirectResponse
    {
        if (! $this->hotel()) {
            return $this->pasUnHotel();
        }

        $type = HotelRoomType::findOrFail($id);
        $type->update(R::cleanType($request->validate(R::typeRules())));
        app(AuditService::class)->log('hotel.roomtype.updated', $type, $type->name);

        return back()->with('success', __('Type « :n » mis à jour.', ['n' => $type->name]));
    }

    public function toggleType(int $id): RedirectResponse
    {
        if (! $this->hotel()) {
            return $this->pasUnHotel();
        }

        $type = HotelRoomType::findOrFail($id);
        $type->update(['is_active' => ! $type->is_active]);
        app(AuditService::class)->log($type->is_active ? 'hotel.roomtype.activated' : 'hotel.roomtype.deactivated', $type, $type->name);

        return back()->with('success', $type->is_active
            ? __('Type « :n » activé.', ['n' => $type->name])
            : __('Type « :n » désactivé.', ['n' => $type->name]));
    }

    public function storePlan(Request $request, int $typeId): RedirectResponse
    {
        if (! ($hotel = $this->hotel())) {
            return $this->pasUnHotel();
        }

        $type = HotelRoomType::findOrFail($typeId);
        $this->normaliser($request);
        $data = $request->validate(R::planRules(array_keys($this->devises($hotel))));
        if ($this->planExiste($type, $data)) {
            return back()->withErrors(['kind' => __('Ce tarif existe déjà pour ce type de chambre.')])->withInput();
        }

        try {
            $plan = HotelRatePlan::create(R::cleanPlan($data) + [
                'tenant_id' => $hotel->id, 'room_type_id' => $type->id, 'is_active' => true, 'sort' => (int) $type->plans()->max('sort') + 1,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Double envoi ou deux onglets : l'index unique (type, durée) a déjà refusé le doublon.
            return back()->withErrors(['kind' => __('Ce tarif existe déjà pour ce type de chambre.')])->withInput();
        }
        app(AuditService::class)->log('hotel.rateplan.created', $plan, $type->name.' · '.$plan->duration_label);

        return back()->with('success', __('Tarif ajouté.'));
    }

    public function updatePlan(Request $request, int $id): RedirectResponse
    {
        if (! ($hotel = $this->hotel())) {
            return $this->pasUnHotel();
        }

        $plan = HotelRatePlan::findOrFail($id);
        $this->normaliser($request);
        // La devise ACTUELLE du tarif reste permise même si son taux a été désactivé depuis : modifier les « inclus »
        // ne doit jamais changer la devise en silence (50 USD ne devient pas 50 HTG).
        $data = $request->validate(R::planRules(array_values(array_unique([...array_keys($this->devises($hotel)), strtoupper($plan->currency)]))));
        if ($this->planExiste($plan->roomType, $data, $plan->id)) {
            return back()->withErrors(['kind' => __('Ce tarif existe déjà pour ce type de chambre.')])->withInput();
        }

        try {
            $plan->update(R::cleanPlan($data));
        } catch (UniqueConstraintViolationException) {
            return back()->withErrors(['kind' => __('Ce tarif existe déjà pour ce type de chambre.')])->withInput();
        }
        app(AuditService::class)->log('hotel.rateplan.updated', $plan, $plan->duration_label);

        return back()->with('success', __('Tarif mis à jour.'));
    }

    public function togglePlan(int $id): RedirectResponse
    {
        if (! $this->hotel()) {
            return $this->pasUnHotel();
        }

        $plan = HotelRatePlan::findOrFail($id);
        $plan->update(['is_active' => ! $plan->is_active]);
        app(AuditService::class)->log($plan->is_active ? 'hotel.rateplan.activated' : 'hotel.rateplan.deactivated', $plan, $plan->duration_label);

        return back()->with('success', $plan->is_active ? __('Tarif activé.') : __('Tarif désactivé.'));
    }

    /* ----------------------------------------------------------------- */

    /** Le code de devise se compare en majuscules : « htg » et « HTG » sont la même devise. */
    private function normaliser(Request $request): void
    {
        if (is_string($request->input('currency'))) {
            $request->merge(['currency' => strtoupper(trim($request->input('currency')))]);
        }
    }

    /** Une nuit, ou un même « moment » (même durée), ne se déclare qu'une fois par type de chambre. */
    private function planExiste(HotelRoomType $type, array $data, ?int $ignore = null): bool
    {
        $q = $type->plans()->where('kind', $data['kind'] === 'block' ? 'block' : 'night');
        if ($data['kind'] === 'block') {
            $q->where('hours', (int) $data['hours']);
        }

        return $q->when($ignore, fn ($x) => $x->where('id', '!=', $ignore))->exists();
    }

    /** Devises acceptables : la principale du commerce, puis celles qui ont un taux actif. */
    private function devises(Business $hotel): array
    {
        return app(CurrencyRateService::class)->choices($hotel->id, strtoupper($hotel->currency ?: 'HTG'));
    }

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
