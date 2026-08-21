<?php

namespace Modules\Tagtoa\App\Models\Menu;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TAGTOA MENU — choix possible d'un groupe d'options (ex. « Grand » +50 HTG).
 */
class ItemOptionChoice extends Model
{
    protected $table = 'tagtoa_menu_item_option_choices';

    protected $fillable = ['option_id', 'label', 'price_delta', 'sort'];

    protected $casts = [
        'price_delta' => 'decimal:2',
        'sort'        => 'integer',
    ];

    public function option(): BelongsTo
    {
        return $this->belongsTo(ItemOption::class, 'option_id');
    }
}
