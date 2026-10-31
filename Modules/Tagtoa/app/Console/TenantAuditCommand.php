<?php

namespace Modules\Tagtoa\App\Console;

use Illuminate\Console\Command;
use Modules\Tagtoa\App\Support\Tenancy\TenantAudit;

/**
 * php artisan tagtoa:tenant-audit [--strict]
 *
 * Lecture seule. Affiche l'état réel de l'isolation par commerce. `--strict`
 * sort en code 1 si un modèle porte `tenant_id` sans isolation (non documenté) :
 * c'est la garde que la CI peut appeler. Les lignes orphelines et les comptes
 * sans commerce sont des CONSTATS (jamais un échec) : on les regarde avant CS-11b.
 */
class TenantAuditCommand extends Command
{
    protected $signature = 'tagtoa:tenant-audit {--strict : Code 1 si un modèle a tenant_id sans isolation}';
    protected $description = "Inventaire en lecture seule de l'isolation par commerce";

    public function handle(TenantAudit $audit): int
    {
        $inv = $audit->inventory();
        $bad = array_diff_key($inv['unscoped_with_column'], TenantAudit::DOCUMENTED_EXCEPTIONS);

        $this->info('Modèles isolés (trait + colonne) : '.count($inv['scoped']));
        foreach ($bad as $class => $table) {
            $this->error("SANS ISOLATION : $class ($table) a une colonne tenant_id mais pas BelongsToTenant");
        }
        foreach ($inv['scoped_without_column'] as $class => $table) {
            $this->error("TRAIT SANS COLONNE : $class ($table)");
        }
        foreach ($inv['tables_missing'] as $class => $table) {
            $this->line("table absente (ignoré) : $table");
        }

        $orph = $audit->orphanRows($inv['scoped']);
        foreach ($orph as $table => $n) {
            $this->warn("lignes sans commerce : $table = ".($n < 0 ? 'illisible' : $n));
        }
        if (! $orph) {
            $this->info('Aucune ligne sans commerce dans les tables isolées.');
        }

        $u = $audit->usersWithoutTenant();
        if ($u === null) {
            $this->line('Comptes : table users/tenant_id introuvable (non vérifié).');
        } else {
            $this->line("Comptes sans tenant_id : {$u['without_tenant']} / {$u['total']}");
        }

        return ($this->option('strict') && ($bad || $inv['scoped_without_column'])) ? self::FAILURE : self::SUCCESS;
    }
}
