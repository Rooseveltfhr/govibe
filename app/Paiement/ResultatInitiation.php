<?php

namespace App\Paiement;

/** Ce qu'un pilote rend après avoir ouvert un paiement chez la passerelle. */
class ResultatInitiation
{
    public function __construct(
        public readonly bool $reussi,
        public readonly ?string $urlRedirection = null,
        public readonly ?string $jeton = null,
        public readonly ?string $erreur = null,
        public readonly array $charge = [],
    ) {}

    public static function redirection(string $url, ?string $jeton = null, array $charge = []): self
    {
        return new self(true, $url, $jeton, null, $charge);
    }

    public static function echec(string $erreur, array $charge = []): self
    {
        return new self(false, null, null, $erreur, $charge);
    }
}
