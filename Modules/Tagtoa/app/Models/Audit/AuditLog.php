<?php

namespace Modules\Tagtoa\App\Models\Audit;

use Illuminate\Database\Eloquent\Model;

/**
 * TAGTOA AUDIT — entrée du journal d'actions (création seule, pas d'update).
 */
class AuditLog extends Model
{
    protected $table = 'tagtoa_audit_logs';

    public $timestamps = false; // seul created_at est renseigné

    protected $fillable = [
        'tenant_id', 'user_id', 'user_name', 'action', 'subject_type',
        'subject_id', 'description', 'ip', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function getActionLabelAttribute(): string
    {
        return \Modules\Tagtoa\App\Services\Audit\AuditService::actionLabel($this->action);
    }
}
