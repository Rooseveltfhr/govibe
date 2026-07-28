<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosTransaction extends Model
{
    use HasFactory;

    protected $table = 'pos_transactions';

    protected $fillable = [
        'reference',
        'session_id',
        'client_id',
        'subtotal',
        'tax',
        'discount',
        'total',
        'status',
        'served_by',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'tax' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(PosSession::class, 'session_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function servedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'served_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PosTransactionItem::class, 'transaction_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PosPayment::class, 'transaction_id');
    }
}
