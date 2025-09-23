<?php

namespace Maan511\OpenapiToLaravel\Models;

/**
 * Represents a single API operation (GET, POST, etc.) with its request schema
 */
class EndpointDefinition
{
    public function __construct(
        public readonly string $path,
        public readonly string $method,
        public readonly string $operationId,
        public readonly ?SchemaObject $requestSchema = null,
        public readonly string $summary = '',
        public readonly string $description = '',
        public readonly array $tags = [],
        public readonly array $parameters = []
    ) {
        $this->validatePath();
        $this->validateMethod();
        $this->validateOperationId();
    }

    /**
     * Create instance from OpenAPI operation definition
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
            operationId: $operation['operationId'] ?? self::generateOperationId($path, $method),
            requestSchema: $requestSchema,
            summary: $operation['summary'] ?? '',
            description: $operation['description'] ?? '',
            tags: $operation['tags'] ?? [],
            parameters: $operation['parameters'] ?? []
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
        return $this->requestSchema !== null;
    }

    /**
     * Check if endpoint has parameters
     */
    public function hasParameters(): bool
    {
        return !empty($this->parameters);
    }

    /**
     * Get parameter names
     */
    public function getParameterNames(): array
    {
        return array_column($this->parameters, 'name');
    }

    /**
     * Get required parameter names
     */
    public function getRequiredParameterNames(): array
    {
        return array_column(
            array_filter($this->parameters, fn($param) => $param['required'] ?? false),
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
        if (!empty($this->operationId)) {
            $className = $this->convertToPascalCase($this->operationId);
        } else {
            // Fallback: generate from method and path
            $className = $this->generateClassNameFromPath();
        }

        // Ensure it ends with "Request"
        if (!str_ends_with($className, 'Request')) {
            $className .= 'Request';
        }

        return $className;
    }

    /**
     * Get path parameters (from path like /users/{id})
     */
    public function getPathParameters(): array
    {
        preg_match_all('/\{([^}]+)\}/', $this->path, $matches);
        return $matches[1] ?? [];
    }

    /**
     * Check if path has parameters
     */
    public function hasPathParameters(): bool
    {
        return !empty($this->getPathParameters());
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
        if (empty($this->path)) {
            throw new \InvalidArgumentException('Path cannot be empty');
        }

        if (!str_starts_with($this->path, '/')) {
            throw new \InvalidArgumentException('Path must start with /');
        }
    }

    /**
     * Validate HTTP method
     */
    private function validateMethod(): void
    {
        $validMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'];

        if (!in_array($this->method, $validMethods)) {
            throw new \InvalidArgumentException(
                "Invalid HTTP method: {$this->method}. Must be one of: " . implode(', ', $validMethods)
            );
        }
    }

    /**
     * Validate operation ID format
     */
    private function validateOperationId(): void
    {
        if (empty($this->operationId)) {
            throw new \InvalidArgumentException('Operation ID cannot be empty');
        }

        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $this->operationId)) {
            throw new \InvalidArgumentException(
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
        $cleanPath = preg_replace('/\{[^}]+\}/', 'Id', $path);
        $parts = explode('/', trim($cleanPath, '/'));
        $parts = array_filter($parts); // Remove empty parts

        $operationId = strtolower($method);
        foreach ($parts as $part) {
            $operationId .= ucfirst(strtolower($part));
        }

        return $operationId;
    }

    /**
     * Convert string to PascalCase
     */
    private function convertToPascalCase(string $string): string
    {
        // Handle camelCase and snake_case
        $string = preg_replace('/[_\-]/', ' ', $string);
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
}