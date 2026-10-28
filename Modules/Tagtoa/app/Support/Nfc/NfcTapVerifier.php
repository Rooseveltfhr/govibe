<?php

namespace Modules\Tagtoa\App\Support\Nfc;

use Illuminate\Support\Facades\DB;
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
 *
 * La lecture du compteur et son avancement sont faits sous verrou de ligne
 * (comme PostLedgerTransaction pour le solde) : sans ça, deux rejeux
 * CONCURRENTS du même tap capturé liraient le même last_counter avant que
 * l'un des deux n'écrive, passeraient tous les deux la fraîcheur, et
 * ChargeWallet ne les rattraperait pas — client_uuid est fourni par
 * l'appelant, donc un rejeu délibéré l'omet simplement et obtient deux clés
 * d'idempotence différentes. C'est ICI, pas dans le ledger, que le rejeu
 * doit être bloqué.
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

        return DB::transaction(function () use ($tag, $key, $uidBin, $ctrBin, $cmacBin) {
            // Relecture sous verrou : la copie déjà chargée dans $tag peut être
            // périmée si un autre tap concurrent vient juste de faire avancer
            // last_counter.
            $locked = NfcTag::whereKey($tag->getKey())->lockForUpdate()->first();
            if (! $locked) {
                return false;
            }

            $result = SunVerifier::verifyTap($key, $uidBin, $ctrBin, $cmacBin, $locked->last_counter);
            if (! $result['ok']) {
                return false;
            }

            $locked->update(['last_counter' => $result['counter']]);
            $tag->last_counter = $result['counter'];

            return true;
        });
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
