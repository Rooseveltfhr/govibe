<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvenementAbonnement extends Model
{
    protected $table = 'evenements_abonnement';

    public const UPDATED_AT = null;

    protected $fillable = [
        'abonnement_id', 'type', 'statut_avant', 'statut_apres',
        'source', 'user_id', 'donnees',
    ];

    protected $casts = ['donnees' => 'array'];

    public function abonnement(): BelongsTo
    {
        return $this->belongsTo(Abonnement::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
