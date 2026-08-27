<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Evenement extends Model
{
    protected $table = 'evenements';

    protected $fillable = [
        'titre', 'slug', 'sous_titre', 'description', 'lieu',
        'date_debut', 'date_fin', 'whatsapp_group_url', 'couleur', 'flyer',
        'actif', 'inscriptions_ouvertes', 'ordre',
    ];

    protected $casts = [
        'date_debut'            => 'date',
        'date_fin'              => 'date',
        'actif'                 => 'boolean',
        'inscriptions_ouvertes' => 'boolean',
        'ordre'                 => 'integer',
    ];

    // L'URL partagée en publicité repose sur le slug, jamais sur l'id.
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(EvenementReservation::class, 'evenement_id');
    }

    public function scopeActif(Builder $query): Builder
    {
        return $query->where('actif', true)->orderBy('ordre')->orderBy('titre');
    }

    /**
     * Génère un slug unique à partir du titre. Appelé à la création et lors
     * d'un changement de titre depuis l'ERP.
     */
    public static function genererSlug(string $titre, ?int $ignorerId = null): string
    {
        $base = Str::slug($titre) ?: 'evenement';
        $slug = $base;
        $n = 2;

        while (static::where('slug', $slug)
            ->when($ignorerId, fn ($q) => $q->where('id', '!=', $ignorerId))
            ->exists()
        ) {
            $slug = $base . '-' . $n++;
        }

        return $slug;
    }

    /**
     * URL du visuel. Deux origines possibles : un fichier livré avec le dépôt
     * (public/images/...) ou un téléversement depuis l'ERP (disque public).
     * On les distingue par l'existence du chemin sur le disque public.
     */
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
     * Couleur d'accent, avec repli sur le rouge de marque si le champ est vide
     * ou ne contient pas un hexadécimal valide.
     */
    public function getCouleurAccentAttribute(): string
    {
        $c = $this->couleur;

        return (is_string($c) && preg_match('/^#[0-9a-fA-F]{6}$/', $c)) ? $c : '#DC2626';
    }

    /**
     * Variante assombrie de la couleur d'accent, pour les dégradés et les
     * survols. Évite de stocker deux couleurs à maintenir en cohérence.
     */
    public function getCouleurFonceeAttribute(): string
    {
        $hex = ltrim($this->couleur_accent, '#');

        $rgb = array_map(
            fn ($c) => (int) round(hexdec($c) * 0.72),
            str_split($hex, 2)
        );

        return sprintf('#%02x%02x%02x', ...$rgb);
    }

    /**
     * Composantes « r, g, b » pour les rgba() des fonds translucides.
     */
    public function getCouleurRgbAttribute(): string
    {
        $hex = ltrim($this->couleur_accent, '#');

        return implode(', ', array_map(fn ($c) => hexdec($c), str_split($hex, 2)));
    }

    public function getDatesLibelleAttribute(): ?string
    {
        if (! $this->date_debut) {
            return null;
        }

        // Locale imposée : le site est francophone, et APP_LOCALE peut valoir
        // « en » sur un serveur dont le .env précède ce réglage — la date
        // s'afficherait alors « 16 October 2026 » sur une page française.
        $debut = $this->date_debut->locale('fr');

        if ($this->date_fin && ! $this->date_fin->isSameDay($this->date_debut)) {
            return $debut->translatedFormat('d F Y')
                . ' au ' . $this->date_fin->locale('fr')->translatedFormat('d F Y');
        }

        return $debut->translatedFormat('d F Y');
    }
}
