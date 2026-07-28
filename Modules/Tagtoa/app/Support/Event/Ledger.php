<?php

namespace Modules\Tagtoa\App\Support\Event;

/**
 * TAGTOA EVENT — cœur comptable du wallet closed-loop (LOGIQUE PURE, testable sans Laravel).
 *
 * Convention de signe UNIFORME pour tous les comptes :
 *   crédit  => balance_after = balance_before + montant   (le compte "reçoit")
 *   débit   => balance_after = balance_before - montant   (le compte "perd")
 *
 * Flux (source = débitée, dest = créditée) :
 *   top_up   : gateway_clearing -> participant      (clearing peut devenir négatif)
 *   purchase : participant       -> vendor          (participant doit avoir le solde)
 *   refund   : participant       -> gateway_clearing
 *   payout   : vendor            -> organizer
 *
 * Invariant : pour une transaction, Σ(débits) == Σ(crédits). Les montants sont des
 * ENTIERS en unités mineures (jamais de float). Les comptes "système" (clearing/house)
 * peuvent passer en négatif ; les comptes "valeur" (participant/vendor/organizer) non.
 */
class Ledger
{
    public const DEBIT = 'debit';
    public const CREDIT = 'credit';

    /** type => [rôle source (débit), rôle dest (crédit), la source doit-elle avoir les fonds ?] */
    public const FLOWS = [
        'top_up'   => ['gateway_clearing', 'participant', false],
        'purchase' => ['participant', 'vendor', true],
        'refund'   => ['participant', 'gateway_clearing', true],
        'payout'   => ['vendor', 'organizer', true],
    ];

    public static function flow(string $type): ?array
    {
        return self::FLOWS[$type] ?? null;
    }

    /** La source d'un flux doit-elle disposer d'un solde suffisant ? */
    public static function requiresFunds(string $type): bool
    {
        $f = self::flow($type);

        return $f ? (bool) $f[2] : false;
    }

    /** Applique la convention de signe : crédit ajoute, débit retire. */
    public static function balanceAfter(int $before, string $direction, int $amount): int
    {
        return $direction === self::CREDIT ? $before + $amount : $before - $amount;
    }

    /** Le solde source couvre-t-il le montant ? (toujours vrai si le flux n'exige pas de fonds) */
    public static function sufficientFunds(string $type, int $sourceBalance, int $amount): bool
    {
        if (! self::requiresFunds($type)) {
            return true;
        }

        return $amount > 0 && $sourceBalance >= $amount;
    }

    /**
     * Construit la paire d'écritures équilibrées pour une transaction.
     *
     * @return array{debit: array, credit: array}
     *
     * @throws \InvalidArgumentException  type inconnu ou montant non positif
     * @throws \RuntimeException          'insufficient_funds' si la source contrainte est à découvert
     */
    public static function buildPair(string $type, int $sourceBefore, int $destBefore, int $amount): array
    {
        $flow = self::flow($type);
        if (! $flow) {
            throw new \InvalidArgumentException('unknown_flow:'.$type);
        }
        if ($amount <= 0) {
            throw new \InvalidArgumentException('amount_must_be_positive');
        }

        [$sourceRole, $destRole, $needFunds] = $flow;

        if ($needFunds && $sourceBefore < $amount) {
            throw new \RuntimeException('insufficient_funds');
        }

        return [
            'debit' => [
                'role'          => $sourceRole,
                'direction'     => self::DEBIT,
                'amount_minor'  => $amount,
                'balance_after' => self::balanceAfter($sourceBefore, self::DEBIT, $amount),
            ],
            'credit' => [
                'role'          => $destRole,
                'direction'     => self::CREDIT,
                'amount_minor'  => $amount,
                'balance_after' => self::balanceAfter($destBefore, self::CREDIT, $amount),
            ],
        ];
    }

    /** Σ(débits) == Σ(crédits) (> 0) pour un ensemble d'écritures. */
    public static function isBalanced(array $entries): bool
    {
        $deb = 0;
        $cre = 0;
        foreach ($entries as $e) {
            $amt = (int) ($e['amount_minor'] ?? 0);
            if (($e['direction'] ?? '') === self::DEBIT) {
                $deb += $amt;
            } elseif (($e['direction'] ?? '') === self::CREDIT) {
                $cre += $amt;
            }
        }

        return $deb > 0 && $deb === $cre;
    }
}
