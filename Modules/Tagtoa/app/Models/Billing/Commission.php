<?php

namespace Modules\Tagtoa\App\Models\Billing;

use Illuminate\Database\Eloquent\Model;

/**
 * TAGTOA Billing — commission prélevée sur une vente marchand.
 */
class Commission extends Model
{
    public const STATUS_VOID    = 0;
    public const STATUS_ACCRUED = 1;
    public const STATUS_SETTLED = 2;

    protected $table = 'tagtoa_commissions';

    protected $fillable = [
        'tenant_id', 'source_type', 'source_id', 'module',
        'gross_amount', 'commission_amount', 'net_amount',
        'commission_percent', 'commission_fixed', 'currency', 'status',
    ];

    protected $casts = [
        'gross_amount'       => 'decimal:2',
        'commission_amount'  => 'decimal:2',
        'net_amount'         => 'decimal:2',
        'commission_percent' => 'decimal:2',
        'commission_fixed'   => 'decimal:2',
        'status'             => 'integer',
    ];
}
