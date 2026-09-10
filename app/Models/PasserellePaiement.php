<?php

namespace App\Models;

use App\Paiement\RegistrePilotes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PasserellePaiement extends Model
{
    protected $table = 'passerelles_paiement';

    protected $fillable = [
        'nom', 'code', 'type', 'titulaire', 'numero_compte', 'reseau',
        'lien_paiement', 'instructions', 'qr_code', 'logo', 'actif', 'ordre',
        'mode', 'pilote', 'environnement', 'identifiants', 'devises_supportees',
        'frais_pourcent', 'frais_fixe', 'disponible_public', 'disponible_caisse',
    ];

    protected $casts = [
        'actif' => 'boolean',
        'ordre' => 'integer',
        // Chiffrées au repos : une base copiée ne livre pas les clés API.
        'identifiants' => 'encrypted:array',
        'devises_supportees' => 'array',
        'frais_pourcent' => 'decimal:3',
        'frais_fixe' => 'decimal:2',
        'disponible_public' => 'boolean',
        'disponible_caisse' => 'boolean',
    ];

    // Les clés ne ressortent jamais dans une sérialisation.
    protected $hidden = ['identifiants'];

    /**
     * Les mêmes valeurs que les défauts de la table.
     *
     * Sans elles, une passerelle fraîchement créée lit « null » sur des
     * colonnes que la base a pourtant remplies — et un code qui teste
     * l'environnement juste après la création se tromperait.
     */
    protected $attributes = [
        'mode' => 'manuel',
        'environnement' => 'test',
        'disponible_public' => true,
        'disponible_caisse' => false,
        'frais_pourcent' => 0,
        'frais_fixe' => 0,
    ];

    public function scopeActif(Builder $query): Builder
    {
        return $query->where('actif', true)->orderBy('ordre')->orderBy('nom');
    }

    /** Moyens proposés au client sur le site et le portail. */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('actif', true)->where('disponible_public', true)
            ->orderBy('ordre')->orderBy('nom');
    }

    /** Moyens sélectionnables en caisse par un employé, cash compris. */
    public function scopeCaisse(Builder $query): Builder
    {
        return $query->where('actif', true)->where('disponible_caisse', true)
            ->orderBy('ordre')->orderBy('nom');
    }

    public function estApi(): bool
    {
        return $this->mode === 'api';
    }

    /**
     * Une passerelle API n'encaisse que si ses clés sont saisies. Sans elles,
     * elle serait proposée au client puis échouerait au moment de payer.
     */
    public function getApiPreteAttribute(): bool
    {
        if (! $this->estApi() || ! $this->pilote) {
            return false;
        }

        $pilote = app(RegistrePilotes::class)->trouver($this->pilote);

        if (! $pilote) {
            return false;
        }

        $config = $this->identifiants ?? [];

        foreach (array_keys($pilote->champsRequis()) as $champ) {
            if (blank($config[$champ] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /** Aperçu masqué : de quoi reconnaître une clé sans jamais la relire. */
    public function apercuIdentifiant(string $champ): ?string
    {
        $valeur = ($this->identifiants ?? [])[$champ] ?? null;

        if (blank($valeur)) {
            return null;
        }

        return mb_substr($valeur, 0, 4).str_repeat('•', 8).mb_substr($valeur, -3);
    }

    public static function types(): array
    {
        return [
            'mobile_money' => 'Mobile Money',
            'banque' => 'Banque',
            'transfert' => 'Transfert',
            'lien' => 'Lien de paiement',
            'crypto' => 'Cryptomonnaie',
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
        if ($this->estApi()) {
            return ! $this->api_prete;
        }

        return $this->type === 'lien'
            ? blank($this->lien_paiement)
            : blank($this->numero_compte);
    }
}
