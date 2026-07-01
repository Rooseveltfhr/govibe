<?php

namespace Modules\Tagtoa\App\Actions\Event\Wallet;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\Tagtoa\App\Exceptions\InsufficientFundsException;
use Modules\Tagtoa\App\Models\Event\WalletAccount;
use Modules\Tagtoa\App\Models\Event\WalletEntry;
use Modules\Tagtoa\App\Models\Event\WalletTxn;
use Modules\Tagtoa\App\Services\Audit\AuditService;
use Modules\Tagtoa\App\Support\Event\Ledger;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA EVENT — poste une transaction wallet atomique en partie double.
 *
 * $source est DÉBITÉE, $dest est CRÉDITÉE. Garanties :
 *  - atomicité (DB::transaction),
 *  - verrou pessimiste (lockForUpdate, ordre d'id stable = pas de deadlock),
 *  - vérification de solde SOUS le verrou (anti double-débit concurrent),
 *  - idempotence via idempotency_key (anti-doublon réseau),
 *  - écritures immuables + mise à jour des soldes cachés.
 */
class PostLedgerTransaction
{
    public function handle(string $type, WalletAccount $source, WalletAccount $dest, int $amount, array $opts = []): WalletTxn
    {
        $idem = $opts['idempotency_key'] ?? null;
        if ($idem && $existing = WalletTxn::where('idempotency_key', $idem)->first()) {
            return $existing;
        }
        if ($source->id === $dest->id) {
            throw new \InvalidArgumentException('same_account');
        }
        if ($source->currency !== $dest->currency) {
            throw new \InvalidArgumentException('currency_mismatch');
        }
        if ($amount <= 0) {
            throw new \InvalidArgumentException('amount_must_be_positive');
        }

        try {
            return DB::transaction(function () use ($type, $source, $dest, $amount, $opts, $idem) {
                // Verrou pessimiste sur les deux comptes, ordre d'id croissant (anti-deadlock).
                $locked = WalletAccount::whereIn('id', [$source->id, $dest->id])
                    ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $src = $locked->get($source->id);
                $dst = $locked->get($dest->id);

                // Vérification de fonds SOUS le verrou (comptes "valeur" uniquement).
                if (! Ledger::sufficientFunds($type, (int) $src->balance_minor, $amount)) {
                    throw new InsufficientFundsException();
                }

                $pair = Ledger::buildPair($type, (int) $src->balance_minor, (int) $dst->balance_minor, $amount);

                $txn = WalletTxn::create([
                    'tenant_id'         => $src->tenant_id ?? Tenant::id(),
                    'event_id'          => $src->event_id ?? $dst->event_id,
                    'type'              => $type,
                    'reference'         => WalletTxn::generateReference(),
                    'idempotency_key'   => $idem,
                    'amount_minor'      => $amount,
                    'currency'          => $src->currency,
                    'status'            => 'posted',
                    'source_account_id' => $src->id,
                    'dest_account_id'   => $dst->id,
                    'payment_ref'       => $opts['payment_ref'] ?? null,
                    'meta'              => $opts['meta'] ?? null,
                    'created_by'        => $opts['created_by'] ?? optional(Tenant::user())->id,
                    'created_at'        => now(),
                ]);

                WalletEntry::create([
                    'txn_id'        => $txn->id,
                    'account_id'    => $src->id,
                    'direction'     => Ledger::DEBIT,
                    'amount_minor'  => $amount,
                    'balance_after' => $pair['debit']['balance_after'],
                    'created_at'    => now(),
                ]);
                WalletEntry::create([
                    'txn_id'        => $txn->id,
                    'account_id'    => $dst->id,
                    'direction'     => Ledger::CREDIT,
                    'amount_minor'  => $amount,
                    'balance_after' => $pair['credit']['balance_after'],
                    'created_at'    => now(),
                ]);

                // Mise à jour des soldes cachés (vérité = somme des écritures).
                $src->balance_minor = $pair['debit']['balance_after'];
                $dst->balance_minor = $pair['credit']['balance_after'];
                $src->save();
                $dst->save();

                app(AuditService::class)->log('wallet.'.$type, $txn, $txn->reference);

                return $txn;
            });
        } catch (QueryException $e) {
            // Course sur idempotency_key : la transaction concurrente a déjà été posée.
            if ($idem && $existing = WalletTxn::where('idempotency_key', $idem)->first()) {
                return $existing;
            }
            throw $e;
        }
    }
}
