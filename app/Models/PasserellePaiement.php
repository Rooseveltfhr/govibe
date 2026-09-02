<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PasserellePaiement extends Model
{
    protected $table = 'passerelles_paiement';

    protected $fillable = [
        'nom', 'code', 'type', 'titulaire', 'numero_compte', 'reseau',
        'lien_paiement', 'instructions', 'qr_code', 'logo', 'actif', 'ordre',
    ];

    protected $casts = [
        'actif' => 'boolean',
        'ordre' => 'integer',
    ];

    public function scopeActif(Builder $query): Builder
    {
        return $query->where('actif', true)->orderBy('ordre')->orderBy('nom');
    }

    public static function types(): array
    {
        return [
            'mobile_money' => 'Mobile Money',
            'banque'       => 'Banque',
            'transfert'    => 'Transfert',
            'lien'         => 'Lien de paiement',
            'crypto'       => 'Cryptomonnaie',
        ];
    }

    public function getTypeLibelleAttribute(): string
    {
        return self::types()[$this->type] ?? $this->type;
    }

    public function getQrCodeUrlAttribute(): ?string
    {
        return $this->fichierUrl($this->qr_code);
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->fichierUrl($this->logo);
    }

    /**
     * Un fichier vient soit du dépôt (public/images/...), soit d'un
     * téléversement depuis l'ERP (disque public). On les distingue par la
     * présence du chemin sur le disque public.
     */
    private function fichierUrl(?string $chemin): ?string
    {
        if (! $chemin) {
            return null;
        }

        return Storage::disk('public')->exists($chemin)
            ? Storage::disk('public')->url($chemin)
            : asset($chemin);
    }

    /**
     * Initiales affichées à la place du logo tant qu'aucun n'a été téléversé.
     * Deux lettres au plus, pour rester lisibles dans une pastille.
     */
    public function getInitialesAttribute(): string
    {
        $mots = preg_split('/[\s\(\)-]+/', $this->nom, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $lettres = array_map(fn ($m) => mb_substr($m, 0, 1), array_slice($mots, 0, 2));

        return mb_strtoupper(implode('', $lettres) ?: '?');
    }

    /**
     * Ce que le client doit recopier ou copier : numéro, adresse, ou rien
     * quand le paiement passe par un lien.
     */
    public function getValeurACopierAttribute(): ?string
    {
        return $this->type === 'lien' ? null : $this->numero_compte;
    }

    /**
     * Une fiche sans coordonnées ni lien n'aide personne à payer : elle est
     * signalée dans l'ERP et masquée du choix public.
     */
    public function getEstIncompleteAttribute(): bool
    {
        return $this->type === 'lien'
            ? blank($this->lien_paiement)
            : blank($this->numero_compte);
    }
}
