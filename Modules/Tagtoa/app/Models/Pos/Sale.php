<?php

namespace Modules\Tagtoa\App\Models\Pos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * TAGTOA POS — vente (paiement simple ou split).
 */
class Sale extends Model
{
    /** Méthodes de paiement supportées. */
    public const METHODS = [
        'cash' => 'Cash', 'moncash' => 'MonCash', 'natcash' => 'NatCash', 'zelle' => 'Zelle',
        'paypal' => 'PayPal', 'card' => 'Carte (VISA/Mastercard)', 'unibank' => 'Virement Unibank',
        'sogebank' => 'Virement Sogebank', 'usdt' => 'USDT', 'bitcoin' => 'Bitcoin', 'loyalty' => 'Loyalty NFC',
    ];

    protected $table = 'tagtoa_pos_sales';

    protected $fillable = [
        'terminal_id', 'staff_id', 'reference', 'subtotal', 'discount', 'total', 'currency',
        'payments', 'customer_phone', 'client_uuid', 'status', 'sold_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2', 'discount' => 'decimal:2', 'total' => 'decimal:2',
        'payments' => 'array', 'status' => 'integer', 'sold_at' => 'datetime',
    ];

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class, 'terminal_id');
    }

    /**
     * Caissier qui a encaissé. Facultatif : les ventes d'avant n'en ont pas, et
     * un commerce sans employé vend sous le seul nom du patron.
     *
     * La vente SURVIT au départ de l'employé (nullOnDelete) : une recette
     * appartient au commerce, pas à la personne qui l'a encaissée.
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(\Modules\Tagtoa\App\Models\Staff\Staff::class, 'staff_id');
    }

    /** Nom à afficher dans un rapport, même si l'employé a quitté le commerce. */
    public function getCashierNameAttribute(): string
    {
        return $this->staff?->name ?: __('Patron');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class, 'sale_id');
    }

    public static function generateReference(): string
    {
        do {
            $ref = 'TGP-'.strtoupper(Str::random(6));
        } while (static::where('reference', $ref)->exists());
        return $ref;
    }
}
