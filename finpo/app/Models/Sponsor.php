<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sponsor extends Model
{
    protected $guarded = [];

    public function levelLabel(): string
    {
        return config('finpo.sponsor_levels.'.$this->level.'.label', ucfirst((string) $this->level));
    }

    public function levelColor(): string
    {
        return config('finpo.sponsor_levels.'.$this->level.'.color', '#e8b931');
    }
}
