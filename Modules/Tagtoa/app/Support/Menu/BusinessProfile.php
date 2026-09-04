<?php

namespace Modules\Tagtoa\App\Support\Menu;

/**
 * TAGTOA MENU — le formulaire s'adapte au type d'établissement.
 *
 * Un hôtel ne vend pas des plats : il vend des chambres, avec une capacité, des
 * lits et un prix PAR NUIT. Un bar vend un volume et un degré d'alcool. Saisir
 * une chambre dans un formulaire pensé pour un plat oblige le marchand à tout
 * écrire dans la description — et le client ne peut plus filtrer ni comparer.
 *
 * Le moteur reste le même partout (catégories → articles → options) : seuls
 * changent le vocabulaire, les catégories proposées au démarrage et les champs
 * supplémentaires. Ces champs sont stockés dans `items.attributes` (JSON), donc
 * ajouter un type ne demande aucune migration.
 *
 * Classe PURE : aucune dépendance Laravel, testable sans base de données.
 */
class BusinessProfile
{
    /** Types de champ acceptés (le formulaire et la validation s'y adossent). */
    public const T_TEXT   = 'text';
    public const T_NUMBER = 'number';
    public const T_SELECT = 'select';
    public const T_TAGS   = 'tags';   // liste de choix multiples
    public const T_BOOL   = 'bool';

