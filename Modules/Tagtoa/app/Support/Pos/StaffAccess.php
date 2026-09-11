<?php

namespace Modules\Tagtoa\App\Support\Pos;

/**
 * TAGTOA POS — qui a le droit de faire quoi dans un commerce.
 *
 * Un compte TAGTOA est un commerce réel, avec son patron et ses employés.
 * Le patron est le seul à voir l'ensemble : les ventes de toutes les caisses,
 * qui tenait quelle caisse, et le catalogue qu'il est seul à pouvoir modifier.
 * Un caissier encaisse sur sa caisse et ne voit que ses propres ventes.
 *
 * Classe PURE : aucune dépendance Laravel ni base de données, donc testable
 * sans rien monter. C'est ce qui permet de raisonner sur les droits sans avoir
 * à ouvrir une session.
 */
class StaffAccess
{
    /** Le patron du commerce. Voit tout, décide de tout. */
    public const ROLE_OWNER = 'owner';

    /** Chef de poste : encaisse, voit les ventes de sa caisse, accorde des remises. */
    public const ROLE_MANAGER = 'manager';

    /** Caissier : encaisse et ne voit que ses propres ventes. */
    public const ROLE_CASHIER = 'cashier';

    public const ROLES = [
        self::ROLE_OWNER   => 'Patron',
        self::ROLE_MANAGER => 'Gérant',
        self::ROLE_CASHIER => 'Caissier',
    ];

    /**
     * Droits, du plus ouvert au plus fermé.
     *
     * `catalog.delete` n'appartient qu'au patron : le catalogue est partagé par
     * toutes les caisses, donc supprimer un article chez l'un le retire chez
     * tous. Un caissier vend, rend un article à un client et retire une ligne
     * du panier en cours — il ne touche jamais le catalogue.
     */
    public const ABILITIES = [
        'sell',             // encaisser
        'cart.remove',      // retirer une ligne du panier en cours
        'sale.refund',      // rendre un article à un client
        'sales.own',        // voir SES ventes
        'sales.till',       // voir les ventes de SA caisse
        'sales.all',        // voir les ventes de TOUTES les caisses
        'discount',         // accorder une remise
        'catalog.view',     // consulter le catalogue
        'catalog.edit',     // créer / modifier un article
        'catalog.delete',   // supprimer un article du catalogue
        'staff.manage',     // créer et gérer les employés
        'settings',         // réglages du commerce
    ];

    /** Ce que chaque rôle peut faire. Le patron n'est pas listé : il peut tout. */
    private const GRANTS = [
        self::ROLE_MANAGER => [
            'sell', 'cart.remove', 'sale.refund',
            'sales.own', 'sales.till',
            'discount', 'catalog.view', 'catalog.edit',
        ],
        self::ROLE_CASHIER => [
            'sell', 'cart.remove',
            'sales.own',
            'catalog.view',
        ],
    ];

    /** Actions assez sérieuses pour redemander le PIN, même déjà connecté. */
    public const PIN_CONFIRMED = [
        'sale.refund', 'discount', 'catalog.delete',
    ];

    public static function isValidRole(?string $role): bool
    {
        return $role !== null && array_key_exists($role, self::ROLES);
    }

    /**
     * Ce rôle peut-il faire cette action ? PUR.
     *
     * Rôle inconnu ⇒ non. Mieux vaut bloquer un employé mal enregistré que lui
     * ouvrir une caisse.
     */
    public static function can(?string $role, string $ability): bool
    {
        if ($role === self::ROLE_OWNER) {
            return true;
        }
        if (! self::isValidRole($role)) {
            return false;
        }

        return in_array($ability, self::GRANTS[$role] ?? [], true);
    }

    /** Toutes les actions permises à ce rôle, dans l'ordre de la liste. PUR. */
    public static function abilitiesFor(?string $role): array
    {
        return array_values(array_filter(
            self::ABILITIES,
            fn ($a) => self::can($role, $a)
        ));
    }

    /**
     * Cette action exige-t-elle de retaper le PIN ? PUR.
     *
     * Le patron n'y échappe pas : une caisse reste souvent ouverte sur le
     * comptoir, et c'est précisément là que le PIN protège.
     */
    public static function needsPin(string $ability): bool
    {
        return in_array($ability, self::PIN_CONFIRMED, true);
    }

    /**
     * Jusqu'où ce rôle voit-il les ventes ? PUR.
     * 'all' = toutes les caisses · 'till' = sa caisse · 'own' = les siennes.
     */
    public static function salesScope(?string $role): string
    {
        if (self::can($role, 'sales.all')) {
            return 'all';
        }

        return self::can($role, 'sales.till') ? 'till' : 'own';
    }
}
