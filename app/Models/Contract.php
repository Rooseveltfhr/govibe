<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contract extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference', 'client_id', 'template_id',
        'title', 'content',
        'start_date', 'end_date', 'value',
        'status', 'signed_at', 'sent_at', 'sent_by', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date'   => 'date',
            'value'      => 'decimal:2',
            'signed_at'  => 'datetime',
            'sent_at'    => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ContractTemplate::class, 'template_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function generateReference(): string
    {
        return 'CTR-' . date('Y') . '-' . strtoupper(substr(uniqid(), -5));
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'active'     => '<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">Actif</span>',
            'draft'      => '<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700">Brouillon</span>',
            'expired'    => '<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">Expiré</span>',
            'terminated' => '<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-600">Résilié</span>',
            default      => '<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">'.$this->status.'</span>',
        };
    }
}
