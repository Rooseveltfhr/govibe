<?php

namespace Modules\Tagtoa\App\Services\Card;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Tagtoa\App\Models\Card\CardAccount;
use Modules\Tagtoa\App\Models\Card\CardTxn;
use Modules\Tagtoa\App\Services\Billing\RevenueService;
use Modules\Tagtoa\App\Support\Card\CardWallet;
use Modules\Tagtoa\App\Support\Money;

/**
 * TAGTOA CARD — service de la carte closed-loop plateforme.
 *
 * Débit/recharge ATOMIQUES (lockForUpdate) + IDEMPOTENTS (une référence ne
 * s'applique qu'une fois) + grand livre immuable. Une carte débitée chez un
 * marchand crée une commission plateforme (RevenueService).
 */
class CardWalletService
{
    /** Résout un UID NFC (tap) → compte carte, ou null si inconnu. */
    public function resolveByUid(string $uid): ?CardAccount
    {
        $uid = trim($uid);
        if ($uid === '') {
            return null;
        }

        return CardAccount::where('uid_hash', CardWallet::hashUid($uid))->first();
    }

    /** Résout par code imprimé (repli quand le NFC n'est pas lisible). */
    public function resolveByCode(string $code): ?CardAccount
    {
        $code = strtoupper(trim($code));

        return $code === '' ? null : CardAccount::where('code', $code)->first();
    }

    /**
     * Émet (ou récupère) une carte pour un UID. Idempotent sur l'UID.
     *
     * @param  array{tenant_id?:?string,holder_name?:?string,holder_phone?:?string,currency?:string,pin?:?string,code?:?string}  $opts
     */
    public function issue(string $uid, array $opts = []): CardAccount
    {
        $currency = strtoupper($opts['currency'] ?? 'HTG');
        $pin = $opts['pin'] ?? null;

        return CardAccount::firstOrCreate(
            ['uid_hash' => CardWallet::hashUid($uid)],
            [
                'tenant_id'     => $opts['tenant_id'] ?? null,
                'uid_enc'       => Crypt::encryptString(CardWallet::normalizeUid($uid)),
                'code'          => $opts['code'] ?? $this->generateCode(),
                'holder_name'   => $opts['holder_name'] ?? null,
                'holder_phone'  => $opts['holder_phone'] ?? null,
                'currency'      => $currency,
                'balance_minor' => 0,
                'pin_hash'      => (CardWallet::isValidPinFormat($pin) && $pin) ? Hash::make($pin) : null,
                'status'        => CardWallet::STATUS_ACTIVE,
                'issued_at'     => now(),
            ]
        );
    }

    /**
     * Recharge une carte (cash chez un marchand, ou en ligne). Atomique + idempotent.
     * Retourne la transaction, ou null si montant invalide.
     */
    public function topUp(CardAccount $card, float $amount, array $ctx = []): ?CardTxn
    {
        $minor = Money::toMinor($amount, $card->currency);
        if ($minor <= 0) {
            return null;
        }
        $reference = $ctx['reference'] ?? CardWallet::reference();

        return DB::transaction(function () use ($card, $minor, $reference, $ctx) {
            if ($existing = CardTxn::where('reference', $reference)->first()) {
                return $existing; // idempotence
            }
            /** @var CardAccount $locked */
            $locked = CardAccount::whereKey($card->id)->lockForUpdate()->first();
            $newBalance = CardWallet::applyTopup((int) $locked->balance_minor, $minor);
            $locked->update(['balance_minor' => $newBalance, 'last_used_at' => now()]);

            return CardTxn::create([
                'card_account_id'     => $locked->id,
                'tenant_id'           => $ctx['tenant_id'] ?? $locked->tenant_id,
                'type'                => CardWallet::TYPE_TOPUP,
                'amount_minor'        => $minor,
                'balance_after_minor' => $newBalance,
                'currency'            => $locked->currency,
                'reference'           => $reference,
                'context_type'        => $ctx['context_type'] ?? null,
                'context_id'          => $ctx['context_id'] ?? null,
                'meta'                => $ctx['meta'] ?? null,
            ]);
        });
    }

