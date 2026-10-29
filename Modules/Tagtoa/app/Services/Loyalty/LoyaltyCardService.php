<?php

namespace Modules\Tagtoa\App\Services\Loyalty;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Tagtoa\App\Exceptions\LoyaltyRuleException;
use Modules\Tagtoa\App\Models\Loyalty\Card;
use Modules\Tagtoa\App\Models\Loyalty\Program;
use Modules\Tagtoa\App\Models\Loyalty\Reward;
use Modules\Tagtoa\App\Models\Loyalty\Transaction;
use Modules\Tagtoa\App\Services\Notifications\NotificationService;

/**
 * TAGTOA Loyalty — numéros Luhn 16 chiffres (préfixe 4297), émission, top-up/redeem.
 */
class LoyaltyCardService
{
    private const PREFIX = '4297';

    public function __construct(private NotificationService $notifications = new NotificationService())
    {
    }

    public function generateCardNumber(): string
    {
        do {
            $body = self::PREFIX.str_pad((string) random_int(0, 99999999999), 11, '0', STR_PAD_LEFT);
            $card = $body.$this->luhnCheckDigit($body);
        } while (Card::where('card_number', $card)->exists());

        return $card;
    }

    public function luhnCheckDigit(string $partial): int
    {
        $sum = 0; $double = true;
        for ($i = strlen($partial) - 1; $i >= 0; $i--) {
            $d = (int) $partial[$i];
            if ($double) { $d *= 2; if ($d > 9) { $d -= 9; } }
            $sum += $d; $double = ! $double;
        }
        return (10 - ($sum % 10)) % 10;
    }

