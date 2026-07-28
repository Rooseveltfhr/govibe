<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Formation extends Model
{
    protected $fillable = [
        'nom', 'description', 'date_debut', 'date_fin',
        'lieu', 'whatsapp_link', 'max_participants', 'active',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'active' => 'boolean',
    ];

    public function inscriptions()
    {
        return $this->hasMany(Inscription::class);
    }

    public function placesRestantes(): int
    {
        return $this->max_participants - $this->inscriptions()->count();
    }
}
