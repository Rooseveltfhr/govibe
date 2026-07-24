<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProgramSession extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['day' => 'date', 'featured' => 'boolean', 'active' => 'boolean'];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function speakers(): BelongsToMany
    {
        return $this->belongsToMany(Speaker::class, 'program_session_speaker')
            ->withPivot('role');
    }

    public function typeLabel(): string
    {
        return config('finpo.session_types.'.$this->type, ucfirst((string) $this->type));
    }
}
