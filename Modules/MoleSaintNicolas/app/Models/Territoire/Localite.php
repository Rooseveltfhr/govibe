<?php

namespace App\Models\Territoire;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Localite extends Model
{
    protected $fillable = ['section_communale_id', 'name', 'lat', 'lng'];

    public function sectionCommunale(): BelongsTo
    {
        return $this->belongsTo(SectionCommunale::class);
    }
}
