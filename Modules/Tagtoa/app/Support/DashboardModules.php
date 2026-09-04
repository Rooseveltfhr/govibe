<?php

namespace Modules\Tagtoa\App\Support;

/**
 * TAGTOA — ce que le marchand voit dans son tableau de bord.
 *
 * Source UNIQUE pour la barre latérale et la page d'accueil : les deux lisaient
 * chacune leur propre liste en dur et divergeaient à chaque ajout de module.
 *
 * Masquer n'est PAS supprimer : les routes d'un module masqué restent servies et
 * ses données intactes. Un marchand qui a déjà des liens NFC imprimés, des
 * cartes de fidélité ou une boutique en ligne ne perd rien — on cesse seulement
 * de mettre ces modules en avant. Rallumer un module = ajouter sa clé dans
 * `config('tagtoa.modules_enabled')`, sans toucher au code.
 */
class DashboardModules
{
    /** Modules mis en avant par défaut. Le reste existe mais reste discret. */
    public const DEFAULT_ENABLED = [
        'menu', 'pos', 'event', 'pay',
        'analytics', 'customers', 'reviews', 'qr', 'plan',
    ];

    /**
     * Catalogue complet. `group` : 'module' (les outils qui font gagner de
     * l'argent) ou 'account' (les écrans de suivi et de compte).
     */
    public const CATALOG = [
        // --- Les quatre outils métier de TAGTOA ---
        'menu' => [
            'label' => 'Menu', 'icon' => 'fa-utensils', 'group' => 'module',
            'desc'  => 'Menu digital NFC/QR : restaurant, hôtel, club, bar, lounge — photos, prix, commande.',
        ],
        'pos' => [
            'label' => 'Caisse (POS)', 'icon' => 'fa-cash-register', 'group' => 'module',
            'desc'  => 'Caisse tactile qui marche même sans internet, multi-paiement.',
        ],
        'event' => [
            'label' => 'Événements', 'icon' => 'fa-ticket', 'group' => 'module',
            'desc'  => 'Billetterie et contrôle d\'entrée NFC/QR.',
        ],
        'pay' => [
            'label' => 'Paiements', 'icon' => 'fa-money-bill-transfer', 'group' => 'module',
            'desc'  => 'Liens de paiement et de don : MonCash, NatCash, Zelle, PayPal, carte, crypto.',
        ],

        // --- Suivi et compte ---
        'analytics' => [
            'label' => 'Analytics', 'icon' => 'fa-chart-line', 'group' => 'account',
            'desc'  => 'Revenus, ventes, visites et meilleurs produits en temps réel.',
        ],
        'customers' => [
            'label' => 'Clients (CRM)', 'icon' => 'fa-users', 'group' => 'account',
            'desc'  => 'Base clients agrégée de tous vos modules.',
        ],
        'reviews' => [
            'label' => 'Avis clients', 'icon' => 'fa-star', 'group' => 'account',
            'desc'  => 'Collectez et modérez les avis sur vos pages publiques.',
        ],
        'qr' => [
            'label' => 'QR & Partage', 'icon' => 'fa-qrcode', 'group' => 'account',
            'desc'  => 'QR codes de vos pages publiques et affiches à imprimer.',
        ],
        'plan' => [
            'label' => 'Abonnement', 'icon' => 'fa-crown', 'group' => 'account',
            'desc'  => 'Votre forfait TAGTOA et vos factures.',
        ],

        // --- Existants, masqués par défaut (routes et données conservées) ---
        'site' => [
            'label' => 'Site web', 'icon' => 'fa-globe', 'group' => 'module',
            'desc'  => 'Site vitrine par abonnement : services, contact, galerie.',
        ],
        'store' => [
            'label' => 'Boutique', 'icon' => 'fa-bag-shopping', 'group' => 'module',
            'desc'  => 'Boutique en ligne : catalogue, panier, commande.',
        ],
        'cards' => [
            'label' => 'Cartes TAGTOA', 'icon' => 'fa-credit-card', 'group' => 'module',
            'desc'  => 'Carte NFC prépayée : émettre, recharger, payer.',
        ],
        'loyalty' => [
            'label' => 'Fidélité', 'icon' => 'fa-id-card', 'group' => 'module',
            'desc'  => 'Cartes NFC de fidélité : points, solde, récompenses.',
        ],
        'links' => [
            'label' => 'Liens', 'icon' => 'fa-link', 'group' => 'module',
            'desc'  => 'Page de liens et de don.',
        ],
        'booking' => [
            'label' => 'Réservations', 'icon' => 'fa-calendar-check', 'group' => 'module',
            'desc'  => 'Prise de rendez-vous : prestations, créneaux, confirmation.',
        ],
        'billing' => [
            'label' => 'Revenu & forfait', 'icon' => 'fa-wallet', 'group' => 'account',
            'desc'  => 'Abonnement ou commission : votre choix.',
        ],
        'audit' => [
            'label' => 'Journal d\'audit', 'icon' => 'fa-clipboard-list', 'group' => 'account',
            'desc'  => 'Traçabilité des actions sensibles : modération, finances, statuts.',
        ],
    ];

    /** Clés activées (config si dispo, sinon la valeur par défaut). */
    public static function enabledKeys(): array
    {
        try {
            $cfg = function_exists('config') ? config('tagtoa.modules_enabled') : null;
        } catch (\Throwable $e) {
            $cfg = null;
        }

        $keys = is_array($cfg) && $cfg !== [] ? $cfg : self::DEFAULT_ENABLED;

        // Une clé inconnue du catalogue produirait un lien mort : on l'écarte.
        return array_values(array_filter($keys, fn ($k) => isset(self::CATALOG[$k])));
    }

    public static function isEnabled(string $key): bool
    {
        return in_array($key, self::enabledKeys(), true);
    }

    /**
     * Modules activés, dans l'ordre du catalogue (donc un ordre d'affichage
     * stable, quel que soit l'ordre de la config). Chaque entrée porte sa clé
     * et son URL, pour que les vues n'aient plus rien à deviner.
     *
     * @param  string|null  $group  'module', 'account', ou null pour tout
     */
    public static function enabled(?string $group = null): array
    {
        $keys = self::enabledKeys();
        $out  = [];

        foreach (self::CATALOG as $key => $meta) {
            if (! in_array($key, $keys, true)) {
                continue;
            }
            if ($group !== null && $meta['group'] !== $group) {
                continue;
            }
            $out[$key] = $meta + ['key' => $key, 'url' => '/tagtoa/'.$key];
        }

        return $out;
    }
}
