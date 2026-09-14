<?php

namespace Modules\Tagtoa\App\Support\Stand;

/**
 * TAGTOA SMART STAND — l'identité imprimée et le secret qui prouve la possession.
 *
 * Deux choses de nature différente, volontairement séparées :
 *
 *   • L'IDENTIFIANT PUBLIC « TG-000001 » est une IDENTITÉ, comme une plaque
 *     d'immatriculation. Il est imprimé au recto, affiché sur une table de
 *     restaurant, photographiable par n'importe qui. Il est séquentiel, et
 *     c'est voulu : une plage s'affecte à un revendeur, un carton se range, un
 *     numéro se dicte au téléphone.
 *
 *   • Le SECRET est une AUTORITÉ. Il vit sous un panneau à gratter, et c'est la
 *     SEULE information que la photo d'un stand ne montre pas. Tout le modèle de
 *     sécurité tient là-dessus : sans lui, quiconque photographie un stand chez
 *     un concurrent pourrait réclamer son compte.
 *
 * Rendre l'identifiant imprévisible ne protégerait rien — il est affiché — et
 * coûterait la logistique. C'est la SÉPARATION qui protège, pas l'obscurité.
 *
 * ⚠️ CE QUI EST GRAVÉ ICI EST IRRÉVERSIBLE. Le format de l'identifiant et la
 * longueur du secret partent chez l'imprimeur ; dix mille objets en circulation
 * ne se rappellent pas. Toute évolution devra cohabiter avec l'existant.
 *
 * Classe PURE : aucune dépendance Laravel, testable sans base de données.
 */
class StandId
{
    /** Préfixe imprimé. Court, prononçable, sans ambiguïté à l'oral. */
    public const PREFIX = 'TG';

    /** Chiffres du numéro de série. Six couvre 999 999 stands par préfixe. */
    public const DIGITS = 6;

    /**
     * Alphabet Crockford Base32 — I, L, O et U retirés.
     *
     * Un client tape ce code sur un téléphone, à une main, parfois dans un
     * restaurant mal éclairé. Retirer I/1, L/1, O/0 supprime la faute la plus
     * fréquente ; U est retiré par Crockford pour éviter de former des mots
     * grossiers par hasard sur un objet imprimé à dix mille exemplaires.
     */
    public const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    /**
     * Longueur du secret. 32⁸ ≈ 1,1 × 10¹², soit 40 bits.
     *
     * Avec la limitation à 10 essais par heure et par stand, forcer un code
     * demanderait en moyenne plus de deux millions d'années. Huit caractères se
     * tapent en dix secondes — c'est le point d'équilibre entre une sécurité
     * réelle et un commerçant qui ne renonce pas.
     */
    public const SECRET_LENGTH = 8;

    /* ------------------------------------------------------------------
       L'identifiant public.
       ------------------------------------------------------------------ */

