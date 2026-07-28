<?php

namespace Modules\Tagtoa\App\Models\Billing;

use Illuminate\Database\Eloquent\Model;

/**
 * TAGTOA — définition de forfait éditable (surcharge la config `tagtoa.plans`).
 */
class PlanDefinition extends Model
{
    protected $table = 'tagtoa_plans';

    protected $fillable = ['plan_key', 'label', 'price', 'limits', 'sort', 'is_active'];

    protected $casts = [
        'price'     => 'decimal:2',
        'limits'    => 'array',
        'is_active' => 'boolean',
        'sort'      => 'integer',
    ];
}
