<?php

namespace Modules\Tagtoa\App\Support\Menu;

/**
 * TAGTOA — l'icône d'une catégorie de menu.
 *
 * Les catégories portaient un EMOJI, saisi à la main. Trois problèmes :
 *
 *   • un emoji se dessine différemment sur chaque téléphone, et sur beaucoup
 *     d'Android bon marché il tombe en carré blanc — juste à côté du nom du
 *     restaurant, c'est-à-dire là où la page doit inspirer confiance ;
 *   • il ne s'aligne pas sur la ligne de base du texte, et fait sautiller la
 *     barre de catégories ;
 *   • presque aucun marchand n'en met un : la barre est alors nue.
 *
 * On DÉDUIT donc l'icône du nom de la catégorie, sans rien demander à personne.
 * Un marchand qui veut choisir la sienne enregistre une classe Font Awesome
 * (« fa-fish ») et elle est reprise telle quelle — la déduction n'est qu'un
 * défaut, jamais une contrainte.
 *
 * Les mots sont cherchés en FRANÇAIS, en CRÉOLE et en ANGLAIS : c'est ainsi
 * qu'un marchand haïtien écrit réellement sa carte.
 *
 * Classe PURE : aucune dépendance Laravel, testable sans base de données.
 */
class CategoryIcon
{
    /** Quand rien ne correspond. Neutre et toujours juste sur une carte. */
    public const DEFAUT = 'fa-utensils';

    /**
     * Mot-clé → icône. L'ORDRE COMPTE : le premier mot trouvé gagne.
     *
     * Les termes les plus précis viennent donc avant les plus larges — sans
     * quoi « jus de fruit » deviendrait « fruit » et non « boisson », et
     * « soupe de poisson » deviendrait « soupe ».
     */
    public const MOTS = [
        // Boissons — d'abord les plus précises
        'champagne'   => 'fa-champagne-glasses',
        'cocktail'    => 'fa-martini-glass-citrus',
        'wine'        => 'fa-wine-glass',
        'vin'         => 'fa-wine-glass',
        'bier'        => 'fa-beer-mug-empty',
        'beer'        => 'fa-beer-mug-empty',
        'bye'         => 'fa-beer-mug-empty',   // « byè » en créole
        'rhum'        => 'fa-whiskey-glass',
        'rum'         => 'fa-whiskey-glass',
        'kleren'      => 'fa-whiskey-glass',
        'alcool'      => 'fa-whiskey-glass',
        'cafe'        => 'fa-mug-saucer',
        'café'        => 'fa-mug-saucer',
        'coffee'      => 'fa-mug-saucer',
        'the'         => 'fa-mug-hot',
        'thé'         => 'fa-mug-hot',
        'tea'         => 'fa-mug-hot',
        'jus'         => 'fa-glass-water',
        'juice'       => 'fa-glass-water',
        'smoothie'    => 'fa-glass-water',
        'soda'        => 'fa-bottle-water',
        'eau'         => 'fa-bottle-water',
        'dlo'         => 'fa-bottle-water',
        'water'       => 'fa-bottle-water',
        'boisson'     => 'fa-mug-hot',
        'bwason'      => 'fa-mug-hot',
        'drink'       => 'fa-mug-hot',

        // Plats
        'pizza'       => 'fa-pizza-slice',
        'burger'      => 'fa-burger',
        'sandwich'    => 'fa-bread-slice',
        'poisson'     => 'fa-fish',
        'pwason'      => 'fa-fish',
        'fish'        => 'fa-fish',
        'fruits de mer' => 'fa-shrimp',
        'seafood'     => 'fa-shrimp',
        'lambi'       => 'fa-shrimp',
        'poulet'      => 'fa-drumstick-bite',
        'poul'        => 'fa-drumstick-bite',
        'chicken'     => 'fa-drumstick-bite',
        'griot'       => 'fa-drumstick-bite',
        'viande'      => 'fa-drumstick-bite',
        'vyann'       => 'fa-drumstick-bite',
        'meat'        => 'fa-drumstick-bite',
        'riz'         => 'fa-bowl-rice',
        'diri'        => 'fa-bowl-rice',
        'rice'        => 'fa-bowl-rice',
        'soupe'       => 'fa-bowl-food',
        'soup'        => 'fa-bowl-food',
        'salade'      => 'fa-leaf',
        'salad'       => 'fa-leaf',
        'legume'      => 'fa-carrot',
        'légume'      => 'fa-carrot',
        'vegan'       => 'fa-seedling',
        'pasta'       => 'fa-bowl-food',
        'spaghetti'   => 'fa-bowl-food',
        'taco'        => 'fa-bacon',
        'grill'       => 'fa-fire-burner',
        'barbecue'    => 'fa-fire-burner',
        'bbq'         => 'fa-fire-burner',

        // Moments du repas
        'entree'      => 'fa-bowl-food',
        'entrée'      => 'fa-bowl-food',
        'starter'     => 'fa-bowl-food',
        'appetizer'   => 'fa-bowl-food',
        'dejeuner'    => 'fa-egg',
        'déjeuner'    => 'fa-egg',
        'breakfast'   => 'fa-egg',
        'brunch'      => 'fa-egg',
        'plat'        => 'fa-utensils',
        'main'        => 'fa-utensils',
        'accompagn'   => 'fa-plate-wheat',
        'side'        => 'fa-plate-wheat',

        // Sucré
        'dessert'     => 'fa-ice-cream',
        'glace'       => 'fa-ice-cream',
        'cream'       => 'fa-ice-cream',
        'gateau'      => 'fa-cake-candles',
        'gâteau'      => 'fa-cake-candles',
        'cake'        => 'fa-cake-candles',
        'patisserie'  => 'fa-cake-candles',
        'pâtisserie'  => 'fa-cake-candles',
        'snack'       => 'fa-cookie-bite',
        'biscuit'     => 'fa-cookie-bite',
        'fruit'       => 'fa-apple-whole',
        'pain'        => 'fa-bread-slice',
        'bread'       => 'fa-bread-slice',

        // Rayons d'un petit commerce (boutik/épicerie) — CategoryPresets::COMMON
        'alimentation'  => 'fa-basket-shopping',
        'collation'     => 'fa-cookie-bite',
        'nettoyage'     => 'fa-pump-soap',
        'hygiene'       => 'fa-pump-soap',
        'hygiène'       => 'fa-pump-soap',
        'cosmetique'    => 'fa-pump-soap',
        'cosmétique'    => 'fa-pump-soap',
        'divers'        => 'fa-box',

        // Hôtel et autres métiers
        'chambre'     => 'fa-bed',
        'room'        => 'fa-bed',
        'suite'       => 'fa-bed',
        'service'     => 'fa-concierge-bell',
        'spa'         => 'fa-spa',
        'massage'     => 'fa-spa',
        'piscine'     => 'fa-person-swimming',
        'menu'        => 'fa-plate-wheat',
        'combo'       => 'fa-plate-wheat',
        'promo'       => 'fa-tags',
        'offre'       => 'fa-tags',
        'special'     => 'fa-star',
        'spécial'     => 'fa-star',
        'nouveau'     => 'fa-star',
    ];

