<?php

namespace Modules\Tagtoa\App\Models\Pay;

use Illuminate\Database\Eloquent\Model;

/**
 * TAGTOA Pay — identifiants API d'un driver (chiffrés au repos).
 *
 * `values` est chiffré avec APP_KEY : il n'est JAMAIS lisible dans un dump SQL.
 * Il est aussi masqué à la sérialisation pour qu'un `toJson()` accidentel dans
 * une vue ou un log ne puisse pas le divulguer.
 */
class GatewayCredential extends Model
{
    protected $table = 'tagtoa_gateway_credentials';

    protected $fillable = ['driver', 'values'];

    protected $casts = ['values' => 'encrypted:array'];

    protected $hidden = ['values'];
}
