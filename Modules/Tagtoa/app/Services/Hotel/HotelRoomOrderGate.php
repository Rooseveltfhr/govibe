<?php

namespace Modules\Tagtoa\App\Services\Hotel;

use Illuminate\Support\Facades\RateLimiter;
use Modules\Tagtoa\App\Exceptions\OrderRuleException;
use Modules\Tagtoa\App\Models\Hotel\HotelRoom;
use Modules\Tagtoa\App\Models\Hotel\HotelService;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Menu\Order;
use Modules\Tagtoa\App\Support\Hotel\HotelRoomOrderToken;

/**
 * TAGTOA HÔTEL (H5b) — vérifie qu'une commande Menu vient bien d'une chambre de CET hôtel.
 *
 * Même rôle que la vérification d'une table par QR (MenuOrderService), mais pour une chambre. Tout est
 * revérifié à la commande, côté serveur : jeton signé pour CE menu → support → chambre active → hôtel
 * (HotelPortalResolver, sans cache) → même commerce que le menu → un service « room service »
 * actif de l'hôtel qui pointe vers CE menu. Un jeton présent mais invalide est REFUSÉ (jamais ignoré
 * en silence), comme un QR de table périmé.
 */
class HotelRoomOrderGate
{
    /** Commandes ouvertes (non terminées) au plus par chambre. */
    public const OPEN_MAX = 3;

    /** Commandes par heure et par chambre. */
    public const HOURLY_MAX = 10;

    public function __construct(protected HotelPortalResolver $portal)
    {
    }

    /**
     * @return array{room:HotelRoom, key:string}
     *
     * @throws OrderRuleException 'invalid_room' | 'room_busy'
     */
    public function check(Menu $menu, ?string $token): array
    {
        $standId = HotelRoomOrderToken::verify($token, (int) $menu->id);
        $ctx = $standId ? $this->portal->resolve($standId) : null;
        if (! $ctx || $ctx['stand']->tenant_id !== $menu->tenant_id) {
            throw new OrderRuleException('invalid_room');
        }

        $room = $ctx['room'];
        $service = HotelService::allTenants()->where('tenant_id', $menu->tenant_id)
            ->where('kind', 'room_service')->where('is_active', true)->where('menu_id', $menu->id)->exists();
        if (! $service) {
            throw new OrderRuleException('invalid_room');
        }

        $key = 'hotel-room-order:'.$room->id;
        $ouvertes = Order::where('tenant_id', $menu->tenant_id)->where('hotel_room_id', $room->id)
            ->whereNotIn('status', Order::DONE_STATUSES)->count();
        if ($ouvertes >= self::OPEN_MAX || RateLimiter::tooManyAttempts($key, self::HOURLY_MAX)) {
            throw new OrderRuleException('room_busy');
        }

        return ['room' => $room, 'key' => $key];
    }

    /** À appeler une fois la commande enregistrée. */
    public function recorded(string $key): void
    {
        RateLimiter::hit($key, 3600);
    }
}
