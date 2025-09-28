<?php

namespace Maan511\OpenapiToLaravel\Models;

use InvalidArgumentException;

/**
 * Represents a single API operation (GET, POST, etc.) with its request schema
 */
class EndpointDefinition
{
    /**
     * @param  array<array<string, mixed>>  $parameters
     */
    public function __construct(
        public readonly string $path,
        public readonly string $method,
        public readonly string $operationId,
        public readonly ?SchemaObject $requestSchema = null,
        public readonly string $summary = '',
        public readonly string $description = '',
        /** @var array<string> */
        public readonly array $tags = [],
        /** @var array<array<string, mixed>> */
        public readonly array $parameters = []
    ) {
        $this->validatePath();
        $this->validateMethod();
        $this->validateOperationId();
    }

    /**
     * Create instance from OpenAPI operation definition
     */
    /**
     * Create instance from OpenAPI operation definition
     *
     * @param  array<string, mixed>  $operation
     */
    public static function fromOperation(
        string $path,
        string $method,
        array $operation,
        ?SchemaObject $requestSchema = null
    ): self {
        return new self(
            path: $path,
            method: strtoupper($method),
            operationId: self::validateString($operation['operationId'] ?? null) ?? self::generateOperationId($path, $method),
            requestSchema: $requestSchema,
            summary: self::validateString($operation['summary'] ?? null) ?? '',
            description: self::validateString($operation['description'] ?? null) ?? '',
            tags: self::validateStringArray($operation['tags'] ?? []),
            parameters: self::validateArray($operation['parameters'] ?? [])
        );
    }

    /**
     * Get unique endpoint identifier
     */
    public function getId(): string
    {
        return "{$this->method}_{$this->path}";
    }

    /**
     * Get endpoint display name
     */
    public function getDisplayName(): string
    {
        return "{$this->method} {$this->path}";
    }

    /**
     * Check if endpoint has request body
     */
    public function hasRequestBody(): bool
    {
        return $this->requestSchema instanceof \Maan511\OpenapiToLaravel\Models\SchemaObject;
    }

    /**
     * Check if endpoint has parameters
     */
    public function hasParameters(): bool
    {
        return $this->parameters !== [];
    }

    /**
     * Get parameter names
     *
     * @return array<string>
     */
    public function getParameterNames(): array
    {
        return array_column($this->parameters, 'name');
    }

    /**
     * Get required parameter names
     *
     * @return array<string>
     */
    public function getRequiredParameterNames(): array
    {
        return array_column(
            array_filter($this->parameters, fn (array $param): mixed => $param['required'] ?? false),
            'name'
        );
    }

    /**
     * Check if this is a read operation (GET, HEAD, OPTIONS)
     */
    public function isReadOperation(): bool
    {
        return in_array($this->method, ['GET', 'HEAD', 'OPTIONS']);
    }

    /**
     * Check if this is a write operation (POST, PUT, PATCH, DELETE)
     */
    public function isWriteOperation(): bool
    {
        return in_array($this->method, ['POST', 'PUT', 'PATCH', 'DELETE']);
    }

    /**
     * Generate FormRequest class name from this endpoint
     */
    public function generateFormRequestClassName(): string
    {
        // Use operationId if available and convert to PascalCase
        if ($this->operationId !== '' && $this->operationId !== '0') {
            $className = $this->convertToPascalCase($this->operationId);
        } else {
            // Fallback: generate from method and path
            $className = $this->generateClassNameFromPath();
        }

        // Ensure it ends with "Request"
        if (! str_ends_with($className, 'Request')) {
            $className .= 'Request';
        }

        return $className;
    }

    /**
     * Get path parameters (from path like /users/{id})
     *
     * @return array<string>
     */
    public function getPathParameters(): array
    {
        preg_match_all('/\{([^}]+)\}/', $this->path, $matches);

        return $matches[1];
    }

    /**
     * Check if path has parameters
     */
    public function hasPathParameters(): bool
    {
        return $this->getPathParameters() !== [];
    }

