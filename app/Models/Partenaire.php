<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Partenaire extends Model
{
    protected $fillable = [
        'prenom', 'nom', 'organisation', 'pays', 'ville',
        'telephone', 'email', 'type_partenariat', 'description',
        'statut', 'notes_admin',
        'logo', 'site_web', 'affiche_public', 'ordre',
    ];

    protected $casts = [
        'affiche_public' => 'boolean',
        'ordre'          => 'integer',
    ];

    public function getNomCompletAttribute(): string
    {
        return $this->prenom . ' ' . $this->nom;
    }

    /**
     * Nom affiché sur la vitrine : celui de l'organisation quand il existe,
     * sinon celui de la personne.
     */
    public function getNomVitrineAttribute(): string
    {
        return $this->organisation ?: $this->nom_complet;
    }

    /**
     * URL publique du logo, ou null si aucun logo n'a été téléversé.
     */
    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? Storage::disk('public')->url($this->logo) : null;
    }

    public function getTypeLibelleAttribute(): string
    {
        return self::typesPartenariat()[$this->type_partenariat] ?? $this->type_partenariat;
    }

    /**
     * Les partenaires affichés sur /partenaires : explicitement publiés,
     * triés par ordre puis par nom d'organisation.
     */
    public function scopeVitrine(Builder $query): Builder
    {
        return $query->where('affiche_public', true)
                     ->orderBy('ordre')
                     ->orderBy('organisation');
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
