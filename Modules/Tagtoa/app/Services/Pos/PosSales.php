<?php

namespace Modules\Tagtoa\App\Services\Pos;

use Illuminate\Database\Eloquent\Builder;
use Modules\Tagtoa\App\Models\Pos\Sale;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Models\Staff\Staff;

/**
 * TAGTOA POS — jusqu'où chacun voit les ventes.
 *
 * Règle du commerce : « chak kès jere vant pa yo separement ; se patron an
 * selman ki ka wè tout transactions ». Autrement dit, trois portées :
 *
 *   all   le patron — toutes les caisses du commerce, avec le nom de qui tenait
 *         quelle caisse ;
 *   till  le gérant — ce qui s'est encaissé sur SA caisse, quel que soit
 *         l'employé qui l'a relayé dans la journée ;
 *   own   le caissier — ses propres ventes, et rien d'autre.
 *
 * La portée vient du RÔLE (StaffAccess), jamais de ce que demande l'écran :
 * un caissier qui bricolerait l'URL d'un rapport n'y gagnerait rien.
 */
class PosSales
{
    /**
     * Ventes qu'un employé a le droit de voir.
     *
     * Toujours limitée au commerce, même pour le patron : la portée la plus
     * ouverte reste « toutes MES caisses », jamais celles du voisin.
     */
    public function visibleTo(Staff $staff, Terminal $terminal): Builder
    {
        $query = Sale::query()->whereHas(
            'terminal',
            fn ($t) => $t->where('tagtoa_pos_terminals.tenant_id', $staff->tenant_id)
        );

        return match ($staff->salesScope()) {
            'all'   => $query,
            'till'  => $query->where('terminal_id', $terminal->id),
            default => $query->where('staff_id', $staff->id),
        };
    }

    /**
     * Ventes d'une caisse vues par le PATRON du commerce (aucune session
     * employé : le tableau de bord est déjà celui du patron).
     */
    public function forOwner(?string $tenantId, ?Terminal $terminal = null): Builder
    {
        $query = Sale::query()->whereHas(
            'terminal',
            fn ($t) => $t->where('tagtoa_pos_terminals.tenant_id', $tenantId)
        );

        return $terminal ? $query->where('terminal_id', $terminal->id) : $query;
    }

    /**
     * Recette d'une journée, par employé — « qui a encaissé combien ».
     * Les ventes sans caissier (commerce sans employé, ou employé parti)
     * remontent sous le nom du patron plutôt que de disparaître du total.
     *
     * @return array<string, array{count:int, total:float}>
     */
    public function byCashier(Builder $sales): array
    {
        $out = [];
        foreach ($sales->with('staff')->get() as $sale) {
            $nom = $sale->cashier_name;
            $out[$nom] ??= ['count' => 0, 'total' => 0.0];
            $out[$nom]['count']++;
            $out[$nom]['total'] += (float) $sale->total;
        }

        // Trié sur la recette, pas sur le nombre de ventes : `arsort` compare
        // les tableaux élément par élément et aurait classé sur le compte.
        uasort($out, fn ($a, $b) => $b['total'] <=> $a['total']);

        return $out;
    }
}