    /**
     * Get normalized signature with generic parameter placeholders for matching
     */
    public function getNormalizedSignature(): string
    {
        // Replace parameter names with generic placeholders
        $paramCounter = 1;
        $normalizedPath = preg_replace_callback('/\{[^}]+\}/', function () use (&$paramCounter): string {
            return '{param' . $paramCounter++ . '}';
        }, $this->path) ?? $this->path;

        return strtoupper($this->method) . ':' . $normalizedPath;
    }

    /**
     * Get all tags as comma-separated string
     */
    public function getTagsString(): string
    {
        return implode(', ', $this->tags);
    }

    /**
     * Check if endpoint is tagged with specific tag
     */
    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags);
    }

    /**
     * Validate path format
     */
    private function validatePath(): void
    {
        if ($this->path === '' || $this->path === '0') {
            throw new InvalidArgumentException('Path cannot be empty');
        }

        if (! str_starts_with($this->path, '/')) {
            throw new InvalidArgumentException('Path must start with /');
        }
    }

    /**
     * Validate HTTP method
     */
    private function validateMethod(): void
    {
        $validMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'];

        if (! in_array($this->method, $validMethods)) {
            throw new InvalidArgumentException(
                "Invalid HTTP method: {$this->method}. Must be one of: " . implode(', ', $validMethods)
            );
        }
    }

    /**
     * Validate operation ID format
     */
    private function validateOperationId(): void
    {
        if ($this->operationId === '' || $this->operationId === '0') {
            throw new InvalidArgumentException('Operation ID cannot be empty');
        }

        if (! preg_match('/^[a-zA-Z]\w*$/', $this->operationId)) {
            throw new InvalidArgumentException(
                "Invalid operation ID: {$this->operationId}. Must start with letter and contain only letters, numbers, and underscores."
            );
        }
    }

    /**
     * Generate operation ID from path and method
     */
    private static function generateOperationId(string $path, string $method): string
    {
        // Remove path parameters and convert to camelCase
        $cleanPath = preg_replace('/\{[^}]+\}/', 'Id', $path) ?? $path;
        $parts = explode('/', trim($cleanPath, '/'));
        $parts = array_filter($parts); // Remove empty parts

        $operationId = strtolower($method);
        foreach ($parts as $part) {
            // Handle hyphens and underscores in path segments
            $part = preg_replace('/[_\-]/', ' ', $part) ?? $part;
            $part = ucwords(strtolower($part));
            $operationId .= str_replace(' ', '', $part);
        }

        return $operationId;
    }

    /**
     * Convert string to PascalCase
     */
    private function convertToPascalCase(string $string): string
    {
        // Handle camelCase and snake_case
        $string = preg_replace('/[_\-]/', ' ', $string) ?? $string;
        $string = ucwords($string);

        return str_replace(' ', '', $string);
    }

    /**
     * Generate class name from method and path
     */
    private function generateClassNameFromPath(): string
    {
        $method = ucfirst(strtolower($this->method));
        $pathParts = explode('/', trim($this->path, '/'));
        $pathParts = array_filter($pathParts);

        $pathString = '';
        foreach ($pathParts as $part) {
            // Convert {id} to Id
            if (preg_match('/\{(.+)\}/', $part, $matches)) {
                $pathString .= ucfirst($matches[1]);
            } else {
                $pathString .= ucfirst(strtolower($part));
            }
        }

        return $method . $pathString;
    }

    /**
     * Convert to array representation
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'method' => $this->method,
            'operationId' => $this->operationId,
            'summary' => $this->summary,
            'description' => $this->description,
            'tags' => $this->tags,
            'parameters' => $this->parameters,
            'requestSchema' => $this->requestSchema?->toArray(),
        ];
    }

    /**
     * Validate and cast to string or null
     */
    private static function validateString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        return null;
    }

    /**
     * Validate and cast to string array
     *
     * @return array<string>
     */
    private static function validateStringArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $item) {
            if (is_string($item)) {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * Validate and cast to array
     *
     * @return array<array<string, mixed>>
     */
    private static function validateArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        return [];
    }
}
