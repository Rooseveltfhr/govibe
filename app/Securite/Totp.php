<?php

namespace App\Securite;

/**
 * Codes à usage unique fondés sur le temps (RFC 6238), tels que les produisent
 * Google Authenticator, Authy ou 1Password.
 *
 * Écrit ici plutôt qu'ajouté en dépendance : le déploiement installe les
 * paquets depuis le serveur, et une dépendance de plus est une raison de plus
 * qu'un déploiement échoue et laisse le site en maintenance. L'algorithme est
 * entièrement spécifié et la RFC publie ses propres vecteurs de test — ils sont
 * dans la suite de tests, et c'est eux qui répondent de cette implémentation.
 *
 * Ce qui n'est pas ici est tout aussi important : la fenêtre de tolérance,
 * l'anti-rejeu et la limitation des tentatives vivent dans le service qui
 * appelle cette classe, parce qu'ils dépendent de l'utilisateur, pas du calcul.
 */
final class Totp
{
    /** Durée de vie d'un code, en secondes. La valeur que tous les lecteurs attendent. */
    public const PERIODE = 30;

    public const CHIFFRES = 6;

    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Un secret partagé de 160 bits, la taille recommandée par la RFC 4226.
     *
     * random_bytes() et non rand() : un secret prévisible se recalcule, et
     * toute la double authentification tombe avec lui.
     */
    public static function secret(int $octets = 20): string
    {
        return self::encoderBase32(random_bytes($octets));
    }

    /** Le code attendu pour un pas de temps donné. */
    public static function code(string $secret, int $pasDeTemps, int $chiffres = self::CHIFFRES): string
    {
        $cle = self::decoderBase32($secret);

        // Le compteur voyage sur 8 octets, gros-boutiste : c'est ce que la RFC
        // impose, et un lecteur tiers ne tolère aucune autre convention.
        $empreinte = hash_hmac('sha1', pack('J', $pasDeTemps), $cle, true);

        // Troncature dynamique : les quatre derniers bits désignent où lire.
        $depart = ord($empreinte[19]) & 0x0F;
        $valeur = ((ord($empreinte[$depart]) & 0x7F) << 24)
            | ((ord($empreinte[$depart + 1]) & 0xFF) << 16)
            | ((ord($empreinte[$depart + 2]) & 0xFF) << 8)
            | (ord($empreinte[$depart + 3]) & 0xFF);

        return str_pad(
            (string) ($valeur % (10 ** $chiffres)),
            $chiffres,
            '0',
            STR_PAD_LEFT
        );
    }

    public static function pasDeTemps(?int $instant = null): int
    {
        return intdiv($instant ?? time(), self::PERIODE);
    }

    /**
     * Vérifie un code et rend le pas de temps accepté, ou null.
     *
     * Rendre le pas plutôt qu'un booléen n'est pas un détail : c'est ce qui
     * permet à l'appelant de refuser qu'un même code serve deux fois. Un code
     * vit trente secondes ; sans cette mémoire, quelqu'un qui le lit par-dessus
     * l'épaule a trente secondes pour s'en servir.
     *
     * La fenêtre vaut un pas de part et d'autre : les horloges de téléphone
     * dérivent. Plus large, on allonge d'autant la durée de vie d'un code volé.
     */
    public static function verifier(
        string $secret,
        string $code,
        int $fenetre = 1,
        ?int $instant = null,
    ): ?int {
        $code = preg_replace('/\D+/', '', $code) ?? '';

        if (strlen($code) !== self::CHIFFRES) {
            return null;
        }

        $courant = self::pasDeTemps($instant);

        for ($ecart = -$fenetre; $ecart <= $fenetre; $ecart++) {
            // hash_equals et non « == » : une comparaison qui s'arrête au
            // premier caractère différent laisse deviner le code, chiffre après
            // chiffre, en mesurant le temps de réponse.
            if (hash_equals(self::code($secret, $courant + $ecart), $code)) {
                return $courant + $ecart;
            }
        }

        return null;
    }

    /**
     * L'adresse otpauth:// que lit l'application d'authentification.
     *
     * L'émetteur apparaît deux fois — dans l'étiquette et en paramètre — parce
     * que les lecteurs ne s'accordent pas sur celui qu'ils lisent.
     */
    public static function uri(string $secret, string $compte, string $emetteur): string
    {
        $etiquette = rawurlencode($emetteur).':'.rawurlencode($compte);

        return 'otpauth://totp/'.$etiquette.'?'.http_build_query([
            'secret' => $secret,
            'issuer' => $emetteur,
            'algorithm' => 'SHA1',
            'digits' => self::CHIFFRES,
            'period' => self::PERIODE,
        ]);
    }

    /** Le secret en groupes de quatre, pour être recopié à la main sans erreur. */
    public static function lisible(string $secret): string
    {
        return trim(chunk_split($secret, 4, ' '));
    }

    // ── Base32, RFC 4648 ─────────────────────────────────

    public static function encoderBase32(string $octets): string
    {
        $bits = '';

        foreach (str_split($octets) as $octet) {
            $bits .= str_pad(decbin(ord($octet)), 8, '0', STR_PAD_LEFT);
        }

        $sortie = '';

        foreach (str_split($bits, 5) as $morceau) {
            $sortie .= self::ALPHABET[bindec(str_pad($morceau, 5, '0', STR_PAD_RIGHT))];
        }

        return $sortie;
    }

    public static function decoderBase32(string $secret): string
    {
        // Les gens recopient le secret avec ses espaces et son remplissage.
        $secret = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $secret) ?? '');
        $bits = '';

        foreach (str_split($secret) as $caractere) {
            $position = strpos(self::ALPHABET, $caractere);

            if ($position === false) {
                continue;
            }

            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $octets = '';

        // Le dernier groupe incomplet est du remplissage : il ne porte pas
        // d'octet et l'inclure fausserait la clé.
        foreach (str_split($bits, 8) as $morceau) {
            if (strlen($morceau) === 8) {
                $octets .= chr(bindec($morceau));
            }
        }

        return $octets;
    }
}