    /**
     * La classe Font Awesome d'une catégorie. PUR.
     *
     * @param  string|null  $stocke  ce que le marchand a enregistré (classe ou emoji)
     * @param  string|null  $nom     le nom de la catégorie
     */
    public static function resolve(?string $stocke, ?string $nom = null): string
    {
        // Choix explicite du marchand : on ne discute pas.
        $s = trim((string) $stocke);
        if ($s !== '' && preg_match('/^fa-[a-z0-9-]+$/i', $s)) {
            return strtolower($s);
        }

        return self::deduire($nom);
    }

    /** L'icône déduite d'un nom de catégorie. PUR. */
    public static function deduire(?string $nom): string
    {
        $n = self::normaliser($nom);
        if ($n === '') {
            return self::DEFAUT;
        }

        foreach (self::MOTS as $mot => $icone) {
            if (self::contient($n, self::normaliser($mot))) {
                return $icone;
            }
        }

        return self::DEFAUT;
    }

    /**
     * Le nom contient-il ce mot-clé ? PUR.
     *
     * Les mots COURTS exigent une frontière de mot ; les longs se cherchent en
     * sous-chaîne. Sans cette distinction, « Other » contiendrait « the » et
     * une catégorie « Autres » en anglais s'afficherait avec une tasse de thé —
     * le genre de défaut que personne ne signale et que tout le monde voit.
     *
     * Les mots longs, eux, DOIVENT rester en sous-chaîne : « Boissons »,
     * « Desserts maison » et « Nos poissons » ne sont pas des mots isolés.
     */
    private static function contient(string $nom, string $mot): bool
    {
        if (mb_strlen($mot) > 4) {
            return str_contains($nom, $mot);
        }

        return (bool) preg_match('/(?<![a-z0-9])'.preg_quote($mot, '/').'(?![a-z0-9])/u', $nom);
    }

    /**
     * Minuscules sans accents. PUR.
     *
     * « Entrées », « ENTREES » et « entrees » désignent la même chose : sans
     * cette mise à plat, deux marchands sur trois tomberaient sur l'icône par
     * défaut pour une simple histoire d'accent.
     */
    private static function normaliser(?string $texte): string
    {
        $t = mb_strtolower(trim((string) $texte));

        return strtr($t, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
        ]);
    }
}
