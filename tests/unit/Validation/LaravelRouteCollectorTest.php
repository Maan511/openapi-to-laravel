<?php

use Illuminate\Routing\Router;
use Maan511\OpenapiToLaravel\Validation\LaravelRouteCollector;

describe('LaravelRouteCollector', function (): void {
    beforeEach(function (): void {
        $this->router = Mockery::mock(Router::class);
        $this->collector = new LaravelRouteCollector($this->router);
    });

    afterEach(function (): void {
        Mockery::close();
    });

    describe('construction', function (): void {
        it('should create collector with router dependency', function (): void {
            $router = Mockery::mock(Router::class);
            $collector = new LaravelRouteCollector($router);

            expect($collector)->toBeInstanceOf(LaravelRouteCollector::class);
        });
    });

    describe('collectFiltered method signature', function (): void {
        it('should have correct method signature', function (): void {
            $reflection = new ReflectionClass($this->collector);
            $method = $reflection->getMethod('collectFiltered');

            expect($method->isPublic())->toBeTrue();
            expect($method->getNumberOfParameters())->toBe(4);
        });
    });

    describe('public method availability', function (): void {
        it('should have all required public methods', function (): void {
            $reflection = new ReflectionClass($this->collector);

            $expectedMethods = [
                'collect',
                'collectFiltered',
                'getStatistics',
                'getRouteSignatures',
                'findByPattern',
                'findByMiddleware',
                'getAllMiddleware',
                'getAllRouteNames',
            ];

            foreach ($expectedMethods as $methodName) {
                expect($reflection->hasMethod($methodName))->toBeTrue();
                expect($reflection->getMethod($methodName)->isPublic())->toBeTrue();
            }
        });
    });

    describe('private method availability', function (): void {
        it('should have expected private methods', function (): void {
            $reflection = new ReflectionClass($this->collector);

            $expectedPrivateMethods = [
                'shouldIncludeRoute',
                'isClosureRoute',
                'isFrameworkRoute',
            ];

            foreach ($expectedPrivateMethods as $methodName) {
                expect($reflection->hasMethod($methodName))->toBeTrue();
                expect($reflection->getMethod($methodName)->isPrivate())->toBeTrue();
            }
        });
    });

    describe('basic functionality', function (): void {
        it('should handle empty route collections gracefully', function (): void {
            $routeCollection = Mockery::mock('Illuminate\Routing\RouteCollection');
            $routeCollection->shouldReceive('getIterator')->andReturn(new ArrayIterator([]));
            $this->router->shouldReceive('getRoutes')->andReturn($routeCollection);

            $routes = $this->collector->collect();

            expect($routes)->toBeArray();
            expect($routes)->toHaveCount(0);
        });
    });

    describe('method return types', function (): void {
        it('should return arrays from collection methods', function (): void {
            $routeCollection = Mockery::mock('Illuminate\Routing\RouteCollection');
            $routeCollection->shouldReceive('getIterator')->andReturn(new ArrayIterator([]));
            $routeCollection->shouldReceive('count')->andReturn(0);
            $this->router->shouldReceive('getRoutes')->andReturn($routeCollection);

            expect($this->collector->collect())->toBeArray();
            expect($this->collector->getRouteSignatures())->toBeArray();
            expect($this->collector->findByPattern('api/*'))->toBeArray();
            expect($this->collector->findByMiddleware('api'))->toBeArray();
            expect($this->collector->getAllMiddleware())->toBeArray();
            expect($this->collector->getAllRouteNames())->toBeArray();
            expect($this->collector->getStatistics())->toBeArray();
        });
    });
});
