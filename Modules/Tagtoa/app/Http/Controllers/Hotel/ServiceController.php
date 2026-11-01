<?php

namespace Modules\Tagtoa\App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Models\Hotel\HotelService;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Services\Audit\AuditService;
use Modules\Tagtoa\App\Support\Hotel\HotelServiceRules;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA HÔTEL (H3) — catalogue des services de l'hôtel COURANT.
 *
 * Mêmes garanties que les chambres (H2) : réservé au type « hôtel », isolé par commerce
 * (l'identifiant d'un service d'un autre hôtel est introuvable, 404), jamais supprimé.
 * Le room service réutilise un Menu existant du même hôtel ; les commandes depuis la chambre
 * arrivent avec le portail invité (H5).
 */
class ServiceController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if (! ($hotel = $this->hotel())) {
            return $this->pasUnHotel();
        }

        return view('tagtoa::hotel.services', [
            'hotel'    => $hotel,
            'services' => HotelService::orderBy('sort')->orderBy('id')->get(),
            'menus'    => Menu::orderBy('name')->get(['id', 'name']),
            'kinds'    => HotelServiceRules::KINDS,
            'teams'    => HotelServiceRules::TEAMS,
            'locales'  => HotelServiceRules::LOCALES,
            'max'      => HotelServiceRules::MAX_PER_HOTEL,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! ($hotel = $this->hotel())) {
            return $this->pasUnHotel();
        }
        if (HotelService::count() >= HotelServiceRules::MAX_PER_HOTEL) {
            return back()->with('error', __('Au plus :max services par hôtel.', ['max' => HotelServiceRules::MAX_PER_HOTEL]));
        }

        $data = $request->validate(HotelServiceRules::rules($hotel->id));
        $service = HotelService::create(HotelServiceRules::clean($data) + ['tenant_id' => $hotel->id, 'is_active' => true]);

        app(AuditService::class)->log('hotel.service.created', $service, $service->name);

        return back()->with('success', __('Service « :n » ajouté.', ['n' => $service->name]));
    }

    /** Ajoute les services courants absents (par nom) : rejouable, n'écrase rien. */
    public function defaults(): RedirectResponse
    {
        if (! ($hotel = $this->hotel())) {
            return $this->pasUnHotel();
        }

        $existants = HotelService::pluck('name')->map(fn ($n) => mb_strtolower($n))->all();
        $crees = 0;
        foreach (HotelServiceRules::DEFAULTS as $i => $d) {
            $nom = __($d['name']);
            if (in_array(mb_strtolower($nom), $existants, true) || HotelService::count() >= HotelServiceRules::MAX_PER_HOTEL) {
                continue;
            }
            HotelService::create([
                'tenant_id' => $hotel->id, 'kind' => $d['kind'], 'name' => $nom,
                'team' => HotelServiceRules::KINDS[$d['kind']]['team'], 'sort' => $i, 'is_active' => true,
            ]);
            $crees++;
        }

        app(AuditService::class)->log('hotel.service.defaults', null, (string) $crees);

        return back()->with('success', __(':c service(s) ajouté(s) ; ceux qui existaient déjà ont été gardés tels quels.', ['c' => $crees]));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        if (! ($hotel = $this->hotel())) {
            return $this->pasUnHotel();
        }

        $service = HotelService::findOrFail($id);
        $data = $request->validate(HotelServiceRules::rules($hotel->id));
        $service->update(HotelServiceRules::clean($data));

        app(AuditService::class)->log('hotel.service.updated', $service, $service->name);

        return back()->with('success', __('Service « :n » mis à jour.', ['n' => $service->name]));
    }

    /** Active ou désactive. Un service désactivé n'est plus proposé aux clients. */
    public function toggle(int $id): RedirectResponse
    {
        if (! $this->hotel()) {
            return $this->pasUnHotel();
        }

        $service = HotelService::findOrFail($id);
        $service->update(['is_active' => ! $service->is_active]);

        app(AuditService::class)->log($service->is_active ? 'hotel.service.activated' : 'hotel.service.deactivated', $service, $service->name);

        return back()->with('success', $service->is_active
            ? __('Service « :n » activé.', ['n' => $service->name])
            : __('Service « :n » désactivé.', ['n' => $service->name]));
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
