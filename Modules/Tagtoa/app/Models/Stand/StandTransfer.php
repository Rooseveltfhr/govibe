<?php

namespace Modules\Tagtoa\App\Models\Stand;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TAGTOA SMART STAND — une offre de cession : « ces N stands, à qui présentera ce code ».
 *
 * PAS de trait BelongsToTenant, et PAS de colonne `tenant_id` : une cession a
 * deux côtés. L'isoler par un seul commerce cacherait au repreneur l'offre qui
 * lui est destinée. Le cloisonnement s'écrit explicitement, par `scopeFrom()`
 * et `scopeTo()`.
 *
 * Le code en clair ne vit que le temps d'un écran. Ce modèle n'en connaît que
 * le hachage, et ne l'expose jamais.
 */
class StandTransfer extends Model
{
    protected $table = 'tagtoa_stand_transfers';

    protected $fillable = [
        'from_business_id', 'to_business_id', 'code_hash', 'note',
        'expires_at', 'accepted_at', 'cancelled_at', 'actor_name', 'ip',
    ];

    protected $casts = [
        'expires_at'   => 'datetime',
        'accepted_at'  => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Le hachage ne sort jamais par une sérialisation.
     *
     * Il ne suffit pas à reprendre les stands — c'est un hachage — mais le
     * publier donnerait à un attaquant de quoi vérifier hors ligne les codes
     * qu'il essaie, et donc de contourner la limitation du réseau.
     */
    protected $hidden = ['code_hash'];

    public function items(): HasMany
    {
        return $this->hasMany(StandTransferItem::class, 'transfer_id');
    }

    public function stands()
    {
        return $this->hasManyThrough(
            Stand::class, StandTransferItem::class,
            'transfer_id', 'id', 'id', 'stand_id'
        );
    }

    /* ---------------- Lecture ---------------- */

    /** L'offre attend-elle encore un repreneur ? */
    public function isPending(): bool
    {
        return $this->accepted_at === null
            && $this->cancelled_at === null
            && $this->expires_at !== null
            && now()->lessThan($this->expires_at);
    }

    /** Passée de date, jamais acceptée ni annulée. */
    public function isExpired(): bool
    {
        return $this->accepted_at === null
            && $this->cancelled_at === null
            && ($this->expires_at === null || now()->greaterThanOrEqualTo($this->expires_at));
    }

    /** Ce que l'écran affiche, en un mot. */
    public function getStatusLabelAttribute(): string
    {
        return match (true) {
            $this->accepted_at !== null  => __('Acceptée'),
            $this->cancelled_at !== null => __('Annulée'),
            $this->isExpired()           => __('Expirée'),
            default                      => __('En attente'),
        };
    }

    /* ---------------- Portées ---------------- */

    /**
     * Les offres ÉMISES par un commerce.
     *
     * Explicite, parce que le modèle n'a pas de portée automatique : une
     * requête qui oublierait ce filtre montrerait à un marchand les cessions
     * de tous les autres — c'est-à-dire qui vend son affaire, et à qui.
     *
     * Nommée `issuedBy` et non `from` : `from()` est le nom par lequel le
     * constructeur de requêtes choisit sa TABLE. Une portée qui porte ce
     * nom-là marche aujourd'hui par accident d'ordre de résolution, et
     * cesserait de marcher le jour où Laravel déclare la méthode — en
     * changeant la table interrogée, sans erreur, donc sans qu'on le voie.
     */
    public function scopeIssuedBy($query, ?string $businessId)
    {
        return $query->where('from_business_id', $businessId);
    }

    /** Les offres REÇUES par un commerce. Même exigence d'explicite. */
    public function scopeReceivedBy($query, ?string $businessId)
    {
        return $query->where('to_business_id', $businessId);
    }
}
