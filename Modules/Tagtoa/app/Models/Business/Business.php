<?php

namespace Modules\Tagtoa\App\Models\Business;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Support\Menu\BusinessProfile;

/**
 * TAGTOA — un commerce réel : une boulangerie, un bar, un hôtel.
 *
 * Le commerce est l'unité d'isolation du système : `Tenant::id()` renvoie son
 * identifiant, et tout ce qui porte un `tenant_id` lui appartient.
 *
 * PAS de trait BelongsToTenant ici, volontairement : ce modèle est justement
 * celui qui DÉFINIT le commerce. S'il se filtrait lui-même par le commerce
 * courant, on ne pourrait plus lister les commerces d'un compte pour en
 * changer. Son cloisonnement passe par `account_id`.
 */
class Business extends Model
{
    protected $table = 'tagtoa_businesses';

    /** Identifiant fourni à la création (ULID, ou l'ancien tenant_id). */
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'account_id', 'name', 'type', 'categories',
        'sells_products', 'sells_services',
        'logo_path', 'address', 'phone', 'currency', 'is_active',
    ];

    protected $casts = [
        'categories'     => 'array',
        'sells_products' => 'boolean',
        'sells_services' => 'boolean',
        'is_active'      => 'boolean',
    ];

    /** Devises proposées d'emblée ; le marchand peut en saisir une autre. */
    public const SUGGESTED_CURRENCIES = ['HTG', 'USD', 'DOP', 'EUR', 'CAD'];

    /** Nouvel identifiant pour un commerce créé depuis l'application. */
    public static function newId(): string
    {
        return (string) Str::ulid();
    }

    /**
     * Type d'établissement lisible. Une seule source : Menu::TYPES, qui sert
     * déjà au menu digital — un restaurant ne doit pas s'appeler autrement
     * selon l'écran.
     */
    public function getTypeLabelAttribute(): string
    {
        return Menu::TYPES[$this->type]['label'] ?? 'Autre';
    }

    /** Catégories proposées pour ce métier, si le marchand n'en a pas choisi. */
    public function suggestedCategories(): array
    {
        return BusinessProfile::for($this->type)['categories'] ?? [];
    }

    /** Ce que le commerce vend, en une phrase. */
    public function getSellsLabelAttribute(): string
    {
        return match (true) {
            $this->sells_products && $this->sells_services => __('Produits et services'),
            $this->sells_services                          => __('Services'),
            default                                        => __('Produits'),
        };
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? \Illuminate\Support\Facades\Storage::url($this->logo_path) : null;
    }

    /** Initiales, pour le sélecteur de commerce quand il n'y a pas de logo. */
    public function getInitialsAttribute(): string
    {
        $mots = array_values(array_filter(preg_split('/\s+/', trim((string) $this->name)) ?: []));
        if ($mots === []) {
            return '?';
        }

        return mb_strtoupper(mb_substr($mots[0], 0, 1))
            .(count($mots) > 1 ? mb_strtoupper(mb_substr(end($mots), 0, 1)) : '');
    }
}
