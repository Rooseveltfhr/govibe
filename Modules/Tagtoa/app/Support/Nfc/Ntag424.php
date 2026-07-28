<?php

namespace Modules\Tagtoa\App\Support\Nfc;

/**
 * NTAG424 DNA — vérification SUN / SDM (Secure Unique NFC).
 *
 * Une puce NTAG424 signée génère, à chaque lecture, une URL contenant son UID,
 * un compteur de lecture (SDMReadCtr) et un CMAC tronqué. Le CMAC est calculé
 * avec une clé de session dérivée de la clé maître : un CLONE qui ne connaît pas
 * la clé ne peut PAS produire un CMAC valide, et le compteur strictement
 * croissant empêche le REJEU d'une ancienne lecture capturée. C'est ce qui
 * transforme une carte TAGTOA Pay / un porte-monnaie d'événement d'un simple
 * identifiant (clonable) en un jeton infalsifiable.
 *
 * Dérivation (NXP AN12196) :
 *   SV2 = 3Ch C3h 00h 01h 00h 80h || UID(7) || SDMReadCtr(3)   (16 octets)
 *   SesSDMFileReadMACKey = CMAC(Kmac, SV2)
 *   MAC = tronqué( CMAC(SesSDMFileReadMACKey, <données MAC, vide si miroir seul>) )
 * La troncature NTAG424 conserve les octets IMPAIRS du CMAC 16 octets → 8 octets.
 *
 * ⚠️ DORMANT tant qu'aucune clé n'est provisionnée (comme les passerelles PAY).
 * Le préfixe SV2 et le schéma de troncature doivent être validés contre un tag
 * RÉEL (ou l'exemple AN12196) avant activation en production ; la logique de
 * vérification (CMAC RFC 4493, troncature, anti-rejeu, extraction UID) est, elle,
 * testée et prouvée.
 */
class Ntag424
{
    /** Préfixe de dérivation de la clé de session SDMFileRead MAC (NXP AN12196). */
    private const SV2_PREFIX = "\x3C\xC3\x00\x01\x00\x80";

    /**
     * Dérive la clé de session MAC pour une lecture donnée.
     *
     * @param  string  $macKey  clé maître SDMFileReadMAC (16 octets bruts)
     * @param  string  $uid     UID de la puce (7 octets bruts)
     * @param  string  $readCtr compteur de lecture (3 octets bruts, tels que lus)
     */
    public static function sessionMacKey(string $macKey, string $uid, string $readCtr): string
    {
        if (strlen($uid) !== 7 || strlen($readCtr) !== 3) {
            throw new \InvalidArgumentException('UID doit faire 7 octets et SDMReadCtr 3 octets.');
        }

        return AesCmac::mac($macKey, self::SV2_PREFIX.$uid.$readCtr);
    }

    /**
     * Troncature NTAG424 : garde les octets d'index impair (1,3,…,15) du CMAC
     * 16 octets → 8 octets, tels qu'affichés dans l'URL du tag.
     */
    public static function truncate(string $fullCmac): string
    {
        if (strlen($fullCmac) !== 16) {
            throw new \InvalidArgumentException('Le CMAC complet doit faire 16 octets.');
        }
        $out = '';
        for ($i = 1; $i < 16; $i += 2) {
            $out .= $fullCmac[$i];
        }

        return $out; // 8 octets
    }

    /**
     * Calcule le MAC tronqué (8 octets) attendu pour une lecture.
     *
     * @param  string  $macInput  données MAC de la puce (vide pour un miroir UID+ctr seul)
     */
    public static function expectedMac(string $macKey, string $uid, string $readCtr, string $macInput = ''): string
    {
        $ses = self::sessionMacKey($macKey, $uid, $readCtr);

        return self::truncate(AesCmac::mac($ses, $macInput));
    }

    /**
     * Vérifie un CMAC tronqué fourni par un tag (comparaison temps constant).
     *
     * @param  string  $providedCmac  CMAC tronqué reçu (8 octets bruts)
     */
    public static function verify(string $macKey, string $uid, string $readCtr, string $providedCmac, string $macInput = ''): bool
    {
        if (strlen($providedCmac) !== 8) {
            return false;
        }

        return hash_equals(self::expectedMac($macKey, $uid, $readCtr, $macInput), $providedCmac);
    }

    /**
     * Convertit le compteur de lecture 3 octets (petit-boutiste, tel que la puce
     * l'expose) en entier — pour l'anti-rejeu.
     */
    public static function counterValue(string $readCtr): int
    {
        if (strlen($readCtr) !== 3) {
            throw new \InvalidArgumentException('SDMReadCtr doit faire 3 octets.');
        }

        return ord($readCtr[0]) | (ord($readCtr[1]) << 8) | (ord($readCtr[2]) << 16);
    }

    /**
     * Anti-rejeu : le compteur d'une nouvelle lecture doit être STRICTEMENT
     * supérieur au dernier compteur accepté pour cette puce. `null` = jamais vue.
     */
    public static function isFresh(?int $lastCounter, int $current): bool
    {
        return $lastCounter === null ? true : $current > $lastCounter;
    }
}
