<?php

namespace Modules\Tagtoa\App\Support\Catalog;

/**
 * TAGTOA — lire, vérifier et fabriquer un code d'article.
 *
 * Deux réalités cohabitent dans un commerce haïtien :
 *
 *   • les produits industriels portent un code-barres imprimé (EAN-13, EAN-8,
 *     UPC-A) avec un chiffre de contrôle qui permet de détecter une lecture
 *     fausse ;
 *   • une grande partie de ce qui se vend n'en a AUCUN — pâté, fresco, sachet
 *     dlo, manje kwit, artisanat. Pour ceux-là, TAGTOA fabrique un code interne
 *     que le commerce imprime sur une étiquette et rescanne ensuite.
 *
 * Un scanner se trompe : mauvaise lumière, code abîmé, appareil bon marché. Le
 * chiffre de contrôle est ce qui empêche une lecture erronée de désigner un
 * autre article — donc d'encaisser le mauvais prix.
 *
 * Classe PURE : aucune dépendance Laravel, testable sans base de données.
 */
class Barcode
{
    /** Préfixe des codes fabriqués par TAGTOA (produits sans code-barres). */
    public const INTERNAL_PREFIX = 'TAG';

    public const TYPE_EAN13    = 'ean13';
    public const TYPE_EAN8     = 'ean8';
    public const TYPE_UPCA     = 'upca';
    public const TYPE_INTERNAL = 'internal';
    public const TYPE_OTHER    = 'other';   // Code 128 / Code 39 : longueur libre

    /** Longueur maximale acceptée, tous formats confondus. */
    public const MAX_LENGTH = 48;

    /** Garde ce qui peut constituer un code : chiffres, lettres, tiret. PUR. */
    public static function normalize(?string $code): string
    {
        $code = strtoupper(trim((string) $code));
        $code = preg_replace('/[^A-Z0-9\-]/', '', $code) ?? '';

        return mb_substr($code, 0, self::MAX_LENGTH);
    }

    /**
     * Chiffre de contrôle EAN/UPC. PUR.
     *
     * Somme pondérée 1 et 3 en partant de la droite, puis complément à la
     * dizaine supérieure. Vaut pour EAN-13, EAN-8 et UPC-A.
     *
     * @param  string  $digits  le code SANS son dernier chiffre
     */
    public static function checkDigit(string $digits): ?int
    {
        if ($digits === '' || ! ctype_digit($digits)) {
            return null;
        }

        $somme = 0;
        // On pondère depuis la DROITE : le rang dépend de la longueur, pas de
        // la position absolue — c'est ce qui rend la formule valable pour les
        // trois longueurs.
        foreach (array_reverse(str_split($digits)) as $rang => $chiffre) {
            $somme += (int) $chiffre * ($rang % 2 === 0 ? 3 : 1);
        }

        return (10 - $somme % 10) % 10;
    }

    /** Le chiffre de contrôle d'un code EAN/UPC est-il juste ? PUR. */
    public static function hasValidCheckDigit(string $code): bool
    {
        $code = self::normalize($code);
        if (! ctype_digit($code) || ! in_array(strlen($code), [8, 12, 13], true)) {
            return false;
        }

        $attendu = self::checkDigit(substr($code, 0, -1));

        return $attendu !== null && $attendu === (int) substr($code, -1);
    }

    /** Format reconnu d'un code. PUR. */
    public static function typeOf(?string $code): ?string
    {
        $code = self::normalize($code);
        if ($code === '') {
            return null;
        }

        if (str_starts_with($code, self::INTERNAL_PREFIX.'-')) {
            return self::TYPE_INTERNAL;
        }

        if (ctype_digit($code)) {
            return match (strlen($code)) {
                13      => self::TYPE_EAN13,
                12      => self::TYPE_UPCA,
                8       => self::TYPE_EAN8,
                default => self::TYPE_OTHER,
            };
        }

        return self::TYPE_OTHER;
    }

    /**
     * Ce code peut-il être enregistré ? PUR.
     *
     * Un code industriel dont le chiffre de contrôle est faux est REFUSÉ : il
     * vient d'une lecture erronée ou d'une saisie à la main, et l'accepter
     * reviendrait à créer un article que personne ne retrouvera jamais en
     * scannant. Les autres formats (Code 128, Code 39, code interne) n'ont pas
     * de chiffre de contrôle et sont acceptés tels quels.
     */
    public static function isAcceptable(?string $code): bool
    {
        $code = self::normalize($code);
        if (strlen($code) < 4) {
            return false;
        }

        return match (self::typeOf($code)) {
            self::TYPE_EAN13, self::TYPE_EAN8, self::TYPE_UPCA => self::hasValidCheckDigit($code),
            default => true,
        };
    }

    /**
     * Fabrique le code interne d'un article sans code-barres. PUR.
     *
     * Forme « TAG-000245-7 » : préfixe, numéro d'article sur six chiffres, et
     * un chiffre de contrôle calculé comme celui d'un EAN. Le contrôle sert
     * autant ici : une étiquette imprimée puis abîmée sera rejetée plutôt que
     * de désigner un autre article.
     *
     * Volontairement lisible à l'œil : quand le scanner refuse, le caissier
     * tape les chiffres.
     */
    public static function internal(int $productId): string
    {
        $numero = str_pad((string) max(1, $productId), 6, '0', STR_PAD_LEFT);

        return self::INTERNAL_PREFIX.'-'.$numero.'-'.self::checkDigit($numero);
    }

    /** Numéro d'article porté par un code interne, ou null. PUR. */
    public static function internalProductId(?string $code): ?int
    {
        $code = self::normalize($code);

        if (! preg_match('/^'.self::INTERNAL_PREFIX.'-(\d{6})-(\d)$/', $code, $m)) {
            return null;
        }

        // Chiffre de contrôle faux ⇒ lecture abîmée : on ne devine pas.
        if (self::checkDigit($m[1]) !== (int) $m[2]) {
            return null;
        }

        return (int) $m[1];
    }

    /** Libellés des formats, pour l'affichage. */
    public const TYPE_LABELS = [
        self::TYPE_EAN13    => 'EAN-13',
        self::TYPE_EAN8     => 'EAN-8',
        self::TYPE_UPCA     => 'UPC-A',
        self::TYPE_INTERNAL => 'Code TAGTOA',
        self::TYPE_OTHER    => 'Autre format',
    ];

    public static function typeLabel(?string $code): string
    {
        return self::TYPE_LABELS[self::typeOf($code)] ?? 'Inconnu';
    }
}
