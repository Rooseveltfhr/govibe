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
     * Ventes, coût des marchandises, bénéfice.
     *
     * Beaucoup de petits commerces savent combien ils ont vendu et pas du tout
     * combien ils ont gagné. C'est ce que ce calcul répond.
     *
     * Le coût vient de la LIGNE de vente, figé le jour où elle a été encaissée
     * — pas du prix d'achat d'aujourd'hui. Sans cela, changer de fournisseur
     * ferait bouger le bénéfice du mois dernier.
     *
     * `known` dit sur quelle part du chiffre le coût était renseigné : un
     * bénéfice calculé sur 30 % des ventes n'est pas un bénéfice, et le
     * marchand doit pouvoir le voir plutôt que de croire un chiffre creux.
     *
     * @return array{sales:float, cost:float, profit:float, margin:?float, known:float}
     */
    public function profit(Builder $sales): array
    {
        $chiffre = 0.0;
        $cout    = 0.0;
        $connu   = 0.0;

        foreach ($sales->with('items')->get() as $vente) {
            foreach ($vente->items as $ligne) {
                $ligneTotal = (float) $ligne->line_total;
                $chiffre += $ligneTotal;

                if ($ligne->cost_price !== null) {
                    $cout  += (float) $ligne->cost_price * (float) $ligne->qty;
                    $connu += $ligneTotal;
                }
            }
        }

        $benefice = round($connu - $cout, 2);

        return [
            'sales'  => round($chiffre, 2),
            'cost'   => round($cout, 2),
            'profit' => $benefice,
            // Marge sur la part dont on connaît le coût, sinon rien à dire.
            'margin' => $connu > 0 ? round($benefice / $connu * 100, 1) : null,
            // Part du chiffre d'affaires sur laquelle le calcul s'appuie.
            'known'  => $chiffre > 0 ? round($connu / $chiffre * 100, 1) : 0.0,
        ];
    }

    /**
     * La taxe collectée sur une période, par taux.
     *
     * C'est le chiffre que le commerce doit déclarer. Sans lui, le marchand
     * refait l'addition à la main chaque mois, sur des reçus en papier — et
     * c'est exactement le moment où il se trompe.
     *
     * Tout vient des montants FIGÉS sur les ventes : rien n'est recalculé à
     * partir des réglages d'aujourd'hui. Une déclaration qui changerait parce
     * qu'on a modifié un taux depuis ne vaudrait rien.
     *
     * @return array{collected:float, base:float, total:float, sales:int,
     *               byRate:array<int, array{rate:float, base:float, tax:float}>,
     *               label:?string}
     */
    public function taxReport(Builder $sales): array
    {
        $collectee = 0.0;
        $base = 0.0;
        $total = 0.0;
        $compte = 0;
        $parTaux = [];
        $libelle = null;

        foreach ($sales->get() as $vente) {
            $compte++;
            $collectee += (float) $vente->tax_total;
            $base      += (float) $vente->tax_base;
            $total     += (float) $vente->total;
            $libelle ??= $vente->tax_label;

            foreach ((array) $vente->tax_breakdown as $ligne) {
                $taux = (float) ($ligne['rate'] ?? 0);
                $cle = rtrim(rtrim(number_format($taux, 3, '.', ''), '0'), '.');

                if (! isset($parTaux[$cle])) {
                    $parTaux[$cle] = ['rate' => $taux, 'base' => 0.0, 'tax' => 0.0];
                }
                $parTaux[$cle]['base'] = round($parTaux[$cle]['base'] + (float) ($ligne['base'] ?? 0), 2);
                $parTaux[$cle]['tax']  = round($parTaux[$cle]['tax'] + (float) ($ligne['tax'] ?? 0), 2);
            }
        }

        krsort($parTaux, SORT_NUMERIC);

        return [
            'collected' => round($collectee, 2),
            'base'      => round($base, 2),
            'total'     => round($total, 2),
            'sales'     => $compte,
            'byRate'    => array_values($parTaux),
            'label'     => $libelle,
        ];
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
