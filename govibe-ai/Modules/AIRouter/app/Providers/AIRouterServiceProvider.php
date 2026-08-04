<?php

namespace Modules\AIRouter\Providers;

use Modules\AIProvider\Health\CircuitBreaker;
use Modules\AIProvider\Health\ProviderMetrics;
use Modules\AIProvider\Registry\ModelCatalog;
use Modules\AIProvider\Registry\ProviderRegistry;
use Modules\AIRouter\Routing\AiRouter;
use Modules\AIRouter\Routing\CandidateFilter;
use Modules\AIRouter\Routing\Scorer;
use Modules\AIRouter\Support\LanguageDetector;
use Nwidart\Modules\Support\ModuleServiceProvider;

class AIRouterServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'AIRouter';

    protected string $nameLower = 'airouter';

    /** @var string[] */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->singleton(CandidateFilter::class);

        $this->app->singleton(Scorer::class, function (): Scorer {
            /** @var array<string, float> $weights */
            $weights = (array) config('govibe.router.weights', []);

            return new Scorer($weights);
        });

        $this->app->singleton(LanguageDetector::class, function (): LanguageDetector {
            /** @var list<string> $locales */
            $locales = array_values((array) config('govibe.locales', ['fr', 'ht', 'en', 'es']));

            return new LanguageDetector($locales);
        });

        $this->app->singleton(AiRouter::class, fn ($app): AiRouter => new AiRouter(
            $app->make(ModelCatalog::class),
            $app->make(ProviderRegistry::class),
            $app->make(CandidateFilter::class),
            $app->make(Scorer::class),
            $app->make(CircuitBreaker::class),
            $app->make(ProviderMetrics::class),
        ));
    }
}
