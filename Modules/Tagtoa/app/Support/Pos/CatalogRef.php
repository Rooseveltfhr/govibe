<?php

namespace Modules\Tagtoa\App\Support\Pos;

/**
 * TAGTOA — désigner un article, quel que soit le catalogue d'où il vient.
 *
 * Le commerce tient deux listes qui servent la même chose :
 *   • MENU  — le catalogue riche (description, photo, options, champs métier) ;
 *   • POS   — la grille de boutons de la caisse.
 *
 * La caisse doit pouvoir vendre les deux. Or les deux ont leurs propres
 * identifiants qui commencent à 1 : le plat n°7 et le bouton n°7 sont deux
 * choses différentes. Sans discriminant, vendre « l'article 7 » au comptoir
 * retirerait du stock au hasard dans l'une ou l'autre liste.
 *
 * D'où une référence qui porte TOUJOURS son origine : « menu:7 », « pos:7 ».
 *
 * Classe PURE : aucune dépendance Laravel, testable sans base de données.
 */
class CatalogRef
{
    /** Article du menu digital — le catalogue riche. */
    public const SOURCE_MENU = 'menu';

    /** Bouton propre à la caisse. */
    public const SOURCE_POS = 'pos';

    public const SOURCES = [self::SOURCE_MENU, self::SOURCE_POS];

    /** Référence stable d'un article : « menu:7 ». PUR. */
    public static function make(string $source, int $id): string
    {
        return self::normalize($source).':'.$id;
    }

    /**
     * Relit une référence. Renvoie [source, id], ou null si elle n'a pas de sens.
     *
     * Accepte aussi un identifiant nu (« 7 ») et le rattache à la caisse : les
     * caisses déjà installées envoient encore ce format, et une mise à jour
     * d'application ne doit pas interrompre une vente en cours.
     *
     * @return array{0:string,1:int}|null
     */
    public static function parse(mixed $ref): ?array
    {
        if (is_int($ref) || (is_string($ref) && ctype_digit($ref))) {
            $id = (int) $ref;

            return $id > 0 ? [self::SOURCE_POS, $id] : null;
        }

        if (! is_string($ref) || ! str_contains($ref, ':')) {
            return null;
        }

        [$source, $id] = explode(':', $ref, 2);
        $source = strtolower(trim($source));

        // Contrôle STRICT, sans repli : « stock:7 » ou « attaquant:7 » ne doivent
        // désigner aucun article. Retomber sur la caisse comme le fait make()
        // reviendrait à vendre le bouton n°7 parce qu'on a demandé n'importe quoi.
        if (! in_array($source, self::SOURCES, true) || ! ctype_digit($id) || (int) $id <= 0) {
            return null;
        }

        return [$source, (int) $id];
    }

    /** Source d'une référence, ou null. PUR. */
    public static function sourceOf(mixed $ref): ?string
    {
        return self::parse($ref)[0] ?? null;
    }

    /** Identifiant d'une référence, ou null. PUR. */
    public static function idOf(mixed $ref): ?int
    {
        return self::parse($ref)[1] ?? null;
    }

    public static function isValid(mixed $ref): bool
    {
        return self::parse($ref) !== null;
    }

    /**
     * Source connue, sinon la caisse. Sert à CONSTRUIRE une référence, jamais à
     * en relire une : parse() refuse ce qu'il ne reconnaît pas.
     * PUR.
     */
    private static function normalize(string $source): string
    {
        $source = strtolower(trim($source));

        return in_array($source, self::SOURCES, true) ? $source : self::SOURCE_POS;
    }
}
