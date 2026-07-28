<?php

namespace Modules\Tagtoa\App\Models\Event;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TAGTOA EVENT — écriture du grand livre (IMMUABLE). Pas d'update/delete.
 */
class WalletEntry extends Model
{
    protected $table = 'tagtoa_ev_wallet_entries';

    public $timestamps = false;

    public const DEBIT = 'debit';
    public const CREDIT = 'credit';

    protected $fillable = [
        'txn_id', 'account_id', 'direction', 'amount_minor', 'balance_after', 'created_at',
    ];

    protected $casts = [
        'amount_minor'  => 'integer',
        'balance_after' => 'integer',
        'created_at'    => 'datetime',
    ];

    public function txn(): BelongsTo
    {
        return $this->belongsTo(WalletTxn::class, 'txn_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(WalletAccount::class, 'account_id');
    }
}
