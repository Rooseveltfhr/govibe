<?php

namespace Modules\Tagtoa\App\Support\Catalog;

/**
 * TAGTOA — dessiner un code-barres imprimable, sans aucune dépendance.
 *
 * Une étiquette qui montre seulement « TAG-000245-5 » en gros n'est pas un
 * code-barres : elle oblige à taper les chiffres à chaque vente, ce qui est
 * exactement ce que le scanner devait éviter. Il faut de vraies barres.
 *
 * Code 39 est retenu pour une raison précise : son alphabet couvre les
 * chiffres, les lettres et le tiret — donc exactement la forme des codes
 * TAGTOA (« TAG-000245-5 »), sans encodage intermédiaire. Il est lu par
 * toutes les douchettes du marché et par les caméras. Il est moins dense
 * qu'un Code 128, ce qui ne coûte rien ici : une étiquette de boutique a
 * largement la place, et des barres plus larges se lisent MIEUX sur une
 * impression bon marché ou un papier qui a traîné.
 *
 * Le rendu est un SVG : il s'imprime net à n'importe quelle taille, là où une
 * image matricielle donnerait des barres floues que le scanner refuse.
 *
 * Classe PURE : aucune dépendance Laravel, testable sans base de données.
 */
class Code39
{
    /**
     * Alphabet Code 39 : neuf éléments par caractère, barre et espace en
     * alternance en commençant par une barre. « w » = élément large.
     */
    public const PATTERNS = [
        '0' => 'nnnwwnwnn', '1' => 'wnnwnnnnw', '2' => 'nnwwnnnnw', '3' => 'wnwwnnnnn',
        '4' => 'nnnwwnnnw', '5' => 'wnnwwnnnn', '6' => 'nnwwwnnnn', '7' => 'nnnwnnwnw',
        '8' => 'wnnwnnwnn', '9' => 'nnwwnnwnn',
        'A' => 'wnnnnwnnw', 'B' => 'nnwnnwnnw', 'C' => 'wnwnnwnnn', 'D' => 'nnnnwwnnw',
        'E' => 'wnnnwwnnn', 'F' => 'nnwnwwnnn', 'G' => 'nnnnnwwnw', 'H' => 'wnnnnwwnn',
        'I' => 'nnwnnwwnn', 'J' => 'nnnnwwwnn', 'K' => 'wnnnnnnww', 'L' => 'nnwnnnnww',
        'M' => 'wnwnnnnwn', 'N' => 'nnnnwnnww', 'O' => 'wnnnwnnwn', 'P' => 'nnwnwnnwn',
        'Q' => 'nnnnnnwww', 'R' => 'wnnnnnwwn', 'S' => 'nnwnnnwwn', 'T' => 'nnnnwnwwn',
        'U' => 'wwnnnnnnw', 'V' => 'nwwnnnnnw', 'W' => 'wwwnnnnnn', 'X' => 'nwnnwnnnw',
        'Y' => 'wwnnwnnnn', 'Z' => 'nwwnwnnnn',
        '-' => 'nwnnnnwnw', '.' => 'wwnnnnwnn', ' ' => 'nwwnnnwnn',
        '$' => 'nwnwnwnnn', '/' => 'nwnwnnnwn', '+' => 'nwnnnwnwn', '%' => 'nnnwnwnwn',
        '*' => 'nwnnwnwnn',   // délimiteur de début et de fin
    ];

    /** Rapport élément large / élément étroit. 3 est la valeur sûre partout. */
    public const RATIO = 3;

    /** Ce code peut-il être dessiné en Code 39 ? PUR. */
    public static function canEncode(?string $texte): bool
    {
        $texte = strtoupper(trim((string) $texte));
        if ($texte === '') {
            return false;
        }

        foreach (str_split($texte) as $c) {
            // L'astérisque délimite : le laisser au milieu couperait la lecture
            // en deux codes dont aucun ne serait le bon.
            if ($c === '*' || ! isset(self::PATTERNS[$c])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Largeurs des éléments, en modules. PUR.
     *
     * Renvoie une liste alternée barre/espace en commençant par une barre.
     * Séparer ce calcul du dessin permet de le vérifier élément par élément —
     * une barre de trop et le code entier devient illisible.
     *
     * @return array<int,int>|null
     */
    public static function modules(?string $texte): ?array
    {
        if (! self::canEncode($texte)) {
            return null;
        }

        $texte = strtoupper(trim((string) $texte));
        $out = [];

        // Le délimiteur encadre le code, et un espace inter-caractère les sépare.
        foreach (str_split('*'.$texte.'*') as $i => $c) {
            if ($i > 0) {
                $out[] = 1; // espace inter-caractère, toujours étroit
            }
            foreach (str_split(self::PATTERNS[$c]) as $e) {
                $out[] = $e === 'w' ? self::RATIO : 1;
            }
        }

        return $out;
    }

    /**
     * Le code dessiné en SVG. PUR.
     *
     * @param  int  $moduleWidth  largeur d'un module, en unités SVG
     * @param  int  $height       hauteur des barres
     */
    public static function svg(?string $texte, int $moduleWidth = 2, int $height = 60, bool $withText = true): ?string
    {
        $modules = self::modules($texte);
        if ($modules === null) {
            return null;
        }

        $moduleWidth = max(1, $moduleWidth);
        $height = max(20, $height);

        $largeur = array_sum($modules) * $moduleWidth;
        // Marge blanche obligatoire de part et d'autre (« quiet zone ») : sans
        // elle, beaucoup de lecteurs ne trouvent pas le début du code.
        $marge = 10 * $moduleWidth;
        $total = $largeur + 2 * $marge;
        $hauteurTexte = $withText ? 16 : 0;

        $barres = '';
        $x = $marge;
        foreach ($modules as $i => $m) {
            $w = $m * $moduleWidth;
            if ($i % 2 === 0) { // les rangs pairs sont des barres
                $barres .= '<rect x="'.$x.'" y="0" width="'.$w.'" height="'.$height.'"/>';
            }
            $x += $w;
        }

        $libelle = '';
        if ($withText) {
            $libelle = '<text x="'.($total / 2).'" y="'.($height + 13).'" text-anchor="middle"'
                .' font-family="monospace" font-size="13" letter-spacing="1">'
                .htmlspecialchars(strtoupper(trim((string) $texte)), ENT_QUOTES).'</text>';
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$total.'" height="'.($height + $hauteurTexte).'"'
            .' viewBox="0 0 '.$total.' '.($height + $hauteurTexte).'" shape-rendering="crispEdges">'
            .'<rect width="100%" height="100%" fill="#fff"/>'
            .'<g fill="#000">'.$barres.'</g>'
            .$libelle
            .'</svg>';
    }
}
