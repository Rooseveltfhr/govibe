<?php

namespace App\Models\Territoire;

use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasSlug;

    protected $fillable = ['name', 'slug'];

    public function arrondissements(): HasMany
    {
        return $this->hasMany(Arrondissement::class);
    }
}
