<?php

namespace Modules\Tagtoa\Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use PHPUnit\Framework\TestCase;

/**
 * Smoke test : TOUTES les vues Blade du module doivent compiler en PHP valide.
 *
 * Leçon du PR #41 : un `fn(...)=>[...]` dans `@json(...)` produisait du PHP
 * cassé (« Unclosed '[' ») et les pages create/edit crashaient en prod sans
 * que la CI (logique pure seulement) ne le voie. Ce test compile chaque vue
 * avec le VRAI compilateur Blade puis vérifie la syntaxe du PHP généré.
 */
class ViewCompileTest extends TestCase
{
    public function test_all_module_views_compile_to_valid_php(): void
    {
        if (! class_exists(BladeCompiler::class)) {
            $this->markTestSkipped('illuminate/view absent (composer install requis).');
        }

        $viewsDir = __DIR__.'/../../resources/views';
        $cacheDir = sys_get_temp_dir().'/tagtoa-blade-cache';
        @mkdir($cacheDir, 0777, true);

        $compiler = new BladeCompiler(new Filesystem, $cacheDir);

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($viewsDir));
        $failures = [];
        $count = 0;

        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $count++;

            try {
                $php = $compiler->compileString(file_get_contents($file->getPathname()));
            } catch (\Throwable $e) {
                $failures[] = $file->getPathname().' — compile: '.$e->getMessage();

                continue;
            }

            $tmp = tempnam(sys_get_temp_dir(), 'bl').'.php';
            file_put_contents($tmp, $php);
            $out = (string) shell_exec('php -l '.escapeshellarg($tmp).' 2>&1');
            @unlink($tmp);

            if (! str_contains($out, 'No syntax errors detected')) {
                $rel = str_replace($viewsDir.'/', '', $file->getPathname());
                $failures[] = $rel.' — '.trim($out);
            }
        }

        $this->assertGreaterThan(0, $count, 'Aucune vue trouvée — chemin des vues cassé ?');
        $this->assertSame([], $failures, "Vues Blade qui compilent en PHP invalide :\n".implode("\n", $failures));
    }
}
