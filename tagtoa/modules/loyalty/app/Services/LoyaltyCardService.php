<?php

namespace App\Services;

use App\Models\TaGtoaLoyaltyCard;
use App\Models\TaGtoaLoyaltyProgram;
use App\Models\TaGtoaLoyaltyReward;
use App\Models\TaGtoaLoyaltyTransaction;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * TAGTOA LOYALTY — logique métier des cartes de fidélité.
 *
 * - Génération de numéros 16 chiffres Luhn-valides (préfixe TAGTOA 4297)
 * - Émission de cartes (numéro chiffré + CVC hashé)
 * - Recharge (top_up) / utilisation (redeem) atomiques avec historique
 */
class LoyaltyCardService
{
    private const PREFIX = '4297';   // préfixe TAGTOA (BIN fictif, usage interne)

    /* ------------------------------------------------------------------ Numbers */

    /** Génère un numéro de carte 16 chiffres unique et Luhn-valide. */
    public function generateCardNumber(): string
    {
        do {
            // 15 chiffres (préfixe + aléatoire) puis chiffre de contrôle Luhn = 16.
            $body  = self::PREFIX . str_pad((string) random_int(0, 99999999999), 11, '0', STR_PAD_LEFT);
            $card  = $body . $this->luhnCheckDigit($body);
        } while (TaGtoaLoyaltyCard::where('card_number', $card)->exists());

        return $card;
    }

    public function generateCvc(): string
    {
        return str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    /** Chiffre de contrôle Luhn pour un numéro partiel. */
    public function luhnCheckDigit(string $partial): int
    {
        $sum    = 0;
        $double = true; // le chiffre de contrôle sera en position paire depuis la droite
        for ($i = strlen($partial) - 1; $i >= 0; $i--) {
            $d = (int) $partial[$i];
            if ($double) {
                $d *= 2;
                if ($d > 9) {
                    $d -= 9;
                }
            }
            $sum   += $d;
            $double = ! $double;
        }
        return (10 - ($sum % 10)) % 10;
    }

    public function isValidLuhn(string $number): bool
    {
        if (! ctype_digit($number)) {
            return false;
        }
        $sum    = 0;
        $double = false;
        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $d = (int) $number[$i];
            if ($double) {
                $d *= 2;
                if ($d > 9) {
                    $d -= 9;
                }
            }
            $sum   += $d;
            $double = ! $double;
        }
        return $sum % 10 === 0;
    }

    /* ------------------------------------------------------------------ Issue */

    /**
     * Émet une nouvelle carte. Retourne ['card' => TaGtoaLoyaltyCard, 'cvc' => string].
     * Le CVC clair n'est disponible QU'À l'émission (ensuite il n'est plus que hashé).
     */
    public function issueCard(TaGtoaLoyaltyProgram $program, array $data): array
    {
        $number = $this->generateCardNumber();
        $cvc    = $this->generateCvc();

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
            'points'                => $data['points'] ?? 0,
            'status'                => TaGtoaLoyaltyCard::STATUS_ACTIVE,
            'delivery_type'         => $data['delivery_type'] ?? TaGtoaLoyaltyCard::DELIVERY_PICKUP,
            'delivery_address'      => $data['delivery_address'] ?? null,
            'issued_at'             => now(),
        ]);

        return ['card' => $card, 'cvc' => $cvc];
    }

    private function uniqueToken(): string
    {
        do {
            $token = Str::lower(Str::random(24));
        } while (TaGtoaLoyaltyCard::where('public_token', $token)->exists());

        return $token;
    }

    /** Vérifie le CVC saisi contre le hash stocké. */
    public function verifyCvc(TaGtoaLoyaltyCard $card, string $cvc): bool
    {
        return Hash::check($cvc, $card->cvc);
    }

    /* ------------------------------------------------------------------ Money */

    /**
     * Recharge la carte : ajoute du solde et crédite des points selon le barème.
     */
    public function topUp(TaGtoaLoyaltyCard $card, float $amount, array $opts = []): TaGtoaLoyaltyTransaction
    {
        return DB::transaction(function () use ($card, $amount, $opts) {
            $card = TaGtoaLoyaltyCard::lockForUpdate()->findOrFail($card->id);

            $points = $opts['points'] ?? $card->program->pointsForAmount($amount);

            $card->balance += $amount;
            $card->points  += $points;
            $card->save();

            return $card->transactions()->create([
                'type'           => TaGtoaLoyaltyTransaction::TYPE_TOP_UP,
                'amount'         => $amount,
                'points_delta'   => $points,
                'balance_after'  => $card->balance,
                'points_after'   => $card->points,
                'payment_method' => $opts['payment_method'] ?? null,
                'reference'      => $opts['reference'] ?? null,
                'note'           => $opts['note'] ?? null,
                'status'         => TaGtoaLoyaltyTransaction::STATUS_CONFIRMED,
            ]);
        });
    }

    /**
     * Utilise la carte : débite le solde (et/ou les points). Lève une exception si
     * fonds/points insuffisants.
     *
     * @throws \RuntimeException
     */
    public function redeem(TaGtoaLoyaltyCard $card, float $amount, array $opts = []): TaGtoaLoyaltyTransaction
    {
        return DB::transaction(function () use ($card, $amount, $opts) {
            $card = TaGtoaLoyaltyCard::lockForUpdate()->findOrFail($card->id);

            if (! $card->isActive()) {
                throw new \RuntimeException(__('Carte inactive ou expirée.'));
            }

            $pointsDelta = (int) ($opts['points_delta'] ?? 0); // négatif si on dépense des points

            if ($amount > (float) $card->balance + 1e-6) {
                throw new \RuntimeException(__('Solde insuffisant.'));
            }
            if ($card->points + $pointsDelta < 0) {
                throw new \RuntimeException(__('Points insuffisants.'));
            }

            $card->balance -= $amount;
            $card->points  += $pointsDelta;
            $card->save();

            return $card->transactions()->create([
                'type'           => TaGtoaLoyaltyTransaction::TYPE_REDEEM,
                'reward_id'      => $opts['reward_id'] ?? null,
                'amount'         => $amount,
                'points_delta'   => $pointsDelta,
                'balance_after'  => $card->balance,
                'points_after'   => $card->points,
                'payment_method' => $opts['payment_method'] ?? null,
                'reference'      => $opts['reference'] ?? null,
                'note'           => $opts['note'] ?? null,
                'status'         => TaGtoaLoyaltyTransaction::STATUS_CONFIRMED,
            ]);
        });
    }

    /** Échange des points contre une récompense. */
    public function redeemReward(TaGtoaLoyaltyCard $card, TaGtoaLoyaltyReward $reward): TaGtoaLoyaltyTransaction
    {
        if ($card->points < $reward->points_required) {
            throw new \RuntimeException(__('Points insuffisants pour cette récompense.'));
        }

        return $this->redeem($card, 0, [
            'points_delta' => -$reward->points_required,
            'reward_id'    => $reward->id,
            'note'         => __('Récompense : :name', ['name' => $reward->name]),
        ]);
    }
}
