<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'slug', 'service', 'nom', 'description',
        'prix_mensuel', 'prix_annuel', 'devise', 'tca_taux',
        'quotas', 'fonctionnalites', 'essai_jours', 'sur_devis',
        'actif', 'mis_en_avant', 'ordre',
    ];

    protected $casts = [
        'quotas' => 'array',
        'fonctionnalites' => 'array',
        'prix_mensuel' => 'decimal:2',
        'prix_annuel' => 'decimal:2',
        'tca_taux' => 'decimal:2',
        'essai_jours' => 'integer',
        'sur_devis' => 'boolean',
        'actif' => 'boolean',
        'mis_en_avant' => 'boolean',
        'ordre' => 'integer',
    ];

    protected $attributes = [
        'devise' => 'USD',
        'tca_taux' => 0,
        'essai_jours' => 0,
        'sur_devis' => false,
        'actif' => true,
        'mis_en_avant' => false,
        'ordre' => 0,
    ];

    public function abonnements(): HasMany
    {
        return $this->hasMany(Abonnement::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** Les trois services vendus par abonnement. */
    public static function services(): array
    {
        return [
            'site_web' => 'Sites web',
            'hebergement' => 'Hébergement',
            'domaine' => 'Noms de domaine',
        ];
    }

    public static function cycles(): array
    {
        return ['mensuel' => 'Mensuel', 'annuel' => 'Annuel'];
    }

    /**
     * Segment d'URL de chaque service.
     *
     * Séparé de la clé interne : « sites-web » se diffuse en publicité,
     * « site_web » est ce que porte la base. Renommer l'un sans l'autre
     * casserait les liens déjà imprimés.
     */
    public static function segments(): array
    {
        return [
            'site_web' => 'sites-web',
            'hebergement' => 'hebergement',
            'domaine' => 'domaines',
        ];
    }

    public static function serviceDuSegment(string $segment): ?string
    {
        $service = array_search($segment, self::segments(), true);

        return $service === false ? null : $service;
    }

    public function getSegmentAttribute(): string
    {
        return self::segments()[$this->service] ?? $this->service;
    }

    public function getServiceLibelleAttribute(): string
    {
        return self::services()[$this->service] ?? $this->service;
    }

    public function scopeActif(Builder $q): Builder
    {
        return $q->where('actif', true)->orderBy('ordre')->orderBy('nom');
    }

    public function scopePourService(Builder $q, string $service): Builder
    {
        return $q->where('service', $service);
    }

    /** Prix du cycle demandé, ou null si le plan est sur devis. */
    public function prixPour(string $cycle): ?float
    {
        if ($this->sur_devis) {
            return null;
        }

        $prix = $cycle === 'annuel' ? $this->prix_annuel : $this->prix_mensuel;

        return $prix === null ? null : (float) $prix;
    }

    public function getPrixAfficheAttribute(): string
    {
        if ($this->sur_devis || $this->prix_mensuel === null) {
            return 'Sur devis';
        }

        return $this->formater((float) $this->prix_mensuel).' '.$this->devise.' / mois';
    }

    private function formater(float $v): string
    {
        return floor($v) == $v
            ? number_format($v, 0, ',', ' ')
            : number_format($v, 2, ',', ' ');
    }
}
