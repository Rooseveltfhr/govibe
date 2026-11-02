<?php

namespace Modules\Tagtoa\App\Services\Hotel;

use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Models\Hotel\HotelProfile;
use Modules\Tagtoa\App\Support\Hotel\HotelCatalog;

/**
 * TAGTOA HÔTEL — lit et écrit le profil d'UN hôtel, désigné par son commerce.
 *
 * Reçoit le commerce en paramètre (l'appelant a déjà vérifié qu'il appartient au compte
 * connecté — BusinessController::own) : l'édition d'un commerce qui n'est pas celui en
 * cours ne doit pas dépendre du commerce ambiant, d'où la sortie explicite de la portée,
 * toujours filtrée sur l'identifiant du commerce reçu.
 */
class HotelProfileService
{
    public function for(Business $business): ?HotelProfile
    {
        return HotelProfile::allTenants()->where('tenant_id', $business->id)->first();
    }

    /**
     * Ce commerce peut-il cesser d'être un hôtel ? Renvoie la RAISON du refus, ou null.
     *
     * Changer le type d'un hôtel fait disparaître d'un coup le portail, les écrans et le personnel d'hôtel, et
     * laisse sans issue les demandes et commandes de chambre encore ouvertes (personne ne peut plus les clore).
     */
    public function blocksTypeChange(Business $business): ?string
    {
        $id = $business->id;
        if (\Modules\Tagtoa\App\Models\Hotel\HotelRequest::allTenants()->where('tenant_id', $id)
            ->whereIn('status', \Modules\Tagtoa\App\Support\Hotel\HotelRequestRules::OPEN)->exists()) {
            return __('Des demandes d\'invités sont encore ouvertes : clôturez-les avant de changer le type de ce commerce.');
        }
        if (\Modules\Tagtoa\App\Models\Menu\Order::allTenants()->where('tenant_id', $id)->whereNotNull('hotel_room_id')
            ->whereNotIn('status', \Modules\Tagtoa\App\Models\Menu\Order::DONE_STATUSES)->exists()) {
            return __('Des commandes de chambre sont encore en cours : terminez-les avant de changer le type de ce commerce.');
        }
        if (\Modules\Tagtoa\App\Models\Hotel\HotelReservation::allTenants()->where('tenant_id', $id)->whereIn('status', \Modules\Tagtoa\App\Support\Hotel\HotelReservationRules::OPEN)
            ->whereDate('starts_on', '>=', now()->toDateString())->exists()) {
            return __('Des réservations sont encore à venir : traitez-les avant de changer le type de ce commerce.');
        }
        if (\Modules\Tagtoa\App\Models\Hotel\HotelRoom::allTenants()->where('tenant_id', $id)->whereNotNull('stand_id')->exists()) {
            return __('Des chambres ont encore un support QR/NFC actif : révoquez-les avant de changer le type de ce commerce.');
        }

        return null;
    }

    /**
     * Crée ou met à jour le profil. Réservé aux commerces de type « hôtel » : sinon rien
     * n'est écrit (un restaurant ne reçoit jamais de profil d'hôtel, même si la requête en
     * porte les champs).
     *
     * @param  array<string,mixed>  $input   entrée validée (`hotel.*`)
     */
    public function save(Business $business, array $input, ?string $coverPath = null): ?HotelProfile
    {
        if ($business->type !== 'hotel') {
            return null;
        }

        $attrs = HotelCatalog::clean($input);
        if ($coverPath !== null) {
            $attrs['cover_path'] = $coverPath;
        }

        $profil = $this->for($business);
        if ($profil) {
            $profil->update($attrs);

            return $profil;
        }

        // tenant_id posé explicitement : le commerce courant peut être un autre.
        return HotelProfile::create($attrs + ['tenant_id' => $business->id]);
    }
}
