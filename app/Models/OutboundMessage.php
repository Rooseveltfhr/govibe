<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class OutboundMessage extends Model
{
    protected $table = 'outbound_messages';

    protected $fillable = [
        'channel', 'recipient', 'recipient_name', 'subject', 'body',
        'status', 'error', 'context_type', 'context_id', 'sent_by', 'sent_at',
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function context(): MorphTo
    {
        return $this->morphTo();
    }

    public function getChannelIconAttribute(): string
    {
        return match ($this->channel) {
            'whatsapp' => '💬',
            'email'    => '📧',
            'sms'      => '📱',
            default    => '🔔',
        };
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'sent'    => '<span class="text-green-600 font-semibold">✓ Envoyé</span>',
            'failed'  => '<span class="text-red-500 font-semibold">✗ Échec</span>',
            default   => '<span class="text-yellow-500">⏳ En attente</span>',
        };
    }
}
