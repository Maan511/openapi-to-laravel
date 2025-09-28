<?php

namespace Maan511\OpenapiToLaravel;

use Illuminate\Support\ServiceProvider;
use Maan511\OpenapiToLaravel\Console\GenerateFormRequestsCommand;
use Maan511\OpenapiToLaravel\Console\ValidateRoutesCommand;
use Override;

/**
 * Service provider for OpenAPI to Laravel FormRequest package
 */
class OpenapiToLaravelServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        // Register services if needed in the future
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Only register commands when running in console
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateFormRequestsCommand::class,
                ValidateRoutesCommand::class,
            ]);
        }
    }
}
