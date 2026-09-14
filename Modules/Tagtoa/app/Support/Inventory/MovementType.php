<?php

namespace Modules\Tagtoa\App\Support\Inventory;

/**
 * TAGTOA INVENTORY — pourquoi le stock a bougé.
 *
 * Un commerce qui constate qu'il lui manque douze bouteilles ne peut rien en
 * faire tant qu'il ignore POURQUOI. Vendues ? Cassées ? Jamais livrées ?
 * Comptées de travers le mois dernier ? Ce sont quatre problèmes différents,
 * avec quatre réponses différentes — et un seul chiffre ne les distingue pas.
 *
 * D'où un motif obligatoire sur chaque mouvement. C'est ce qui transforme un
 * inventaire en information.
 *
 * Classe PURE : aucune dépendance Laravel, testable sans base de données.
 */
class MovementType
{
    /* --- Sorties --- */

    /** Vendu au comptoir ou par QR. Le cas courant. */
    public const SALE = 'sale';

    /** Cassé, périmé, volé, perdu. Ce que le commerce paie sans encaisser. */
    public const LOSS = 'loss';

    /** Consommé par le commerce lui-même (repas du personnel, offert). */
    public const INTERNAL = 'internal';

    /* --- Entrées --- */

    /** Reçu d'un fournisseur. */
    public const PURCHASE = 'purchase';

    /** Rendu par un client. Le stock revient. */
    public const RETURN_IN = 'return';

    /* --- Corrections --- */

    /** Comptage physique : le stock est REMPLACÉ par ce qui a été compté. */
    public const COUNT = 'count';

    /** Correction manuelle assumée, hors comptage. */
    public const ADJUSTMENT = 'adjustment';

    /** Première mise en stock à la création de l'article. */
    public const OPENING = 'opening';

    /**
     * Motif => [libellé, sens].
     *
     * Le sens n'est pas décoratif : il dit ce que l'écran de saisie doit
     * proposer, et il empêche d'enregistrer une casse qui ferait MONTER le
     * stock.
     *   'out'  — ne peut que faire baisser
     *   'in'   — ne peut que faire monter
     *   'both' — correction, libre dans les deux sens
     */
    public const TYPES = [
        self::SALE       => ['label' => 'Vente',              'sens' => 'out'],
        self::LOSS       => ['label' => 'Perte / casse',      'sens' => 'out'],
        self::INTERNAL   => ['label' => 'Consommation interne', 'sens' => 'out'],
        self::PURCHASE   => ['label' => 'Réception fournisseur', 'sens' => 'in'],
        self::RETURN_IN  => ['label' => 'Retour client',      'sens' => 'in'],
        self::COUNT      => ['label' => 'Comptage physique',  'sens' => 'both'],
        self::ADJUSTMENT => ['label' => 'Correction',         'sens' => 'both'],
        self::OPENING    => ['label' => 'Stock initial',      'sens' => 'both'],
    ];

    /**
     * Motifs que le patron peut choisir lui-même.
     *
     * La vente n'y est PAS : elle est enregistrée par la caisse. La proposer
     * permettrait de fabriquer une vente qui n'a encaissé aucun argent, donc de
     * faire disparaître du stock sans trace comptable.
     */
    public const MANUAL = [
        self::PURCHASE, self::RETURN_IN, self::LOSS,
        self::INTERNAL, self::COUNT, self::ADJUSTMENT,
    ];

    public static function isValid(?string $type): bool
    {
        return $type !== null && isset(self::TYPES[$type]);
    }

    /** Ce motif peut-il être choisi à la main par le patron ? PUR. */
    public static function isManual(?string $type): bool
    {
        return $type !== null && in_array($type, self::MANUAL, true);
    }

    public static function label(?string $type): string
    {
        return self::TYPES[$type]['label'] ?? 'Mouvement';
    }

    public static function direction(?string $type): ?string
    {
        return self::TYPES[$type]['sens'] ?? null;
    }

    /**
     * Ce mouvement respecte-t-il le sens de son motif ? PUR.
     *
     * Une casse qui ferait monter le stock, une réception qui le ferait
     * baisser : c'est presque toujours un signe moins oublié dans la saisie.
     * On refuse plutôt que d'enregistrer un mouvement qui raconte l'inverse de
     * ce qui s'est passé.
     */
    public static function allowsDelta(?string $type, float $delta): bool
    {
        if (! self::isValid($type) || $delta == 0.0) {
            return false; // un mouvement nul n'a rien à dire
        }

        return match (self::direction($type)) {
            'out'   => $delta < 0,
            'in'    => $delta > 0,
            default => true,
        };
    }

    /** Le stock est-il REMPLACÉ (comptage) plutôt qu'ajusté ? PUR. */
    public static function replacesStock(?string $type): bool
    {
        return $type === self::COUNT;
    }
}
