<?php

namespace Modules\Tagtoa\App\Support\Nfc;

use Modules\Tagtoa\App\Models\Event\NfcTag;

/**
 * TAGTOA — passerelle entre un tag NFC (Eloquent) et le vérificateur SUN pur.
 * Partagée par le check-in billetterie et le wallet closed-loop : les deux
 * s'appuient sur le même tag et la même garantie.
 *
 * Un tag ordinaire (sun_required=false, l'écrasante majorité aujourd'hui)
 * passe tel quel — comportement UID-seul inchangé, 0 régression.
 *
 * Un tag marqué sun_required=true ne retombe JAMAIS sur l'UID seul, même si
 * la clé globale (tagtoa.nfc.ntag424) est absente ou le drapeau désactivé :
 * sinon un opérateur qui coupe la clé par erreur ferait croire à une carte
 * « protégée » qu'elle le reste. $ctr/$cmac arrivent en hexadécimal, tels que
 * lus dans l'URL SDM du tag (TagTapVerifier n'existe pas encore côté lecteur
 * — c'est la prochaine étape, une fois une puce réelle disponible pour
 * valider le format exact de l'URL ; voir docs/NFC_SECURITY.md).
 */
class NfcTapVerifier
{
    public static function passes(NfcTag $tag, string $uid, ?string $ctr, ?string $cmac): bool
    {
        if (! $tag->requiresSun()) {
            return true;
        }

        $key = SunVerifier::configKey();
        $uidBin = self::hex($uid, 7);
        $ctrBin = $ctr !== null ? self::hex($ctr, 3) : null;
        $cmacBin = $cmac !== null ? self::hex($cmac, 8) : null;

        if (! SunVerifier::enabled() || ! $key || ! $uidBin || ! $ctrBin || ! $cmacBin) {
            return false;
        }

        $result = SunVerifier::verifyTap($key, $uidBin, $ctrBin, $cmacBin, $tag->last_counter);
        if (! $result['ok']) {
            return false;
        }

        $tag->update(['last_counter' => $result['counter']]);

        return true;
    }

    /** Hexadécimal attendu -> binaire, ou null si absent/malformé (longueur, caractères). */
    private static function hex(string $value, int $expectedBytes): ?string
    {
        $value = trim($value);
        if ($value === '' || ! ctype_xdigit($value) || strlen($value) !== $expectedBytes * 2) {
            return null;
        }

        return hex2bin($value);
    }
}
