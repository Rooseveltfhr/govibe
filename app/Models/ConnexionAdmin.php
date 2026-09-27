<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnexionAdmin extends Model
{
    protected $table = 'connexions_admin';

    // Une trace ne se modifie pas : seule la date de création a un sens.
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'surface', 'email', 'reussie', 'motif', 'ip', 'agent',
    ];

    protected $casts = ['reussie' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Motifs d'échec, distingués pour que le support sache quoi répondre. */
    public static function motifs(): array
    {
        return [
            'inconnu' => 'Compte inexistant',
            'mot_de_passe' => 'Mot de passe incorrect',
            'pas_admin' => 'Compte non administrateur',
            'trop_essais' => 'Trop de tentatives',
        ];
    }
}
