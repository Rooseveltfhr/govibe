<?php

namespace Modules\AIProvider\Exceptions;

use RuntimeException;

class NoProviderAvailableException extends RuntimeException
{
    /** @param list<string> $attempted */
    public function __construct(string $message, public readonly array $attempted = [])
    {
        parent::__construct($message);
    }
}
