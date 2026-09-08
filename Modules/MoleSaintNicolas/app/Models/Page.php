<?php

namespace App\Models;

use App\Models\Concerns\HasContentStatus;
use App\Models\Concerns\HasSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Page statique éditable (à propos, mentions légales...) — brief §8, table `pages`.
 */
class Page extends Model
{
    use HasContentStatus, HasSlug;

    protected $fillable = [
        'title', 'slug', 'meta_description', 'body',
        'content_status', 'source_note', 'created_by', 'verified_by', 'verified_at',
    ];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
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
