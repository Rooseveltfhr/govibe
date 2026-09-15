<?php

namespace Modules\AIProvider\Contracts;

use Modules\AIProvider\DTO\ModelDescriptor;
use Modules\AIProvider\Enums\Capability;

/**
 * Contrat minimal d'un fournisseur IA. Le cœur de l'application ne connaît
 * que cette interface : ajouter un fournisseur n'implique aucune modification
 * du routeur ni des services (principe ouvert/fermé).
 */
interface AIProvider
{
    /** Identifiant stable et unique : 'openai', 'anthropic', 'gemini'… */
    public function key(): string;

    /** Nom lisible affiché dans l'administration. */
    public function name(): string;

    /** @return list<Capability> */
    public function capabilities(): array;

    /** @return list<ModelDescriptor> catalogue déclaré par le manifeste */
    public function models(): array;

    /** Le fournisseur dispose-t-il des identifiants nécessaires ? */
    public function isConfigured(): bool;
}
