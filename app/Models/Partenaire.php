<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Partenaire extends Model
{
    protected $fillable = [
        'prenom', 'nom', 'organisation', 'pays', 'ville',
        'telephone', 'email', 'type_partenariat', 'description',
        'statut', 'notes_admin',
    ];

    public function getNomCompletAttribute(): string
    {
        return $this->prenom . ' ' . $this->nom;
    }

    public static function typesPartenariat(): array
    {
        return [
            'partenaire_strategique'  => 'Partenaire Stratégique',
            'sponsor'                 => 'Sponsor / Commanditaire',
            'partenaire_institutionnel' => 'Partenaire Institutionnel',
            'collaborateur_technique' => 'Collaborateur Technique',
            'mentor_formateur'        => 'Mentor / Formateur',
            'investisseur'            => 'Investisseur',
            'media_presse'            => 'Média / Presse',
            'autre'                   => 'Autre',
        ];
    }
}
