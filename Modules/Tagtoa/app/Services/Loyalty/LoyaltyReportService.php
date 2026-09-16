<?php

namespace Modules\Tagtoa\App\Services\Loyalty;

use Illuminate\Support\Carbon;
use Modules\Tagtoa\App\Models\Loyalty\Program;
use Modules\Tagtoa\App\Models\Loyalty\Transaction;
use Modules\Tagtoa\App\Support\Loyalty\CustomerSegment;

/**
 * TAGTOA Loyalty — rapport de fidélisation : ce qui s'est passé sur une
 * période, et qui compose la base de cartes aujourd'hui.
 *
 * Deux questions différentes, volontairement séparées :
 *   - forPeriod()  : « qu'est-ce qui a bougé entre ces deux dates ? »
 *     (montants, points, nouvelles cartes) — sert à comparer un mois à l'autre.
 *   - segments()   : « qui sont mes clients EN CE MOMENT ? » — une photo de
 *     l'état actuel, pas de la période, sinon un client redevenu VIP le 2 du
 *     mois changerait de case selon la date de fin choisie pour le rapport.
 */
class LoyaltyReportService
{
    public function forPeriod(Program $program, Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $transactions = Transaction::query()
            ->whereHas('card', fn ($q) => $q->where('program_id', $program->id))
            ->whereBetween('created_at', [$from, $to])
            ->get();

        $topUps = $transactions->where('type', Transaction::TYPE_TOP_UP);
        $earns = $transactions->where('type', Transaction::TYPE_EARN);
        $redeems = $transactions->where('type', Transaction::TYPE_REDEEM);

        // points_delta est négatif sur une utilisation (voir LoyaltyCardService::redeem) :
        // on affiche « points utilisés » comme un nombre positif au marchand.
        $pointsRedeemed = (int) abs(min(0, (int) $redeems->sum('points_delta')));

        return [
            'from' => $from,
            'to' => $to,
            'points_issued' => (int) $topUps->sum('points_delta') + (int) $earns->sum('points_delta'),
            'points_redeemed' => $pointsRedeemed,
            'amount_topped_up' => (float) $topUps->sum('amount'),
            'amount_redeemed' => (float) $redeems->sum('amount'),
            'top_up_count' => $topUps->count(),
            'redeem_count' => $redeems->count(),
            'reward_redemptions' => $redeems->whereNotNull('reward_id')->count(),
            'new_cards' => $program->cards()->whereBetween('issued_at', [$from, $to])->count(),
        ];
    }

    /**
     * Répartit les cartes ACTIVES du programme par segment, à l'instant présent.
     *
     * @return array<string,int> clé = constante CustomerSegment, valeur = effectif
     */
    public function segments(Program $program): array
    {
        $now = Carbon::now();
        $counts = array_fill_keys(array_keys(CustomerSegment::LABELS), 0);

        $program->cards()
            ->select(['id', 'points', 'issued_at'])
            ->withMax('transactions as last_transaction_at', 'created_at')
            ->orderBy('id')
            ->chunk(200, function ($cards) use (&$counts, $now) {
                foreach ($cards as $card) {
                    $issuedDaysAgo = $card->issued_at ? $card->issued_at->diffInDays($now) : 0;
                    $lastTransactionDaysAgo = $card->last_transaction_at
                        ? Carbon::parse($card->last_transaction_at)->diffInDays($now)
                        : null;

                    $segment = CustomerSegment::classify([
                        'points' => (int) $card->points,
                        'issued_days_ago' => $issuedDaysAgo,
                        'last_transaction_days_ago' => $lastTransactionDaysAgo,
                    ]);

                    $counts[$segment]++;
                }
            });

        return $counts;
    }
}
