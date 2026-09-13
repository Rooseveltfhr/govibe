<?php

namespace Modules\Tagtoa\App\Support\Order;

/**
 * TAGTOA — par où la commande est arrivée.
 *
 * Un commerce vend au comptoir, par QR au bord de la table, en ligne, et à
 * l'entrée d'un événement. Ce sont quatre écrans, mais UN seul chiffre
 * d'affaires, un seul client, une seule taxe à déclarer.
 *
 * Tant que chaque module comptait dans son coin, le marchand devait
 * additionner quatre écrans de tête pour savoir ce qu'il avait gagné dans la
 * journée — et n'avait aucun moyen de voir qu'un même client achète chez lui
 * par trois chemins différents.
 *
 * Classe PURE : aucune dépendance Laravel, testable sans base de données.
 */
class Channel
{
    /** Au comptoir, sur la caisse. */
    public const POS = 'pos';

    /** Commande passée au QR du menu digital. */
    public const MENU = 'menu';

    /** Boutique en ligne. */
    public const STORE = 'store';

    /** Billetterie d'un événement. */
    public const EVENT = 'event';

    /** Lien de paiement ou de facture envoyé au client. */
    public const LINK = 'link';

    public const LABELS = [
        self::POS   => 'Caisse',
        self::MENU  => 'Menu QR',
        self::STORE => 'Boutique',
        self::EVENT => 'Événement',
        self::LINK  => 'Lien de paiement',
    ];

    public const ALL = [self::POS, self::MENU, self::STORE, self::EVENT, self::LINK];

    public static function isValid(?string $channel): bool
    {
        return $channel !== null && in_array($channel, self::ALL, true);
    }

    public static function label(?string $channel): string
    {
        return self::LABELS[$channel] ?? 'Autre';
    }

    /**
     * Ce canal encaisse-t-il sur-le-champ ?
     *
     * La caisse encaisse en même temps qu'elle vend. Les autres créent une
     * commande qui sera payée plus tard, ou pas. La distinction gouverne
     * l'affichage : une commande « en attente de paiement » n'a pas de sens au
     * comptoir, et une vente de caisse n'a pas à attendre une confirmation.
     */
    public static function isImmediate(?string $channel): bool
    {
        return $channel === self::POS;
    }
}
