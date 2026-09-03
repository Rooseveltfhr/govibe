<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvenementReservation extends Model
{
    protected $table = 'evenement_reservations';

    protected $fillable = [
        'evenement_id', 'prenom', 'nom', 'email', 'whatsapp', 'telephone',
        'pays', 'ville', 'commune', 'profession', 'sexe',
        'situation_matrimoniale', 'statut_actuel', 'motivation',
        'presence_confirmee', 'notes_admin',
    ];

    protected $casts = [
        'presence_confirmee' => 'boolean',
    ];

    public function evenement(): BelongsTo
    {
        return $this->belongsTo(Evenement::class, 'evenement_id');
    }

    public function getNomCompletAttribute(): string
    {
        return $this->prenom . ' ' . $this->nom;
    }

    public static function sexes(): array
    {
        return [
            'femme' => 'Femme',
            'homme' => 'Homme',
            'non_precise' => 'Préfère ne pas préciser',
        ];
    }

    public static function situationsMatrimoniales(): array
    {
        return [
            'celibataire' => 'Célibataire',
            'marie'       => 'Marié(e)',
            'union_libre' => 'Union libre',
            'divorce'     => 'Divorcé(e)',
            'veuf'        => 'Veuf(ve)',
        ];
    }

    public static function statutsActuels(): array
    {
        return [
            'employe'        => 'Employé(e)',
            'sans_emploi'    => 'Sans emploi',
            'etudiant'       => 'Étudiant(e)',
            'professionnel'  => 'Professionnel(le) indépendant(e)',
            'entrepreneur'   => 'Entrepreneur(e)',
            'stagiaire'      => 'Stagiaire',
            'retraite'       => 'Retraité(e)',
            'autre'          => 'Autre',
        ];
    }

    public function getStatutLibelleAttribute(): ?string
    {
        return self::statutsActuels()[$this->statut_actuel] ?? $this->statut_actuel;
    }

    public function getSexeLibelleAttribute(): ?string
    {
        return self::sexes()[$this->sexe] ?? $this->sexe;
    }

    public function getSituationLibelleAttribute(): ?string
    {
        return self::situationsMatrimoniales()[$this->situation_matrimoniale] ?? $this->situation_matrimoniale;
    }
}
