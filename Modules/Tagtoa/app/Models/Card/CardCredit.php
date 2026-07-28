<?php

namespace Modules\Tagtoa\App\Models\Card;

use Illuminate\Database\Eloquent\Model;

/**
 * TAGTOA CARD — solde de crédits d'activation de cartes officielles d'un tenant.
 * Disponible = granted - used.
 */
class CardCredit extends Model
{
    protected $table = 'tagtoa_card_credits';

    protected $fillable = ['tenant_id', 'granted', 'used'];

    protected $casts = ['granted' => 'integer', 'used' => 'integer'];

    public function getAvailableAttribute(): int
    {
        return max(0, (int) $this->granted - (int) $this->used);
    }
}
