<?php

namespace Maan511\OpenapiToLaravel\Models;

use InvalidArgumentException;

/**
 * Represents specific discrepancies between routes and specifications
 */
class RouteMismatch
{
    public const TYPE_MISSING_DOCUMENTATION = 'missing_documentation';

    public const TYPE_MISSING_IMPLEMENTATION = 'missing_implementation';

    public const TYPE_METHOD_MISMATCH = 'method_mismatch';

    public const TYPE_PARAMETER_MISMATCH = 'parameter_mismatch';

    public const TYPE_PATH_MISMATCH = 'path_mismatch';

    public const TYPE_VALIDATION_ERROR = 'validation_error';

    /**
     * @param  array<string, mixed>  $details
     * @param  array<string>  $suggestions
     */
    public function __construct(
        public readonly string $type,
        public readonly string $message,
        public readonly string $path,
        public readonly string $method,
        public readonly array $details = [],
        public readonly array $suggestions = [],
        public readonly string $severity = 'error'
    ) {
        $this->validateType();
        $this->validateSeverity();
    }

    /**
     * Create a generic error mismatch
     */
    public static function createError(string $type, string $message, string $path, string $method): self
    {
        return new self(
            type: $type,
            message: $message,
            path: $path,
            method: $method,
            severity: 'error'
        );
    }

    /**
     * Create a missing documentation mismatch
     *
     * @param  array<string>  $suggestions
     */
    public static function missingDocumentation(LaravelRoute $route, array $suggestions = []): self
    {
        return new self(
            type: self::TYPE_MISSING_DOCUMENTATION,
            message: "Route '{$route->getSignature()}' is implemented but not documented in OpenAPI specification",
            path: $route->getNormalizedPath(),
            method: $route->getPrimaryMethod(),
            details: [
                'route_name' => $route->name,
                'action' => $route->action,
                'middleware' => $route->middleware,
            ],
            suggestions: $suggestions ?: [
                "Add '{$route->getPrimaryMethod()} {$route->getNormalizedPath()}' to your OpenAPI specification",
                'Consider if this route should be excluded from API documentation',
            ]
        );
    }

    /**
     * Create a missing implementation mismatch
     *
     * @param  array<string>  $suggestions
     */
    public static function missingImplementation(EndpointDefinition $endpoint, array $suggestions = []): self
    {
        return new self(
            type: self::TYPE_MISSING_IMPLEMENTATION,
            message: "Endpoint '{$endpoint->getDisplayName()}' is documented but not implemented in Laravel routes",
            path: $endpoint->path,
            method: $endpoint->method,
            details: [
                'operation_id' => $endpoint->operationId,
                'summary' => $endpoint->summary,
                'tags' => $endpoint->tags,
            ],
            suggestions: $suggestions ?: [
                "Implement route '{$endpoint->method} {$endpoint->path}' in your Laravel application",
                'Remove this endpoint from OpenAPI specification if not needed',
            ]
        );
    }

    /**
     * Create a method mismatch
     *
     * @param  array<string>  $laravelMethods
     * @param  array<string>  $openApiMethods
     */
    public static function methodMismatch(string $path, array $laravelMethods, array $openApiMethods): self
    {
        return new self(
            type: self::TYPE_METHOD_MISMATCH,
            message: "Path '{$path}' has different HTTP methods in Laravel routes vs OpenAPI specification",
            path: $path,
            method: implode(',', array_merge($laravelMethods, $openApiMethods)),
            details: [
                'laravel_methods' => $laravelMethods,
                'openapi_methods' => $openApiMethods,
                'missing_in_laravel' => array_diff($openApiMethods, $laravelMethods),
                'missing_in_openapi' => array_diff($laravelMethods, $openApiMethods),
            ],
            suggestions: [
                'Align HTTP methods between Laravel routes and OpenAPI specification',
                'Consider if different methods are intentionally excluded',
            ]
        );
    }

    /**
     * Create a parameter mismatch
     *
     * @param  array<string>  $laravelParams
     * @param  array<string>  $openApiParams
     */
    public static function parameterMismatch(string $path, string $method, array $laravelParams, array $openApiParams): self
    {
        return new self(
            type: self::TYPE_PARAMETER_MISMATCH,
            message: "Path '{$path}' has different parameters in Laravel routes vs OpenAPI specification",
            path: $path,
            method: $method,
            details: [
                'laravel_parameters' => $laravelParams,
                'openapi_parameters' => $openApiParams,
                'missing_in_laravel' => array_diff($openApiParams, $laravelParams),
                'missing_in_openapi' => array_diff($laravelParams, $openApiParams),
            ],
            suggestions: [
                'Align path parameters between Laravel routes and OpenAPI specification',
                'Check for parameter naming differences (camelCase vs snake_case)',
            ]
        );
    }

    /**
     * Get severity level as integer for sorting
     */
    public function getSeverityLevel(): int
    {
        return match ($this->severity) {
            'error' => 3,
            'warning' => 2,
            'info' => 1,
            default => 0,
        };
    }

    /**
     * Check if this is an error-level mismatch
     */
    public function isError(): bool
    {
        return $this->severity === 'error';
    }

    /**
     * Check if this is a warning-level mismatch
     */
    public function isWarning(): bool
    {
        return $this->severity === 'warning';
    }

    /**
     * Get formatted suggestion text
     */
    public function getSuggestionsText(): string
    {
        if ($this->suggestions === []) {
            return 'No suggestions available';
        }

        return implode("\n", array_map(fn (string $s): string => "• {$s}", $this->suggestions));
    }

    /**
     * Convert to array representation
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'message' => $this->message,
            'path' => $this->path,
            'method' => $this->method,
            'severity' => $this->severity,
            'details' => $this->details,
            'suggestions' => $this->suggestions,
        ];
    }

    /**
     * Validate mismatch type
     */
    private function validateType(): void
    {
        $validTypes = [
            self::TYPE_MISSING_DOCUMENTATION,
            self::TYPE_MISSING_IMPLEMENTATION,
            self::TYPE_METHOD_MISMATCH,
            self::TYPE_PARAMETER_MISMATCH,
            self::TYPE_PATH_MISMATCH,
            self::TYPE_VALIDATION_ERROR,
        ];

        if (! in_array($this->type, $validTypes)) {
            throw new InvalidArgumentException("Invalid mismatch type: {$this->type}");
        }
    }

    /**
     * Validate severity level
     */
    private function validateSeverity(): void
    {
        $validSeverities = ['error', 'warning', 'info'];

        if (! in_array($this->severity, $validSeverities)) {
            throw new InvalidArgumentException("Invalid severity level: {$this->severity}");
        }
    }
}
