<?php

namespace Modules\Tagtoa\App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Models\Hotel\HotelRatePlan;
use Modules\Tagtoa\App\Models\Hotel\HotelRoom;
use Modules\Tagtoa\App\Models\Hotel\HotelService;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Staff\Staff;
use Modules\Tagtoa\App\Services\Hotel\HotelProfileService;
use Modules\Tagtoa\App\Support\Pos\StaffAccess;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA HÔTEL — l'entrée « Mon Hôtel » de la barre latérale.
 *
 * Visible pour tout compte :
 *  - commerce courant = hôtel  → le DÉMARRAGE pas à pas (profil, chambres, tarifs, menu, services, QR, personnel) ;
 *  - sinon, si le compte a déjà un hôtel → un bouton pour l'ouvrir (bascule vers lui) ;
 *  - sinon → « Créer mon hôtel » (le formulaire de commerce, métier « Hôtel » déjà choisi).
 *
 * Rien n'est créé ici : les compteurs sont des lectures, bornées au commerce courant (portée tenant).
 */
class HotelHomeController extends Controller
{
    public function index(): View
    {
        $business = Business::where('account_id', Tenant::account())->whereKey(Tenant::id())->first();

        if ($business && $business->type === 'hotel') {
            $steps = $this->steps($business);

            return view('tagtoa::hotel.home', [
                'hotel' => $business,
                'steps' => $steps,
                // Les étapes OPTIONNELLES (restaurant et bar) ne comptent ni dans le total ni dans l'avancement.
                'total' => count(array_filter($steps, fn ($s) => ! ($s['optional'] ?? false))),
                'done'  => count(array_filter($steps, fn ($s) => $s['done'] && ! ($s['optional'] ?? false))),
            ]);
        }

        return view('tagtoa::hotel.home', [
            'hotel'  => null,
            'hotels' => Business::where('account_id', Tenant::account())->where('type', 'hotel')->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Les étapes de mise en place d'un hôtel, dans l'ordre où on les fait. Chaque étape sait si elle est faite
     * (un compteur), ce qu'elle contient (`detail`) et où l'on va (`url`).
     *
     * @return array<int,array{key:string,icon:string,title:string,hint:string,detail:string,done:bool,url:string}>
     */
    private function steps(Business $hotel): array
    {
        $rooms     = HotelRoom::count();
        $withStand = HotelRoom::whereNotNull('stand_id')->count();
        $plans     = HotelRatePlan::where('is_active', true)->count();
        $services  = HotelService::where('is_active', true)->count();
        $menus     = Menu::count();
        $staff     = Staff::where('role', StaffAccess::ROLE_HOTEL)->where('is_active', true)->count();
        $profile   = app(HotelProfileService::class)->for($hotel) !== null;

        return [
            ['key' => 'profile', 'icon' => 'fa-hotel', 'title' => __('Informations de l\'hôtel'),
             'hint' => __('Nom, adresse, téléphone, ce que l\'hôtel propose (piscine, Wi-Fi…).'),
             'detail' => $profile ? __('Renseignées') : __('À compléter'), 'done' => $profile, 'url' => route('tagtoa.business.edit', $hotel->id)],
            ['key' => 'rooms', 'icon' => 'fa-bed', 'title' => __('Chambres'),
             'hint' => __('Ajoutez vos chambres une à une, ou toute une série d\'un coup (101 à 120).'),
             'detail' => trans_choice(':n chambre|:n chambres', $rooms, ['n' => $rooms]), 'done' => $rooms > 0, 'url' => route('tagtoa.hotel.rooms')],
            ['key' => 'rates', 'icon' => 'fa-tags', 'title' => __('Tarifs des chambres'),
             'hint' => __('Prix par nuit et par moment (1 h, 2 h…), avec ce qui est inclus.'),
             'detail' => trans_choice(':n tarif actif|:n tarifs actifs', $plans, ['n' => $plans]), 'done' => $plans > 0, 'url' => route('tagtoa.hotel.roomtypes')],
            ['key' => 'menu', 'icon' => 'fa-utensils', 'title' => __('Restaurant et bar'), 'optional' => true,
             'hint' => __('Seulement si votre hôtel a un restaurant ou un bar : un seul menu, avec les rayons restaurant et bar, livraison et à emporter.'),
             'detail' => trans_choice(':n menu|:n menus', $menus, ['n' => $menus]), 'done' => $menus > 0, 'url' => url('/tagtoa/menu')],
            ['key' => 'services', 'icon' => 'fa-bell-concierge', 'title' => __('Services aux clients'),
             'hint' => __('Ménage, serviettes, réception, room service : ce que le client demande depuis sa chambre.'),
             'detail' => trans_choice(':n service actif|:n services actifs', $services, ['n' => $services]), 'done' => $services > 0, 'url' => route('tagtoa.hotel.services')],
            ['key' => 'qr', 'icon' => 'fa-qrcode', 'title' => __('QR / NFC des chambres'),
             'hint' => __('Activez le support de chaque chambre : le client le scanne pour commander ou demander.'),
             'detail' => $rooms > 0 ? __(':a / :b chambres équipées', ['a' => $withStand, 'b' => $rooms]) : __('Ajoutez d\'abord des chambres'),
             'done' => $rooms > 0 && $withStand === $rooms, 'url' => route('tagtoa.hotel.rooms')],
            ['key' => 'staff', 'icon' => 'fa-user-clock', 'title' => __('Personnel'),
             'hint' => __('Réception, ménage, maintenance : chacun voit seulement les demandes de son équipe.'),
             'detail' => trans_choice(':n employé|:n employés', $staff, ['n' => $staff]), 'done' => $staff > 0, 'url' => route('tagtoa.staff.index')],
        ];
    }
}
