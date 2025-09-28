<?php

namespace Maan511\OpenapiToLaravel\Validation;

use Exception;
use Maan511\OpenapiToLaravel\Models\EndpointDefinition;
use Maan511\OpenapiToLaravel\Models\LaravelRoute;
use Maan511\OpenapiToLaravel\Models\RouteMismatch;
use Maan511\OpenapiToLaravel\Models\ValidationResult;
use Maan511\OpenapiToLaravel\Parser\OpenApiParser;

/**
 * Main orchestrator for route validation
 */
class RouteValidator
{
    public function __construct(
        private readonly LaravelRouteCollector $routeCollector,
        private readonly OpenApiParser $openApiParser
    ) {}

    /**
     * Validate routes against OpenAPI specification
     *
     * @param  array<string, mixed>  $options
     */
    public function validate(string $specPath, array $options = []): ValidationResult
    {
        try {
            // Parse OpenAPI specification
            $specification = $this->openApiParser->parseFromFile($specPath);
            $endpoints = $this->openApiParser->extractEndpoints($specification);

            // Collect Laravel routes
            $laravelRoutes = $this->routeCollector->collect($options);

            // Perform validation
            return $this->performValidation($laravelRoutes, $endpoints, $options);
        } catch (Exception $e) {
            return ValidationResult::failed([
                RouteMismatch::createError('validation_error', $e->getMessage(), '', ''),
            ]);
        }
    }

    /**
     * Validate Laravel routes against OpenAPI endpoints
     *
     * @param  array<LaravelRoute>  $laravelRoutes
     * @param  array<EndpointDefinition>  $endpoints
     * @param  array<string, mixed>  $options
     */
    public function validateRoutes(array $laravelRoutes, array $endpoints, array $options = []): ValidationResult
    {
        return $this->performValidation($laravelRoutes, $endpoints, $options);
    }

    /**
     * Perform the actual validation logic
     *
     * @param  array<LaravelRoute>  $laravelRoutes
     * @param  array<EndpointDefinition>  $endpoints
     * @param  array<string, mixed>  $options
     */
    private function performValidation(array $laravelRoutes, array $endpoints, array $options): ValidationResult
    {
        $mismatches = [];
        $warnings = [];

        // Create lookup maps for efficient comparison
        $routeMap = $this->createRouteMap($laravelRoutes);
        $endpointMap = $this->createEndpointMap($endpoints);

        // Find missing documentation (routes not in OpenAPI)
        $missingDocs = $this->findMissingDocumentation($routeMap, $endpointMap, $options);
        $mismatches = array_merge($mismatches, $missingDocs);

        // Find missing implementation (OpenAPI endpoints not in routes)
        $missingImpl = $this->findMissingImplementation($endpointMap, $routeMap);
        $mismatches = array_merge($mismatches, $missingImpl);

        // Find method mismatches
        $methodMismatches = $this->findMethodMismatches($routeMap, $endpointMap);
        $mismatches = array_merge($mismatches, $methodMismatches);

        // Find parameter mismatches
        $paramMismatches = $this->findParameterMismatches($routeMap, $endpointMap);
        $mismatches = array_merge($mismatches, $paramMismatches);

        // Generate statistics
        $statistics = $this->generateStatistics($laravelRoutes, $endpoints, $mismatches);

        return new ValidationResult(
            isValid: $mismatches === [],
            mismatches: $mismatches,
            warnings: $warnings,
            statistics: $statistics
        );
    }

    /**
     * Create route signature map for efficient lookup
     *
     * @param  array<LaravelRoute>  $routes
     * @return array<string, array<LaravelRoute>>
     */
    private function createRouteMap(array $routes): array
    {
        $map = [];
        foreach ($routes as $route) {
            $signature = $route->getSignature();
            if (! isset($map[$signature])) {
                $map[$signature] = [];
            }
            $map[$signature][] = $route;
        }

        return $map;
    }

