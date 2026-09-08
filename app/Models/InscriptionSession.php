<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class InscriptionSession extends Model
{
    protected $table = 'inscriptions_session';

    protected $fillable = [
        'reference', 'session_formation_id', 'nom_complet', 'whatsapp', 'mode',
        'montant', 'devise', 'moyen_paiement', 'moyen_paiement_nom',
        'fichier', 'fichier_nom_origine', 'fichier_taille', 'fichier_mime',
        'statut', 'commentaire_admin', 'verifiee_par', 'verifiee_le', 'ip',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'verifiee_le' => 'datetime',
    ];

    /** Numéro WhatsApp qui reçoit les inscriptions à vérifier. */
    public const WHATSAPP_VERIFICATION = '50933988754';

    public function session(): BelongsTo
    {
        return $this->belongsTo(SessionFormation::class, 'session_formation_id');
    }

    public static function genererReference(): string
    {
        do {
            $reference = 'FM-'.now()->format('Ymd').'-'.strtoupper(Str::random(4));
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }

    public static function statuts(): array
    {
        return [
            'a_verifier' => 'À vérifier',
            'confirmee' => 'Confirmée',
            'rejetee' => 'Rejetée',
        ];
    }

    public function getStatutLibelleAttribute(): string
    {
        return self::statuts()[$this->statut] ?? $this->statut;
    }

    public function getModeLisibleAttribute(): string
    {
        return SessionFormation::modesDisponibles()[$this->mode] ?? $this->mode;
    }

    public function getMontantAfficheAttribute(): ?string
    {
        if ($this->montant === null) {
            return null;
        }

        $v = (float) $this->montant;

        return (floor($v) == $v
            ? number_format($v, 0, ',', ' ')
            : number_format($v, 2, ',', ' ')).' '.$this->devise;
    }

    public function getTailleLisibleAttribute(): ?string
    {
        if (! $this->fichier_taille) {
            return null;
        }

        return $this->fichier_taille >= 1048576
            ? round($this->fichier_taille / 1048576, 1).' Mo'
            : max(1, (int) round($this->fichier_taille / 1024)).' Ko';
    }

    public function scopeAverifier(Builder $query): Builder
    {
        return $query->where('statut', 'a_verifier');
    }

    /**
     * Le message que le participant envoie pour faire vérifier son paiement.
     *
     * Aucun serveur ne peut écrire dans WhatsApp sans un numéro Cloud API :
     * c'est donc le participant qui pousse le message. L'inscription et la
     * preuve sont déjà enregistrées — le message prévient, il ne transporte
     * pas la donnée.
     */
    public function getMessageWhatsappAttribute(): string
    {
        $lignes = [
            'Bonjour, je viens de m\'inscrire à la '.($this->session?->titre ?? 'formation').' sur govibeht.com.',
            '',
            'Référence : '.$this->reference,
            'Nom : '.$this->nom_complet,
            'Suivi : '.$this->mode_lisible,
        ];

        if ($this->montant_affiche) {
            $lignes[] = 'Montant payé : '.$this->montant_affiche;
        }
        if ($this->moyen_paiement_nom) {
            $lignes[] = 'Moyen : '.$this->moyen_paiement_nom;
        }

        $lignes[] = '';
        $lignes[] = $this->fichier
            ? 'Ma preuve de paiement est déjà envoyée sur le site.'
            : 'Je transmets ma preuve de paiement ici.';

        return implode("\n", $lignes);
    }

    public function getLienWhatsappAttribute(): string
    {
        return 'https://wa.me/'.self::WHATSAPP_VERIFICATION.'?text='.rawurlencode($this->message_whatsapp);
    }
}
