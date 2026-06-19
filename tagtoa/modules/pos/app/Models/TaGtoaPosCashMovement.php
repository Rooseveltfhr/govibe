<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TAGTOA POS — mouvement de caisse (fond, entrée, sortie, clôture).
 */
class TaGtoaPosCashMovement extends Model
{
    use HasFactory;

    protected $table = 'tagtoa_pos_cash_movements';

    protected $fillable = ['terminal_id', 'type', 'amount', 'balance_after', 'reason'];

    protected $casts = [
        'amount'        => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(TaGtoaPosTerminal::class, 'terminal_id');
    }
}
