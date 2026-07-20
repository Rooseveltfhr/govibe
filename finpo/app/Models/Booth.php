<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booth extends Model
{
    protected $guarded = [];

    public function exhibitor(): HasOne
    {
        return $this->hasOne(Exhibitor::class);
    }
}
