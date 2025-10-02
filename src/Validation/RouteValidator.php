<?php

namespace Maan511\OpenapiToLaravel\Validation;

use Exception;
use Maan511\OpenapiToLaravel\Models\EndpointDefinition;
use Maan511\OpenapiToLaravel\Models\LaravelRoute;
use Maan511\OpenapiToLaravel\Models\RouteMismatch;
use Maan511\OpenapiToLaravel\Models\ValidationResult;
use Maan511\OpenapiToLaravel\Parser\OpenApiParser;
use Maan511\OpenapiToLaravel\Parser\ServerPathExtractor;

/**
 * Main orchestrator for route validation
 */
class RouteValidator
{
    private readonly ServerPathExtractor $serverPathExtractor;

    public function __construct(
        private readonly LaravelRouteCollector $routeCollector,
        private readonly OpenApiParser $openApiParser,
        ?ServerPathExtractor $serverPathExtractor = null
    ) {
        $this->serverPathExtractor = $serverPathExtractor ?? new ServerPathExtractor;
    }

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

            // Resolve base path from servers or user specification
            $basePath = $this->serverPathExtractor->resolveBasePath(
                $specification,
                $options['base_path'] ?? null
            );

            $endpoints = $this->openApiParser->extractEndpoints($specification, $basePath);

            // Filter endpoints by include patterns if specified
            if (isset($options['include_patterns']) && ! empty($options['include_patterns'])) {
                $endpoints = $this->filterEndpointsByPatterns($endpoints, $options['include_patterns']);
            }

            // Collect Laravel routes
            $laravelRoutes = $this->routeCollector->collect($options);