    /**
     * Create endpoint signature map for efficient lookup
     *
     * @param  array<EndpointDefinition>  $endpoints
     * @return array<string, array<EndpointDefinition>>
     */
    private function createEndpointMap(array $endpoints): array
    {
        $map = [];
        foreach ($endpoints as $endpoint) {
            $signature = "{$endpoint->method}:{$endpoint->path}";
            if (! isset($map[$signature])) {
                $map[$signature] = [];
            }
            $map[$signature][] = $endpoint;
        }

        return $map;
    }

    /**
     * Find routes that are missing documentation
     *
     * @param  array<string, array<LaravelRoute>>  $routeMap
     * @param  array<string, array<EndpointDefinition>>  $endpointMap
     * @param  array<string, mixed>  $options
     * @return array<RouteMismatch>
     */
    private function findMissingDocumentation(array $routeMap, array $endpointMap, array $options): array
    {
        $mismatches = [];

        foreach ($routeMap as $signature => $routes) {
            if (! isset($endpointMap[$signature])) {
                foreach ($routes as $route) {
                    // Skip routes that should be excluded
                    if (! $this->shouldIncludeRoute($route, $options)) {
                        continue;
                    }

                    $mismatches[] = RouteMismatch::missingDocumentation($route);
                }
            }
        }

        return $mismatches;
    }

    /**
     * Find endpoints that are missing implementation
     *
     * @param  array<string, array<EndpointDefinition>>  $endpointMap
     * @param  array<string, array<LaravelRoute>>  $routeMap
     * @return array<RouteMismatch>
     */
    private function findMissingImplementation(array $endpointMap, array $routeMap): array
    {
        $mismatches = [];

        foreach ($endpointMap as $signature => $endpoints) {
            if (! isset($routeMap[$signature])) {
                foreach ($endpoints as $endpoint) {
                    $mismatches[] = RouteMismatch::missingImplementation($endpoint);
                }
            }
        }

        return $mismatches;
    }

    /**
     * Find method mismatches between routes and endpoints
     *
     * @param  array<string, array<LaravelRoute>>  $routeMap
     * @param  array<string, array<EndpointDefinition>>  $endpointMap
     * @return array<RouteMismatch>
     */
    private function findMethodMismatches(array $routeMap, array $endpointMap): array
    {
        $mismatches = [];
        $pathGroups = $this->groupByPath($routeMap, $endpointMap);

        foreach ($pathGroups as $path => $data) {
            $routeMethods = $data['route_methods'] ?? [];
            $endpointMethods = $data['endpoint_methods'] ?? [];

            if (! empty($routeMethods) && ! empty($endpointMethods) && $routeMethods !== $endpointMethods) {
                $mismatches[] = RouteMismatch::methodMismatch($path, $routeMethods, $endpointMethods);
            }
        }

        return $mismatches;
    }

    /**
     * Find parameter mismatches between routes and endpoints
     *
     * @param  array<string, array<LaravelRoute>>  $routeMap
     * @param  array<string, array<EndpointDefinition>>  $endpointMap
     * @return array<RouteMismatch>
     */
    private function findParameterMismatches(array $routeMap, array $endpointMap): array
    {
        $mismatches = [];

        foreach ($routeMap as $signature => $routes) {
            if (isset($endpointMap[$signature])) {
                $route = $routes[0]; // Take first route for comparison
                $endpoint = $endpointMap[$signature][0]; // Take first endpoint for comparison

                $routeParams = $route->pathParameters;
                $endpointParams = $endpoint->getPathParameters();

                if ($routeParams !== $endpointParams) {
                    $mismatches[] = RouteMismatch::parameterMismatch(
                        $route->getNormalizedPath(),
                        $route->getPrimaryMethod(),
                        $routeParams,
                        $endpointParams
                    );
                }
            }
        }

        return $mismatches;
    }

