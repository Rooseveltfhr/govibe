<?php

namespace Modules\Tagtoa\App\Services\Currency;

/** Taux refusé : porte un code court ('invalid_rate', 'jump_needs_confirmation'…) que l'écran traduit. */
class RateRuleException extends \RuntimeException
{
}
