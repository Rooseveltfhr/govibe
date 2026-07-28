<?php

namespace Modules\Tagtoa\App\Support\Nfc;

/**
 * TAGTOA — vérificateur de tap NTAG424 (SUN/SDM) : combine la vérification du
 * CMAC (Ntag424) et l'anti-rejeu (compteur strictement croissant).
 *
 * Cœur PUR (clé passée en paramètre) → testable. Le wrapper de config reste
 * DORMANT tant que `tagtoa.nfc.ntag424` n'a pas de clé (aucun risque).
 * ⚠️ Ne câbler dans l'encaissement/check-in qu'après validation sur un vrai tag.
 */
class SunVerifier
{
    /**
     * Vérifie un tap : CMAC valide ET compteur plus récent que le dernier vu. PUR.
     *
     * @param  string    $key         clé maître AES-128 (binaire, 16 octets)
     * @param  string    $uid         UID (binaire)
     * @param  string    $ctr         compteur de lecture (binaire, comme Ntag424)
     * @param  string    $cmac        CMAC fourni par le tag (même format que Ntag424::verify)
     * @param  int|null  $lastCounter dernier compteur validé (null = première fois)
     * @return array{ok:bool,counter:int}
     */
    public static function verifyTap(string $key, string $uid, string $ctr, string $cmac, ?int $lastCounter): array
    {
        $counter = Ntag424::counterValue($ctr);

        $macOk = Ntag424::verify($key, $uid, $ctr, $cmac);
        $fresh = Ntag424::isFresh($lastCounter, $counter);

        return ['ok' => $macOk && $fresh, 'counter' => $counter];
    }

    /** NTAG424 activé (clé fournie) ? Tolérant. */
    public static function enabled(): bool
    {
        if (! function_exists('config')) {
            return false;
        }
        $cfg = (array) config('tagtoa.nfc.ntag424', []);

        return ! empty($cfg['enabled']) && ! empty($cfg['key']);
    }

    /** Clé maître (binaire) depuis la config hex, ou null si absente. Tolérant. */
    public static function configKey(): ?string
    {
        if (! function_exists('config')) {
            return null;
        }
        $hex = (string) config('tagtoa.nfc.ntag424.key', '');
        if ($hex === '' || ! ctype_xdigit($hex)) {
            return null;
        }

        return hex2bin($hex);
    }
}
