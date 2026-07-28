<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BootcampRegistration extends Model
{
    protected $table = 'bootcamp_registrations';

    protected $fillable = [
        'full_name', 'email', 'phone', 'country', 'profession', 'organization',
        'module_choice', 'payment_method', 'payment_proof_path', 'motivation',
        'amount', 'currency', 'status', 'admin_notes', 'approved_at', 'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'amount'      => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public static array $MODULES = [
        'module_1' => ['label' => 'Module 1 — Comprendre l\'IA',         'price_htg' => 3500],
        'module_2' => ['label' => 'Module 2 — IA Générative',            'price_htg' => 3500],
        'module_3' => ['label' => 'Module 3 — Productivité avec l\'IA',  'price_htg' => 3500],
        'module_4' => ['label' => 'Module 4 — IA pour les Métiers',      'price_htg' => 3500],
        'module_5' => ['label' => 'Module 5 — Création de Chatbots IA',  'price_htg' => 3500],
        'module_6' => ['label' => 'Module 6 — Création d\'Assistants IA','price_htg' => 3500],
        'module_7' => ['label' => 'Module 7 — Création d\'AI Agents',    'price_htg' => 3500],
        'premium'  => ['label' => 'Bootcamp Premium (7 modules)',         'price_usd' => 100],
    ];

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getModuleLabelAttribute(): string
    {
        return self::$MODULES[$this->module_choice]['label'] ?? $this->module_choice;
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'approved' => '<span class="badge bg-success">Approuvé</span>',
            'rejected' => '<span class="badge bg-danger">Refusé</span>',
            default    => '<span class="badge bg-warning text-dark">En attente</span>',
        };
    }
}
