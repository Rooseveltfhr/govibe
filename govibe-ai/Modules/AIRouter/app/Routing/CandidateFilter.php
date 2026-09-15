<?php

namespace Modules\AIRouter\Routing;

use Modules\AIProvider\DTO\ModelDescriptor;
use Modules\AIProvider\DTO\ProviderHealth;
use Modules\AIRouter\DTO\Candidate;
use Modules\AIRouter\DTO\RoutingContext;

/**
 * Réduction du catalogue aux candidats éligibles. Classe pure : la santé et
 * l'état des disjoncteurs sont fournis en entrée, donc entièrement testable.
 */
class CandidateFilter
{
    /**
     * @param  list<ModelDescriptor>  $models
     * @param  array<string, ProviderHealth>  $health  par clé de fournisseur
     * @param  array<string, bool>  $breakerAllows  par clé de fournisseur (absent = autorisé)
     * @return list<Candidate>
     */
    public function filter(array $models, RoutingContext $context, array $health = [], array $breakerAllows = []): array
    {
        $candidates = [];

        foreach ($models as $model) {
            if (! $model->supports($context->capability)) {
                continue;
            }

            if (in_array($model->providerKey, $context->blockedProviders, true)) {
                continue;
            }

            if (($breakerAllows[$model->providerKey] ?? true) === false) {
                continue;
            }

            if ($context->forcedModel !== null && ! $this->matchesForcedModel($model, $context->forcedModel)) {
                continue;
            }

            // Le contexte doit tenir : inutile de router vers un modèle dont la
            // fenêtre est trop courte, il échouerait à coup sûr.
            $needed = $context->estimatedInputTokens + $context->estimatedOutputTokens;

            if ($needed > 0 && $model->contextWindow > 0 && $needed > $model->contextWindow) {
                continue;
            }

            $candidates[] = new Candidate(
                $model,
                $health[$model->providerKey] ?? ProviderHealth::unknown($model->providerKey),
            );
        }

        return $candidates;
    }

    private function matchesForcedModel(ModelDescriptor $model, string $forced): bool
    {
        return $model->key === $forced
            || $model->publicId() === $forced
            || str_ends_with($forced, '/'.$model->key);
    }
}
