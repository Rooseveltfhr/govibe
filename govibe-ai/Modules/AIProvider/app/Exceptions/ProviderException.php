<?php

namespace Modules\AIProvider\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Échec d'appel à un fournisseur. `retryable` indique au routeur s'il doit
 * basculer vers le candidat suivant (panne, quota, temps dépassé) ou non
 * (requête invalide — réessayer ailleurs échouerait pareillement).
 */
class ProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $providerKey = '',
        public readonly bool $retryable = true,
        public readonly ?int $httpStatus = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function fromHttpStatus(string $providerKey, int $status, string $body): self
    {
        // 4xx (hors 408/409/429) = faute du client : basculer n'aide pas.
        $retryable = $status >= 500 || in_array($status, [408, 409, 429], true);

        return new self(
            sprintf('Le fournisseur %s a répondu %d : %s', $providerKey, $status, mb_substr($body, 0, 500)),
            $providerKey,
            $retryable,
            $status,
        );
    }
}
