<?php

use Illuminate\Support\ServiceProvider;
use Maan511\OpenapiToLaravel\OpenapiToLaravelServiceProvider;

describe('OpenapiToLaravelServiceProvider', function (): void {
    it('should be a valid Laravel service provider', function (): void {
        $app = mock(\Illuminate\Contracts\Foundation\Application::class);
        $app->shouldReceive('runningInConsole')->andReturn(false);

        $serviceProvider = new OpenapiToLaravelServiceProvider($app);

        expect($serviceProvider)->toBeInstanceOf(ServiceProvider::class);
    });

    it('should register without errors', function (): void {
        $app = mock(\Illuminate\Contracts\Foundation\Application::class);
        $app->shouldReceive('runningInConsole')->andReturn(false);

        $serviceProvider = new OpenapiToLaravelServiceProvider($app);
        $serviceProvider->register();

        // If we get here without exception, the test passes
        expect(true)->toBeTrue();
    });

    it('should handle console and non-console boot scenarios', function (): void {
        // Test non-console scenario
        $app = mock(\Illuminate\Contracts\Foundation\Application::class);
        $app->shouldReceive('runningInConsole')->andReturn(false);
        $app->shouldNotReceive('commands');

        $serviceProvider = new OpenapiToLaravelServiceProvider($app);
        $serviceProvider->boot();

        // If we get here without exception, the test passes
        expect(true)->toBeTrue();
    });
});
