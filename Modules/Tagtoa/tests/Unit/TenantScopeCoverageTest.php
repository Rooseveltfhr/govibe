<?php

namespace Modules\Tagtoa\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Garde-fou : tout modèle qui porte un commerce DOIT être isolé automatiquement.
 *
 * C'est ce test qui rend l'isolation « par construction » plutôt que par
 * discipline. Sans lui, le trait serait posé aujourd'hui puis oublié au
 * prochain modèle — et l'isolation redeviendrait une consigne que quelqu'un
 * finit par ne pas lire.
 *
 * Le message d'échec dit quoi faire, pour que la correction prenne dix secondes.
 */
class TenantScopeCoverageTest extends TestCase
{
    /** Modèles portant `tenant_id`, avec ou sans le trait. */
    private function scan(): array
    {
        $dir = __DIR__.'/../../app/Models';
        $with = $without = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
                continue;
            }
            $code = (string) file_get_contents($file->getPathname());
            if (! str_contains($code, "'tenant_id'")) {
                continue; // le commerce est atteint via le parent (item, ligne…)
            }
            $rel = str_replace($dir.'/', '', $file->getPathname());

            str_contains($code, 'use BelongsToTenant;') ? $with[] = $rel : $without[] = $rel;
        }

        sort($with);
        sort($without);

        return [$with, $without];
    }

    public function test_every_model_carrying_a_merchant_is_isolated_automatically(): void
    {
        [$with, $without] = $this->scan();

        $this->assertSame([], $without, "\n".
            "Ces modèles portent un commerce mais ne sont PAS isolés :\n  - ".
            implode("\n  - ", $without)."\n\n".
            "Ajoutez dans la classe :\n".
            "    use Modules\\Tagtoa\\App\\Support\\BelongsToTenant;\n".
            "    …\n".
            "    use BelongsToTenant;\n\n".
            "Si le modèle doit VRAIMENT échapper à l'isolation, dites pourquoi ici même.\n");

        // Garde-fou du garde-fou : un scan qui ne trouve plus rien passerait
        // silencieusement alors que le chemin des modèles serait cassé.
        $this->assertGreaterThan(20, count($with), 'Scan des modèles vide ou chemin cassé ?');
    }

    public function test_the_escape_hatch_is_named_and_easy_to_find(): void
    {
        // Sortir de l'isolation doit rester un acte visible en relecture.
        $trait = (string) file_get_contents(__DIR__.'/../../app/Support/BelongsToTenant.php');

        $this->assertStringContainsString('scopeAllTenants', $trait);
        $this->assertStringContainsString('withoutGlobalScope', $trait);
    }

    public function test_every_deliberate_exit_is_in_a_place_where_it_makes_sense(): void
    {
        // Voir tous les commerces se justifie pour la vue plateforme du fondateur
        // et pour les services qui reçoivent le commerce en paramètre. Ailleurs,
        // c'est un contournement — et ce test le fera remarquer.
        $allowed = [
            'Services/Billing/SuperAdminService.php',   // revenu global du fondateur
            'Services/Card/CardCreditService.php',      // crédite un AUTRE commerce
            'Http/Controllers/SuperAdmin/CardCreditController.php',
            'Support/BelongsToTenant.php',              // définition
        ];

        $found = [];
        $dir = __DIR__.'/../../app';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
                continue;
            }
            if (str_contains((string) file_get_contents($file->getPathname()), 'allTenants(')) {
                $found[] = str_replace($dir.'/', '', $file->getPathname());
            }
        }

        sort($found);
        sort($allowed);

        $this->assertSame($allowed, $found, "\n".
            "Une sortie d'isolation est apparue hors des endroits prévus.\n".
            "Si elle est légitime, ajoutez le fichier à la liste ci-dessus AVEC sa raison.\n");
    }
}
