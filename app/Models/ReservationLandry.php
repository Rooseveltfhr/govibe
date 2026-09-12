<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ReservationLandry extends Model
{
    protected $table = 'reservations_landry';

    protected $fillable = [
        'reference', 'nom_complet', 'whatsapp', 'adresse', 'point_repere',
        'mode_service', 'instructions_recuperation', 'mode_facturation',
        'quantite_vetements', 'frequence',
        'mode_paiement', 'accepte_frais_inscription', 'frais_inscription', 'devise',
        'statut', 'notes_internes', 'traitee_par', 'traitee_le', 'ip',
    ];

    protected $casts = [
        'accepte_frais_inscription' => 'boolean',
        'frais_inscription' => 'decimal:2',
        'quantite_vetements' => 'integer',
        'traitee_le' => 'datetime',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traitee_par');
    }

    public static function genererReference(): string
    {
        do {
            $reference = 'LD-'.now()->format('Ymd').'-'.strtoupper(Str::random(4));
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }

    // ── Listes de référence ──────────────────────────────

    public static function modesService(): array
    {
        return [
            'domicile' => 'À domicile',
            'local' => 'Dans notre local GOVIBE',
        ];
    }

    public static function modesFacturation(): array
    {
        return [
            'unite' => 'Par unité de vêtement',
            'poids' => 'Par poids',
        ];
    }

    public static function frequences(): array
    {
        return [
            'plusieurs_semaine' => 'Plusieurs fois par semaine',
            'hebdomadaire' => 'Une fois par semaine',
            'bimensuel' => 'Toutes les 2 semaines',
            'mensuel' => 'Une fois par mois',
            'occasionnel' => 'Occasionnellement',
            'autre' => 'Autre',
        ];
    }

    public static function modesPaiement(): array
    {
        return [
            'abonnement' => 'Abonnement',
            'par_service' => 'Paiement à chaque service',
        ];
    }

    public static function statuts(): array
    {
        return [
            'nouvelle' => 'Nouvelle',
            'contactee' => 'Contactée',
            'confirmee' => 'Confirmée',
            'annulee' => 'Annulée',
        ];
    }

    // ── Libellés ─────────────────────────────────────────

    public function getModeServiceLibelleAttribute(): string
    {
        return self::modesService()[$this->mode_service] ?? $this->mode_service;
    }

    public function getModeFacturationLibelleAttribute(): string
    {
        return self::modesFacturation()[$this->mode_facturation] ?? $this->mode_facturation;
    }

    public function getFrequenceLibelleAttribute(): string
    {
        return self::frequences()[$this->frequence] ?? $this->frequence;
    }

    public function getModePaiementLibelleAttribute(): string
    {
        return self::modesPaiement()[$this->mode_paiement] ?? $this->mode_paiement;
    }

    public function getStatutLibelleAttribute(): string
    {
        return self::statuts()[$this->statut] ?? $this->statut;
    }

    public function getFraisAfficheAttribute(): ?string
    {
        if (! $this->accepte_frais_inscription || $this->frais_inscription === null) {
            return null;
        }

        $v = (float) $this->frais_inscription;

        return (floor($v) == $v
            ? number_format($v, 0, ',', ' ')
            : number_format($v, 2, ',', ' ')).' '.$this->devise;
    }

    public function scopeNouvelles(Builder $q): Builder
    {
        return $q->where('statut', 'nouvelle');
    }

    /**
     * Le message que le client envoie à l'équipe.
     *
     * La réservation est déjà enregistrée à ce moment : le message prévient,
     * il ne transporte pas la donnée.
     */
    public function getMessageWhatsappAttribute(): string
    {
        $lignes = [
            'Bonjour LANDRY, je viens de faire ma réservation sur govibeht.com.',
            '',
            'Référence : '.$this->reference,
            'Nom : '.$this->nom_complet,
            'Adresse : '.$this->adresse,
        ];

        if ($this->point_repere) {
            $lignes[] = 'Point de repère : '.$this->point_repere;
        }

        $lignes[] = 'Service : '.$this->mode_service_libelle;
        $lignes[] = 'Facturation : '.$this->mode_facturation_libelle;

        if ($this->quantite_vetements) {
            $lignes[] = 'Quantité approximative : '.$this->quantite_vetements.' vêtements';
        }

        $lignes[] = 'Fréquence : '.$this->frequence_libelle;
        $lignes[] = 'Paiement : '.$this->mode_paiement_libelle;
        $lignes[] = 'Inscription : '.($this->accepte_frais_inscription
            ? 'acceptée ('.$this->frais_affiche.')'
            : 'je souhaite plus d\'informations');

        return implode("\n", $lignes);
    }

    public function getLienWhatsappAttribute(): string
    {
        $numero = preg_replace('/\D+/', '', (string) config('govibe.landry.whatsapp'));

        return 'https://wa.me/'.$numero.'?text='.rawurlencode($this->message_whatsapp);
    }
}
