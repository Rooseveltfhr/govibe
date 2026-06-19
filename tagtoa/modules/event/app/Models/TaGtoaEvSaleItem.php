<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TAGTOA EVENT — article vendu in-event (buvette, stand…).
 */
class TaGtoaEvSaleItem extends Model
{
    use HasFactory;

    protected $table = 'tagtoa_ev_sale_items';

    protected $fillable = ['event_id', 'name', 'price', 'emoji', 'is_active', 'sort'];

    protected $casts = [
        'price'     => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(TaGtoaEvent::class, 'event_id');
    }
}
