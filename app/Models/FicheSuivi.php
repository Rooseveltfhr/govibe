<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FicheSuivi extends Model
{
    protected $table = 'fiche_suivis';

    protected $fillable = [
        'fiche_technique_id', 'agent', 'type', 'message', 'reponse_prospect', 'statut_apres',
    ];

    public function fiche(): BelongsTo
    {
        return $this->belongsTo(FicheTechnique::class, 'fiche_technique_id');
    }

    public static function types(): array
    {
        return [
            'note'      => 'Note interne',
            'appel'     => 'Appel téléphonique',
            'whatsapp'  => 'WhatsApp',
            'email'     => 'Email',
            'visite'    => 'Visite sur place',
            'rdv'       => 'Rendez-vous',
            'demo'      => 'Démonstration',
            'proposition' => 'Proposition envoyée',
        ];
    }

    public function getTypeLibelleAttribute(): string
    {
        return self::types()[$this->type] ?? $this->type;
    }

    /**
     * Une note interne ne vaut pas un échange avec le prospect : seule la
     * seconde fait avancer le dossier, et la liste les distingue.
     */
    public function getEstEchangeAttribute(): bool
    {
        return $this->type !== 'note';
    }
}
