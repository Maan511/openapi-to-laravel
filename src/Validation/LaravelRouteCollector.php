<?php

namespace Maan511\OpenapiToLaravel\Validation;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Maan511\OpenapiToLaravel\Models\LaravelRoute;

/**
 * Extracts and normalizes Laravel application routes
 */
class LaravelRouteCollector
{
    public function __construct(
        private readonly Router $router
    ) {}

    /**
     * Collect all Laravel routes that should be validated
     *
     * @param  array<string, mixed>  $options
     * @return array<LaravelRoute>
     */
    public function collect(array $options = []): array
    {
        $routes = [];
        $routeCollection = $this->router->getRoutes();

        foreach ($routeCollection as $route) {
            if ($this->shouldIncludeRoute($route, $options)) {
                $routes[] = LaravelRoute::fromLaravelRoute($route);
            }
        }

        return $routes;
    }

    /**
     * Collect routes filtered by specific criteria
     *
     * @param  array<string>  $includePatterns
     * @param  array<string>  $excludeMiddleware
     * @param  array<string>  $includeDomains
     * @param  array<string>  $excludePatterns
     * @return array<LaravelRoute>
     */
    public function collectFiltered(
        array $includePatterns = [],
        array $excludeMiddleware = [],
        array $includeDomains = [],
        array $excludePatterns = []
    ): array {
        return $this->collect([
            'include_patterns' => $includePatterns,
            'exclude_middleware' => $excludeMiddleware,
            'include_domains' => $includeDomains,
            'exclude_patterns' => $excludePatterns,
        ]);
    }

    /**
     * Get routes statistics
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function getStatistics(array $options = []): array
    {
        $allRoutes = $this->router->getRoutes();
        $filteredRoutes = $this->collect($options);

        $methodCounts = [];
        $middlewareCounts = [];

        foreach ($filteredRoutes as $route) {
            // Count methods
            foreach ($route->methods as $method) {
                $methodCounts[$method] = ($methodCounts[$method] ?? 0) + 1;
            }

            // Count middleware
            foreach ($route->middleware as $middleware) {
                $middlewareCounts[$middleware] = ($middlewareCounts[$middleware] ?? 0) + 1;
            }
        }

        return [
            'total_routes' => $allRoutes->count(),
            'filtered_routes' => count($filteredRoutes),
            'method_distribution' => $methodCounts,
            'middleware_usage' => $middlewareCounts,
            'api_routes' => count(array_filter($filteredRoutes, fn (LaravelRoute $r): bool => $r->isApiRoute())),
        ];
    }

    /**
     * Check if a route should be included in collection
     *
     * @param  array<string, mixed>  $options
     */
    private function shouldIncludeRoute(Route $route, array $options): bool
    {
        // Skip closure routes (they don't have meaningful action names)
        if ($this->isClosureRoute($route)) {
            return false;
        }

        // Skip framework internal routes
        if ($this->isFrameworkRoute($route)) {
            return false;
        }

        // Apply include patterns
        if (isset($options['include_patterns']) && ! empty($options['include_patterns'])) {
            $included = false;
            foreach ($options['include_patterns'] as $pattern) {
                if (fnmatch($pattern, $route->uri())) {
                    $included = true;
                    break;
                }
            }
            if (! $included) {
                return false;
            }
        }

        // Apply exclude patterns
        if (isset($options['exclude_patterns'])) {
            foreach ($options['exclude_patterns'] as $pattern) {
                if (fnmatch($pattern, $route->uri()) || fnmatch($pattern, $route->getName() ?? '')) {
                    return false;
                }
            }
        }

        // Apply exclude middleware
        if (isset($options['exclude_middleware'])) {
            $routeMiddleware = $route->gatherMiddleware();
            foreach ($options['exclude_middleware'] as $middleware) {
                if (in_array($middleware, $routeMiddleware)) {
                    return false;
                }
            }
        }

        // Apply include domains
        if (isset($options['include_domains']) && ! empty($options['include_domains'])) {
            $routeDomain = $route->getDomain();
            if (! in_array($routeDomain, $options['include_domains'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if route is a closure route
     */
    private function isClosureRoute(Route $route): bool
    {
        return str_contains($route->getActionName(), 'Closure');
    }

    /**
     * Check if route is a framework internal route
     */
    private function isFrameworkRoute(Route $route): bool
    {
        $uri = $route->uri();

        // Common framework route patterns
        $frameworkPatterns = [
            '_ignition/*',
            'telescope/*',
            'horizon/*',
            '_debugbar/*',
            'livewire/*',
            'nova-api/*',
            'sanctum/*',
        ];

        foreach ($frameworkPatterns as $pattern) {
            if (fnmatch($pattern, $uri)) {
                return true;
            }
        }

        // Check action namespace for framework routes
        $action = $route->getActionName();
        $frameworkNamespaces = [
            'Illuminate\\',
            'Laravel\\',
            'Facade\\Ignition\\',
            'Laravel\\Telescope\\',
            'Laravel\\Horizon\\',
            'Barryvdh\\Debugbar\\',
            'Livewire\\',
        ];

        foreach ($frameworkNamespaces as $namespace) {
            if (str_starts_with($action, $namespace)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get unique route signatures for deduplication
     *
     * @param  array<string, mixed>  $options
     * @return array<string>
     */
    public function getRouteSignatures(array $options = []): array
    {
        $routes = $this->collect($options);

        return array_map(fn (LaravelRoute $route): string => $route->getSignature(), $routes);
    }

    /**
     * Find routes by pattern
     *
     * @return array<LaravelRoute>
     */
    public function findByPattern(string $pattern): array
    {
        return $this->collect(['include_patterns' => [$pattern]]);
    }

    /**
     * Find routes by middleware
     *
     * @return array<LaravelRoute>
     */
    public function findByMiddleware(string $middleware): array
    {
        $routes = [];
        $routeCollection = $this->router->getRoutes();

        foreach ($routeCollection as $route) {
            if (in_array($middleware, $route->gatherMiddleware())) {
                $routes[] = LaravelRoute::fromLaravelRoute($route);
            }
        }

        return $routes;
    }

    /**
     * Get all available middleware in the application
     *
     * @return array<string>
     */
    public function getAllMiddleware(): array
    {
        $middleware = [];
        $routeCollection = $this->router->getRoutes();

        foreach ($routeCollection as $route) {
            $middleware = array_merge($middleware, $route->gatherMiddleware());
        }

        return array_unique($middleware);
    }

    /**
     * Get all route names
     *
     * @return array<string>
     */
    public function getAllRouteNames(): array
    {
        $names = [];
        $routeCollection = $this->router->getRoutes();

        foreach ($routeCollection as $route) {
            if ($route->getName()) {
                $names[] = $route->getName();
            }
        }

        return array_unique($names);
    }
}
