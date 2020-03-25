<?php

namespace BeyondCode\LaravelScopeChecks;

use Illuminate\Support\ServiceProvider;

class LaravelScopeChecksServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/scope-checks.php', 'scope-checks');
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/scope-checks.php' => config_path('scope-checks.php'),
            ]);
        }
    }
}