            // Perform validation
            return $this->performValidation($laravelRoutes, $endpoints, $options);
        } catch (Exception $e) {
            return ValidationResult::failed([
                RouteMismatch::createError(RouteMismatch::TYPE_VALIDATION_ERROR, $e->getMessage(), '', ''),
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
        // Filter endpoints by include patterns if specified
        if (isset($options['include_patterns']) && ! empty($options['include_patterns'])) {
            $endpoints = $this->filterEndpointsByPatterns($endpoints, $options['include_patterns']);
        }

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

        // Filter routes by include patterns if specified (before creating route map)
        if (isset($options['include_patterns']) && ! empty($options['include_patterns'])) {
            $laravelRoutes = array_filter($laravelRoutes, fn (LaravelRoute $route): bool => $this->shouldIncludeRoute($route, $options));
        }

        // Create lookup maps for efficient comparison
        $routeMap = $this->createRouteMap($laravelRoutes);
        $endpointMap = $this->createEndpointMap($endpoints);

        // Find missing documentation (routes not in OpenAPI)
        $missingDocs = $this->findMissingDocumentation($routeMap, $endpointMap, $options);
        $mismatches = array_merge($mismatches, $missingDocs);

        // Find missing implementation (OpenAPI endpoints not in routes)
        $missingImpl = $this->findMissingImplementation($endpointMap, $routeMap);
        $mismatches = array_merge($mismatches, $missingImpl);

        // Find parameter mismatches (for routes/endpoints that exist in both)
        $paramMismatches = $this->findParameterMismatches($routeMap, $endpointMap);
        $mismatches = array_merge($mismatches, $paramMismatches);

        // Apply filtering if specified
        if (! empty($options['filter_types'])) {
            $mismatches = $this->filterMismatchesByType($mismatches, $options['filter_types']);
        }

        // Sort mismatches alphabetically by path then by intuitive method order
        usort($mismatches, function (RouteMismatch $a, RouteMismatch $b): int {
            $pathCompare = strcmp($a->path, $b->path);
            if ($pathCompare !== 0) {
                return $pathCompare;
            }

            return $this->compareHttpMethods($a->method, $b->method);
        });

        // Generate statistics
        $statistics = $this->generateStatistics($laravelRoutes, $endpoints, $mismatches, ! empty($options['filter_types']));

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
            $normalizedSignature = $route->getNormalizedSignature();
            if (! isset($map[$normalizedSignature])) {
                $map[$normalizedSignature] = [];
            }
            $map[$normalizedSignature][] = $route;
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
            $normalizedSignature = $endpoint->getNormalizedSignature();
            if (! isset($map[$normalizedSignature])) {
                $map[$normalizedSignature] = [];
            }
            $map[$normalizedSignature][] = $endpoint;
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

                // Normalize parameter lists to handle order differences
                $normalizedRouteParams = is_array($routeParams) ? $routeParams : [];
                $normalizedEndpointParams = is_array($endpointParams) ? $endpointParams : [];

                // Only check for mismatches if parameter counts differ
                // Since we're using normalized signatures for matching,
                // routes with same parameter structure should not be flagged as mismatches
                if (count($normalizedRouteParams) !== count($normalizedEndpointParams)) {
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
     * Generate validation statistics
     *
     * @param  array<LaravelRoute>  $routes
     * @param  array<EndpointDefinition>  $endpoints
     * @param  array<RouteMismatch>  $mismatches
     * @param  bool  $isFiltered  Whether mismatches have been filtered
     * @return array<string, mixed>
     */
    private function generateStatistics(array $routes, array $endpoints, array $mismatches, bool $isFiltered = false): array
    {
        $mismatchCounts = [];
        foreach ($mismatches as $mismatch) {
            $type = $mismatch->type;
            $mismatchCounts[$type] = ($mismatchCounts[$type] ?? 0) + 1;
        }

        // Calculate individual coverage statistics
        $missingDocs = count(array_filter($mismatches, fn (\Maan511\OpenapiToLaravel\Models\RouteMismatch $m): bool => $m->type === RouteMismatch::TYPE_MISSING_DOCUMENTATION));
        $missingImpl = count(array_filter($mismatches, fn (\Maan511\OpenapiToLaravel\Models\RouteMismatch $m): bool => $m->type === RouteMismatch::TYPE_MISSING_IMPLEMENTATION));

        // When filtered, count based on filtered mismatches, not all routes/endpoints
        if ($isFiltered) {
            $totalRoutes = $missingDocs;
            $totalEndpoints = $missingImpl;
            $coveredRoutes = 0; // All filtered mismatches represent uncovered items
            $coveredEndpoints = 0;
        } else {
            $totalRoutes = count($routes);
            $totalEndpoints = count($endpoints);
            $coveredRoutes = max(0, $totalRoutes - $missingDocs);
            $coveredEndpoints = max(0, $totalEndpoints - $missingImpl);
        }

        $routeCoveragePercentage = $totalRoutes > 0 ? round(($coveredRoutes / $totalRoutes) * 100, 2) : 100.0;
        $endpointCoveragePercentage = $totalEndpoints > 0 ? round(($coveredEndpoints / $totalEndpoints) * 100, 2) : 100.0;

        return [
            'total_routes' => $totalRoutes,
            'covered_routes' => $coveredRoutes,
            'route_coverage_percentage' => $routeCoveragePercentage,
            'total_endpoints' => $totalEndpoints,
            'covered_endpoints' => $coveredEndpoints,
            'endpoint_coverage_percentage' => $endpointCoveragePercentage,
            'total_mismatches' => count($mismatches),
            'mismatch_breakdown' => $mismatchCounts,
            'total_coverage_percentage' => $this->calculateCoverage($totalRoutes, $totalEndpoints, $coveredRoutes, $coveredEndpoints),
        ];
    }

    /**
     * Calculate coverage percentage
     */
    private function calculateCoverage(int $totalRoutes, int $totalEndpoints, int $coveredRoutes, int $coveredEndpoints): float
    {
        $totalItems = $totalRoutes + $totalEndpoints;
        if ($totalItems === 0) {
            return 100.0;
        }

        $coveredItems = $coveredRoutes + $coveredEndpoints;

        return round(($coveredItems / $totalItems) * 100, 2);
    }

    /**
     * Check if route should be included in validation
     *
     * @param  array<string, mixed>  $options
     */
    private function shouldIncludeRoute(LaravelRoute $route, array $options): bool
    {
        // Apply exclude middleware (needed for direct route validation)
        if (isset($options['exclude_middleware'])) {
            foreach ($options['exclude_middleware'] as $middleware) {
                if ($route->hasMiddleware($middleware)) {
                    return false;
                }
            }
        }

        // Apply include patterns if specified
        if (isset($options['include_patterns']) && ! empty($options['include_patterns']) && ! PatternMatcher::matchesAny($options['include_patterns'], $route->getNormalizedPath())) {
            return false;
        }

        return $route->isApiRoute();
    }

    /**
     * Filter endpoints by include patterns
     *
     * @param  array<EndpointDefinition>  $endpoints
     * @param  array<string>  $includePatterns
     * @return array<EndpointDefinition>
     */
    private function filterEndpointsByPatterns(array $endpoints, array $includePatterns): array
    {
        return array_filter($endpoints, fn (EndpointDefinition $endpoint): bool => PatternMatcher::matchesAny($includePatterns, $endpoint->path));
    }

    /**
     * Filter mismatches by specified types
     *
     * @param  array<RouteMismatch>  $mismatches
     * @param  array<string>  $filterTypes
     * @return array<RouteMismatch>
     */
    private function filterMismatchesByType(array $mismatches, array $filterTypes): array
    {
        if ($filterTypes === []) {
            return $mismatches;
        }

        return array_filter($mismatches, fn (RouteMismatch $mismatch): bool => in_array($mismatch->type, $filterTypes));
    }

    /**
     * Compare HTTP methods for intuitive sorting
     *
     * Orders methods in a logical progression: GET, POST, PUT, PATCH, DELETE, then others alphabetically
     */
    private function compareHttpMethods(string $methodA, string $methodB): int
    {
        $methodOrder = [
            'GET' => 1,
            'POST' => 2,
            'PUT' => 3,
            'PATCH' => 4,
            'DELETE' => 5,
            'HEAD' => 6,
            'OPTIONS' => 7,
        ];

        $orderA = $methodOrder[$methodA] ?? 99;
        $orderB = $methodOrder[$methodB] ?? 99;

        if ($orderA !== $orderB) {
            return $orderA <=> $orderB;
        }

        // If both methods are not in the predefined order, sort alphabetically
        return strcmp($methodA, $methodB);
    }
}