    public function isValidLuhn(string $number): bool
    {
        if (! ctype_digit($number)) {
            return false;
        }
        $sum = 0; $double = false;
        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $d = (int) $number[$i];
            if ($double) { $d *= 2; if ($d > 9) { $d -= 9; } }
            $sum += $d; $double = ! $double;
        }
        return $sum % 10 === 0;
    }

    public function issueCard(Program $program, array $data): array
    {
        $number = $this->generateCardNumber();
        $cvc    = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        $card = $program->cards()->create([
            'public_token'          => $this->uniqueToken(),
            'card_number'           => $number,
            'card_number_encrypted' => Crypt::encryptString($number),
            'cvc'                   => Hash::make($cvc),
            'expiry_date'           => $data['expiry_date'] ?? now()->addYears(3)->endOfMonth(),
            'cardholder_name'       => $data['cardholder_name'],
            'cardholder_phone'      => $data['cardholder_phone'] ?? null,
            'cardholder_email'      => $data['cardholder_email'] ?? null,
            'balance'               => $data['balance'] ?? 0,
            'points'                => 0,
            'status'                => Card::STATUS_ACTIVE,
            'issued_at'             => now(),
        ]);

        return ['card' => $card, 'cvc' => $cvc];
    }

    private function uniqueToken(): string
    {
        do {
            $t = Str::lower(Str::random(24));
        } while (Card::where('public_token', $t)->exists());
        return $t;
    }

    public function topUp(Card $card, float $amount, array $opts = []): Transaction
    {
        $transaction = DB::transaction(function () use ($card, $amount, $opts) {
            $card = Card::lockForUpdate()->findOrFail($card->id);

            if ($existing = $this->existingTransaction($card, $opts)) {
                return $existing;
            }
            if (! $card->isActive()) {
                throw new LoyaltyRuleException(__('Carte inactive ou expirée.'));
            }

            $points = $opts['points'] ?? $card->program->pointsForAmount($amount);
            $card->balance += $amount;
            $card->points  += $points;
            $card->save();

            return $card->transactions()->create([
                'type'           => Transaction::TYPE_TOP_UP,
                'amount'         => $amount,
                'points_delta'   => $points,
                'balance_after'  => $card->balance,
                'points_after'   => $card->points,
                'payment_method' => $opts['payment_method'] ?? null,
                'reference'      => $opts['reference'] ?? null,
                'status'         => 1,
            ]);
        });

        // APRÈS le commit, jamais dedans : une notification qui échoue ne doit
        // pas faire échouer la recharge elle-même (voir NotificationService).
        // Jamais si la référence existait déjà — un rejeu ne doit pas renotifier.
        if ($transaction->wasRecentlyCreated) {
            $this->notifications->notifyLoyaltyMovement($transaction->card, $transaction);
        }

        return $transaction;
    }

    /**
     * Mouvement déjà enregistré pour cette (carte, référence) ? Clé
     * d'idempotence : sans elle, deux confirmations du même paiement
     * (webhook + retour navigateur) créditeraient/débiteraient deux fois —
     * le verrou sur la carte sérialise les appels concurrents mais ne les
     * rend pas idempotents à lui seul. Une référence absente (recharge/
     * débit manuels) ne protège jamais contre rien : chaque appel compte.
     */
    protected function existingTransaction(Card $card, array $opts): ?Transaction
    {
        $reference = $opts['reference'] ?? null;
        if ($reference === null) {
            return null;
        }

        return $card->transactions()->where('reference', $reference)->first();
    }

    /** Vrai si l'exception vient de la contrainte unique (card_id, reference). */
    private function isDuplicateReference(QueryException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'reference') && (
            str_contains($message, 'Integrity constraint violation')
            || str_contains($message, 'UNIQUE constraint failed')
        );
    }

    /**
     * Crédite des points sans toucher le solde (récompense d'achat, ex. commande
     * MENU payée) — distinct de topUp() qui recharge le solde ET les points.
     */
    public function earnPoints(Card $card, int $points, array $opts = []): ?Transaction
    {
        if ($points <= 0) {
            return null;
        }

        try {
            $transaction = DB::transaction(function () use ($card, $points, $opts) {
                $card = Card::lockForUpdate()->findOrFail($card->id);

                if ($existing = $this->existingTransaction($card, $opts)) {
                    return $existing;
                }
                if (! $card->isActive()) {
                    return null;
                }
                $card->points += $points;
                $card->save();

                return $card->transactions()->create([
                    'type'          => Transaction::TYPE_EARN,
                    'points_delta'  => $points,
                    'balance_after' => $card->balance,
                    'points_after'  => $card->points,
                    'reference'     => $opts['reference'] ?? null,
                    'note'          => $opts['note'] ?? null,
                    'status'        => 1,
                ]);
            });
        } catch (QueryException $e) {
            // Dernier filet, et seulement ici : earnPoints() est la SEULE des
            // trois méthodes atteignable depuis une route publique (webhook de
            // passerelle + retour navigateur confirment la même commande). La
            // lecture ci-dessus se fait sous le verrou, mais en REPEATABLE READ
            // un SELECT non verrouillant lit un instantané : si la ligne de
            // l'autre requête y échappait, c'est la contrainte unique en base
            // qui refuserait l'écriture. Rendre le mouvement déjà enregistré
            // plutôt qu'une 500 : une passerelle qui reçoit une erreur réessaie,
            // et rejouerait la course indéfiniment.
            if (! $this->isDuplicateReference($e)) {
                throw $e;
            }

            return $this->existingTransaction($card, $opts);
        }

        if ($transaction !== null && $transaction->wasRecentlyCreated) {
            $this->notifications->notifyLoyaltyMovement($transaction->card, $transaction);
        }

        return $transaction;
    }

    public function redeem(Card $card, float $amount, array $opts = []): Transaction
    {
        $transaction = DB::transaction(function () use ($card, $amount, $opts) {
            $card = Card::lockForUpdate()->findOrFail($card->id);

            if ($existing = $this->existingTransaction($card, $opts)) {
                return $existing;
            }
            if (! $card->isActive()) {
                throw new LoyaltyRuleException(__('Carte inactive ou expirée.'));
            }
            $pd = (int) ($opts['points_delta'] ?? 0);
            if ($amount > (float) $card->balance + 1e-6) {
                throw new LoyaltyRuleException(__('Solde insuffisant.'));
            }
            if ($card->points + $pd < 0) {
                throw new LoyaltyRuleException(__('Points insuffisants.'));
            }
            $card->balance -= $amount;
            $card->points  += $pd;
            $card->save();

            return $card->transactions()->create([
                'type'          => Transaction::TYPE_REDEEM,
                'reward_id'     => $opts['reward_id'] ?? null,
                'amount'        => $amount,
                'points_delta'  => $pd,
                'balance_after' => $card->balance,
                'points_after'  => $card->points,
                'reference'     => $opts['reference'] ?? null,
                'note'          => $opts['note'] ?? null,
                'status'        => 1,
            ]);
        });

        if ($transaction->wasRecentlyCreated) {
            $this->notifications->notifyLoyaltyMovement($transaction->card, $transaction);
        }

        return $transaction;
    }

    public function redeemReward(Card $card, Reward $reward): Transaction
    {
        if ($card->points < $reward->points_required) {
            throw new LoyaltyRuleException(__('Points insuffisants pour cette récompense.'));
        }
        return $this->redeem($card, 0, [
            'points_delta' => -$reward->points_required,
            'reward_id'    => $reward->id,
            'note'         => __('Récompense : :n', ['n' => $reward->name]),
        ]);
    }
}
