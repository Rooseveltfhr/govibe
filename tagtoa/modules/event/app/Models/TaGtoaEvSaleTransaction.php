<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TAGTOA EVENT — transaction de vente in-event (paiement par wallet du ticket).
 */
class TaGtoaEvSaleTransaction extends Model
{
    use HasFactory;

    protected $table = 'tagtoa_ev_sale_transactions';

    protected $fillable = [
        'event_id', 'ticket_id', 'items', 'total', 'payment_method', 'client_uuid', 'status',
    ];

    protected $casts = [
        'items'  => 'array',
        'total'  => 'decimal:2',
        'status' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(TaGtoaEvent::class, 'event_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(TaGtoaEvTicket::class, 'ticket_id');
    }
}
