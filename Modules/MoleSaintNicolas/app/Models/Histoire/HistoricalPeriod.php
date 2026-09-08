<?php

namespace App\Models\Histoire;

use App\Models\Concerns\HasContentStatus;
use App\Models\Concerns\HasSlug;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HistoricalPeriod extends Model
{
    use HasContentStatus, HasSlug;

    protected $fillable = [
        'name', 'slug', 'start_year', 'end_year', 'display_order', 'description',
        'content_status', 'source_note', 'created_by', 'verified_by', 'verified_at',
    ];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }

    public function events(): HasMany
    {
        return $this->hasMany(HistoricalEvent::class)->orderBy('circa_year')->orderBy('happened_on');
    }

    public function figures(): HasMany
    {
        return $this->hasMany(HistoricalFigure::class);
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