    /** « TG-000001 » depuis un numéro de série. PUR. */
    public static function format(int $serial, string $prefix = self::PREFIX): string
    {
        return strtoupper($prefix).'-'.str_pad((string) max(1, $serial), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Numéro de série porté par un identifiant, ou null. PUR.
     *
     * Tolérant à la saisie : on accepte les minuscules et les espaces, parce
     * qu'un commerçant qui recopie « tg 000001 » désigne bien son stand.
     */
    public static function serial(?string $publicId): ?int
    {
        $id = self::normalizeId($publicId);

        if (! preg_match('/^[A-Z]{2,8}-(\d{'.self::DIGITS.'})$/', $id, $m)) {
            return null;
        }

        $serial = (int) $m[1];

        return $serial > 0 ? $serial : null;
    }

    /**
     * Identifiant nettoyé : majuscules, sans espaces ni ponctuation parasite. PUR.
     *
     * Le tiret est REMIS s'il manque. « TG 000001 » et « TG000001 » désignent
     * le même stand que « TG-000001 » — un commerçant qui recopie son numéro à
     * la main ne doit pas s'entendre dire qu'il n'existe pas.
     */
    public static function normalizeId(?string $publicId): string
    {
        $id = strtoupper(trim((string) $publicId));
        $id = preg_replace('/[^A-Z0-9\-]/', '', $id) ?? '';

        if (preg_match('/^([A-Z]{2,8})(\d{'.self::DIGITS.'})$/', $id, $m)) {
            $id = $m[1].'-'.$m[2];
        }

        return mb_substr($id, 0, 24);
    }

    /** Cet identifiant a-t-il la forme attendue ? PUR. */
    public static function isValidId(?string $publicId): bool
    {
        return self::serial($publicId) !== null;
    }

    /** Préfixe porté par un identifiant, ou null. PUR. */
    public static function prefixOf(?string $publicId): ?string
    {
        $id = self::normalizeId($publicId);

        return preg_match('/^([A-Z]{2,8})-\d{'.self::DIGITS.'}$/', $id, $m) ? $m[1] : null;
    }

    /* ------------------------------------------------------------------
       Le secret d'activation.
       ------------------------------------------------------------------ */

    /**
     * Fabrique un secret. NON PUR par nature — il doit être imprévisible.
     *
     * `random_int` est l'unique source acceptable : `rand()` et `mt_rand()` sont
     * prévisibles à partir de quelques tirages, et un attaquant qui possède UN
     * stand pourrait alors déduire ceux du même lot.
     */
    public static function makeSecret(int $length = self::SECRET_LENGTH): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $out = '';

        for ($i = 0; $i < max(4, $length); $i++) {
            $out .= self::ALPHABET[random_int(0, $max)];
        }

        return $out;
    }

    /**
     * Secret nettoyé, prêt à comparer. PUR.
     *
     * Applique les corrections Crockford : ce que la personne a tapé est ramené
     * à ce que l'alphabet peut contenir. Quelqu'un qui lit « O » sur son
     * étiquette a vu un zéro ; le refuser lui ferait croire que son code est
     * faux alors qu'il est juste.
     */
    public static function normalizeSecret(?string $secret): string
    {
        $s = strtoupper(trim((string) $secret));

        // Les séparateurs que les gens ajoutent en recopiant.
        $s = str_replace([' ', '-', '.', '_'], '', $s);

        // Corrections Crockford : O→0, I/L→1.
        $s = strtr($s, ['O' => '0', 'I' => '1', 'L' => '1']);

        // U n'existe pas dans l'alphabet : il vient forcément d'une lecture
        // fautive de V, la seule lettre qui lui ressemble à l'impression.
        $s = strtr($s, ['U' => 'V']);

        $s = preg_replace('/[^'.self::ALPHABET.']/', '', $s) ?? '';

        return mb_substr($s, 0, 32);
    }

    /** Ce secret a-t-il la forme attendue ? PUR. */
    public static function isValidSecret(?string $secret, int $length = self::SECRET_LENGTH): bool
    {
        return strlen(self::normalizeSecret($secret)) === max(4, $length);
    }

    /**
     * Secret groupé pour l'impression : « A3F9-K2MP ».
     *
     * Huit caractères d'affilée se recopient mal ; en deux groupes de quatre,
     * l'œil ne perd pas sa place. Le groupement est cosmétique — la
     * normalisation retire les tirets avant toute comparaison.
     * PUR.
     */
    public static function pretty(string $secret): string
    {
        $s = self::normalizeSecret($secret);

        return strlen($s) === 8 ? substr($s, 0, 4).'-'.substr($s, 4) : $s;
    }

    /**
     * Entropie du secret, en bits. PUR.
     *
     * Sert au test de garde : si quelqu'un raccourcit le secret ou réduit
     * l'alphabet « pour simplifier la saisie », la sécurité s'effondre sans
     * qu'aucune fonctionnalité ne cesse de marcher — le genre de régression
     * qu'on ne voit jamais.
     */
    public static function entropyBits(int $length = self::SECRET_LENGTH): float
    {
        return round($length * log(strlen(self::ALPHABET), 2), 2);
    }

    /* ------------------------------------------------------------------
       L'URL imprimée.
       ------------------------------------------------------------------ */

    /**
     * Le chemin gravé dans le QR et dans la puce. PUR.
     *
     * Court — il tient en QR version 2 avec correction haute, donc de gros
     * modules qui se lisent de loin, de biais, sur un papier taché. Et sans
     * rien qui puisse changer : ni nom de commerce, ni numéro de table, ni nom
     * de module. Tout ce qui peut changer un jour ne s'imprime pas.
     */
    public static function path(string $publicId): string
    {
        return '/s/'.self::normalizeId($publicId);
    }
}
