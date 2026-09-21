<?php

namespace Modules\Tagtoa\App\Support\Menu;

/**
 * TAGTOA MENU — « Commander via Agent IA », sans aucun appel à un service
 * externe payant.
 *
 * Aucune intégration LLM n'existe dans TAGTOA (pas de clé API, pas de
 * fournisseur configuré) : plutôt que d'en brancher une sans que le
 * fondateur ait choisi le fournisseur, le budget et posé le
 * /security-review qu'exige CLAUDE.md pour toute nouvelle donnée client
 * envoyée à un tiers, ce parseur fait le travail en LOCAL — mots-clés,
 * quantités, aucun réseau, aucun coût, aucun risque de fuite.
 *
 * MÉTHODE : le message est réduit à une suite de mots. Chaque article du
 * catalogue est cherché comme suite EXACTE et CONTIGUË de ses propres mots
 * dans cette suite (les plus longs noms d'abord, pour qu'un nom générique
 * court — « Riz » — ne mange pas les mots d'un nom plus spécifique — « Riz
 * ak pwa »). Une fois un article trouvé, ses mots sont « consommés » : ils
 * ne peuvent plus servir à un autre article, et le mot juste avant peut
 * porter sa quantité (chiffre ou mot-nombre). Pas de découpage préalable
 * par « et »/« ak »/« and » : ce mot appartient parfois AU NOM d'un plat
 * (« Riz ak pwa », « Pen ak manba ») — le couper d'avance le briserait.
 *
 * Classe PURE : aucune dépendance Laravel, testable sans base de données.
 * Les prix ne sont JAMAIS repris d'ici — MenuOrderService les relit
 * toujours du catalogue au moment de la commande.
 */
class OrderChatParser
{
    /** Mot-nombre → quantité. Français, kreyòl, anglais — les trois langues déjà servies par TAGTOA. */
    private const NOMBRES = [
        'un' => 1, 'une' => 1, 'deux' => 2, 'trois' => 3, 'quatre' => 4, 'cinq' => 5, 'six' => 6,
        'yon' => 1, 'de' => 2, 'twa' => 3, 'kat' => 4, 'senk' => 5, 'sis' => 6,
        'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6,
    ];

    /** Déterminants sans valeur de correspondance, retirés des noms d'articles avant comparaison. */
    private const MOTS_VIDES = ['le', 'la', 'les', 'des', 'du', 'yon', 'youn', 'yo', 'a', 'an', 'the'];

    /** Mots de liaison seuls : un reste composé UNIQUEMENT de ceux-ci n'est jamais signalé « non compris ». */
    private const CONNECTEURS = ['et', 'ak', 'epi', 'and', 'avec', 'avek', 'pou', 'nan', 'svp', 'please'];

    /**
     * @param  array<int, array{id:int, name:string}>  $catalogue  Articles DISPONIBLES de ce menu seulement.
     * @return array{matches: list<array{id:int, name:string, qty:int}>, unmatched: list<string>}
     */
    public static function parse(array $catalogue, string $message): array
    {
        $mots = self::mots(self::normalise($message));
        $n = count($mots);
        $consomme = array_fill(0, $n, false);

        $items = array_values(array_filter(array_map(fn ($it) => [
            'id' => $it['id'], 'name' => $it['name'],
            'mots' => array_values(array_diff(self::mots(self::normalise($it['name'])), self::MOTS_VIDES)),
        ], $catalogue), fn ($it) => $it['mots'] !== []));

        // Les noms les plus longs d'abord : plus spécifiques, ils doivent
        // gagner avant qu'un nom générique plus court ne mange leurs mots.
        usort($items, fn ($a, $b) => count($b['mots']) <=> count($a['mots']));

        $matches = [];
        foreach ($items as $item) {
            $longueur = count($item['mots']);
            $cible = array_map([self::class, 'singulier'], $item['mots']);

            for ($i = 0; $i <= $n - $longueur; $i++) {
                if (self::plageLibre($consomme, $i, $longueur)) {
                    $span = array_map([self::class, 'singulier'], array_slice($mots, $i, $longueur));
                    if ($span === $cible) {
                        $qty = 1;
                        if ($i > 0 && ! $consomme[$i - 1]) {
                            $motQte = $mots[$i - 1];
                            if (isset(self::NOMBRES[$motQte])) {
                                $qty = self::NOMBRES[$motQte];
                                $consomme[$i - 1] = true;
                            } elseif (ctype_digit($motQte)) {
                                $qty = max(1, (int) $motQte);
                                $consomme[$i - 1] = true;
                            }
                        }
                        for ($k = $i; $k < $i + $longueur; $k++) {
                            $consomme[$k] = true;
                        }

                        if (isset($matches[$item['id']])) {
                            $matches[$item['id']]['qty'] += $qty;
                        } else {
                            $matches[$item['id']] = ['id' => $item['id'], 'name' => $item['name'], 'qty' => $qty];
                        }
                    }
                }
            }
        }

        return ['matches' => array_values($matches), 'unmatched' => self::segmentsNonReconnus($mots, $consomme)];
    }

    private static function plageLibre(array $consomme, int $debut, int $longueur): bool
    {
        for ($k = $debut; $k < $debut + $longueur; $k++) {
            if ($consomme[$k]) {
                return false;
            }
        }

        return true;
    }

    /** Regroupe les mots jamais consommés en segments lisibles, en ignorant les purs mots de liaison. */
    private static function segmentsNonReconnus(array $mots, array $consomme): array
    {
        $segments = [];
        $courant = [];

        foreach ($mots as $i => $mot) {
            if (! $consomme[$i]) {
                $courant[] = $mot;
            } elseif ($courant) {
                $segments[] = $courant;
                $courant = [];
            }
        }
        if ($courant) {
            $segments[] = $courant;
        }

        $lisibles = [];
        foreach ($segments as $segment) {
            $utile = array_diff($segment, self::CONNECTEURS);
            if ($utile) {
                $lisibles[] = implode(' ', $segment);
            }
        }

        return $lisibles;
    }

    /** Minuscules, accents retirés, ponctuation en espaces. PUR. */
    private static function normalise(string $texte): string
    {
        $texte = mb_strtolower($texte, 'UTF-8');
        $sansAccents = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texte);
        if ($sansAccents !== false) {
            $texte = $sansAccents;
        }

        return trim(preg_replace('/[^a-z0-9\s]/u', ' ', $texte) ?? '');
    }

    /** @return list<string> */
    private static function mots(string $texteNormalise): array
    {
        return array_values(array_filter(preg_split('/\s+/u', $texteNormalise) ?: [], fn ($m) => $m !== ''));
    }

    /**
     * Pluriel simple retiré pour la comparaison seulement (jamais pour la
     * lecture de quantité, qui reste sur le mot brut) — « colas » doit
     * pouvoir désigner l'article « Cola ». Un mot-nombre n'est jamais
     * touché : « trois » ne doit jamais devenir « troi ».
     */
    private static function singulier(string $mot): string
    {
        if (mb_strlen($mot) > 3 && str_ends_with($mot, 's') && ! isset(self::NOMBRES[$mot])) {
            return mb_substr($mot, 0, -1);
        }

        return $mot;
    }
}
