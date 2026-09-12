<?php

namespace Modules\Tagtoa\App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Tagtoa\App\Support\BelongsToTenant;

/**
 * TAGTOA — un fournisseur du commerce.
 *
 * Cloisonné par commerce comme tout le reste : l'annuaire de la boulangerie
 * n'est pas celui du bar, même si c'est le même patron.
 */
class Supplier extends Model
{
    use BelongsToTenant;

    protected $table = 'tagtoa_suppliers';

    protected $fillable = [
        'tenant_id', 'name', 'contact_name', 'phone', 'whatsapp',
        'email', 'address', 'notes', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'supplier_id');
    }

    /** De quoi l'appeler : WhatsApp d'abord, c'est ce qui sert en Haïti. */
    public function getReachAttribute(): ?string
    {
        return $this->whatsapp ?: ($this->phone ?: null);
    }
}
