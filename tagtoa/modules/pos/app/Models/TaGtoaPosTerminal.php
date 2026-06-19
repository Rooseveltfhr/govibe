<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * TAGTOA POS — caisse / terminal.
 */
class TaGtoaPosTerminal extends Model
{
    use HasFactory;
    use BelongsToTenant;

    protected $table = 'tagtoa_pos_terminals';

    protected $fillable = ['vcard_id', 'tenant_id', 'name', 'currency', 'is_active', 'cash_balance'];

    protected $casts = [
        'is_active'    => 'boolean',
        'cash_balance' => 'decimal:2',
    ];

    public function vcard(): BelongsTo
    {
        return $this->belongsTo(Vcard::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(TaGtoaPosProduct::class, 'terminal_id')->orderBy('sort');
    }

    public function activeProducts(): HasMany
    {
        return $this->products()->where('is_active', true);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(TaGtoaPosSale::class, 'terminal_id');
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(TaGtoaPosCashMovement::class, 'terminal_id');
    }
}
