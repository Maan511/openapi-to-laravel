<?php

namespace Maan511\OpenapiToLaravel\Validation;

use Maan511\OpenapiToLaravel\Models\EndpointDefinition;
use Maan511\OpenapiToLaravel\Models\LaravelRoute;

/**
 * Implements efficient route comparison logic
 */
class RouteComparator
{
    /**
     * Compare two routes for exact match
     */
    public function exactMatch(LaravelRoute $route, EndpointDefinition $endpoint): bool
    {
        return $this->normalizeMethod($route->getPrimaryMethod()) === $this->normalizeMethod($endpoint->method)
            && $this->normalizePath($route->getNormalizedPath()) === $this->normalizePath($endpoint->path);
    }

    /**
     * Find similar routes using fuzzy matching
     *
     * @param  array<EndpointDefinition>  $endpoints
     * @return array<array{endpoint: EndpointDefinition, similarity: float}>
     */
    public function findSimilarEndpoints(LaravelRoute $route, array $endpoints): array
    {
        $similarities = [];

        foreach ($endpoints as $endpoint) {
            $similarity = $this->calculateSimilarity($route, $endpoint);
            if ($similarity > 0.5) { // Only include reasonably similar matches
                $similarities[] = [
                    'endpoint' => $endpoint,
                    'similarity' => $similarity,
                ];
            }
        }

        // Sort by similarity (highest first)
        usort($similarities, fn (array $a, array $b): int => $b['similarity'] <=> $a['similarity']);

        return $similarities;
    }

    /**
     * Find similar routes for an endpoint
     *
     * @param  array<LaravelRoute>  $routes
     * @return array<array{route: LaravelRoute, similarity: float}>
     */
    public function findSimilarRoutes(EndpointDefinition $endpoint, array $routes): array
    {
        $similarities = [];

        foreach ($routes as $route) {
            $similarity = $this->calculateSimilarity($route, $endpoint);
            if ($similarity > 0.5) {
                $similarities[] = [
                    'route' => $route,
                    'similarity' => $similarity,
                ];
            }
        }

        // Sort by similarity (highest first)
        usort($similarities, fn (array $a, array $b): int => $b['similarity'] <=> $a['similarity']);

        return $similarities;
    }

    /**
     * Calculate similarity between route and endpoint
     */
    public function calculateSimilarity(LaravelRoute $route, EndpointDefinition $endpoint): float
    {
        $pathSimilarity = $this->calculatePathSimilarity(
            $route->getNormalizedPath(),
            $endpoint->path
        );

        $methodSimilarity = $this->calculateMethodSimilarity(
            $route->getPrimaryMethod(),
            $endpoint->method
        );

        // Weighted average: path is more important than method
        return ($pathSimilarity * 0.8) + ($methodSimilarity * 0.2);
    }

    /**
     * Calculate path similarity using Levenshtein distance
     */
    public function calculatePathSimilarity(string $path1, string $path2): float
    {
        $normalizedPath1 = $this->normalizePath($path1);
        $normalizedPath2 = $this->normalizePath($path2);

        if ($normalizedPath1 === $normalizedPath2) {
            return 1.0;
        }

        // Handle parameter variations specially
        $path1Parts = explode('/', trim($normalizedPath1, '/'));
        $path2Parts = explode('/', trim($normalizedPath2, '/'));

        if (count($path1Parts) === count($path2Parts)) {
            $matchingParts = 0;
            $counter = count($path1Parts);
            for ($i = 0; $i < $counter; $i++) {
                if ($path1Parts[$i] === $path2Parts[$i]) {
                    $matchingParts++;
                } elseif (preg_match('/^\{.+\}$/', $path1Parts[$i]) && preg_match('/^\{.+\}$/', $path2Parts[$i])) {
                    // Both are parameters, check if they're variations
                    $param1 = trim($path1Parts[$i], '{}');
                    $param2 = trim($path2Parts[$i], '{}');
                    if ($this->areParameterVariations($param1, $param2)) {
                        $matchingParts++;
                    } else {
                        $matchingParts += 0.8; // Similar but not identical parameters
                    }
                }
            }

            return $matchingParts / count($path1Parts);
        }

        $maxLength = max(strlen($normalizedPath1), strlen($normalizedPath2));
        if ($maxLength === 0) {
            return 1.0;
        }

        $distance = levenshtein($normalizedPath1, $normalizedPath2);

        return 1.0 - ($distance / $maxLength);
    }

    /**
     * Calculate method similarity
     */
    public function calculateMethodSimilarity(string $method1, string $method2): float
    {
        $normalizedMethod1 = $this->normalizeMethod($method1);
        $normalizedMethod2 = $this->normalizeMethod($method2);

        return $normalizedMethod1 === $normalizedMethod2 ? 1.0 : 0.0;
    }

