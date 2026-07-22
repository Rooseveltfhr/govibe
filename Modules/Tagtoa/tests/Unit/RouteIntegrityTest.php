<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Support\Dev\RouteNames;
use PHPUnit\Framework\TestCase;

/**
 * Intégrité des routes : chaque `route('tagtoa.xxx')` utilisé dans une vue ou
 * un contrôleur DOIT correspondre à une route définie dans routes/web.php.
 *
 * Pourquoi : un nom de route erroné (faute de frappe, route renommée/supprimée)
 * lève RouteNotFoundException → 500 en prod. La CI ne boot pas Laravel (cœur
 * Biztap absent), donc on ne détecterait jamais ce crash sans vérification
 * statique. Même classe de bug que le crash Blade @json du PR #41 : invisible
 * en logique pure, fatal en prod.
 *
 * On ne vérifie QUE les noms préfixés `tagtoa.` : les routes du cœur Biztap
 * (home, logout, mail…) sont définies ailleurs et légitimement hors périmètre.
 */
class RouteIntegrityTest extends TestCase
{
    public function test_every_used_tagtoa_route_is_defined(): void
    {
        $moduleRoot = __DIR__.'/../..';
        $routesFile = $moduleRoot.'/routes/web.php';

        $defined = RouteNames::defined((string) file_get_contents($routesFile));
        $this->assertNotEmpty($defined, 'Aucune route analysée — routes/web.php introuvable ou parseur cassé ?');

        // Sources qui utilisent route() : vues Blade + contrôleurs.
        $sources = [];
        foreach ([$moduleRoot.'/resources/views', $moduleRoot.'/app/Http/Controllers'] as $dir) {
            if (! is_dir($dir)) {
                continue;
            }
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($files as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                    $sources[] = (string) file_get_contents($file->getPathname());
                }
            }
        }

        $used = array_filter(
            RouteNames::used($sources),
            fn (string $name) => str_starts_with($name, 'tagtoa.')
        );
        $this->assertNotEmpty($used, 'Aucune utilisation de route tagtoa.* trouvée — chemins de scan cassés ?');

        $definedSet = array_flip($defined);
        $missing = array_values(array_filter(
            $used,
            fn (string $name) => ! isset($definedSet[$name])
        ));
        sort($missing);

        $this->assertSame([], $missing,
            "Routes tagtoa.* utilisées mais NON définies dans routes/web.php (500 en prod) :\n"
            .implode("\n", array_map(fn ($m) => "  - $m", $missing)));
    }
}