    /**
     * Profil par type d'établissement.
     *  - noun / nouns : comment nommer un article ici
     *  - price_hint   : ce que le prix veut dire (par nuit, par verre…)
     *  - price_suffix : ce qu'on colle au prix côté client (« / nuit »)
     *  - categories   : catégories proposées à la création (jamais imposées)
     *  - fields       : champs supplémentaires, stockés en JSON
     */
    public const PROFILES = [
        'restaurant' => [
            'noun'       => 'Plat',
            'nouns'      => 'Plats',
            'price_hint' => 'Prix du plat',
            'price_suffix' => null,
            'categories' => ['Entrées', 'Plats principaux', 'Grillades', 'Accompagnements', 'Desserts', 'Boissons'],
            'fields'     => [
                'prep_time'   => ['label' => 'Temps de préparation', 'type' => self::T_NUMBER, 'unit' => 'min', 'min' => 0, 'max' => 480],
                'portion'     => ['label' => 'Portion',              'type' => self::T_SELECT, 'options' => ['1 personne', '2 personnes', 'À partager', 'Familial']],
                'spice'       => ['label' => 'Piment',               'type' => self::T_SELECT, 'options' => ['Pas piquant', 'Doux', 'Piquant', 'Très piquant']],
                'diet'        => ['label' => 'Régime',               'type' => self::T_TAGS,   'options' => ['Végétarien', 'Végan', 'Sans gluten', 'Halal', 'Sans porc']],
                'allergens'   => ['label' => 'Allergènes',           'type' => self::T_TAGS,   'options' => ['Arachides', 'Fruits de mer', 'Lait', 'Œufs', 'Gluten', 'Soja', 'Fruits à coque']],
            ],
        ],

        'hotel' => [
            'noun'       => 'Chambre',
            'nouns'      => 'Chambres',
            'price_hint' => 'Prix par nuit',
            'price_suffix' => '/ nuit',
            'categories' => ['Chambres', 'Suites', 'Services', 'Restauration', 'Navette & excursions'],
            'fields'     => [
                'room_type'  => ['label' => 'Type de chambre', 'type' => self::T_SELECT, 'options' => ['Simple', 'Double', 'Twin', 'Triple', 'Suite', 'Bungalow', 'Appartement']],
                'capacity'   => ['label' => 'Capacité',        'type' => self::T_NUMBER, 'unit' => 'personnes', 'min' => 1, 'max' => 30],
                'beds'       => ['label' => 'Lits',            'type' => self::T_NUMBER, 'unit' => 'lit(s)',    'min' => 0, 'max' => 20],
                'area'       => ['label' => 'Superficie',      'type' => self::T_NUMBER, 'unit' => 'm²',        'min' => 0, 'max' => 2000],
                'view'       => ['label' => 'Vue',             'type' => self::T_SELECT, 'options' => ['Mer', 'Montagne', 'Jardin', 'Piscine', 'Ville', 'Cour intérieure']],
                'amenities'  => ['label' => 'Équipements',     'type' => self::T_TAGS,   'options' => ['Climatisation', 'Wi-Fi', 'Eau chaude', 'Télévision', 'Balcon', 'Cuisine', 'Coffre-fort', 'Inverter / génératrice', 'Piscine privée']],
                'breakfast'  => ['label' => 'Petit-déjeuner inclus', 'type' => self::T_BOOL],
            ],
        ],

        'bar' => [
            'noun'       => 'Boisson',
            'nouns'      => 'Boissons',
            'price_hint' => 'Prix',
            'price_suffix' => null,
            'categories' => ['Bières', 'Rhums & spiritueux', 'Cocktails', 'Vins', 'Sans alcool', 'À grignoter'],
            'fields'     => [
                'serving'   => ['label' => 'Format',         'type' => self::T_SELECT, 'options' => ['Verre', 'Bouteille', 'Pichet', 'Canette', 'Shot']],
                'volume'    => ['label' => 'Volume',         'type' => self::T_NUMBER, 'unit' => 'cl', 'min' => 0, 'max' => 500],
                'abv'       => ['label' => 'Degré d\'alcool', 'type' => self::T_NUMBER, 'unit' => '%', 'min' => 0, 'max' => 100],
                'served'    => ['label' => 'Service',        'type' => self::T_SELECT, 'options' => ['Frais', 'Glacé', 'Chambré', 'Chaud']],
            ],
        ],

        'club' => [
            'noun'       => 'Article',
            'nouns'      => 'Articles',
            'price_hint' => 'Prix',
            'price_suffix' => null,
            'categories' => ['Bouteilles', 'Tables & VIP', 'Cocktails', 'Bières', 'Entrées & pass'],
            'fields'     => [
                'serving'   => ['label' => 'Format',          'type' => self::T_SELECT, 'options' => ['Bouteille', 'Verre', 'Table', 'Pass']],
                'volume'    => ['label' => 'Volume',          'type' => self::T_NUMBER, 'unit' => 'cl', 'min' => 0, 'max' => 500],
                'abv'       => ['label' => 'Degré d\'alcool',  'type' => self::T_NUMBER, 'unit' => '%', 'min' => 0, 'max' => 100],
                'guests'    => ['label' => 'Personnes incluses', 'type' => self::T_NUMBER, 'unit' => 'personnes', 'min' => 0, 'max' => 50],
                'perks'     => ['label' => 'Inclus',          'type' => self::T_TAGS, 'options' => ['Mixers', 'Serveur dédié', 'Entrée VIP', 'Coupe-file', 'Espace privé']],
            ],
        ],

        'lounge' => [
            'noun'       => 'Article',
            'nouns'      => 'Articles',
            'price_hint' => 'Prix',
            'price_suffix' => null,
            'categories' => ['Cocktails', 'Chicha', 'Tapas', 'Bières & vins', 'Sans alcool'],
            'fields'     => [
                'serving'   => ['label' => 'Format',         'type' => self::T_SELECT, 'options' => ['Verre', 'Bouteille', 'Plateau', 'Chicha']],
                'volume'    => ['label' => 'Volume',         'type' => self::T_NUMBER, 'unit' => 'cl', 'min' => 0, 'max' => 500],
                'abv'       => ['label' => 'Degré d\'alcool', 'type' => self::T_NUMBER, 'unit' => '%', 'min' => 0, 'max' => 100],
                'duration'  => ['label' => 'Durée',          'type' => self::T_NUMBER, 'unit' => 'min', 'min' => 0, 'max' => 600],
            ],
        ],

        'cafe' => [
            'noun'       => 'Article',
            'nouns'      => 'Articles',
            'price_hint' => 'Prix',
            'price_suffix' => null,
            'categories' => ['Cafés', 'Thés & infusions', 'Jus & smoothies', 'Viennoiseries', 'Sandwichs'],
            'fields'     => [
                'size'      => ['label' => 'Taille',    'type' => self::T_SELECT, 'options' => ['Petit', 'Moyen', 'Grand']],
                'temp'      => ['label' => 'Service',   'type' => self::T_SELECT, 'options' => ['Chaud', 'Froid', 'Glacé']],
                'prep_time' => ['label' => 'Temps de préparation', 'type' => self::T_NUMBER, 'unit' => 'min', 'min' => 0, 'max' => 120],
                'diet'      => ['label' => 'Régime',    'type' => self::T_TAGS, 'options' => ['Sans lactose', 'Sans sucre', 'Végan', 'Sans gluten']],
            ],
        ],

        'other' => [
            'noun'       => 'Article',
            'nouns'      => 'Articles',
            'price_hint' => 'Prix',
            'price_suffix' => null,
            'categories' => ['Produits', 'Services'],
            'fields'     => [
                'duration' => ['label' => 'Durée', 'type' => self::T_NUMBER, 'unit' => 'min', 'min' => 0, 'max' => 10080],
                'unit'     => ['label' => 'Unité de vente', 'type' => self::T_TEXT, 'max' => 40],
            ],
        ],
    ];

