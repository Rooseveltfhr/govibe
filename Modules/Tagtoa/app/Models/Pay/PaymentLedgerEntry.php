<?php

namespace Modules\Tagtoa\App\Models\Pay;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Tagtoa\App\Support\BelongsToTenant;

/**
 * TAGTOA PAY — une ligne de grand livre, jamais modifiée après sa création.
 *
 * Aucune méthode d'écriture au-delà de la création n'est exposée ici : pas
 * d'update()/delete() applicatif attendu. Voir PaymentLedger::record() pour
 * le seul point d'entrée qui écrit dans cette table.
 */
class PaymentLedgerEntry extends Model
{
    use BelongsToTenant;

    protected $table = 'tagtoa_payment_ledger_entries';

    /** Pas d'updated_at : une ligne de grand livre ne se modifie jamais. */
    const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id', 'payable_type', 'payable_id',
        'from_status', 'to_status', 'amount', 'currency', 'gateway',
        'reference', 'meta',
    ];

    protected $casts = [
        'amount'     => 'decimal:2',
        'meta'       => 'array',
        'created_at' => 'datetime',
    ];

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }
}
