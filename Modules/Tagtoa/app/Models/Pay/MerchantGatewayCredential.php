<?php

namespace Modules\Tagtoa\App\Models\Pay;

use Illuminate\Database\Eloquent\Model;

/**
 * TAGTOA Pay — identifiants API du MARCHAND pour un driver (chiffrés au repos).
 * Quand ils servent, l'encaissement va directement sur le compte du marchand.
 */
class MerchantGatewayCredential extends Model
{
    protected $table = 'tagtoa_merchant_gateway_credentials';

    protected $fillable = ['tenant_id', 'driver', 'values'];

    protected $casts = ['values' => 'encrypted:array'];

    protected $hidden = ['values'];
}
