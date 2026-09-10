<?php

namespace App\Paiement;

use App\Paiement\Pilotes\PiloteApi;

class RegistrePilotes
{
    /** @var array<string,PiloteApi> */
    private array $pilotes = [];

    public function __construct()
    {
        foreach (config('paiement.pilotes', []) as $classe) {
            $pilote = app($classe);
            $this->pilotes[$classe::cle()] = $pilote;
        }
    }

    public function trouver(?string $cle): ?PiloteApi
    {
        return $cle ? ($this->pilotes[$cle] ?? null) : null;
    }

    /** @return array<string,PiloteApi> */
    public function tous(): array
    {
        return $this->pilotes;
    }

    public function cles(): array
    {
        return array_keys($this->pilotes);
    }
}
