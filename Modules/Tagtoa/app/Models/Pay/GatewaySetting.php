<?php

namespace Modules\Tagtoa\App\Models\Pay;

use Illuminate\Database\Eloquent\Model;

/**
 * TAGTOA Pay — réglage PLATEFORME d'une passerelle (super_admin).
 * Une ligne = une surcharge du registre codé (Support/PaymentGateway).
 */
class GatewaySetting extends Model
{
    protected $table = 'tagtoa_gateway_settings';

    protected $fillable = [
        'gateway', 'is_enabled', 'credential_mode', 'fee_percent', 'fee_fixed', 'notes',
    ];

    protected $casts = [
        'is_enabled'  => 'boolean',
        'fee_percent' => 'decimal:2',
        'fee_fixed'   => 'decimal:2',
    ];
}
