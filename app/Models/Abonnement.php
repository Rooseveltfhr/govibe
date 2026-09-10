<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Abonnement extends Model
{
    use HasUuids;

    protected $fillable = [
        'uuid', 'reference', 'client_id', 'plan_id', 'service', 'plan_nom',
        'statut', 'cycle', 'prix_unitaire', 'devise', 'tca_taux', 'taux_change_htg',
        'date_debut', 'essai_fin', 'periode_debut', 'periode_fin', 'date_prochaine_facture',
        'renouvellement_auto', 'resilie_le', 'resiliation_effective_le',
        'motif_resiliation', 'notes_internes',
    ];

    protected $casts = [
        'prix_unitaire' => 'decimal:2',
        'tca_taux' => 'decimal:2',
        'taux_change_htg' => 'decimal:6',
        'date_debut' => 'date',
        'essai_fin' => 'date',
        'periode_debut' => 'date',
        'periode_fin' => 'date',
        'date_prochaine_facture' => 'date',
        'renouvellement_auto' => 'boolean',
        'resilie_le' => 'datetime',
        'resiliation_effective_le' => 'date',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function factures(): HasMany
    {
        return $this->hasMany(Invoice::class)->latest('issued_date');
    }

    public function evenements(): HasMany
    {
        return $this->hasMany(EvenementAbonnement::class)->latest();
    }

    public static function genererReference(): string
    {
        do {
            $reference = 'AB-'.now()->format('Ymd').'-'.strtoupper(Str::random(4));
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }

    /** Le parcours d'un abonnement, de l'essai à la résiliation. */
    public static function statuts(): array
    {
        return [
            'essai' => "Période d'essai",
            'actif' => 'Actif',
            'en_retard' => 'Paiement en retard',
            'suspendu' => 'Suspendu',
            'resilie' => 'Résilié',
            'expire' => 'Expiré',
        ];
    }

    public function getStatutLibelleAttribute(): string
    {
        return self::statuts()[$this->statut] ?? $this->statut;
    }

    public function getCycleLibelleAttribute(): string
    {
        return Plan::cycles()[$this->cycle] ?? $this->cycle;
    }

    public function getServiceLibelleAttribute(): string
    {
        return Plan::services()[$this->service] ?? $this->service;
    }

    /** Un abonnement qui rend encore le service, même impayé. */
    public function estEnService(): bool
    {
        return in_array($this->statut, ['essai', 'actif', 'en_retard'], true);
    }

    public function getMontantHtAttribute(): float
    {
        return (float) $this->prix_unitaire;
    }

    public function getMontantTaxeAttribute(): float
    {
        return round($this->montant_ht * ((float) $this->tca_taux / 100), 2);
    }

    public function getMontantTtcAttribute(): float
    {
        return round($this->montant_ht + $this->montant_taxe, 2);
    }

    /** Fin de la période qui commence à la date donnée. */
    public function finDePeriode(Carbon $debut): Carbon
    {
        return $this->cycle === 'annuel'
            ? $debut->copy()->addYear()->subDay()
            : $debut->copy()->addMonth()->subDay();
    }

    public function scopeAfacturer(Builder $q): Builder
    {
        return $q->whereIn('statut', ['essai', 'actif', 'en_retard'])
            ->where('renouvellement_auto', true)
            ->whereNotNull('date_prochaine_facture')
            ->whereDate('date_prochaine_facture', '<=', now());
    }

    public function scopeEnService(Builder $q): Builder
    {
        return $q->whereIn('statut', ['essai', 'actif', 'en_retard']);
    }
}
