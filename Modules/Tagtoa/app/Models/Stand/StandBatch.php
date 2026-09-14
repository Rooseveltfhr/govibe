<?php

namespace Modules\Tagtoa\App\Models\Stand;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TAGTOA — un lot de production.
 *
 * Pas de cloisonnement par commerce : un lot appartient à TAGTOA, avant
 * qu'aucun marchand n'existe pour lui.
 */
class StandBatch extends Model
{
    protected $table = 'tagtoa_stand_batches';

    protected $fillable = [
        'code', 'quantity', 'id_prefix', 'range_start', 'range_end',
        'manufacturer', 'hardware', 'manifest_sha256',
        'produced_at', 'received_at', 'recalled_at',
    ];

    protected $casts = [
        'produced_at' => 'datetime',
        'received_at' => 'datetime',
        'recalled_at' => 'datetime',
    ];

    public function stands(): HasMany
    {
        return $this->hasMany(Stand::class, 'batch_id');
    }

    /**
     * Lot rappelé : défaut d'impression, secrets compromis.
     *
     * Aucun stand du lot n'est plus réclamable — sans avoir à toucher dix mille
     * lignes une par une.
     */
    public function isRecalled(): bool
    {
        return $this->recalled_at !== null;
    }
}
