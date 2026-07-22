<?php

namespace Modules\Tagtoa\App\Support\Nfc;

/**
 * AES-CMAC (RFC 4493) — primitive cryptographique PURE.
 *
 * Brique de base de l'authentification NTAG424 (SUN/SDM) : une puce NFC
 * sécurisée signe chaque lecture avec un CMAC calculé sur son UID + un compteur,
 * ce qui rend le tag INFALSIFIABLE (un clone qui ne connaît pas la clé ne peut
 * pas produire un CMAC valide). Sans ça, un tag identifié par simple UID est
 * clonable — inacceptable pour une carte de paiement / un porte-monnaie.
 *
 * Implémentation vérifiable : testée contre les vecteurs officiels RFC 4493
 * (annexe D), donc prouvée correcte en CI sans matériel. S'appuie sur le
 * chiffreur AES-128 d'OpenSSL (pas de crypto maison au niveau bloc).
 */
class AesCmac
{
    private const BLOCK = 16;
    private const RB = "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x87";

    /**
     * Calcule le CMAC (16 octets bruts) du message sous la clé donnée.
     *
     * @param  string  $key  clé AES-128 (16 octets bruts)
     * @param  string  $msg  message (octets bruts, longueur quelconque dont 0)
     */
    public static function mac(string $key, string $msg): string
    {
        if (strlen($key) !== self::BLOCK) {
            throw new \InvalidArgumentException('AES-CMAC exige une clé de 16 octets.');
        }

        [$k1, $k2] = self::subkeys($key);

        $n = intdiv(strlen($msg) + self::BLOCK - 1, self::BLOCK);
        $complete = false;
        if ($n === 0) {
            $n = 1;
        } else {
            $complete = (strlen($msg) % self::BLOCK) === 0;
        }

        $lastStart = ($n - 1) * self::BLOCK;
        $lastBlock = substr($msg, $lastStart);
        if ($complete) {
            $mLast = self::xor($lastBlock, $k1);
        } else {
            $mLast = self::xor(self::pad($lastBlock), $k2);
        }

        $x = str_repeat("\x00", self::BLOCK);
        for ($i = 0; $i < $n - 1; $i++) {
            $block = substr($msg, $i * self::BLOCK, self::BLOCK);
            $x = self::aes($key, self::xor($x, $block));
        }

        return self::aes($key, self::xor($x, $mLast));
    }

    /** Version hexadécimale (minuscule) du CMAC. */
    public static function macHex(string $key, string $msg): string
    {
        return bin2hex(self::mac($key, $msg));
    }

    /** Comparaison en temps constant d'un CMAC attendu (octets bruts). */
    public static function verify(string $key, string $msg, string $expected): bool
    {
        return hash_equals(self::mac($key, $msg), $expected);
    }

    /** Dérive les sous-clés K1, K2 (RFC 4493 §2.3). */
    private static function subkeys(string $key): array
    {
        $l = self::aes($key, str_repeat("\x00", self::BLOCK));
        $k1 = self::shiftLeft($l);
        if (ord($l[0]) & 0x80) {
            $k1 = self::xor($k1, self::RB);
        }
        $k2 = self::shiftLeft($k1);
        if (ord($k1[0]) & 0x80) {
            $k2 = self::xor($k2, self::RB);
        }

        return [$k1, $k2];
    }

    /** Chiffrement AES-128-ECB d'un seul bloc (16 octets → 16 octets). */
    private static function aes(string $key, string $block): string
    {
        $out = openssl_encrypt($block, 'aes-128-ecb', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);
        if ($out === false || strlen($out) < self::BLOCK) {
            throw new \RuntimeException('Échec du chiffrement AES (OpenSSL).');
        }

        return substr($out, 0, self::BLOCK);
    }

    /** Décalage à gauche de 1 bit sur 16 octets. */
    private static function shiftLeft(string $b): string
    {
        $out = '';
        $carry = 0;
        for ($i = self::BLOCK - 1; $i >= 0; $i--) {
            $v = (ord($b[$i]) << 1) | $carry;
            $carry = ($v >> 8) & 1;
            $out = chr($v & 0xFF).$out;
        }

        return $out;
    }

    /** Padding RFC 4493 : 0x80 puis des 0x00 jusqu'à 16 octets. */
    private static function pad(string $b): string
    {
        return str_pad($b."\x80", self::BLOCK, "\x00", STR_PAD_RIGHT);
    }

    private static function xor(string $a, string $b): string
    {
        return $a ^ $b;
    }
}
