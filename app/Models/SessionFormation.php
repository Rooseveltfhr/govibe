<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class SessionFormation extends Model
{
    protected $table = 'sessions_formation';

    protected $fillable = [
        'slug', 'titre', 'sous_titre', 'description', 'modules',
        'prix', 'devise', 'modes', 'lieu_presentiel', 'precision_online',
        'date_texte', 'date_debut', 'heure_texte',
        'places_limitees', 'max_participants',
        'whatsapp_contact', 'flyer', 'couleur',
        'inscriptions_ouvertes', 'actif', 'ordre',
    ];

    protected $casts = [
        'modules' => 'array',
        'modes' => 'array',
        'prix' => 'decimal:2',
        'date_debut' => 'date',
        'places_limitees' => 'boolean',
        'inscriptions_ouvertes' => 'boolean',
        'actif' => 'boolean',
        'ordre' => 'integer',
    ];

    public function inscriptions(): HasMany
    {
        return $this->hasMany(InscriptionSession::class, 'session_formation_id');
    }

    public function scopeActif(Builder $query): Builder
    {
        return $query->where('actif', true)->orderBy('ordre')->orderBy('titre');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** Les modes proposés, libellés pour l'affichage. */
    public static function modesDisponibles(): array
    {
        return [
            'presentiel' => 'Présentiel',
            'online' => 'En ligne',
        ];
    }

    /** @return array<string,string> */
    public function getModesLisiblesAttribute(): array
    {
        $tous = self::modesDisponibles();

        return array_intersect_key($tous, array_flip($this->modes ?? array_keys($tous)));
    }

    public function getPrixAfficheAttribute(): string
    {
        $v = (float) $this->prix;

        return (floor($v) == $v
            ? number_format($v, 0, ',', ' ')
            : number_format($v, 2, ',', ' ')).' '.$this->devise;
    }

    /** Nuance foncée dérivée de la couleur, pour les dégradés. */
    public function getCouleurFonceeAttribute(): string
    {
        $hex = ltrim($this->couleur ?: '#DC2626', '#');
        if (strlen($hex) !== 6) {
            return '#991b1b';
        }

        $sombre = array_map(
            fn ($p) => str_pad(dechex((int) max(0, hexdec($p) * 0.62)), 2, '0', STR_PAD_LEFT),
            str_split($hex, 2)
        );

        return '#'.implode('', $sombre);
    }

    public function getFlyerUrlAttribute(): ?string
    {
        if (! $this->flyer) {
            return null;
        }

        return Storage::disk('public')->exists($this->flyer)
            ? Storage::disk('public')->url($this->flyer)
            : asset($this->flyer);
    }

    /**
     * Une session fermée reste atteignable par son lien direct — une publicité
     * en cours ne doit pas tomber sur une page morte — mais n'accepte plus
     * d'inscription.
     */
    public function getAccepteInscriptionsAttribute(): bool
    {
        if (! $this->inscriptions_ouvertes) {
            return false;
        }

        return ! $this->max_participants
            || $this->inscriptions()->where('statut', '!=', 'rejetee')->count() < $this->max_participants;
    }
}
