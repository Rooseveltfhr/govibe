<?php

namespace Modules\Tagtoa\App\Services\Loyalty;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Tagtoa\App\Models\Loyalty\Card;
use Modules\Tagtoa\App\Models\Loyalty\Program;
use Modules\Tagtoa\App\Models\Loyalty\Reward;
use Modules\Tagtoa\App\Models\Loyalty\Transaction;

/**
 * TAGTOA Loyalty — numéros Luhn 16 chiffres (préfixe 4297), émission, top-up/redeem.
 */
class LoyaltyCardService
{
    private const PREFIX = '4297';

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
        return DB::transaction(function () use ($card, $amount, $opts) {
            $card = Card::lockForUpdate()->findOrFail($card->id);
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
    }

    public function redeem(Card $card, float $amount, array $opts = []): Transaction
    {
        return DB::transaction(function () use ($card, $amount, $opts) {
            $card = Card::lockForUpdate()->findOrFail($card->id);
            if (! $card->isActive()) {
                throw new \RuntimeException(__('Carte inactive ou expirée.'));
            }
            $pd = (int) ($opts['points_delta'] ?? 0);
            if ($amount > (float) $card->balance + 1e-6) {
                throw new \RuntimeException(__('Solde insuffisant.'));
            }
            if ($card->points + $pd < 0) {
                throw new \RuntimeException(__('Points insuffisants.'));
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
    }

    public function redeemReward(Card $card, Reward $reward): Transaction
    {
        if ($card->points < $reward->points_required) {
            throw new \RuntimeException(__('Points insuffisants pour cette récompense.'));
        }
        return $this->redeem($card, 0, [
            'points_delta' => -$reward->points_required,
            'reward_id'    => $reward->id,
            'note'         => __('Récompense : :n', ['n' => $reward->name]),
        ]);
    }
}
