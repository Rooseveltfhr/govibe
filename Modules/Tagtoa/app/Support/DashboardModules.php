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
 *
 * ── Hiérarchie ───────────────────────────────────────────────────────────────
 * La barre latérale était PLATE : « Stock » et « Équipe » se retrouvaient au
 * même niveau que « Caisse », alors qu'on ne les ouvre JAMAIS pour eux-mêmes —
 * on les ouvre parce qu'on tient une caisse. Résultat : treize entrées de même
 * poids, et le marchand devait reconstruire mentalement ce qui va avec quoi.
 *
 * Chaque module déclare donc maintenant ses propres écrans (`children`). Trois
 * groupes :
 *   - 'module'  → outil métier, visible en haut de la barre, ouvre ses écrans
 *   - 'account' → suivi et compte, une seule page chacun
 *   - 'feature' → écran RÉEL, atteint DEPUIS son module parent, jamais seul
 *
 * 'feature' ne désactive rien : la route est servie, la clé reste « activée »,
 * l'onboarding la voit. Seule la place dans le menu change.
 */
class DashboardModules
{
    /** Modules mis en avant par défaut. Le reste existe mais reste discret. */
    public const DEFAULT_ENABLED = [
        'menu', 'pos', 'event', 'pay',
        'business', 'staff', 'stands', 'inventory', 'orders', 'analytics', 'customers', 'reviews', 'qr', 'plan',
    ];

