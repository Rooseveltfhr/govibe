<?php

namespace Modules\Tagtoa\App\Models\Card;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Tagtoa\App\Support\Card\CardWallet;
use Modules\Tagtoa\App\Support\Money;

/**
 * TAGTOA CARD — compte d'une carte NFC prépayée closed-loop (valable partout
 * sur TAGTOA). Le solde en clair (balance_minor) est un cache ; la vérité est la
 * somme du grand livre immuable (tagtoa_card_txns).
 */
class CardAccount extends Model
{
    protected $table = 'tagtoa_card_accounts';

    protected $fillable = [
        'tenant_id', 'uid_hash', 'uid_enc', 'code', 'holder_name', 'holder_phone',
        'currency', 'balance_minor', 'pin_hash', 'status', 'official', 'activated_at',
        'issued_at', 'last_used_at',
    ];

    protected $casts = [
        'balance_minor' => 'integer',
        'official'      => 'boolean',
        'activated_at'  => 'datetime',
        'issued_at'     => 'datetime',
        'last_used_at'  => 'datetime',
    ];

    protected $hidden = ['uid_hash', 'uid_enc', 'pin_hash'];

    public function txns(): HasMany
    {
        return $this->hasMany(CardTxn::class, 'card_account_id')->latest();
    }

    public function isSpendable(): bool
    {
        return CardWallet::isSpendable($this->status);
    }

    public function hasPin(): bool
    {
        return ! empty($this->pin_hash);
    }

    /** Solde formaté (ex. « 250 G »). */
    public function getBalanceLabelAttribute(): string
    {
        return Money::formatMinor((int) $this->balance_minor, $this->currency);
    }

    public function getMaskedCodeAttribute(): string
    {
        return $this->code ?: ('#'.$this->id);
    }
}
