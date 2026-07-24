<?php

namespace Modules\Tagtoa\App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class TagtoaServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Tagtoa';

    protected string $moduleNameLower = 'tagtoa';

    public function boot(): void
    {
        $this->registerConfig();
        $this->registerViews();
        $this->overrideAuthViews();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/migrations'));
        $this->loadJsonTranslationsFrom(module_path($this->moduleName, 'resources/lang'));
    }

    /**
     * Remplace certaines vues du cœur (ex. auth/login) par les versions TAGTOA,
     * de façon VERSIONNÉE et réversible : on prépend un chemin de vues, donc
     * `view('auth.login')` résout d'abord `resources/views/overrides/auth/login`.
     * Les vues non présentes dans overrides retombent sur le cœur (sûr).
     */
    protected function overrideAuthViews(): void
    {
        try {
            $override = module_path($this->moduleName, 'resources/views/overrides');
            if (is_dir($override)) {
                view()->getFinder()->prependLocation($override);
            }
        } catch (\Throwable $e) {
            // en cas d'échec, on garde la vue du cœur (login jamais cassé)
        }
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            module_path($this->moduleName, 'config/config.php') => config_path($this->moduleNameLower.'.php'),
        ], 'config');

        $this->mergeConfigFrom(
            module_path($this->moduleName, 'config/config.php'),
            $this->moduleNameLower
        );
    }

    public function registerViews(): void
    {
        $viewPath   = resource_path('views/modules/'.$this->moduleNameLower);
        $sourcePath = module_path($this->moduleName, 'resources/views');

        $this->publishes([$sourcePath => $viewPath], ['views', $this->moduleNameLower.'-module-views']);

        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->moduleNameLower);
    }

    public function provides(): array
    {
        return [];
    }

    private function getPublishableViewPaths(): array
    {
        $paths = [];
        foreach (config('view.paths') as $path) {
            if (is_dir($path.'/modules/'.$this->moduleNameLower)) {
                $paths[] = $path.'/modules/'.$this->moduleNameLower;
            }
        }

        return $paths;
    }
}
