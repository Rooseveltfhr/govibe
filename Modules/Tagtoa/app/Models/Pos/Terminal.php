<?php

namespace Modules\Tagtoa\App\Models\Pos;

use App\Models\Vcard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TAGTOA POS — caisse / terminal.
 */
class Terminal extends Model
{
    protected $table = 'tagtoa_pos_terminals';

    protected $fillable = ['vcard_id', 'tenant_id', 'name', 'currency', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function vcard(): BelongsTo
    {
        return $this->belongsTo(Vcard::class, 'vcard_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'terminal_id')->orderBy('sort');
    }

    public function activeProducts(): HasMany
    {
        return $this->products()->where('is_active', true);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'terminal_id');
    }
}
