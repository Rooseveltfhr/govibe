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
    /**
     * Les SEULS modèles autorisés à porter un commerce sans être isolés
     * automatiquement — chacun avec la raison qui le justifie.
     *
     * Toute entrée ici est un trou dans l'isolation : elle doit se défendre en
     * relecture, et le test plus bas empêche la liste de s'allonger.
     */
    private const EXEMPTS = [
        // Un stand NON RÉCLAMÉ n'appartient à aucun commerce. La portée
        // automatique le rendrait introuvable au scan — c'est-à-dire au moment
        // précis où il faut le trouver pour proposer son activation. Le
        // cloisonnement du marchand passe par scopeOfBusiness(), explicite.
        'Stand/Stand.php',

        // L'histoire d'un stand commence AVANT qu'un commerce existe :
        // fabriqué, affecté à un revendeur, vendu. Isoler ce journal cacherait
        // exactement les événements qui prouvent la provenance dans un litige.
        'Stand/StandEvent.php',
    ];

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

            if (in_array($rel, self::EXEMPTS, true)) {
                continue; // exemption nommée et justifiée ci-dessus
            }

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

    public function test_the_exemption_list_stays_short(): void
    {
        // Une liste d'exceptions qui s'allonge est une règle qui se dissout. Si
        // elle doit grandir, c'est le signe qu'il manque une portée au trait,
        // pas qu'il faut assouplir la garde.
        $this->assertLessThanOrEqual(2, count(self::EXEMPTS),
            "Trop de modèles échappent à l'isolation automatique.");
    }

    public function test_every_exempt_model_still_exists(): void
    {
        // Une exemption qui désigne un fichier disparu masquerait un modèle
        // homonyme recréé ailleurs — sans isolation, et sans que rien n'échoue.
        foreach (self::EXEMPTS as $rel) {
            $this->assertFileExists(__DIR__.'/../../app/Models/'.$rel,
                "L'exemption « $rel » ne correspond plus à aucun modèle : retirez-la.");
        }
    }

    public function test_every_exempt_model_carries_its_own_explicit_scope(): void
    {
        // Échapper à la portée automatique n'autorise pas à n'en avoir aucune :
        // il faut alors un chemin NOMMÉ par lequel le marchand ne voit que le
        // sien, sinon l'exemption devient une fuite.
        $stand = (string) file_get_contents(__DIR__.'/../../app/Models/Stand/Stand.php');

        $this->assertStringContainsString('scopeOfBusiness', $stand,
            'Un modèle exempté doit offrir une portée explicite.');
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
