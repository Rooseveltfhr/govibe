<?php

namespace Modules\Tagtoa\App\Models\Order;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Tagtoa\App\Support\BelongsToTenant;
use Modules\Tagtoa\App\Support\Order\Channel;
use Modules\Tagtoa\App\Support\Order\OrderStatus;

/**
 * TAGTOA — une commande, quel que soit le chemin par lequel elle est arrivée.
 *
 * Vue commune sur les quatre modules. Le détail métier reste chez eux : le
 * numéro de table du restaurant, l'adresse de livraison, le billet. Ici vit ce
 * qui est commun — et c'est ce qui permet enfin de dire au marchand ce qu'il a
 * gagné dans la journée, tous canaux confondus.
 */
class Order extends Model
{
    use BelongsToTenant;

    protected $table = 'tagtoa_orders';

    protected $fillable = [
        'tenant_id', 'channel', 'source_type', 'source_id', 'reference',
        'customer_id', 'customer_name', 'customer_phone',
        'subtotal', 'discount', 'tax_base', 'tax_total', 'total', 'currency',
        'status', 'payment_status', 'staff_id', 'placed_at',
    ];

    protected $casts = [
        'subtotal'  => 'decimal:2',
        'discount'  => 'decimal:2',
        'tax_base'  => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total'     => 'decimal:2',
        'placed_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function getChannelLabelAttribute(): string
    {
        return Channel::label($this->channel);
    }

    public function getStatusLabelAttribute(): string
    {
        return OrderStatus::label($this->status);
    }

    public function getPaymentLabelAttribute(): string
    {
        return OrderStatus::paymentLabel($this->payment_status);
    }

    /**
     * Le nom à afficher.
     *
     * « Client de passage » n'est pas un défaut à corriger : c'est la
     * situation normale d'un achat au comptoir, et l'écrire ainsi évite au
     * marchand de croire qu'une information a été perdue.
     */
    public function getWhoAttribute(): string
    {
        return $this->customer_name ?: ($this->customer_phone ?: __('Client de passage'));
    }

    /** Cette commande compte-t-elle dans la recette ? */
    public function countsAsRevenue(): bool
    {
        return OrderStatus::countsAsRevenue($this->status, $this->payment_status);
    }

    /** Commandes encore à traiter. */
    public function scopeOpen($query)
    {
        return $query->whereNotIn('status', OrderStatus::CLOSED);
    }

    /** Commandes qui comptent dans le chiffre d'affaires. */
    public function scopeRevenue($query)
    {
        return $query->whereNotIn('status', [OrderStatus::CANCELLED, OrderStatus::REFUNDED])
            ->whereIn('payment_status', [OrderStatus::PAID, OrderStatus::PARTIAL]);
    }
}
