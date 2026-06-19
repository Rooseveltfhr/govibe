<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * TAGTOA POS — vente (paiement simple ou split).
 */
class TaGtoaPosSale extends Model
{
    use HasFactory;

    public const STATUS_PENDING   = 0;
    public const STATUS_COMPLETED = 1;
    public const STATUS_REFUNDED  = 2;
    public const STATUS_VOID      = 3;

    /** Méthodes de paiement supportées (CLAUDE.md §16). */
    public const PAYMENT_METHODS = [
        'cash'      => 'Cash',
        'moncash'   => 'MonCash',
        'natcash'   => 'NatCash',
        'zelle'     => 'Zelle',
        'paypal'    => 'PayPal',
        'card'      => 'Carte (VISA/Mastercard)',
        'unibank'   => 'Virement Unibank',
        'sogebank'  => 'Virement Sogebank',
        'cod'       => 'Cash on Delivery',
        'usdt'      => 'USDT',
        'bitcoin'   => 'Bitcoin',
        'loyalty'   => 'Loyalty NFC',
    ];

    protected $table = 'tagtoa_pos_sales';

    protected $fillable = [
        'terminal_id', 'reference', 'subtotal', 'discount', 'total', 'currency',
        'payments', 'customer_phone', 'client_uuid', 'status', 'sold_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'total'    => 'decimal:2',
        'payments' => 'array',
        'status'   => 'integer',
        'sold_at'  => 'datetime',
    ];

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(TaGtoaPosTerminal::class, 'terminal_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TaGtoaPosSaleItem::class, 'sale_id');
    }

    public static function generateReference(): string
    {
        do {
            $ref = 'TGP-' . strtoupper(Str::random(6));
        } while (static::where('reference', $ref)->exists());
        return $ref;
    }
}
