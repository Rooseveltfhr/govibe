<?php

namespace Modules\Tagtoa\App\Models\Stand;

use Illuminate\Database\Eloquent\Model;

/**
 * TAGTOA — une tentative de réclamation.
 *
 * Le code essayé n'est JAMAIS enregistré, même faux : une frappe malheureuse
 * peut être le code d'un stand voisin, et le journal deviendrait une liste de
 * codes valides en clair.
 */
class StandClaimAttempt extends Model
{
    protected $table = 'tagtoa_stand_claim_attempts';

    public const UPDATED_AT = null;

    protected $fillable = ['stand_id', 'public_id_tried', 'succeeded', 'ip', 'user_agent'];

    protected $casts = ['succeeded' => 'boolean'];
}
