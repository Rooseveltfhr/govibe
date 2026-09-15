<?php

namespace Modules\Tagtoa\App\Models\Stand;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TAGTOA — une ligne de l'histoire d'un stand. Écrite une fois, jamais modifiée.
 *
 * C'est ce qui permet de trancher un litige : qui a réclamé, depuis quelle
 * adresse, à quelle heure. Réécrire une ligne effacerait précisément la trace
 * qu'on cherche.
 */
class StandEvent extends Model
{
    protected $table = 'tagtoa_stand_events';

    /** Journal : une date de création, aucune date de modification. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'stand_id', 'event', 'from_state', 'to_state',
        'actor_type', 'actor_id', 'actor_name', 'tenant_id',
        'ip', 'user_agent', 'meta',
    ];

    protected $casts = ['meta' => 'array'];

    /* Les événements du cycle de vie. Une chaîne libre finirait par diverger
       entre les écrans qui l'écrivent et ceux qui la lisent. */
    public const MINTED    = 'minted';      // fabriqué par la commande de lot
    public const RECEIVED  = 'received';    // lot reçu en entrepôt
    public const ALLOCATED = 'allocated';   // affecté à un partenaire
    public const SOLD      = 'sold';        // vendu, déclaré par le revendeur
    public const CLAIMED   = 'claimed';     // réclamé par un commerce
    public const RELEASED  = 'released';    // rendu à l'état non réclamé
    public const TRANSFER  = 'transferred'; // cédé à un autre commerce
    public const TRANSFER_OFFERED   = 'transfer_offered';   // cession proposée
    public const TRANSFER_CANCELLED = 'transfer_cancelled'; // cession retirée ou expirée
    public const SUSPENDED = 'suspended';
    public const REVOKED   = 'revoked';
    public const LOST      = 'lost';
    public const REPLACED  = 'replaced';

    public function stand(): BelongsTo
    {
        return $this->belongsTo(Stand::class, 'stand_id');
    }
}
