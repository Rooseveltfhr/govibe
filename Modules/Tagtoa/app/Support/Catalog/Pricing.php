<?php

namespace Modules\Tagtoa\App\Support\Catalog;

use Modules\Tagtoa\App\Services\Inventory\StockService;

/**
 * TAGTOA — prix d'achat, prix de vente, et ce qu'il reste entre les deux.
 *
 * Beaucoup de petits commerces savent exactement combien ils ont vendu, et pas
 * du tout combien ils ont gagné. C'est la différence entre une caisse qui
 * compte l'argent et un outil qui dit si le commerce tient debout.
 *
 * Rien de tout cela n'est calculable sans le prix d'achat : c'est pourquoi il
 * entre ici, avant le scanner.
 *
 * Classe PURE : aucune dépendance Laravel, testable sans base de données.
 */
class Pricing
{
    /** Unités de vente. Celles d'Haïti et de la région d'abord. */
    public const UNITS = [
        'piece'  => ['label' => 'Pièce',      'decimal' => false],
        'lb'     => ['label' => 'Livre',      'decimal' => true],
        'kg'     => ['label' => 'Kilogramme', 'decimal' => true],
        'g'      => ['label' => 'Gramme',     'decimal' => false],
        'l'      => ['label' => 'Litre',      'decimal' => true],
        'ml'     => ['label' => 'Millilitre', 'decimal' => false],
        'gode'   => ['label' => 'Gode',       'decimal' => true],
        'mamit'  => ['label' => 'Mamit',      'decimal' => true],
        'timamit'=> ['label' => 'Ti mamit',   'decimal' => true],
        'sac'    => ['label' => 'Sac',        'decimal' => false],
        'boite'  => ['label' => 'Boîte',      'decimal' => false],
        'douzaine' => ['label' => 'Douzaine', 'decimal' => false],
    ];

    public const DEFAULT_UNIT = 'piece';

    /** Unité connue, sinon la pièce. PUR. */
    public static function unit(?string $unit): string
    {
        return isset(self::UNITS[$unit]) ? $unit : self::DEFAULT_UNIT;
    }

    public static function unitLabel(?string $unit): string
    {
        return self::UNITS[self::unit($unit)]['label'];
    }

    /** Cette unité se vend-elle en quantités fractionnées (2,5 livres) ? PUR. */
    public static function allowsDecimal(?string $unit): bool
    {
        return self::UNITS[self::unit($unit)]['decimal'];
    }

    /**
     * Marge en valeur : ce qui reste sur une unité vendue. PUR.
     *
     * Renvoie null quand le prix d'achat n'est pas renseigné — et c'est
     * important : afficher « 0 » laisserait croire que le commerce ne gagne
     * rien, alors que la vérité est qu'on ne sait pas encore.
     */
    public static function margin(?float $cost, ?float $price): ?float
    {
        if ($cost === null || $price === null) {
            return null;
        }

        return round($price - $cost, 2);
    }

    /**
     * Marge en pourcentage DU PRIX DE VENTE. PUR.
     *
     * C'est la convention commerciale : sur 100 gourdes encaissées, combien
     * restent. Un prix de vente nul ne donne pas une marge infinie mais null —
     * un article offert n'a pas de marge à afficher.
     */
    public static function marginPercent(?float $cost, ?float $price): ?float
    {
        $marge = self::margin($cost, $price);
        if ($marge === null || $price === null || $price <= 0) {
            return null;
        }

        return round($marge / $price * 100, 1);
    }

    /**
     * Le prix de vente couvre-t-il au moins le prix d'achat ? PUR.
     *
     * Vendre à perte arrive — écouler un stock qui approche de sa date. Mais
     * cela doit se voir, pas se découvrir en fin de mois.
     */
    public static function isSoldAtLoss(?float $cost, ?float $price): bool
    {
        $marge = self::margin($cost, $price);

        return $marge !== null && $marge < 0;
    }

    /**
     * Prix de vente suggéré à partir du coût et d'une marge visée. PUR.
     *
     * La marge est exprimée en pourcentage du prix de VENTE, comme partout
     * ailleurs ici. Viser 100 % ou plus n'a pas de sens (il faudrait un prix
     * infini) : on refuse plutôt que de rendre un nombre absurde.
     */
    public static function suggestPrice(?float $cost, float $targetPercent): ?float
    {
        if ($cost === null || $cost < 0 || $targetPercent < 0 || $targetPercent >= 100) {
            return null;
        }

        return round($cost / (1 - $targetPercent / 100), 2);
    }

    /**
     * Quantité normalisée pour cette unité. PUR.
     *
     * Une pièce ne se vend pas par 2,5 : on arrondit à l'entier SUPÉRIEUR
     * plutôt que d'encaisser une quantité que le client ne peut pas emporter.
     * Une livre, une mamit, un gode, si — et c'est l'essentiel du commerce de
     * quartier.
     */
    public static function normalizeQty(?string $unit, float $qty): float
    {
        return self::allowsDecimal($unit)
            ? StockService::qty($qty)
            : (float) ceil(max(0.0, $qty));
    }

    /** Total d'une ligne, à l'unité près. PUR. */
    public static function lineTotal(?string $unit, float $price, float $qty): float
    {
        return round($price * self::normalizeQty($unit, $qty), 2);
    }
}
