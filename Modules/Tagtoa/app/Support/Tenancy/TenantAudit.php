<?php

namespace Modules\Tagtoa\App\Support\Tenancy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Tagtoa\App\Support\BelongsToTenant;

/**
 * TAGTOA — inventaire LECTURE SEULE de l'isolation par commerce (CS-11a).
 *
 * Il répond, preuves à l'appui, à trois questions avant que quiconque touche à
 * la portée automatique (CS-11b) :
 *   1. quels modèles portent une colonne `tenant_id` SANS l'isolation du trait ;
 *   2. quelles tables isolées contiennent déjà des lignes SANS commerce
 *      (invisibles pour tout le monde, ou visibles de tous selon le contexte) ;
 *   3. combien de comptes n'ont aucun `tenant_id` (la portée est alors ouverte).
 *
 * N'écrit rien, ne lève jamais : une table absente est un constat, pas une panne.
 */
class TenantAudit
{
    /**
     * Modèles qui ont `tenant_id` sans le trait, ET c'est voulu : ils sont lus
     * via leur parent (déjà isolé) ou par une requête qui filtre elle-même.
     * Chaque entrée doit dire POURQUOI ; ajouter une ligne ici est une décision
     * relue, pas un réflexe.
     *
     * @var array<class-string, string>
     */
    public const DOCUMENTED_EXCEPTIONS = [
        \Modules\Tagtoa\App\Models\Stand\Stand::class =>
            "Un stand non réclamé n'appartient à aucun commerce et doit rester trouvable au scan ; "
            .'le cloisonnement est écrit sur les requêtes du marchand (ofBusiness / heldBy).',
        \Modules\Tagtoa\App\Models\Stand\StandEvent::class =>
            "Journal des stands : écrit aussi pour des stands sans commerce (frappe, allocation revendeur) ; "
            .'lu uniquement via un stand déjà cloisonné.',
    ];

    /** @return array<class-string<Model>, string> classe => fichier */
    public static function modelClasses(): array
    {
        $out  = [];
        $base = dirname(__DIR__, 2).'/Models';
        $it   = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $rel   = substr($file->getPathname(), strlen($base) + 1, -4);
            $class = 'Modules\\Tagtoa\\App\\Models\\'.str_replace('/', '\\', $rel);
            if (class_exists($class) && is_subclass_of($class, Model::class)
                && ! (new \ReflectionClass($class))->isAbstract()) {
                $out[$class] = $file->getPathname();
            }
        }
        ksort($out);

        return $out;
    }

    public static function usesScope(string $class): bool
    {
        return in_array(BelongsToTenant::class, class_uses_recursive($class), true);
    }

    /** @return array{scoped:array,unscoped_with_column:array,scoped_without_column:array,tables_missing:array} */
    public function inventory(?array $classes = null): array
    {
        $r = ['scoped' => [], 'unscoped_with_column' => [], 'scoped_without_column' => [], 'tables_missing' => []];
        foreach ($classes ?? array_keys(self::modelClasses()) as $class) {
            $table = (new $class)->getTable();
            if (! Schema::hasTable($table)) {
                $r['tables_missing'][$class] = $table;
                continue;
            }
            $has    = Schema::hasColumn($table, 'tenant_id');
            $scoped = self::usesScope($class);
            if ($scoped && $has) {
                $r['scoped'][$class] = $table;
            } elseif ($scoped) {
                $r['scoped_without_column'][$class] = $table;
            } elseif ($has) {
                $r['unscoped_with_column'][$class] = $table;
            }
        }

        return $r;
    }

    /**
     * Lignes sans commerce par table isolée (NULL ou chaîne vide).
     *
     * @return array<string,int>
     */
    public function orphanRows(array $scopedTables): array
    {
        $out = [];
        foreach (array_unique($scopedTables) as $table) {
            try {
                $n = DB::table($table)->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', ''))->count();
                if ($n > 0) {
                    $out[$table] = $n;
                }
            } catch (\Throwable $e) {
                $out[$table] = -1; // illisible : à regarder à la main
            }
        }
        ksort($out);

        return $out;
    }

    /**
     * Comptes (table `users` de l'hôte) sans `tenant_id` : pour eux `Tenant::id()`
     * vaut null et la portée automatique ne filtre RIEN. Retourne null si la
     * table ou la colonne n'existe pas (rien à constater).
     */
    public function usersWithoutTenant(): ?array
    {
        try {
            if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'tenant_id')) {
                return null;
            }
            $q = fn () => DB::table('users')->where(fn ($w) => $w->whereNull('tenant_id')->orWhere('tenant_id', ''));

            return ['total' => DB::table('users')->count(), 'without_tenant' => $q()->count()];
        } catch (\Throwable $e) {
            return null;
        }
    }
}
