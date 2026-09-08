<?php

namespace App\Models;

use App\Models\Concerns\HasContentStatus;
use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Événement public (billetterie/agenda, brief §6 : "/evenements") — distinct
 * de HistoricalEvent (timeline historique).
 */
class Event extends Model
{
    use HasContentStatus, HasSlug;

    protected $fillable = [
        'title', 'slug', 'description', 'location', 'starts_at', 'ends_at',
        'content_status', 'source_note', 'created_by', 'verified_by', 'verified_at',
    ];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'verified_at' => 'datetime'];
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('starts_at', '>=', now());
    }

    public function isUpcoming(): bool
    {
        return $this->starts_at->isFuture();
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
