<?php

namespace Modules\Tagtoa\App\Support\Order;

/**
 * TAGTOA — où en est une commande, et où en est son paiement.
 *
 * DEUX axes séparés, volontairement. Les confondre est l'erreur classique :
 * une commande peut être payée d'avance et pas encore préparée, ou livrée et
 * jamais payée (le voisin qui passe régler demain). Un seul statut obligerait
 * à inventer des états comme « livrée-impayée » qui se multiplient sans fin.
 *
 * Chaque module gardait son propre vocabulaire — la caisse un entier, le menu
 * et la boutique des chaînes qui ne se recouvraient pas tout à fait. Un
 * rapport commun devait les traduire dans les deux sens, et se trompait.
 *
 * Classe PURE : aucune dépendance Laravel, testable sans base de données.
 */
class OrderStatus
{
    /* --- Où en est la commande --- */

    public const PENDING   = 'pending';    // reçue, rien n'a bougé
    public const CONFIRMED = 'confirmed';  // acceptée par le commerce
    public const PREPARING = 'preparing';  // en cuisine, en préparation
    public const READY     = 'ready';      // prête à emporter
    public const SHIPPED   = 'shipped';    // partie en livraison
    public const COMPLETED = 'completed';  // remise au client
    public const CANCELLED = 'cancelled';  // annulée
    public const REFUNDED  = 'refunded';   // remboursée après coup

    public const LABELS = [
        self::PENDING   => 'En attente',
        self::CONFIRMED => 'Confirmée',
        self::PREPARING => 'En préparation',
        self::READY     => 'Prête',
        self::SHIPPED   => 'En livraison',
        self::COMPLETED => 'Terminée',
        self::CANCELLED => 'Annulée',
        self::REFUNDED  => 'Remboursée',
    ];

    public const ALL = [
        self::PENDING, self::CONFIRMED, self::PREPARING,
        self::READY, self::SHIPPED, self::COMPLETED, self::CANCELLED, self::REFUNDED,
    ];

    /** Rien ne sera plus encaissé ni préparé. */
    public const CLOSED = [self::COMPLETED, self::CANCELLED, self::REFUNDED];

    /* --- Où en est le paiement --- */

    public const UNPAID   = 'unpaid';
    public const PARTIAL  = 'partial';   // acompte, split incomplet
    public const PAID     = 'paid';
    public const REFUND   = 'refunded';

    public const PAYMENT_LABELS = [
        self::UNPAID  => 'Impayée',
        self::PARTIAL => 'Acompte versé',
        self::PAID    => 'Payée',
        self::REFUND  => 'Remboursée',
    ];

    public const PAYMENTS = [self::UNPAID, self::PARTIAL, self::PAID, self::REFUND];

    /**
     * Vocabulaires des modules, traduits vers le vocabulaire commun.
     *
     * L'événement compte en entiers (0 en attente, 1 payé, 2 annulé) parce
     * qu'il est antérieur à cette table. Le traduire ici plutôt que de le
     * réécrire évite de toucher une billetterie qui fonctionne.
     *
     * Un statut de module absent de cette table est un BUG, pas un cas à
     * ignorer : la colonne vertébrale afficherait une commande livrée comme
     * encore à préparer. Un test le vérifie module par module.
     */
    public const FROM_EVENT = [
        0 => self::PENDING,
        1 => self::COMPLETED,
        2 => self::CANCELLED,
    ];

    /** L'état d'une commande d'événement, dans le vocabulaire commun. */
    public static function fromEvent(int $status): string
    {
        return self::FROM_EVENT[$status] ?? self::PENDING;
    }

    public static function isValid(?string $status): bool
    {
        return $status !== null && in_array($status, self::ALL, true);
    }

    public static function isValidPayment(?string $status): bool
    {
        return $status !== null && in_array($status, self::PAYMENTS, true);
    }

    public static function label(?string $status): string
    {
        return self::LABELS[$status] ?? 'Inconnu';
    }

    public static function paymentLabel(?string $status): string
    {
        return self::PAYMENT_LABELS[$status] ?? 'Inconnu';
    }

    /** La commande compte-t-elle encore dans le travail à faire ? */
    public static function isOpen(?string $status): bool
    {
        return self::isValid($status) && ! in_array($status, self::CLOSED, true);
    }

    /**
     * Cette commande compte-t-elle dans le chiffre d'affaires ?
     *
     * Une annulation et un remboursement n'y entrent pas : les compter
     * gonflerait la recette d'argent que le commerce n'a pas, ou plus.
     */
    public static function countsAsRevenue(?string $status, ?string $payment): bool
    {
        if (in_array($status, [self::CANCELLED, self::REFUNDED], true)) {
            return false;
        }

        return in_array($payment, [self::PAID, self::PARTIAL], true);
    }

    /**
     * Où en est le paiement, vu les montants. PUR.
     *
     * Un centime de tolérance : un split en trois laisse souvent un arrondi,
     * et refuser de marquer « payée » pour 0,01 obligerait le caissier à
     * bricoler un ajustement à chaque fois.
     */
    public static function paymentFor(float $paid, float $total): string
    {
        if ($total <= 0) {
            return $paid > 0 ? self::PAID : self::UNPAID;
        }

        if ($paid >= $total - 0.01) {
            return self::PAID;
        }

        return $paid > 0 ? self::PARTIAL : self::UNPAID;
    }
}
