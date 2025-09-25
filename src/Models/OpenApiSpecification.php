<?php

namespace Maan511\OpenapiToLaravel\Models;

/**
 * Represents a parsed OpenAPI 3.x specification document
 */
class OpenApiSpecification
{
    public function __construct(
        public readonly string $filePath,
        public readonly string $version,
        /** @var array<string, mixed> */
        public readonly array $info,
        /** @var array<string, mixed> */
        public readonly array $paths,
        /** @var array<string, mixed> */
        public readonly array $components = [],
        /** @var array<string, mixed> */
        public readonly array $servers = []
    ) {
        // Minimal validation in constructor to allow test scenarios
        // Full validation should be done via validateSpecification method
    }

    /**
     * Create instance from parsed specification array
     *
     * @param  array<string, mixed>  $spec
     */
    public static function fromArray(array $spec, string $filePath): self
    {
        return new self(
            filePath: $filePath,
            version: self::validateString($spec['openapi'] ?? '') ?? '',
            info: self::validateArray($spec['info'] ?? []),
            paths: self::validateArray($spec['paths'] ?? []),
            components: self::validateArray($spec['components'] ?? []),
            servers: self::validateArray($spec['servers'] ?? [])
        );
    }

    /**
     * Get specification title
     */
    public function getTitle(): string
    {
        $title = $this->info['title'] ?? 'Untitled API';

        return is_string($title) ? $title : 'Untitled API';
    }

    /**
     * Get specification version
     */
    public function getSpecVersion(): string
    {
        $version = $this->info['version'] ?? '1.0.0';

        return is_string($version) ? $version : '1.0.0';
    }

    /**
     * Get specification description
     */
    public function getDescription(): string
    {
        $description = $this->info['description'] ?? '';

        return is_string($description) ? $description : '';
    }

    /**
     * Get all endpoint paths
     *
     * @return array<string>
     */
    public function getPaths(): array
    {
        return array_keys($this->paths);
    }

    /**
     * Get operations for a specific path
     *
     * @return array<string, mixed>
     */
    public function getOperationsForPath(string $path): array
    {
        $operations = $this->paths[$path] ?? [];

        return is_array($operations) ? $operations : [];
    }

    /**
     * Get all schemas from components
     *
     * @return array<string, mixed>
     */
    public function getSchemas(): array
    {
        $schemas = $this->components['schemas'] ?? [];

        return is_array($schemas) ? $schemas : [];
    }

    /**
     * Get schema by reference
     *
     * @return array<string, mixed>|null
     */
    public function getSchemaByRef(string $ref): ?array
    {
        // Handle $ref format: #/components/schemas/SchemaName
        if (! str_starts_with($ref, '#/components/schemas/')) {
            return null;
        }

        $schemaName = substr($ref, strlen('#/components/schemas/'));
        $schemas = $this->getSchemas();
        $schema = $schemas[$schemaName] ?? null;

        return is_array($schema) ? $schema : null;
    }

    /**
     * Check if specification has a specific path
     */
    public function hasPath(string $path): bool
    {
        return isset($this->paths[$path]);
    }

    /**
     * Check if specification has components
     */
    public function hasComponents(): bool
    {
        return ! empty($this->components);
    }

    /**
     * Get all HTTP methods used in the specification
     *
     * @return array<string>
     */
    public function getAllMethods(): array
    {
        $methods = [];

        foreach ($this->paths as $operations) {
            if (is_array($operations)) {
                $methods = array_merge($methods, array_keys($operations));
            }
        }

        return array_unique(array_map(fn ($method): string => strtoupper((string) $method), $methods));
    }

    /**
     * Count total number of operations
     */
    public function getOperationCount(): int
    {
        $count = 0;

        foreach ($this->paths as $operations) {
            if (is_array($operations)) {
                $count += count($operations);
            }
        }

        return $count;
    }

    /**
     * Convert to array representation
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'openapi' => $this->version,
            'info' => $this->info,
            'paths' => $this->paths,
            'components' => $this->components,
            'servers' => $this->servers,
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
     * Validate and cast to array
     *
     * @return array<string, mixed>
     */
    private static function validateArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        return [];
    }
}
