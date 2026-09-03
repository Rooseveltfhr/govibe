<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PreuvePaiement extends Model
{
    protected $table = 'preuves_paiement';

    protected $fillable = [
        'reference', 'nom', 'telephone', 'email',
        'moyen', 'moyen_nom', 'montant', 'devise', 'transaction_id', 'motif', 'note',
        'fichier', 'fichier_nom_origine', 'fichier_taille', 'fichier_mime',
        'statut', 'commentaire_admin', 'verifiee_par', 'verifiee_le', 'ip',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'verifiee_le' => 'datetime',
    ];

    /** Numéro de suivi que le client cite sur WhatsApp. */
    public static function genererReference(): string
    {
        do {
            $reference = 'PP-'.now()->format('Ymd').'-'.strtoupper(Str::random(4));
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }

    public static function statuts(): array
    {
        return [
            'recue' => 'Reçue',
            'verifiee' => 'Vérifiée',
            'rejetee' => 'Rejetée',
        ];
    }

    public function getStatutLibelleAttribute(): string
    {
        return self::statuts()[$this->statut] ?? $this->statut;
    }

    public function scopeEnAttente(Builder $query): Builder
    {
        return $query->where('statut', 'recue');
    }

    /**
     * Le message que le client envoie sur WhatsApp. Il porte la référence :
     * c'est le seul lien entre la capture reçue sur le site et la
     * conversation, WhatsApp ne pouvant pas recevoir le fichier depuis la page.
     */
    public function getMessageWhatsappAttribute(): string
    {
        $lignes = ['Bonjour, je viens d\'envoyer ma preuve de paiement sur govibeht.com.'];
        $lignes[] = 'Référence : '.$this->reference;
        $lignes[] = 'Nom : '.$this->nom;

        if ($this->moyen_nom) {
            $lignes[] = 'Moyen : '.$this->moyen_nom;
        }
        if ($this->montant !== null) {
            $lignes[] = 'Montant : '.number_format((float) $this->montant, 2, ',', ' ').' '.$this->devise;
        }
        if ($this->transaction_id) {
            $lignes[] = 'Transaction : '.$this->transaction_id;
        }
        if ($this->motif) {
            $lignes[] = 'Motif : '.$this->motif;
        }

        return implode("\n", $lignes);
    }

    /** Numéro WhatsApp de l'équipe, au format attendu par wa.me. */
    public const WHATSAPP = '50933988754';

    public function getLienWhatsappAttribute(): string
    {
        return 'https://wa.me/'.self::WHATSAPP.'?text='.rawurlencode($this->message_whatsapp);
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
}
