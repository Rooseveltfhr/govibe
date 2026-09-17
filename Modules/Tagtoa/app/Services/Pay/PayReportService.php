<?php

namespace Modules\Tagtoa\App\Services\Pay;

use Illuminate\Support\Carbon;
use Modules\Tagtoa\App\Models\Pay\PaymentPage;
use Modules\Tagtoa\App\Models\Pay\PaymentProof;

/**
 * TAGTOA PAY — rapport d'un lien de paiement : ce qui s'est passé sur une
 * période, et où en est la conversion depuis toujours.
 *
 * Deux questions séparées à dessein, même principe que LoyaltyReportService :
 *   - forPeriod()  : « combien ai-je encaissé, et sur combien de preuves ? »
 *     — compare un mois à l'autre.
 *   - conversion() : « sur toutes les visites depuis la création du lien,
 *     combien sont devenues un paiement ? » — `views` est un compteur qui
 *     ignore quand chaque vue a eu lieu, donc ne peut pas être borné par
 *     période sans fausser le taux (une vue de l'an dernier compterait
 *     contre les preuves du mois courant).
 */
class PayReportService
{
    public function forPeriod(PaymentPage $page, Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $proofs = PaymentProof::where('payment_page_id', $page->id)
            ->whereBetween('created_at', [$from, $to])
            ->get();

        $approved = $proofs->where('status', PaymentProof::STATUS_APPROVED);
        $pending = $proofs->where('status', PaymentProof::STATUS_PENDING);
        $rejected = $proofs->where('status', PaymentProof::STATUS_REJECTED);

        return [
            'from' => $from,
            'to' => $to,
            'revenue' => (float) $approved->sum('amount'),
            'approved_count' => $approved->count(),
            'pending_count' => $pending->count(),
            'rejected_count' => $rejected->count(),
            'total_count' => $proofs->count(),
        ];
    }

    /**
     * Instantané — pas la période — de la conversion depuis toujours.
     *
     * @return array{views:int, approved:int, rate:?float} `rate` est null sans
     *         aucune vue : un taux de 0 % suggérerait des vues sans résultat,
     *         alors qu'il n'y a simplement encore rien à mesurer.
     */
    public function conversion(PaymentPage $page): array
    {
        $views = (int) $page->views;
        $approved = PaymentProof::where('payment_page_id', $page->id)
            ->where('status', PaymentProof::STATUS_APPROVED)->count();

        return [
            'views' => $views,
            'approved' => $approved,
            'rate' => $views > 0 ? round($approved / $views * 100, 1) : null,
        ];
    }
}
