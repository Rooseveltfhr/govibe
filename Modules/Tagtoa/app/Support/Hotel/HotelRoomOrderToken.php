<?php

namespace Modules\Tagtoa\App\Support\Hotel;

use Modules\Tagtoa\App\Support\Stand\StandId;

/**
 * TAGTOA HÔTEL (H5b) — jeton qui autorise UNE commande Menu « depuis une chambre ».
 *
 * Émis par le portail de la chambre (seulement après avoir revérifié support → chambre → hôtel),
 * lié au support ET au menu visé, signé HMAC avec la clé de l'application, sans état côté serveur.
 * Il porte l'identifiant du support (public) ; le serveur revérifie TOUT à la commande
 * (HotelRoomOrderGate) : le jeton prouve seulement que la page de la chambre a été ouverte il y a peu,
 * pour CE menu. Un jeton d'un autre menu, expiré, daté du futur ou altéré est refusé.
 *
 * Durée plus longue que celle des demandes (6 h) : la commande peut partir d'une file hors ligne.
 */
class HotelRoomOrderToken
{
    public const TTL = 21600;

    public static function issue(string $standId, int $menuId, ?int $now = null): string
    {
        $stand = StandId::normalizeId($standId);
        $t = ($now ?? time()).'.'.bin2hex(random_bytes(8));

        return $stand.'.'.$t.'.'.self::sign($stand, $menuId, $t);
    }

    /** L'identifiant de support si le jeton est valide POUR CE MENU, sinon null. */
    public static function verify(?string $token, int $menuId, ?int $now = null): ?string
    {
        if (! is_string($token) || ! preg_match('/^([A-Z0-9\-]{1,24})\.(\d{9,12})\.([a-f0-9]{16})\.([a-f0-9]{64})$/', $token, $m)) {
            return null;
        }
        $now ??= time();
        $age = $now - (int) $m[2];
        if ($age < -60 || $age > self::TTL) {
            return null;
        }

        return hash_equals(self::sign($m[1], $menuId, $m[2].'.'.$m[3]), $m[4]) ? $m[1] : null;
    }

    private static function sign(string $standId, int $menuId, string $t): string
    {
        return hash_hmac('sha256', 'hotel-room-order|'.$standId.'|'.$menuId.'|'.$t, (string) config('app.key'));
    }
}
