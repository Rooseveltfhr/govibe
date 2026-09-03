<?php

namespace Modules\AIProvider\Providers;

use Illuminate\Contracts\Cache\Repository as Cache;
use Modules\AIProvider\Console\ProvidersStatusCommand;
use Modules\AIProvider\Console\SyncCatalogCommand;
use Modules\AIProvider\Health\CircuitBreaker;
use Modules\AIProvider\Health\ProviderMetrics;
use Modules\AIProvider\Registry\ModelCatalog;
use Modules\AIProvider\Registry\ProviderRegistry;
use Modules\AIProvider\Support\CircuitBreakerState;
use Nwidart\Modules\Support\ModuleServiceProvider;

class AIProviderServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'AIProvider';

    protected string $nameLower = 'aiprovider';

    /** @var string[] */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /** @var string[] */
    protected array $commands = [
        SyncCatalogCommand::class,
        ProvidersStatusCommand::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->singleton(ProviderRegistry::class, function (): ProviderRegistry {
            /** @var array<string, array<string, mixed>> $definitions */
            $definitions = (array) config('aiprovider.providers', []);

            return new ProviderRegistry($definitions);
        });

        $this->app->singleton(ModelCatalog::class, fn ($app): ModelCatalog => new ModelCatalog(
            $app->make(ProviderRegistry::class),
        ));

        $this->app->singleton(CircuitBreakerState::class, fn (): CircuitBreakerState => new CircuitBreakerState(
            (int) config('aiprovider.circuit_breaker.failure_threshold', 5),
            (int) config('aiprovider.circuit_breaker.cooldown_seconds', 60),
        ));

        $this->app->singleton(CircuitBreaker::class, fn ($app): CircuitBreaker => new CircuitBreaker(
            $app->make(Cache::class),
            $app->make(CircuitBreakerState::class),
        ));

        $this->app->singleton(ProviderMetrics::class, fn ($app): ProviderMetrics => new ProviderMetrics(
            $app->make(Cache::class),
            (int) config('aiprovider.metrics.window_seconds', 300),
        ));
    }
}
