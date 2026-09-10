<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnexionPortail extends Model
{
    protected $table = 'connexions_portail';

    // Une trace ne se modifie pas : seule la date de création a un sens.
    public const UPDATED_AT = null;

    protected $fillable = [
        'compte_portail_id', 'email', 'reussie', 'motif', 'ip', 'agent',
    ];

    protected $casts = ['reussie' => 'boolean'];

    public function compte(): BelongsTo
    {
        return $this->belongsTo(ComptePortail::class, 'compte_portail_id');
    }

    /** Motifs d'échec, distingués pour que le support sache quoi répondre. */
    public static function motifs(): array
    {
        return [
            'inconnu' => 'Compte inexistant',
            'mot_de_passe' => 'Mot de passe incorrect',
            'desactive' => 'Compte désactivé',
            'trop_essais' => 'Trop de tentatives',
        ];
    }
}
