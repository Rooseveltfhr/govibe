<?php

namespace Modules\AIRouter\Routing;

use Modules\AIProvider\Contracts\AIProvider;
use Modules\AIProvider\DTO\ModelDescriptor;
use Modules\AIProvider\Exceptions\NoProviderAvailableException;
use Modules\AIProvider\Exceptions\ProviderException;
use Modules\AIProvider\Health\CircuitBreaker;
use Modules\AIProvider\Health\ProviderMetrics;
use Modules\AIProvider\Registry\ModelCatalog;
use Modules\AIProvider\Registry\ProviderRegistry;
use Modules\AIRouter\DTO\Candidate;
use Modules\AIRouter\DTO\RoutingContext;
use Modules\AIRouter\DTO\RoutingDecision;
use Throwable;

/**
 * Orchestrateur du routage : classe les candidats, exécute l'appel sur le
 * meilleur, et bascule sur le suivant en cas de panne.
 *
 * L'exécution est générique (un `callable`) pour que chaque capacité — chat
 * aujourd'hui, images ou transcription demain — hérite du même comportement
 * de bascule, de mesure et de disjoncteur sans le réimplémenter.
 */
class AiRouter
{
    public function __construct(
        private readonly ModelCatalog $catalog,
        private readonly ProviderRegistry $registry,
        private readonly CandidateFilter $filter,
        private readonly Scorer $scorer,
        private readonly CircuitBreaker $breaker,
        private readonly ProviderMetrics $metrics,
    ) {}

    /**
     * Candidats éligibles, classés du meilleur au moins bon.
     *
     * @return list<Candidate>
     */
    public function rank(RoutingContext $context): array
    {
        $models = $this->catalog->forCapability($context->capability);

        $health = [];
        $breakerAllows = [];

        foreach ($models as $model) {
            $key = $model->providerKey;

            if (! isset($health[$key])) {
                $health[$key] = $this->metrics->health($key);
                $breakerAllows[$key] = $this->breaker->allows($key);
            }
        }

        return $this->scorer->rank(
            $this->filter->filter($models, $context, $health, $breakerAllows),
            $context,
        );
    }

    /**
     * Description effective d'un modèle (manifeste + surcharges en base),
     * utilisée notamment pour calculer le coût réel après l'appel.
     */
    public function describe(string $providerKey, string $modelKey): ?ModelDescriptor
    {
        return $this->catalog->find($providerKey.'/'.$modelKey);
    }

    /**
     * Exécute `$callback` sur le meilleur candidat disponible, avec bascule
     * automatique. Le retour porte à la fois le résultat et la trace de la
     * décision, journalisée ensuite dans `ai_requests`.
     *
     * @template TResult
     *
     * @param  callable(AIProvider, ModelDescriptor): TResult  $callback
     * @return array{result: TResult, decision: RoutingDecision, latency_ms: int}
     *
     * @throws NoProviderAvailableException
     * @throws ProviderException
     */
    public function execute(RoutingContext $context, callable $callback): array
    {
        $candidates = $this->rank($context);

        if ($candidates === []) {
            throw new NoProviderAvailableException(
                'Aucun fournisseur disponible pour la capacité '.$context->capability->value,
            );
        }

        $failed = [];
        $attempts = 0;
        $lastError = null;

        foreach ($candidates as $candidate) {
            if ($attempts >= $context->maxAttempts) {
                break;
            }

            $provider = $this->registry->get($candidate->providerKey());

            if ($provider === null) {
                continue;
            }

            $attempts++;
            $startedAt = hrtime(true);

            try {
                $result = $callback($provider, $candidate->model);

                $latencyMs = $this->elapsedMs($startedAt);
                $this->metrics->record($candidate->providerKey(), $latencyMs, true);
                $this->breaker->recordSuccess($candidate->providerKey());

                return [
                    'result' => $result,
                    'decision' => new RoutingDecision(
                        providerKey: $candidate->providerKey(),
                        modelKey: $candidate->model->key,
                        reason: $candidate->reason,
                        attempts: $attempts,
                        failedProviders: $failed,
                    ),
                    'latency_ms' => $latencyMs,
                ];
            } catch (ProviderException $e) {
                $this->metrics->record($candidate->providerKey(), $this->elapsedMs($startedAt), false);
                $this->breaker->recordFailure($candidate->providerKey());
                $failed[] = $candidate->providerKey();
                $lastError = $e;

                // Requête invalide : basculer ne changerait rien, on remonte.
                if (! $e->retryable) {
                    throw $e;
                }
            } catch (Throwable $e) {
                $this->metrics->record($candidate->providerKey(), $this->elapsedMs($startedAt), false);
                $this->breaker->recordFailure($candidate->providerKey());
                $failed[] = $candidate->providerKey();
                $lastError = $e;
            }
        }

        throw new NoProviderAvailableException(
            sprintf(
                'Tous les fournisseurs ont échoué (%d tentative(s)) : %s',
                $attempts,
                $lastError?->getMessage() ?? 'raison inconnue',
            ),
            $failed,
        );
    }

    private function elapsedMs(float|int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }
}
