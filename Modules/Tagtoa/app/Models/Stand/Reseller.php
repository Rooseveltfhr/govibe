<?php

namespace Modules\Tagtoa\App\Models\Stand;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TAGTOA SMART STAND — un revendeur.
 *
 * PAS de trait BelongsToTenant, volontairement — et pour une raison qui n'est
 * pas celle des stands.
 *
 * La colonne s'appelle `business_id`, PAS `tenant_id` — et c'est le garde-fou
 * d'isolation qui l'a imposé, à juste titre. Une colonne nommée `tenant_id`
 * promet « cette ligne appartient à ce commerce » ; ici elle voudrait dire
 * « ce commerce EST ce revendeur ». Deux sens pour un nom, c'est ainsi qu'une
 * règle se dissout.
 *
 * La portée automatique rendrait d'ailleurs les revendeurs invisibles au
 * fondateur, qui doit justement tous les voir pour leur affecter des cartons.
 * Le cloisonnement passe par `scopeOfBusiness()`, en toutes lettres.
 */
class Reseller extends Model
{
    protected $table = 'tagtoa_stand_resellers';

    protected $fillable = [
        'business_id', 'name', 'contact_phone', 'zone', 'commission_pct', 'is_active', 'notes',
    ];

    protected $casts = [
        'commission_pct' => 'decimal:2',
        'is_active'      => 'boolean',
    ];

    /** Les stands que ce revendeur détient physiquement. */
    public function stands(): HasMany
    {
        return $this->hasMany(Stand::class, 'holder_id')
            ->where('holder_type', Stand::HOLDER_RESELLER);
    }

    /**
     * Le revendeur d'un commerce, s'il en est un et s'il est actif.
     *
     * Renvoie null plutôt que de lever : « ce commerce n'est pas revendeur »
     * n'est pas une erreur, c'est le cas de la quasi-totalité des comptes.
     */
    public static function forBusiness(?string $tenantId): ?self
    {
        return $tenantId
            ? self::where('business_id', $tenantId)->where('is_active', true)->first()
            : null;
    }

    /** Cloisonnement EXPLICITE : ce revendeur-ci, et pas un autre. */
    public function scopeOfBusiness($query, ?string $businessId)
    {
        return $query->where('business_id', $businessId);
    }
}