    /**
     * Catalogue complet.
     *
     * `group`    : 'module' | 'account' | 'feature' (voir l'en-tête de classe)
     * `children` : les écrans du module, dans l'ordre où on s'en sert.
     *              `needs` = la clé dont dépend l'écran ; si elle est masquée,
     *              le lien disparaît au lieu de mener à un module éteint.
     *              `alias` = raccourci vers un écran qui appartient à un AUTRE
     *              module ; il s'affiche, mais n'attire pas l'écran à lui quand
     *              on cherche où l'on se trouve (voir `locate`).
     *              `sep`   = ouvre un bloc, avec ce titre. Treize entrées
     *              d'affilée se lisent comme une liste de courses ; en blocs,
     *              on trouve sans lire.
     */
    public const CATALOG = [
        // --- Les quatre outils métier de TAGTOA ---
        'menu' => [
            'label' => 'Menu', 'icon' => 'fa-utensils', 'group' => 'module',
            'desc'  => 'Menu digital NFC/QR : restaurant, hôtel, club, bar, lounge — photos, prix, commande.',
            'children' => [
                ['label' => 'Ma carte',     'icon' => 'fa-utensils',       'url' => '/tagtoa/menu'],
                // Le stock est le MÊME qu'en caisse : un plat vendu au comptoir
                // et un plat commandé au QR sortent du même inventaire. Le lien
                // est donc volontairement présent des deux côtés — un marchand
                // qui n'a QUE le menu doit pouvoir y arriver sans passer par POS.
                ['label' => 'Stock',        'icon' => 'fa-boxes-stacked',  'url' => '/tagtoa/inventory', 'needs' => 'inventory', 'alias' => true],
                ['label' => 'Smart Stands', 'icon' => 'fa-sign-hanging',   'url' => '/tagtoa/stands',    'needs' => 'stands'],
                ['label' => 'Avis clients', 'icon' => 'fa-star',           'url' => '/tagtoa/reviews',   'needs' => 'reviews'],
            ],
        ],
        'pos' => [
            'label' => 'Caisse (POS)', 'icon' => 'fa-cash-register', 'group' => 'module',
            'desc'  => 'Caisse tactile qui marche même sans internet, multi-paiement.',
            // Treize écrans, cinq blocs. La caisse n'en montrait qu'un seul non
            // par choix, mais parce que ses URL exigeaient toutes le numéro du
            // poste — et qu'un menu ne connaît pas le numéro 7. Les routes
            // vivent maintenant sous le commerce, et le menu peut enfin dire
            // tout ce que la caisse sait faire.
            //
            // `sep` ouvre un bloc : on vend, on range, on suit les gens et
            // l'argent, on tient la caisse, on règle. C'est l'ordre d'une
            // journée de travail, pas un classement alphabétique.
            'children' => [
                ['label' => 'Nouvelle vente', 'icon' => 'fa-cart-plus',     'url' => '/tagtoa/pos/sell',       'sep' => 'Vendre'],
                ['label' => 'Commandes',      'icon' => 'fa-clipboard-list','url' => '/tagtoa/orders',         'needs' => 'orders', 'alias' => true],
                ['label' => 'Tickets / Reçus','icon' => 'fa-receipt',       'url' => '/tagtoa/pos/tickets'],

                ['label' => 'Produits',       'icon' => 'fa-box',           'url' => '/tagtoa/pos/products',   'sep' => 'Catalogue'],
                ['label' => 'Catégories',     'icon' => 'fa-folder-tree',   'url' => '/tagtoa/pos/categories'],
                ['label' => 'Inventaire',     'icon' => 'fa-boxes-stacked', 'url' => '/tagtoa/inventory',      'needs' => 'inventory'],
                ['label' => 'Codes-barres',   'icon' => 'fa-barcode',       'url' => '/tagtoa/catalog/codes'],

                ['label' => 'Clients',        'icon' => 'fa-users',         'url' => '/tagtoa/customers',      'needs' => 'customers', 'alias' => true, 'sep' => 'Gens & argent'],
                ['label' => 'Paiements',      'icon' => 'fa-credit-card',   'url' => '/tagtoa/pay/methods',    'alias' => true],
                ['label' => 'Retours',        'icon' => 'fa-rotate-left',   'url' => '/tagtoa/pos/returns'],

                ['label' => 'Mes caisses',    'icon' => 'fa-cash-register', 'url' => '/tagtoa/pos',            'sep' => 'Le poste'],
                ['label' => 'Caissiers',      'icon' => 'fa-user-tie',      'url' => '/tagtoa/staff',          'needs' => 'staff'],
                ['label' => 'Rapports',       'icon' => 'fa-chart-column',  'url' => '/tagtoa/pos/reports'],

                ['label' => 'Paramètres',     'icon' => 'fa-gear',          'url' => '/tagtoa/pos/settings',   'sep' => 'Réglages'],
            ],
        ],
        'event' => [
            'label' => 'Événements', 'icon' => 'fa-ticket', 'group' => 'module',
            'desc'  => 'Billetterie et contrôle d\'entrée NFC/QR.',
            'children' => [
                ['label' => 'Mes événements',    'icon' => 'fa-ticket', 'url' => '/tagtoa/event'],
                ['label' => 'Nouvel événement',  'icon' => 'fa-plus',   'url' => '/tagtoa/event/create'],
            ],
        ],
        'pay' => [
            'label' => 'Paiements', 'icon' => 'fa-money-bill-transfer', 'group' => 'module',
            'desc'  => 'Liens de paiement et de don : MonCash, NatCash, Zelle, PayPal, carte, crypto.',
            'children' => [
                ['label' => 'Liens de paiement',  'icon' => 'fa-link',        'url' => '/tagtoa/pay'],
                ['label' => 'Moyens de paiement', 'icon' => 'fa-sliders',     'url' => '/tagtoa/pay/methods'],
                ['label' => 'Revenu & forfait',   'icon' => 'fa-wallet',      'url' => '/tagtoa/billing', 'needs' => 'billing'],
                ['label' => 'Cartes TAGTOA',      'icon' => 'fa-credit-card', 'url' => '/tagtoa/cards',   'needs' => 'cards'],
            ],
        ],

        // --- Suivi et compte : une page chacun, pas de sous-écran ---
        'orders' => [
            'label' => 'Commandes', 'icon' => 'fa-receipt', 'group' => 'account',
            'desc'  => 'Toutes vos ventes, tous canaux confondus : caisse, menu QR, billetterie, liens.',
        ],
        'analytics' => [
            'label' => 'Analytics', 'icon' => 'fa-chart-line', 'group' => 'account',
            'desc'  => 'Revenus, ventes, visites et meilleurs produits en temps réel.',
        ],
        'customers' => [
            'label' => 'Clients (CRM)', 'icon' => 'fa-users', 'group' => 'account',
            'desc'  => 'Base clients agrégée de tous vos modules.',
        ],
        'qr' => [
            'label' => 'QR & Partage', 'icon' => 'fa-qrcode', 'group' => 'account',
            'desc'  => 'QR codes de vos pages publiques et affiches à imprimer.',
        ],
        'business' => [
            'label' => 'Mes commerces', 'icon' => 'fa-shop', 'group' => 'account',
            'desc'  => 'Votre commerce : nom, métier, catégories, devise. Et un second si vous en ouvrez un.',
        ],
        'plan' => [
            'label' => 'Abonnement', 'icon' => 'fa-crown', 'group' => 'account',
            'desc'  => 'Votre forfait TAGTOA et vos factures.',
        ],

        // --- Écrans rattachés à un module (voir 'feature' en en-tête) ---
        'inventory' => [
            'label' => 'Stock', 'icon' => 'fa-boxes-stacked', 'group' => 'feature',
            'desc'  => 'Ce qu\'il reste en réserve, ce qu\'il faut recommander, et où sont passés les articles manquants.',
        ],
        'staff' => [
            'label' => 'Équipe', 'icon' => 'fa-users-gear', 'group' => 'feature',
            'desc'  => 'Les personnes qui tiennent vos caisses : rôle, code d\'accès, ce que chacune peut faire.',
        ],
        'stands' => [
            'label' => 'Mes stands', 'icon' => 'fa-sign-hanging', 'group' => 'feature',
            'desc'  => 'Vos TAGTOA Smart Stands : où va chacun, et ce que le client voit en scannant.',
        ],
        'reviews' => [
            'label' => 'Avis clients', 'icon' => 'fa-star', 'group' => 'feature',
            'desc'  => 'Collectez et modérez les avis sur vos pages publiques.',
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

    /**
     * LA BARRE DU BAS — cinq destinations, sur tout TAGTOA.
     *
     * Sur un téléphone, la barre latérale est un tiroir : deux gestes pour
     * atteindre n'importe quoi, et rien à l'écran qui dise où l'on peut aller.
     * Les cinq endroits où un marchand retourne toute la journée méritent d'être
     * visibles en permanence, comme dans n'importe quelle application qu'il
     * utilise déjà.
     *
     * Cinq, pas six : au-delà, les libellés se coupent et les cibles passent
     * sous le pouce. Le cinquième ouvre le reste.
     *
     * `match` : les préfixes d'URL qui allument l'onglet. « Commandes » s'allume
     * aussi sur les commandes d'un menu ou d'un événement, parce que c'est bien
     * là qu'on est.
     */
    public const BOTTOM = [
        ['key' => 'home',   'label' => 'Accueil',   'icon' => 'fa-house',         'url' => '/tagtoa/home',   'match' => ['tagtoa/home']],
        ['key' => 'pos',    'label' => 'Caisse',    'icon' => 'fa-cash-register', 'url' => '/tagtoa/pos',    'match' => ['tagtoa/pos*', 'tagtoa/inventory*', 'tagtoa/catalog*']],
        ['key' => 'menu',   'label' => 'Menu',      'icon' => 'fa-utensils',      'url' => '/tagtoa/menu',   'match' => ['tagtoa/menu*', 'tagtoa/stands*']],
        ['key' => 'orders', 'label' => 'Commandes', 'icon' => 'fa-receipt',       'url' => '/tagtoa/orders', 'match' => ['tagtoa/orders*']],
        ['key' => 'more',   'label' => 'Plus',      'icon' => 'fa-ellipsis',      'url' => null,             'match' => []],
    ];

    /**
     * Ce que « Plus » contient : tout le reste, dans l'ordre où on le cherche.
     *
     * Lu depuis le MÊME catalogue que la barre latérale — une liste écrite à la
     * main ici divergerait au premier module ajouté, et le marchand se
     * retrouverait avec un module visible d'un côté, introuvable de l'autre.
     * Les quatre premiers onglets en sont retirés : ils sont déjà sous le pouce.
     */
    public static function more(): array
    {
        $dejaEnBas = array_column(self::BOTTOM, 'key');
        $out = [];

        foreach (self::enabled() as $key => $m) {
            if (in_array($key, $dejaEnBas, true)) {
                continue;
            }
            $out[$key] = $m;
        }

        return $out;
    }

    /** L'onglet du bas correspondant au chemin courant, ou null. */
    public static function bottomActive(string $path): ?string
    {
        $path = trim($path, '/');

        foreach (self::BOTTOM as $tab) {
            foreach ($tab['match'] as $motif) {
                $regex = '#^'.str_replace('\*', '.*', preg_quote($motif, '#')).'$#';
                if (preg_match($regex, $path)) {
                    return $tab['key'];
                }
            }
        }

        return null;
    }

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
     * Les écrans d'un module, débarrassés de ceux qui mènent à un module éteint.
     *
     * Un module sans `children` en renvoie un seul : lui-même. Les vues ont donc
     * toujours une liste à parcourir, sans avoir à traiter deux cas.
     */
    public static function children(string $key): array
    {
        $meta = self::CATALOG[$key] ?? null;
        if ($meta === null) {
            return [];
        }

        $enfants = $meta['children'] ?? [];
        if ($enfants === []) {
            return [['label' => $meta['label'], 'icon' => $meta['icon'], 'url' => '/tagtoa/'.$key]];
        }

        return array_values(array_filter(
            $enfants,
            fn ($c) => ! isset($c['needs']) || self::isEnabled($c['needs'])
        ));
    }

    /**
     * Modules activés, dans l'ordre du catalogue (donc un ordre d'affichage
     * stable, quel que soit l'ordre de la config). Chaque entrée porte sa clé,
     * son URL et ses écrans, pour que les vues n'aient plus rien à deviner.
     *
     * @param  string|null  $group  'module', 'account', 'feature', ou null pour tout
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
            $out[$key] = array_merge($meta, [
                'key'      => $key,
                'url'      => '/tagtoa/'.$key,
                'children' => self::children($key),
            ]);
        }

        return $out;
    }

    /**
     * Le module auquel appartient le chemin courant — et l'écran exact dedans.
     *
     * Sert à deux choses : ouvrir le bon groupe dans la barre latérale, et
     * afficher la barre d'écrans du module en haut de la page (la seule façon
     * de voir « tout ce qu'il y a dans POS » sur un téléphone, où la barre
     * latérale est repliée).
     *
     * Le premier module qui correspond gagne : le stock est listé sous Menu ET
     * sous Caisse (c'est le même stock), et on ne veut pas deux groupes ouverts.
     *
     * @return array{0:?string,1:?string} [clé du module, URL de l'écran actif]
     */
    public static function locate(string $path): array
    {
        $path = '/'.trim($path, '/');

        // Deux passes : les écrans qui appartiennent VRAIMENT au module, puis
        // seulement les raccourcis. Sans cela, « Stock » ouvrirait le groupe
        // Menu alors que l'écran est celui de la Caisse.
        foreach ([false, true] as $alias) {
            $meilleur = null; // l'URL la plus longue qui préfixe le chemin gagne
            $module   = null;

            foreach (self::enabled() as $key => $m) {
                foreach ($m['children'] as $c) {
                    if ((bool) ($c['alias'] ?? false) !== $alias) {
                        continue;
                    }
                    $u = rtrim($c['url'], '/');
                    if ($path !== $u && ! str_starts_with($path, $u.'/')) {
                        continue;
                    }
                    if ($meilleur === null || strlen($u) > strlen($meilleur)) {
                        $meilleur = $u;
                        $module   = $key;
                    }
                }
            }

            if ($module !== null) {
                return [$module, $meilleur];
            }
        }

        return [null, null];
    }
}
