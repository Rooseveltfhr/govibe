<?php

namespace App\Paiement;

/**
 * Verdict de la passerelle sur un paiement.
 *
 * Le montant et la devise sont rendus tels que la passerelle les a vus : c'est
 * ce qui permet de refuser un paiement dont la somme ne correspond pas à ce qui
 * était demandé.
 */
class ResultatVerification
{
    public function __construct(
        public readonly string $statut,           // reussi · en_attente · echoue
        public readonly ?string $referenceExterne = null,
        public readonly ?float $montant = null,
        public readonly ?string $devise = null,
        public readonly ?string $erreur = null,
        public readonly array $charge = [],
    ) {}

    public static function reussi(string $reference, ?float $montant, ?string $devise, array $charge = []): self
    {
        return new self('reussi', $reference, $montant, $devise, null, $charge);
    }

    public static function enAttente(array $charge = []): self
    {
        return new self('en_attente', null, null, null, null, $charge);
    }

    public static function echoue(string $erreur, array $charge = []): self
    {
        return new self('echoue', null, null, null, $erreur, $charge);
    }
}