    /**
     * Débite une carte pour un paiement chez un marchand. Vérifie statut + PIN +
     * solde. Atomique + idempotent. Enregistre la commission plateforme.
     *
     * @return array{ok:bool,code:string,message:string,txn:?CardTxn,card:CardAccount}
     */
    public function charge(CardAccount $card, float $amount, ?string $pin = null, array $ctx = []): array
    {
        $fail = fn (string $code, string $msg) => ['ok' => false, 'code' => $code, 'message' => $msg, 'txn' => null, 'card' => $card];

        if (! $card->isSpendable()) {
            return $fail('blocked', __('Carte inactive (bloquée ou perdue).'));
        }
        if ($card->hasPin() && ! Hash::check((string) $pin, (string) $card->pin_hash)) {
            return $fail('pin', __('Code PIN incorrect.'));
        }

        $minor = Money::toMinor($amount, $card->currency);
        if ($minor <= 0) {
            return $fail('amount', __('Montant invalide.'));
        }
        $reference = $ctx['reference'] ?? CardWallet::reference();

        $result = DB::transaction(function () use ($card, $minor, $reference, $ctx, $fail) {
            if ($existing = CardTxn::where('reference', $reference)->first()) {
                return ['ok' => true, 'code' => 'ok', 'message' => __('Paiement déjà appliqué.'), 'txn' => $existing, 'card' => $card->fresh()];
            }
            /** @var CardAccount $locked */
            $locked = CardAccount::whereKey($card->id)->lockForUpdate()->first();

            if (! CardWallet::canCharge((int) $locked->balance_minor, $minor)) {
                return $fail('insufficient', __('Solde insuffisant.'));
            }

            $newBalance = CardWallet::applyCharge((int) $locked->balance_minor, $minor);
            $locked->update(['balance_minor' => $newBalance, 'last_used_at' => now()]);

            $txn = CardTxn::create([
                'card_account_id'     => $locked->id,
                'tenant_id'           => $ctx['tenant_id'] ?? null,
                'type'                => CardWallet::TYPE_CHARGE,
                'amount_minor'        => $minor,
                'balance_after_minor' => $newBalance,
                'currency'            => $locked->currency,
                'reference'           => $reference,
                'context_type'        => $ctx['context_type'] ?? null,
                'context_id'          => $ctx['context_id'] ?? null,
                'meta'                => $ctx['meta'] ?? null,
            ]);

            return ['ok' => true, 'code' => 'ok', 'message' => __('Paiement accepté.'), 'txn' => $txn, 'card' => $locked->fresh()];
        });

        // Commission plateforme sur le débit (marchand encaisseur). On la saute
        // quand la carte règle une vente d'un autre module qui enregistre déjà sa
        // propre commission (ex. billet event) — pour ne pas compter deux fois.
        if (empty($ctx['skip_commission']) && $result['ok'] && $result['txn'] && $result['txn']->wasRecentlyCreated) {
            app(RevenueService::class)->record(
                'card_charge',
                (int) $result['txn']->id,
                $ctx['module'] ?? 'pay',
                (float) Money::fromMinor($minor, $card->currency),
                $ctx['tenant_id'] ?? null,
                $card->currency
            );
        }

        return $result;
    }

    /** Rembourse un débit sur la carte (recrédit). Atomique + idempotent. */
    public function refund(CardAccount $card, float $amount, array $ctx = []): ?CardTxn
    {
        $minor = Money::toMinor($amount, $card->currency);
        if ($minor <= 0) {
            return null;
        }
        $reference = $ctx['reference'] ?? CardWallet::reference();

        return DB::transaction(function () use ($card, $minor, $reference, $ctx) {
            if ($existing = CardTxn::where('reference', $reference)->first()) {
                return $existing;
            }
            /** @var CardAccount $locked */
            $locked = CardAccount::whereKey($card->id)->lockForUpdate()->first();
            $newBalance = CardWallet::applyTopup((int) $locked->balance_minor, $minor);
            $locked->update(['balance_minor' => $newBalance]);

            return CardTxn::create([
                'card_account_id'     => $locked->id,
                'tenant_id'           => $ctx['tenant_id'] ?? $locked->tenant_id,
                'type'                => CardWallet::TYPE_REFUND,
                'amount_minor'        => $minor,
                'balance_after_minor' => $newBalance,
                'currency'            => $locked->currency,
                'reference'           => $reference,
                'context_type'        => $ctx['context_type'] ?? null,
                'context_id'          => $ctx['context_id'] ?? null,
                'meta'                => $ctx['meta'] ?? null,
            ]);
        });
    }

    /** Change le statut d'une carte (active|blocked|lost). */
    public function setStatus(CardAccount $card, string $status): bool
    {
        if (! in_array($status, CardWallet::STATUSES, true)) {
            return false;
        }

        return $card->update(['status' => $status]);
    }

    /** Définit/retire le PIN d'une carte. */
    public function setPin(CardAccount $card, ?string $pin): bool
    {
        if (! CardWallet::isValidPinFormat($pin)) {
            return false;
        }

        return $card->update(['pin_hash' => $pin ? Hash::make($pin) : null]);
    }

    /** Code lisible unique imprimé sur la carte. */
    protected function generateCode(): string
    {
        do {
            $code = 'TAG'.strtoupper(bin2hex(random_bytes(3)));
        } while (CardAccount::where('code', $code)->exists());

        return $code;
    }
}
