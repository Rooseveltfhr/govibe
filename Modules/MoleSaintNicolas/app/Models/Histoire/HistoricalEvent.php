<?php

namespace App\Models\Histoire;

use App\Models\Concerns\HasContentStatus;
use App\Models\Concerns\HasSlug;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistoricalEvent extends Model
{
    use HasContentStatus, HasSlug;

    protected $fillable = [
        'historical_period_id', 'title', 'slug', 'happened_on', 'circa_year', 'description',
        'content_status', 'source_note', 'created_by', 'verified_by', 'verified_at',
    ];

    protected function casts(): array
    {
        return ['happened_on' => 'date', 'verified_at' => 'datetime'];
    }

    /** Année à afficher, précise ou approximative. */
    public function getYearAttribute(): ?int
    {
        return $this->circa_year ?? $this->happened_on?->year;
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(HistoricalPeriod::class, 'historical_period_id');
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