    /**
     * Group routes and endpoints by path for cross-comparison
     *
     * @param  array<string, array<LaravelRoute>>  $routeMap
     * @param  array<string, array<EndpointDefinition>>  $endpointMap
     * @return array<string, array{route_methods: array<string>, endpoint_methods: array<string>}>
     */
    private function groupByPath(array $routeMap, array $endpointMap): array
    {
        $groups = [];

        // Process routes
        foreach (array_keys($routeMap) as $signature) {
            [$method, $path] = explode(':', (string) $signature, 2);
            if (! isset($groups[$path])) {
                $groups[$path] = ['route_methods' => [], 'endpoint_methods' => []];
            }
            if (! in_array($method, $groups[$path]['route_methods'])) {
                $groups[$path]['route_methods'][] = $method;
            }
        }

        // Process endpoints
        foreach (array_keys($endpointMap) as $signature) {
            [$method, $path] = explode(':', (string) $signature, 2);
            if (! isset($groups[$path])) {
                $groups[$path] = ['route_methods' => [], 'endpoint_methods' => []];
            }
            if (! in_array($method, $groups[$path]['endpoint_methods'])) {
                $groups[$path]['endpoint_methods'][] = $method;
            }
        }

        return $groups;
    }

    /**
     * Generate validation statistics
     *
     * @param  array<LaravelRoute>  $routes
     * @param  array<EndpointDefinition>  $endpoints
     * @param  array<RouteMismatch>  $mismatches
     * @return array<string, mixed>
     */
    private function generateStatistics(array $routes, array $endpoints, array $mismatches): array
    {
        $mismatchCounts = [];
        foreach ($mismatches as $mismatch) {
            $type = $mismatch->type;
            $mismatchCounts[$type] = ($mismatchCounts[$type] ?? 0) + 1;
        }

        return [
            'total_routes' => count($routes),
            'total_endpoints' => count($endpoints),
            'total_mismatches' => count($mismatches),
            'mismatch_breakdown' => $mismatchCounts,
            'coverage_percentage' => $this->calculateCoverage($routes, $endpoints, $mismatches),
        ];
    }

    /**
     * Calculate coverage percentage
     *
     * @param  array<LaravelRoute>  $routes
     * @param  array<EndpointDefinition>  $endpoints
     * @param  array<RouteMismatch>  $mismatches
     */
    private function calculateCoverage(array $routes, array $endpoints, array $mismatches): float
    {
        $totalItems = count($routes) + count($endpoints);
        if ($totalItems === 0) {
            return 100.0;
        }

        $missingDocs = count(array_filter($mismatches, fn (\Maan511\OpenapiToLaravel\Models\RouteMismatch $m): bool => $m->type === RouteMismatch::TYPE_MISSING_DOCUMENTATION));
        $missingImpl = count(array_filter($mismatches, fn (\Maan511\OpenapiToLaravel\Models\RouteMismatch $m): bool => $m->type === RouteMismatch::TYPE_MISSING_IMPLEMENTATION));

        $coveredItems = $totalItems - $missingDocs - $missingImpl;

        return round(($coveredItems / $totalItems) * 100, 2);
    }

    /**
     * Check if route should be included in validation
     *
     * @param  array<string, mixed>  $options
     */
    private function shouldIncludeRoute(LaravelRoute $route, array $options): bool
    {
        // Apply include patterns
        if (isset($options['include_patterns'])) {
            $included = false;
            foreach ($options['include_patterns'] as $pattern) {
                if (fnmatch($pattern, $route->uri)) {
                    $included = true;
                    break;
                }
            }
            if (! $included) {
                return false;
            }
        }

        // Apply exclude middleware
        if (isset($options['exclude_middleware'])) {
            foreach ($options['exclude_middleware'] as $middleware) {
                if ($route->hasMiddleware($middleware)) {
                    return false;
                }
            }
        }

        // Apply ignore routes patterns
        if (isset($options['ignore_routes'])) {
            foreach ($options['ignore_routes'] as $pattern) {
                if (fnmatch($pattern, $route->name) || fnmatch($pattern, $route->uri)) {
                    return false;
                }
            }
        }

        return $route->isApiRoute();
    }
}
