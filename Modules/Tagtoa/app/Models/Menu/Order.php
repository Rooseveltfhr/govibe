<?php

namespace Modules\Tagtoa\App\Models\Menu;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * TAGTOA MENU — commande client (capturée en base).
 */
class Order extends Model
{
    protected $table = 'tagtoa_menu_orders';

    /** Cycle de vie d'une commande. */
    public const STATUSES = ['pending', 'confirmed', 'preparing', 'ready', 'completed', 'cancelled'];

    public const STATUS_META = [
        'pending'   => ['label' => 'En attente',  'pill' => 'a'],
        'confirmed' => ['label' => 'Confirmée',   'pill' => 'g'],
        'preparing' => ['label' => 'En préparation', 'pill' => 'a'],
        'ready'     => ['label' => 'Prête',       'pill' => 'g'],
        'completed' => ['label' => 'Terminée',    'pill' => 'g'],
        'cancelled' => ['label' => 'Annulée',     'pill' => 'r'],
    ];

    protected $fillable = [
        'menu_id', 'tenant_id', 'reference', 'subtotal', 'total', 'currency',
        'status', 'payment_status', 'channel', 'customer_name', 'customer_phone',
        'table_label', 'note', 'client_uuid', 'placed_at',
    ];

    protected $casts = [
        'subtotal'  => 'decimal:2',
        'total'     => 'decimal:2',
        'placed_at' => 'datetime',
    ];

    public static function generateReference(): string
    {
        return 'CMD-'.strtoupper(Str::random(8));
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'menu_id');
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
