<?php

namespace Modules\Tagtoa\App\Services\Pay;

use Illuminate\Database\Eloquent\Model;
use Modules\Tagtoa\App\Models\Pay\PaymentLedgerEntry;

/**
 * TAGTOA PAY — seul point d'entrée qui écrit dans tagtoa_payment_ledger_entries.
 *
 * Volontairement minuscule : une seule méthode, qui ne fait qu'ajouter une
 * ligne. Rien ici ne lit ni ne recalcule un solde — PAY n'en a pas (voir la
 * note de périmètre dans la migration). Ce service protège une seule chose :
 * qu'une transition de statut de paiement laisse TOUJOURS une trace
 * qu'aucun code applicatif ne reviendra modifier.
 */
class PaymentLedger
{
    /**
     * $payable est un PayTransaction OU un ApiPayment — les deux portent
     * tenant_id/amount/currency/reference ; `gateway` n'existe que sur
     * PayTransaction, d'où le `?->`.
     */
    public function record(Model $payable, ?string $fromStatus, string $toStatus, array $meta = []): PaymentLedgerEntry
    {
        return PaymentLedgerEntry::create([
            'tenant_id'   => $payable->tenant_id,
            'payable_type'=> $payable::class,
            'payable_id'  => $payable->getKey(),
            'from_status' => $fromStatus,
            'to_status'   => $toStatus,
            'amount'      => $payable->amount,
            'currency'    => $payable->currency,
            'gateway'     => $payable->gateway ?? null,
            'reference'   => $payable->reference,
            'meta'        => $meta ?: null,
        ]);
    }
}
