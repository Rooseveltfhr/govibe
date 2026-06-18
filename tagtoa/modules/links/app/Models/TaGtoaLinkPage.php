<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * TAGTOA LINKS — page Linktree-style.
 *
 * @property int    $id
 * @property int    $vcard_id
 * @property string $tenant_id
 * @property string $title
 * @property string $alias
 * @property string $bio
 * @property string $theme
 * @property string $donation_label
 * @property int    $pay_page_id
 * @property bool   $is_active
 * @property int    $views
 */
class TaGtoaLinkPage extends Model implements HasMedia
{
    use HasFactory;
    use BelongsToTenant;
    use InteractsWithMedia;

    public const THEMES = ['dark', 'light', 'blue'];

    protected $table = 'tagtoa_link_pages';

    protected $fillable = [
        'vcard_id', 'tenant_id', 'title', 'alias', 'bio', 'theme',
        'donation_label', 'pay_page_id', 'is_active', 'views',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'views'     => 'integer',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')->singleFile();
    }

    public static function generateAlias(string $base): string
    {
        $slug  = Str::slug($base) ?: 'links';
        $alias = $slug;
        $i     = 1;
        while (static::query()->where('alias', $alias)->exists()) {
            $alias = $slug . '-' . (++$i);
        }
        return $alias;
    }

    public function vcard(): BelongsTo
    {
        return $this->belongsTo(Vcard::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(TaGtoaLink::class, 'link_page_id')->orderBy('sort');
    }

    public function activeLinks(): HasMany
    {
        return $this->links()->where('is_active', true);
    }

    /** Page de paiement liée pour les dons (si configurée). */
    public function payPage(): BelongsTo
    {
        return $this->belongsTo(TaGtoaPaymentPage::class, 'pay_page_id');
    }

    public function getAvatarUrlAttribute(): ?string
    {
        $m = $this->getFirstMedia('avatar');
        return $m ? $m->getUrl() : null;
    }

    public function getPublicUrlAttribute(): string
    {
        return url('/links/' . $this->alias);
    }
}
