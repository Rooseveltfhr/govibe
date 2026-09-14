<?php

namespace Modules\Tagtoa\App\Models\Shop;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Tagtoa\App\Support\BelongsToTenant;
use Modules\Tagtoa\App\Support\Order\OrderStatus;

/**
 * BOUTIQUE TAGTOA — la commande de matériel d'un marchand.
 *
 * Elle APPARTIENT au commerce (portée automatique) : c'est lui qui a commandé,
 * c'est lui qui doit la retrouver. Le fondateur les voit toutes par une sortie
 * d'isolation nommée, côté super-admin.
 *
 * Le vocabulaire de statut est celui du reste de la plateforme : une seule
 * liste, sinon « expédiée » finit par vouloir dire deux choses différentes
 * selon l'écran qu'on regarde.
 */
class ShopOrder extends Model
{
    use BelongsToTenant;

    protected $table = 'tagtoa_shop_orders';

    protected $fillable = [
        'tenant_id', 'reference', 'status', 'currency',
        'subtotal', 'shipping', 'total',
        'contact_name', 'contact_phone', 'address', 'city', 'note', 'reply',
        'placed_at', 'confirmed_at', 'shipped_at', 'delivered_at',
    ];

    protected $casts = [
        'subtotal'     => 'decimal:2',
        'shipping'     => 'decimal:2',
        'total'        => 'decimal:2',
        'placed_at'    => 'datetime',
        'confirmed_at' => 'datetime',
        'shipped_at'   => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ShopOrderItem::class, 'order_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return OrderStatus::label($this->status);
    }

    /** Une commande close ne bouge plus : ni quantité, ni prix, ni transport. */
    public function isClosed(): bool
    {
        return in_array($this->status, OrderStatus::CLOSED, true);
    }
}
