<?php

namespace Modules\Tagtoa\App\Support\Hotel;

use Modules\Tagtoa\App\Support\Stand\StandId;

/**
 * TAGTOA HÔTEL (H5) — jeton court émis avec la page d'une chambre et exigé pour créer une demande.
 *
 * L'identifiant du support est public et énumérable : il ne suffit donc pas à ÉCRIRE. Le jeton
 * prouve que la page a été chargée il y a peu (3 h), pour CE support. Signé HMAC avec la clé de
 * l'application, sans état côté serveur. Un jeton d'un autre support, expiré, daté du futur ou
 * altéré est refusé.
 */
class HotelPortalToken
{
    public static function issue(string $standId, ?int $now = null): string
    {
        // Nonce aléatoire : deux pages chargées dans la même seconde n'ont jamais le même jeton (il est à usage unique).
        $t = ($now ?? time()).'.'.bin2hex(random_bytes(8));

        return $t.'.'.self::sign(StandId::normalizeId($standId), $t);
    }

    public static function verify(string $standId, ?string $token, ?int $now = null): bool
    {
        if (! is_string($token) || ! preg_match('/^(\d{9,12})\.([a-f0-9]{16})\.([a-f0-9]{64})$/', $token, $m)) {
            return false;
        }
        $now ??= time();
        $age = $now - (int) $m[1];
        if ($age < -60 || $age > HotelRequestRules::TOKEN_TTL) {
            return false;
        }

        return hash_equals(self::sign(StandId::normalizeId($standId), $m[1].'.'.$m[2]), $m[3]);
    }

    private static function sign(string $standId, string $t): string
    {
        return hash_hmac('sha256', 'hotel-portal|'.$standId.'|'.$t, (string) config('app.key'));
    }
}
