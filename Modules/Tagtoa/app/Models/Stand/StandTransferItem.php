<?php

namespace Modules\Tagtoa\App\Models\Stand;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TAGTOA — un stand dans une offre de cession.
 *
 * Une table de liaison, et rien d'autre : ni montant, ni état. L'état du stand
 * vit sur le stand (`transfer_pending`), à un seul endroit. Le recopier ici
 * créerait deux vérités qui finiraient par diverger, et c'est alors la
 * mauvaise qu'on lirait.
 */
class StandTransferItem extends Model
{
    protected $table = 'tagtoa_stand_transfer_items';

    protected $fillable = ['transfer_id', 'stand_id'];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StandTransfer::class, 'transfer_id');
    }

    public function stand(): BelongsTo
    {
        return $this->belongsTo(Stand::class, 'stand_id');
    }
}
