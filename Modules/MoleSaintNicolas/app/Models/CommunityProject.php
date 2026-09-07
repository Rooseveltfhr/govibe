<?php

namespace App\Models;

use App\Models\Concerns\HasContentStatus;
use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Projet communautaire mené dans la commune (route, école, adduction d'eau...).
 * Les visiteurs peuvent commenter — un commentaire n'est visible qu'après
 * approbation (voir ProjectComment).
 */
class CommunityProject extends Model
{
    use HasContentStatus, HasSlug;

    protected $fillable = [
        'title', 'slug', 'status', 'description',
        'content_status', 'source_note', 'created_by', 'verified_by', 'verified_at',
    ];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ProjectComment::class);
    }

    public function approvedComments(): HasMany
    {
        return $this->comments()->where('is_approved', true)->latest();
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
