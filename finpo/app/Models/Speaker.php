<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Speaker extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['featured' => 'boolean', 'active' => 'boolean'];
    }

    public function sessions(): BelongsToMany
    {
        return $this->belongsToMany(ProgramSession::class, 'program_session_speaker')
            ->withPivot('role');
    }

    public function categoryLabel(): string
    {
        return config('finpo.speaker_categories.'.$this->category, ucfirst((string) $this->category));
    }
}
