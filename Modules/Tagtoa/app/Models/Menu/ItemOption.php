<?php

namespace Modules\Tagtoa\App\Models\Menu;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TAGTOA MENU — groupe d'options d'un plat (ex. « Taille », « Suppléments »).
 */
class ItemOption extends Model
{
    protected $table = 'tagtoa_menu_item_options';

    protected $fillable = ['item_id', 'name', 'required', 'multiple', 'sort'];

    protected $casts = [
        'required' => 'boolean',
        'multiple' => 'boolean',
        'sort'     => 'integer',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function choices(): HasMany
    {
        return $this->hasMany(ItemOptionChoice::class, 'option_id')->orderBy('sort');
    }
}
