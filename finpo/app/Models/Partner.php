<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    protected $guarded = [];

    public function categoryLabel(): string
    {
        return config('finpo.partner_categories.'.$this->category, ucfirst((string) $this->category));
    }
}
