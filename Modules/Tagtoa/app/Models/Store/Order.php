<?php

namespace Modules\Tagtoa\App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * TAGTOA STORE — commande boutique.
 */
class Order extends Model
{
    protected $table = 'tagtoa_store_orders';

    public const STATUSES = ['pending', 'confirmed', 'shipped', 'completed', 'cancelled'];

    public const STATUS_META = [
        'pending'   => ['label' => 'En attente', 'pill' => 'a'],
        'confirmed' => ['label' => 'Confirmée',  'pill' => 'g'],
        'shipped'   => ['label' => 'Expédiée',   'pill' => 'a'],
        'completed' => ['label' => 'Terminée',   'pill' => 'g'],
        'cancelled' => ['label' => 'Annulée',    'pill' => 'r'],
    ];

    protected $fillable = [
        'store_id', 'tenant_id', 'reference', 'subtotal', 'total', 'currency',
        'status', 'payment_status', 'channel', 'customer_name', 'customer_phone',
        'customer_address', 'note', 'client_uuid', 'placed_at',
    ];

    protected $casts = [
        'subtotal'  => 'decimal:2',
        'total'     => 'decimal:2',
        'placed_at' => 'datetime',
    ];

    public static function generateReference(): string
    {
        return 'BTK-'.strtoupper(Str::random(8));
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function getStatusMetaAttribute(): array
    {
        return self::STATUS_META[$this->status] ?? ['label' => ucfirst($this->status), 'pill' => 'n'];
    }
}
