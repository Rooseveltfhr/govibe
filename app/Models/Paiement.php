<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class Paiement extends Model
{
    use HasUuids;

    protected $fillable = [
        'uuid', 'reference', 'client_id', 'payable_type', 'payable_id',
        'passerelle_id', 'mode', 'pilote', 'passerelle_nom',
        'montant', 'devise', 'montant_converti', 'devise_convertie', 'taux_change',
        'statut', 'reference_externe', 'jeton_externe', 'cle_idempotence',
        'echec_motif', 'charge_utile', 'preuve_paiement_id',
        'approuve_par', 'approuve_le', 'paye_le', 'ip',
    ];

    protected $casts = [
        'charge_utile' => 'array',
        'montant' => 'decimal:2',
        'montant_converti' => 'decimal:2',
        'taux_change' => 'decimal:6',
        'approuve_le' => 'datetime',
        'paye_le' => 'datetime',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function passerelle(): BelongsTo
    {
        return $this->belongsTo(PasserellePaiement::class, 'passerelle_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function preuve(): BelongsTo
    {
        return $this->belongsTo(PreuvePaiement::class, 'preuve_paiement_id');
    }

    public function evenements(): HasMany
    {
        return $this->hasMany(EvenementPaiement::class)->latest();
    }

    public static function genererReference(): string
    {
        do {
            $reference = 'PM-'.now()->format('Ymd').'-'.strtoupper(Str::random(4));
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }

    public static function statuts(): array
    {
        return [
            'initie' => 'Initié',
            'en_attente' => 'En attente',
            'verification' => 'À vérifier',
            'reussi' => 'Réussi',
            'echoue' => 'Échoué',
            'expire' => 'Expiré',
            'rembourse' => 'Remboursé',
            'annule' => 'Annulé',
        ];
    }

    public static function modes(): array
    {
        return ['api' => 'Automatique', 'manuel' => 'Manuel', 'cash' => 'Espèces'];
    }

    public function getStatutLibelleAttribute(): string
    {
        return self::statuts()[$this->statut] ?? $this->statut;
    }

    public function getModeLibelleAttribute(): string
    {
        return self::modes()[$this->mode] ?? $this->mode;
    }

    public function estReussi(): bool
    {
        return $this->statut === 'reussi';
    }

    /**
     * Un paiement réussi ne se rejoue pas. C'est ce qui empêche un retour de
     * passerelle rejoué — ou un agent qui clique deux fois — de créditer deux
     * fois la même somme.
     */
    public function estFige(): bool
    {
        return in_array($this->statut, ['reussi', 'rembourse', 'annule'], true);
    }

    public function getMontantAfficheAttribute(): string
    {
        return $this->formater((float) $this->montant).' '.$this->devise;
    }

    public function getMontantConvertiAfficheAttribute(): ?string
    {
        if ($this->montant_converti === null) {
            return null;
        }

        return $this->formater((float) $this->montant_converti).' '.$this->devise_convertie;
    }

    private function formater(float $v): string
    {
        return floor($v) == $v
            ? number_format($v, 0, ',', ' ')
            : number_format($v, 2, ',', ' ');
    }

    public function scopeReussis(Builder $q): Builder
    {
        return $q->where('statut', 'reussi');
    }

    public function scopeAverifier(Builder $q): Builder
    {
        return $q->whereIn('statut', ['verification', 'en_attente']);
    }
}
