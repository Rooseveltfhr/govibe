<?php

namespace Modules\Tagtoa\App\Support\Menu;

/**
 * TAGTOA MENU — traduire une carte sans dupliquer une seule ligne.
 *
 * ── LE PROBLÈME QUE CETTE CLASSE ÉVITE ───────────────────────────────────
 *
 * La solution naïve — un menu par langue — oblige le marchand à répéter
 * chaque plat, chaque prix, chaque photo autant de fois qu'il y a de langues,
 * et à les maintenir en phase pour toujours. Le jour où un plat change de
 * prix, il faut se souvenir de le changer dans les quatre menus, ou le client
 * qui lit en anglais paie un prix d'il y a six mois.
 *
 * Ici, IL N'EXISTE QU'UN SEUL PLAT. Son prix, sa photo, son stock ne sont écrits
 * qu'une fois. Seul le TEXTE — le nom, la description — a une variante par
 * langue, stockée à côté du texte de base, jamais à sa place.
 *
 * ── CE QUI NE CHANGE PAS POUR LES MENUS EXISTANTS ────────────────────────
 *
 * Le champ de base (`name`, `description`, `tagline`) reste ce que le marchand
 * a toujours tapé — dans la langue qu'il veut, sans rien choisir. Un menu sans
 * la moindre traduction s'affiche exactement comme avant : `resolve()` retombe
 * sur ce champ dès qu'aucune traduction n'existe pour la langue demandée.
 * Aucune des milliers de cartes déjà publiées ne change d'apparence.
 *
 * Classe PURE : aucune dépendance Laravel, testable sans base de données.
 */
class Translatable
{
    /**
     * Le texte à afficher pour un champ, dans une langue.
     *
     * Ne renvoie JAMAIS une chaîne vide si le texte de base existe : une
     * traduction manquante doit montrer le texte du marchand, pas un trou dans
     * la carte. Une traduction VIDE (le marchand a effacé le champ pour dire
     * « pas de traduction ici ») est traitée comme une absence, pour la même
     * raison.
     */
    public static function resolve(?array $translations, ?string $default, string $field, string $locale): string
    {
        $valeur = $translations[$locale][$field] ?? null;

        if (is_string($valeur) && trim($valeur) !== '') {
            return $valeur;
        }

        return (string) ($default ?? '');
    }

    /**
     * Nettoie ce qu'un formulaire a soumis avant de l'écrire en base.
     *
     * Trois refus, chacun pour empêcher une classe de problème entière :
     *   - une langue absente de `$languesConnues` (un code inventé, ou une
     *     langue retirée de la configuration depuis) ;
     *   - un champ absent de `$champsAutorises` (le formulaire de la carte n'a
     *     JAMAIS de raison d'écrire, disons, un champ `price` par ce chemin —
     *     seul le texte se traduit) ;
     *   - une valeur vide, qui ne mérite pas une ligne en base.
     *
     * Renvoie `null` plutôt qu'un tableau vide quand il ne reste rien : un
     * marchand qui vide toutes ses traductions doit voir la colonne redevenir
     * vraiment vide, pas un `{}` qui traînerait pour toujours.
     *
     * @param  mixed  $soumis           ce que le formulaire a envoyé, brut
     * @param  array<string>  $languesConnues   codes de langue acceptés (ex. Locale::codes())
     * @param  array<string>  $champsAutorises  noms de champ acceptés pour ce modèle
     */
    public static function sanitize($soumis, array $languesConnues, array $champsAutorises): ?array
    {
        if (! is_array($soumis)) {
            return null;
        }

        $propre = [];

        foreach ($soumis as $langue => $champs) {
            if (! is_string($langue) || ! in_array($langue, $languesConnues, true) || ! is_array($champs)) {
                continue;
            }

            $ligne = [];
            foreach ($champs as $champ => $valeur) {
                if (! in_array($champ, $champsAutorises, true) || ! is_scalar($valeur)) {
                    continue;
                }
                $valeur = trim((string) $valeur);
                if ($valeur !== '') {
                    $ligne[$champ] = $valeur;
                }
            }

            if ($ligne !== []) {
                $propre[$langue] = $ligne;
            }
        }

        return $propre !== [] ? $propre : null;
    }

    /**
     * Langues dans lesquelles CE modèle porte au moins un mot traduit.
     *
     * Sert à l'écran du marchand : un badge « FR · EN » à côté d'un plat dit
     * d'un coup d'œil ce qui a déjà été fait, sans ouvrir chaque article.
     *
     * @return array<string>
     */
    public static function locales(?array $translations): array
    {
        if (! is_array($translations)) {
            return [];
        }

        return array_values(array_filter(array_keys($translations), 'is_string'));
    }
}
