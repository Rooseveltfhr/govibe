<?php

namespace Modules\Tagtoa\App\Support\Api;

/**
 * TAGTOA API — fabrication et vérification des jetons développeur. LOGIQUE PURE
 * (aucune dépendance Laravel) : testable directement.
 *
 * Format : {prefix}_{secret}
 *   prefix = tag_live_XXXXXXXX  (public, sert d'index pour retrouver la ligne)
 *   secret = 40 caractères aléatoires (jamais stockés en clair)
 *
 * Stockage : `key_prefix` en clair + `key_hash` = SHA-256 du JETON COMPLET.
 * SHA-256 suffit ici (contrairement à un mot de passe) parce que le secret est
 * généré aléatoirement avec une entropie élevée : il n'est pas devinable par
 * force brute ou dictionnaire. La comparaison se fait en temps constant.
 */
class ApiToken
{
    public const PREFIX_LIVE = 'tag_live';
    public const PREFIX_TEST = 'tag_test';

    /** Longueur de la partie aléatoire du préfixe (identifiant public). */
    public const PREFIX_RANDOM = 8;

    /** Longueur du secret. */
    public const SECRET_LENGTH = 40;

    private const ALPHABET = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    /**
     * Génère un jeton complet.
     *
     * @return array{token: string, prefix: string, hash: string}
     */
    public static function generate(bool $live = true): array
    {
        $prefix = ($live ? self::PREFIX_LIVE : self::PREFIX_TEST).'_'.self::randomString(self::PREFIX_RANDOM);
        $token  = $prefix.'_'.self::randomString(self::SECRET_LENGTH);

        return ['token' => $token, 'prefix' => $prefix, 'hash' => self::hash($token)];
    }

    /** SHA-256 du jeton complet. */
    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Extrait le préfixe public d'un jeton présenté (les 3 premiers segments).
     * Retourne null si le jeton est manifestement mal formé — on évite ainsi
     * toute requête base inutile sur une entrée aberrante.
     */
    public static function prefixOf(?string $token): ?string
    {
        if (! is_string($token) || $token === '') {
            return null;
        }
        $parts = explode('_', $token);
        if (count($parts) !== 4) {
            return null;
        }
        [$a, $b, $c, $secret] = $parts;
        if ($a !== 'tag' || ! in_array($a.'_'.$b, [self::PREFIX_LIVE, self::PREFIX_TEST], true)) {
            return null;
        }
        if ($c === '' || $secret === '') {
            return null;
        }

        return $a.'_'.$b.'_'.$c;
    }

    /** Comparaison en temps constant entre un jeton présenté et un hash stocké. */
    public static function matches(?string $token, ?string $storedHash): bool
    {
        if (! is_string($token) || ! is_string($storedHash) || $storedHash === '') {
            return false;
        }

        return hash_equals($storedHash, self::hash($token));
    }

    /**
     * Extrait le jeton d'un en-tête Authorization.
     * Accepte « Bearer xxx » (recommandé) et la valeur brute.
     */
    public static function fromHeader(?string $header): ?string
    {
        if (! is_string($header)) {
            return null;
        }
        $header = trim($header);
        if ($header === '') {
            return null;
        }
        if (stripos($header, 'bearer ') === 0) {
            $header = trim(substr($header, 7));
        }

        return $header !== '' ? $header : null;
    }

    /** Masque un jeton pour l'affichage (on ne remontre jamais le secret). */
    public static function mask(string $prefix): string
    {
        return $prefix.'_'.str_repeat('•', 12);
    }

    private static function randomString(int $length): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= self::ALPHABET[random_int(0, $max)];
        }

        return $out;
    }
}
