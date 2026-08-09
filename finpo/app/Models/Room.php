<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    protected $guarded = [];

    public function sessions(): HasMany
    {
        return $this->hasMany(ProgramSession::class);
    }
}
