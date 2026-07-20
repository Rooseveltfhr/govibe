<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Exhibitor extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['socials' => 'array', 'featured' => 'boolean'];
    }

    public function booth(): BelongsTo
    {
        return $this->belongsTo(Booth::class);
    }
}
