<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvenementPaiement extends Model
{
    protected $table = 'evenements_paiement';

    // Une trace ne se modifie pas.
    public const UPDATED_AT = null;

    protected $fillable = [
        'paiement_id', 'type', 'statut_avant', 'statut_apres',
        'source', 'user_id', 'donnees',
    ];

    protected $casts = ['donnees' => 'array'];

    public function paiement(): BelongsTo
    {
        return $this->belongsTo(Paiement::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