    /**
     * Check if paths have similar parameter structure
     */
    public function haveSimilarParameters(LaravelRoute $route, EndpointDefinition $endpoint): bool
    {
        $routeParams = $route->pathParameters;
        $endpointParams = $endpoint->getPathParameters();

        // Same number of parameters
        if (count($routeParams) !== count($endpointParams)) {
            return false;
        }
        // Check parameter names (allowing for case differences)
        $paramCount = count($routeParams);

        for ($index = 0; $index < $paramCount; $index++) {
            $routeParam = strtolower($routeParams[$index]);
            $endpointParam = strtolower($endpointParams[$index]);

            // Allow for common variations
            if ($routeParam !== $endpointParam && ! $this->areParameterVariations($routeParam, $endpointParam)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Generate suggestions for matching routes/endpoints
     *
     * @return array<string>
     */
    public function generateMatchingSuggestions(LaravelRoute $route, EndpointDefinition $endpoint): array
    {
        $suggestions = [];

        $pathSimilarity = $this->calculatePathSimilarity($route->getNormalizedPath(), $endpoint->path);
        $methodSimilarity = $this->calculateMethodSimilarity($route->getPrimaryMethod(), $endpoint->method);

        if ($pathSimilarity > 0.8 && $methodSimilarity < 1.0) {
            $suggestions[] = "Consider changing HTTP method from '{$route->getPrimaryMethod()}' to '{$endpoint->method}' or vice versa";
        }

        if ($methodSimilarity === 1.0 && $pathSimilarity > 0.6 && $pathSimilarity < 1.0) {
            $suggestions[] = "Paths are similar but not identical: '{$route->getNormalizedPath()}' vs '{$endpoint->path}'";
            $suggestions[] = 'Check for parameter naming differences or path structure variations';
        }

        if ($this->haveSimilarParameters($route, $endpoint)) {
            $suggestions[] = 'Parameter structures match - this might be the same endpoint';
        }

        return $suggestions;
    }

    /**
     * Normalize HTTP method for comparison
     */
    private function normalizeMethod(string $method): string
    {
        return strtoupper(trim($method));
    }

    /**
     * Normalize path for comparison
     */
    private function normalizePath(string $path): string
    {
        // Ensure leading slash
        $path = '/' . ltrim($path, '/');

        // Remove trailing slash (except for root)
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        // Normalize parameter format to {param}
        $path = preg_replace('/\{([^}?]+)\?\}/', '{$1}', $path) ?? $path; // Remove optional markers
        $path = preg_replace('/\{([^}]+)\}/', '{$1}', $path) ?? $path; // Ensure consistent braces

        return strtolower($path);
    }

    /**
     * Check if two parameter names are variations of each other
     */
    private function areParameterVariations(string $param1, string $param2): bool
    {
        // Exact match
        if ($param1 === $param2) {
            return true;
        }

        // Common variations
        $variations = [
            ['id', 'identifier'],
            ['user_id', 'userId', 'user'],
            ['post_id', 'postId', 'post'],
            ['category_id', 'categoryId', 'category'],
            ['user_id', 'userid'], // Additional common variations
        ];

        foreach ($variations as $group) {
            if (in_array($param1, $group) && in_array($param2, $group)) {
                return true;
            }
        }

        // Check for snake_case vs camelCase
        $param1Snake = $this->toSnakeCase($param1);
        $param1Camel = $this->toCamelCase($param1);
        $param2Snake = $this->toSnakeCase($param2);
        $param2Camel = $this->toCamelCase($param2);

        return $param1Snake === $param2Snake ||
               $param1Camel === $param2Camel ||
               $param1Snake === $param2Camel ||
               $param1Camel === $param2Snake;
    }

    /**
     * Convert string to snake_case
     */
    private function toSnakeCase(string $string): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $string) ?? $string);
    }

    /**
     * Convert string to camelCase
     */
    private function toCamelCase(string $string): string
    {
        $string = str_replace('_', ' ', $string);
        $string = ucwords($string);
        $string = str_replace(' ', '', $string);

        return lcfirst($string);
    }

    /**
     * Create a hash/fingerprint for quick comparison
     */
    public function createFingerprint(string $method, string $path): string
    {
        return md5($this->normalizeMethod($method) . ':' . $this->normalizePath($path));
    }

    /**
     * Batch compare routes and endpoints for exact matches
     *
     * @param  array<LaravelRoute>  $routes
     * @param  array<EndpointDefinition>  $endpoints
     * @return array{matches: array<array{route: LaravelRoute, endpoint: EndpointDefinition, type: string}>, unmatched_routes: array<LaravelRoute>, unmatched_endpoints: array<EndpointDefinition>}
     */
    public function batchCompare(array $routes, array $endpoints): array
    {
        $matches = [];
        $unmatchedRoutes = [];
        $unmatchedEndpoints = [];

        // Create fingerprint maps for efficient matching
        $routeMap = [];
        foreach ($routes as $route) {
            $fingerprint = $this->createFingerprint($route->getPrimaryMethod(), $route->getNormalizedPath());
            $routeMap[$fingerprint] = $route;
        }

        $endpointMap = [];
        foreach ($endpoints as $endpoint) {
            $fingerprint = $this->createFingerprint($endpoint->method, $endpoint->path);
            $endpointMap[$fingerprint] = $endpoint;
        }

        // Find exact matches
        foreach ($routeMap as $fingerprint => $route) {
            if (isset($endpointMap[$fingerprint])) {
                $matches[] = [
                    'route' => $route,
                    'endpoint' => $endpointMap[$fingerprint],
                    'type' => 'exact',
                ];
                unset($endpointMap[$fingerprint]);
            } else {
                $unmatchedRoutes[] = $route;
            }
        }

        $unmatchedEndpoints = array_values($endpointMap);

        return [
            'matches' => $matches,
            'unmatched_routes' => $unmatchedRoutes,
            'unmatched_endpoints' => $unmatchedEndpoints,
        ];
    }
}
