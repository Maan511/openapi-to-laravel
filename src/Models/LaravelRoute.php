<?php

namespace Maan511\OpenapiToLaravel\Models;

use Illuminate\Routing\Route;
use InvalidArgumentException;

/**
 * Represents a Laravel route in standardized format for comparison
 */
class LaravelRoute
{
    /**
     * @param  array<string>  $methods
     * @param  array<string>  $middleware
     * @param  array<string>  $pathParameters
     */
    public function __construct(
        public readonly string $uri,
        public readonly array $methods,
        public readonly string $name,
        public readonly string $action,
        public readonly array $middleware = [],
        public readonly array $pathParameters = [],
        public readonly ?string $domain = null
    ) {
        $this->validateUri();
        $this->validateMethods();
    }

    /**
     * Create instance from Laravel Route object
     */
    public static function fromLaravelRoute(Route $route): self
    {
        return new self(
            uri: $route->uri(),
            methods: $route->methods(),
            name: $route->getName() ?? '',
            action: $route->getActionName(),
            middleware: $route->gatherMiddleware(),
            pathParameters: self::extractPathParameters($route->uri()),
            domain: $route->getDomain()
        );
    }

    /**
     * Get normalized path for comparison
     */
    public function getNormalizedPath(): string
    {
        // Convert Laravel {param} to OpenAPI {param} format and normalize
        $path = '/' . trim($this->uri, '/');

        // Replace Laravel route parameters with normalized format
        $path = preg_replace('/\{([^}?]+)\?\}/', '{$1}', $path) ?? $path; // Remove optional markers

        return $path;
    }

    /**
     * Get primary HTTP method (first one for routes with multiple methods)
     */
    public function getPrimaryMethod(): string
    {
        $filteredMethods = array_filter($this->methods, fn (string $method): bool => $method !== 'HEAD');

        return strtoupper($filteredMethods[0] ?? 'GET');
    }

    /**
     * Check if route has specific HTTP method
     */
    public function hasMethod(string $method): bool
    {
        return in_array(strtoupper($method), array_map('strtoupper', $this->methods));
    }

    /**
     * Check if route has path parameters
     */
    public function hasPathParameters(): bool
    {
        return $this->pathParameters !== [];
    }

    /**
     * Get route signature for quick comparison
     */
    public function getSignature(): string
    {
        return $this->getPrimaryMethod() . ':' . $this->getNormalizedPath();
    }

    /**
     * Check if route should be included in API documentation
     */
    public function isApiRoute(): bool
    {
        // Skip routes that are typically not part of API documentation
        $excludePatterns = [
            '_ignition',
            'telescope',
            'horizon',
            '_debugbar',
            'livewire',
        ];

        foreach ($excludePatterns as $pattern) {
            if (str_contains($this->uri, $pattern)) {
                return false;
            }
        }
        // Include routes that have API middleware or start with api/
        if ($this->hasMiddleware('api')) {
            return true;
        }

        return str_starts_with($this->uri, 'api/');
    }

    /**
     * Check if route has specific middleware
     */
    public function hasMiddleware(string $middleware): bool
    {
        return in_array($middleware, $this->middleware);
    }

    /**
     * Get unique identifier for this route
     */
    public function getId(): string
    {
        return md5($this->getSignature() . implode(',', $this->middleware) . ($this->domain ?? ''));
    }

    /**
     * Convert to array representation
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'uri' => $this->uri,
            'methods' => $this->methods,
            'name' => $this->name,
            'action' => $this->action,
            'middleware' => $this->middleware,
            'pathParameters' => $this->pathParameters,
            'domain' => $this->domain,
            'normalizedPath' => $this->getNormalizedPath(),
            'signature' => $this->getSignature(),
        ];
    }

    /**
     * Extract path parameters from URI
     *
     * @return array<string>
     */
    private static function extractPathParameters(string $uri): array
    {
        preg_match_all('/\{([^}?]+)\?\??\}/', $uri, $matches);

        return $matches[1];
    }

    /**
     * Validate URI format
     */
    private function validateUri(): void
    {
        if ($this->uri === '') {
            throw new InvalidArgumentException('Route URI cannot be empty');
        }
    }

    /**
     * Validate HTTP methods
     */
    private function validateMethods(): void
    {
        if ($this->methods === []) {
            throw new InvalidArgumentException('Route must have at least one HTTP method');
        }

        $validMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'];

        foreach ($this->methods as $method) {
            if (! in_array(strtoupper($method), $validMethods, true)) {
                throw new InvalidArgumentException("Invalid HTTP method: {$method}");
            }
        }
    }
}
