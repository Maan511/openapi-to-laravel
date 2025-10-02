<?php

namespace Maan511\OpenapiToLaravel\Models;

/**
 * Represents a matched route/endpoint pair with status
 */
class RouteMatch
{
    public const STATUS_MATCH = 'match';

    public const STATUS_MISSING_DOCUMENTATION = 'missing_documentation';

    public const STATUS_MISSING_IMPLEMENTATION = 'missing_implementation';

    public const STATUS_PARAMETER_MISMATCH = 'parameter_mismatch';

    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly ?LaravelRoute $route = null,
        public readonly ?EndpointDefinition $endpoint = null,
        public readonly string $status = self::STATUS_MATCH,
        public ?RouteMismatch $mismatch = null
    ) {}

    /**
     * Create a match for routes that exist in both sources
     */
    public static function createMatch(LaravelRoute $route, EndpointDefinition $endpoint, ?RouteMismatch $mismatch = null): self
    {
        $status = $mismatch instanceof \Maan511\OpenapiToLaravel\Models\RouteMismatch ? self::STATUS_PARAMETER_MISMATCH : self::STATUS_MATCH;

        return new self(
            method: $endpoint->method,
            path: $endpoint->path,
            route: $route,
            endpoint: $endpoint,
            status: $status,
            mismatch: $mismatch
        );
    }

    /**
     * Create a match for Laravel route without OpenAPI endpoint
     */
    public static function createMissingDocumentation(LaravelRoute $route): self
    {
        return new self(
            method: $route->getPrimaryMethod(),
            path: $route->getNormalizedPath(),
            route: $route,
            endpoint: null,
            status: self::STATUS_MISSING_DOCUMENTATION
        );
    }

    /**
     * Create a match for OpenAPI endpoint without Laravel route
     */
    public static function createMissingImplementation(EndpointDefinition $endpoint): self
    {
        return new self(
            method: $endpoint->method,
            path: $endpoint->path,
            route: null,
            endpoint: $endpoint,
            status: self::STATUS_MISSING_IMPLEMENTATION
        );
    }

    /**
     * Check if this represents a true match (exists in both sources)
     */
    public function isMatch(): bool
    {
        return $this->route instanceof \Maan511\OpenapiToLaravel\Models\LaravelRoute && $this->endpoint instanceof \Maan511\OpenapiToLaravel\Models\EndpointDefinition;
    }

    /**
     * Check if route is missing documentation
     */
    public function isMissingDocumentation(): bool
    {
        return $this->status === self::STATUS_MISSING_DOCUMENTATION;
    }

    /**
     * Check if endpoint is missing implementation
     */
    public function isMissingImplementation(): bool
    {
        return $this->status === self::STATUS_MISSING_IMPLEMENTATION;
    }

    /**
     * Check if there's a parameter mismatch
     */
    public function hasParameterMismatch(): bool
    {
        return $this->status === self::STATUS_PARAMETER_MISMATCH;
    }

    /**
     * Get display status for table
     */
    public function getDisplayStatus(): string
    {
        return match ($this->status) {
            self::STATUS_MATCH => '',
            self::STATUS_MISSING_DOCUMENTATION => '✗ Missing Doc',
            self::STATUS_MISSING_IMPLEMENTATION => '✗ Missing Impl',
            self::STATUS_PARAMETER_MISMATCH => '⚠ Param Mismatch',
            default => '⚠ Unknown',
        };
    }

    /**
     * Get source indicator (Laravel, OpenAPI, or Both)
     */
    public function getSource(): string
    {
        if ($this->route && $this->endpoint) {
            return 'Both';
        }
        if ($this->route instanceof \Maan511\OpenapiToLaravel\Models\LaravelRoute) {
            return 'Laravel';
        }
        if ($this->endpoint instanceof \Maan511\OpenapiToLaravel\Models\EndpointDefinition) {
            return 'OpenAPI';
        }

        return 'Unknown';
    }

    /**
     * Get Laravel parameters
     *
     * @return array<string>
     */
    public function getLaravelParameters(): array
    {
        return $this->route instanceof \Maan511\OpenapiToLaravel\Models\LaravelRoute ? $this->route->pathParameters : [];
    }

    /**
     * Get OpenAPI parameters
     *
     * @return array<string>
     */
    public function getOpenApiParameters(): array
    {
        return $this->endpoint?->getPathParameters() ?? [];
    }

    /**
     * Convert to array representation
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'method' => $this->method,
            'path' => $this->path,
            'status' => $this->status,
            'source' => $this->getSource(),
            'laravel_parameters' => $this->getLaravelParameters(),
            'openapi_parameters' => $this->getOpenApiParameters(),
            'route' => $this->route?->toArray(),
            'endpoint' => $this->endpoint?->toArray(),
            'mismatch' => $this->mismatch?->toArray(),
        ];
    }
}
