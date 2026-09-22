<?php

namespace Modules\Tagtoa\App\Support\Catalog;

/**
 * TAGTOA — catégories les plus courantes d'un petit commerce haïtien,
 * proposées en un clic dans POS ET dans MENU. Jamais imposées : le nom d'un
 * rayon reste un champ libre partout, ceci n'est qu'une suggestion.
 *
 * Liste UNIQUE, lue par les deux écrans plutôt que recopiée : sans ça, POS
 * et MENU finiraient par proposer deux listes différentes pour la même
 * réalité.
 */
class CategoryPresets
{
    public const COMMON = [
        'Boisson',
        'Alimentation',
        'Légumes',
        'Alcool',
        'Collations',
        'Nettoyage & hygiène',
        'Cosmétique',
        'Divers',
    ];
}
