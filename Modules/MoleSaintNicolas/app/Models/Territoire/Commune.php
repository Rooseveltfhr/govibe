<?php

namespace App\Models\Territoire;

use App\Models\Concerns\HasContentStatus;
use App\Models\Concerns\HasSlug;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Commune extends Model
{
    use HasContentStatus, HasSlug;

    protected $fillable = [
        'arrondissement_id', 'name', 'slug', 'description',
        'population', 'population_year', 'lat', 'lng',
        'content_status', 'source_note', 'created_by', 'verified_by', 'verified_at',
    ];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }

    public function arrondissement(): BelongsTo
    {
        return $this->belongsTo(Arrondissement::class);
    }

    public function sectionsCommunales(): HasMany
    {
        // Sans tri explicite, l'ordre renvoyé par la requête n'est pas garanti
        // (dépend du moteur/plan d'exécution) — l'ordre de création est
        // l'ordre d'affichage attendu (menu principal, fiche commune).
        return $this->hasMany(SectionCommunale::class)->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
