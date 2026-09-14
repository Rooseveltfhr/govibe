<?php

namespace Modules\Tagtoa\App\Models\Order;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Tagtoa\App\Support\BelongsToTenant;

/**
 * TAGTOA — un client du commerce. Toujours optionnel.
 *
 * Une commande sans fiche est parfaitement normale : c'est même le cas le plus
 * fréquent au comptoir. La fiche sert quand le client la veut — un habitué,
 * une entreprise qui exige une facture à son NIF, une livraison à une adresse.
 */
class Customer extends Model
{
    use BelongsToTenant;

    protected $table = 'tagtoa_customers';

    protected $fillable = [
        'tenant_id', 'name', 'phone', 'phone_key', 'email', 'address',
        'tax_number', 'notes', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    /**
     * Comment l'appeler à l'écran.
     *
     * Un client peut n'avoir laissé qu'un téléphone : afficher une ligne vide
     * serait pire que d'afficher le numéro.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name ?: ($this->phone ?: __('Client sans nom'));
    }
}
