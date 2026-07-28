<?php

namespace Modules\Tagtoa\App\Support\Dev;

/**
 * TAGTOA DEV — analyse statique PURE des noms de routes (sans Laravel).
 *
 * Sert au test d'intégrité : un `route('nom')` utilisé dans une vue ou un
 * contrôleur mais NON défini dans routes/web.php provoque un 500 en prod
 * (RouteNotFoundException). La CI ne bootant pas Laravel (cœur Biztap absent),
 * on vérifie la cohérence par lecture de texte.
 */
class RouteNames
{
    /**
     * Noms de routes PLEINEMENT qualifiés définis dans le source de routes.
     * Gère les préfixes de nom de groupe : ->name('a.b.')->group(fn () { ->name('c') } ) => a.b.c.
     *
     * @return string[]  PUR
     */
    public static function defined(string $src): array
    {
        $names = [];
        $stack = [];   // [ [prefix, openDepth], ... ]
        $depth = 0;

        foreach (explode("\n", $src) as $line) {
            $opensGroup = strpos($line, '->group(') !== false;
            preg_match_all("/->name\((['\"])([^'\"]*)\\1\)/", $line, $m);
            $found = $m[2] ?? [];

            if ($opensGroup) {
                // Le nom présent sur la ligne du groupe est un PRÉFIXE.
                $prefix = $found ? (string) end($found) : '';
                $stack[] = [$prefix, $depth];
            } elseif ($found) {
                // Route feuille : nom complet = concat des préfixes + feuille.
                $prefix = '';
                foreach ($stack as $e) {
                    $prefix .= $e[0];
                }
                foreach ($found as $n) {
                    $names[] = $prefix.$n;
                }
            }

            $depth += substr_count($line, '{') - substr_count($line, '}');

            // Referme les groupes dont la profondeur d'ouverture est atteinte.
            while ($stack && $depth <= $stack[count($stack) - 1][1]) {
                array_pop($stack);
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Noms de routes UTILISÉS via route('nom'...) dans un ensemble de sources.
     *
     * @param  string[]  $sources  contenus de fichiers (vues, contrôleurs)
     * @return string[]  PUR
     */
    public static function used(array $sources): array
    {
        $used = [];
        foreach ($sources as $src) {
            if (preg_match_all("/route\((['\"])([^'\"]+)\\1/", $src, $m)) {
                foreach ($m[2] as $n) {
                    $used[] = $n;
                }
            }
        }

        return array_values(array_unique($used));
    }
}