    /** Profil d'un type, avec repli sur « other » pour un type inconnu. */
    public static function for(?string $type): array
    {
        return self::PROFILES[$type] ?? self::PROFILES['other'];
    }

    /** Suffixe affiché derrière le prix côté client (« / nuit »), ou null. */
    public static function priceSuffix(?string $type): ?string
    {
        return self::for($type)['price_suffix'] ?? null;
    }

    /** Champs supplémentaires déclarés pour ce type. */
    public static function fields(?string $type): array
    {
        return self::for($type)['fields'];
    }

    /**
     * Nettoie ce qu'a envoyé le formulaire : on ne garde QUE les champs déclarés
     * pour ce type, chacun contraint à son domaine. Rien d'inconnu n'atteint la
     * base — même discipline que pour les moyens de paiement.
     *
     * Renvoie null quand il ne reste rien : on évite d'écrire « {} » en base.
     */
    public static function sanitize(?string $type, mixed $input): ?array
    {
        if (! is_array($input)) {
            return null;
        }

        $out = [];

        foreach (self::fields($type) as $key => $spec) {
            if (! array_key_exists($key, $input)) {
                continue;
            }
            $value = self::clean($spec, $input[$key]);
            if ($value !== null) {
                $out[$key] = $value;
            }
        }

        return $out ?: null;
    }

    /** Contraint UNE valeur au domaine de son champ. null = à ne pas stocker. */
    private static function clean(array $spec, mixed $value): mixed
    {
        switch ($spec['type']) {
            case self::T_BOOL:
                // Une case décochée n'est pas envoyée : seule une valeur vraie compte.
                return filter_var($value, FILTER_VALIDATE_BOOLEAN) ?: null;

            case self::T_NUMBER:
                if ($value === '' || $value === null || ! is_numeric($value)) {
                    return null;
                }
                $n = (float) $value;
                $n = max((float) ($spec['min'] ?? 0), min((float) ($spec['max'] ?? PHP_INT_MAX), $n));

                // Entier quand c'est un entier : « 4 personnes », pas « 4.0 ».
                return floor($n) === $n ? (int) $n : round($n, 2);

            case self::T_SELECT:
                return in_array($value, $spec['options'], true) ? $value : null;

            case self::T_TAGS:
                if (! is_array($value)) {
                    return null;
                }
                // Uniquement des choix du catalogue, sans doublon, ordre stable.
                $kept = array_values(array_unique(array_filter(
                    $value,
                    fn ($v) => in_array($v, $spec['options'], true)
                )));

                return $kept ?: null;

            case self::T_TEXT:
            default:
                $s = trim((string) $value);
                if ($s === '') {
                    return null;
                }

                return mb_substr($s, 0, (int) ($spec['max'] ?? 120));
        }
    }

    /**
     * Attributs prêts à afficher : [label, valeur lisible] dans l'ordre du
     * profil. Un attribut devenu inconnu (le marchand a changé de type) est
     * ignoré plutôt que montré brut au client.
     */
    public static function display(?string $type, ?array $attributes): array
    {
        if (! $attributes) {
            return [];
        }

        $out = [];
        foreach (self::fields($type) as $key => $spec) {
            if (! isset($attributes[$key])) {
                continue;
            }
            $v = $attributes[$key];

            $text = match ($spec['type']) {
                self::T_BOOL   => $v ? 'Oui' : null,
                self::T_TAGS   => is_array($v) ? implode(', ', $v) : null,
                self::T_NUMBER => trim($v.' '.($spec['unit'] ?? '')),
                default        => (string) $v,
            };

            if ($text !== null && $text !== '') {
                $out[] = ['label' => $spec['label'], 'value' => $text];
            }
        }

        return $out;
    }
}
