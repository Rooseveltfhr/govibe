<?php

namespace Modules\Tagtoa\App\Exceptions;

/**
 * TAGTOA EVENT — solde wallet insuffisant pour un débit contraint.
 */
class InsufficientFundsException extends \RuntimeException
{
    public function __construct(string $message = 'insufficient_funds')
    {
        parent::__construct($message);
    }
}
