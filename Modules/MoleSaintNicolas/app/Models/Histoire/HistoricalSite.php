<?php

namespace App\Models\Histoire;

use App\Models\Concerns\HasContentStatus;
use App\Models\Concerns\HasSlug;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lieu historique physique (fort, monument, ruine...) — distinct des
 * événements/périodes de la timeline (brief §6 : "Sites historiques,
 * liste + fiche détaillée + carte").
 */
class HistoricalSite extends Model
{
    use HasContentStatus, HasSlug;

    protected $fillable = [
        'name', 'slug', 'category', 'description', 'lat', 'lng',
        'content_status', 'source_note', 'created_by', 'verified_by', 'verified_at',
    ];

    protected function casts(): array
    {
        return ['lat' => 'decimal:7', 'lng' => 'decimal:7', 'verified_at' => 'datetime'];
    }

    public function hasCoordinates(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
