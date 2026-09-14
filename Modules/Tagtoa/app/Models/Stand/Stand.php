<?php

namespace Modules\Tagtoa\App\Models\Stand;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Tagtoa\App\Support\Stand\StandId;
use Modules\Tagtoa\App\Support\Stand\StandState;

/**
 * TAGTOA SMART STAND — un objet physique et son identité numérique.
 *
 * PAS de trait BelongsToTenant, volontairement : un stand non réclamé
 * n'appartient à aucun commerce. Une portée automatique le rendrait invisible
 * au scan — c'est-à-dire au moment précis où il faut le trouver pour proposer
 * son activation. Le cloisonnement s'écrit explicitement sur les requêtes du
 * marchand (`scopeOfBusiness`).
 */
class Stand extends Model
{
    protected $table = 'tagtoa_stands';

    protected $fillable = [
        'batch_id', 'public_id', 'serial', 'secret_hash', 'secret_version',
        'physical_state', 'digital_state', 'holder_type', 'holder_id',
        'tenant_id', 'location_label', 'target_module',
        'claimed_at', 'last_scanned_at', 'scan_count',
        'claim_reserved_until', 'claim_reserved_token',
    ];

    protected $casts = [
        'serial'          => 'integer',
        'secret_version'  => 'integer',
        'scan_count'      => 'integer',
        'claimed_at'           => 'datetime',
        'last_scanned_at'      => 'datetime',
        'claim_reserved_until' => 'datetime',
    ];

    /**
     * Le secret ne sort JAMAIS par un accesseur, une sérialisation ou un log.
     *
     * `$hidden` ne suffit pas seul — on n'en garde que le hachage — mais il
     * évite qu'un `toJson()` distrait le fasse voyager jusqu'à un écran de
     * revendeur, qui ne doit rien en connaître.
     */
    protected $hidden = ['secret_hash', 'claim_reserved_token'];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(StandBatch::class, 'batch_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(StandEvent::class, 'stand_id');
    }

    /* ---------------- Lecture ---------------- */

    /** L'URL gravée dans le QR et la puce. */
    public function getPathAttribute(): string
    {
        return StandId::path($this->public_id);
    }

    public function getPhysicalLabelAttribute(): string
    {
        return StandState::physicalLabel($this->physical_state);
    }

    public function getDigitalLabelAttribute(): string
    {
        return StandState::digitalLabel($this->digital_state);
    }

    /**
     * Ce stand peut-il être réclamé ?
     *
     * Un lot rappelé ferme tous ses stands d'un coup : le contrôle du lot est
     * donc fait ici, pas seulement sur l'état de l'objet.
     */
    public function isClaimable(): bool
    {
        if ($this->batch && $this->batch->isRecalled()) {
            return false;
        }

        return StandState::isClaimable($this->physical_state, $this->digital_state);
    }

    /** Le scan doit-il mener au commerce ? */
    public function resolvesToBusiness(): bool
    {
        return StandState::resolvesToBusiness($this->digital_state) && $this->tenant_id !== null;
    }

    /* ---------------- Portées ---------------- */

    /**
     * Les stands d'UN commerce.
     *
     * Explicite, parce que le modèle n'a pas de portée automatique : écrire
     * `Stand::all()` sur l'écran d'un marchand donnerait les stands de tous.
     */
    public function scopeOfBusiness($query, ?string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /** Retrouve un stand par ce qui est imprimé dessus, quelle que soit la saisie. */
    public function scopeByPublicId($query, ?string $publicId)
    {
        return $query->where('public_id', StandId::normalizeId($publicId));
    }
}
