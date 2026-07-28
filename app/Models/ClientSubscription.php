<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientSubscription extends Model
{
    protected $fillable = [
        'client_id', 'plan_slug', 'plan_name', 'billing_cycle',
        'price', 'currency', 'starts_at', 'ends_at',
        'status', 'notes', 'assigned_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at'   => 'date',
            'price'     => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'active'    => '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">Actif</span>',
            'trial'     => '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">Essai</span>',
            'expired'   => '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">Expiré</span>',
            'cancelled' => '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-600">Annulé</span>',
            default     => '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700">'.$this->status.'</span>',
        };
    }
}
