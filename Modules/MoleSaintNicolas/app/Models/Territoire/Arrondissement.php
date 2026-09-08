<?php

namespace App\Models\Territoire;

use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Arrondissement extends Model
{
    use HasSlug;

    protected $fillable = ['department_id', 'name', 'slug', 'area_km2', 'population', 'population_year'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function communes(): HasMany
    {
        return $this->hasMany(Commune::class);
    }
}
