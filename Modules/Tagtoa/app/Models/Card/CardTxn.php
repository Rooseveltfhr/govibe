<?php

namespace Modules\Tagtoa\App\Models\Card;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Tagtoa\App\Support\Money;

/**
 * TAGTOA CARD — ligne IMMUABLE du grand livre d'une carte (recharge/débit/remb.).
 */
class CardTxn extends Model
{
    protected $table = 'tagtoa_card_txns';

    protected $fillable = [
        'card_account_id', 'tenant_id', 'type', 'amount_minor', 'balance_after_minor',
        'currency', 'reference', 'context_type', 'context_id', 'meta',
    ];

    protected $casts = [
        'amount_minor'        => 'integer',
        'balance_after_minor' => 'integer',
        'meta'                => 'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(CardAccount::class, 'card_account_id');
    }

    public function getAmountLabelAttribute(): string
    {
        $sign = $this->type === 'charge' ? '−' : '+';

        return $sign.' '.Money::formatMinor((int) $this->amount_minor, $this->currency);
    }
}
