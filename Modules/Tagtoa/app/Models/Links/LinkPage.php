<?php

namespace Modules\Tagtoa\App\Models\Links;

use App\Models\Vcard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Tagtoa\App\Models\Pay\PaymentPage;

/**
 * TAGTOA Links — page Linktree-style (tagtoa.com/links/{alias}).
 */
class LinkPage extends Model
{
    public const THEMES = ['dark', 'light', 'blue'];

    protected $table = 'tagtoa_link_pages';

    protected $fillable = [
        'vcard_id', 'tenant_id', 'title', 'alias', 'bio', 'theme', 'avatar_path',
        'donation_label', 'pay_page_id', 'is_active', 'views',
    ];

    protected $casts = ['is_active' => 'boolean', 'views' => 'integer'];

    public static function generateAlias(string $base): string
    {
        $slug = Str::slug($base) ?: 'links';
        $alias = $slug; $i = 1;
        while (static::query()->where('alias', $alias)->exists()) {
            $alias = $slug.'-'.(++$i);
        }
        return $alias;
    }

    public function vcard(): BelongsTo
    {
        return $this->belongsTo(Vcard::class, 'vcard_id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(Link::class, 'link_page_id')->orderBy('sort');
    }

    public function activeLinks(): HasMany
    {
        return $this->links()->where('is_active', true);
    }

    public function payPage(): BelongsTo
    {
        return $this->belongsTo(PaymentPage::class, 'pay_page_id');
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path ? Storage::url($this->avatar_path) : null;
    }

    public function getPublicUrlAttribute(): string
    {
        return url('/links/'.$this->alias);
    }
}
