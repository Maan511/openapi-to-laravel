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
        public readonly array $info,
        public readonly array $paths,
        public readonly array $components = [],
        public readonly array $servers = []
    ) {
        // Minimal validation in constructor to allow test scenarios
        // Full validation should be done via validateSpecification method
    }

    /**
     * Create instance from parsed specification array
     */
    public static function fromArray(array $spec, string $filePath): self
    {
        return new self(
            filePath: $filePath,
            version: $spec['openapi'] ?? '',
            info: $spec['info'] ?? [],
            paths: $spec['paths'] ?? [],
            components: $spec['components'] ?? [],
            servers: $spec['servers'] ?? []
        );
    }

    /**
     * Get specification title
     */
    public function getTitle(): string
    {
        return $this->info['title'] ?? 'Untitled API';
    }

    /**
     * Get specification version
     */
    public function getSpecVersion(): string
    {
        return $this->info['version'] ?? '1.0.0';
    }

    /**
     * Get specification description
     */
    public function getDescription(): string
    {
        return $this->info['description'] ?? '';
    }

    /**
     * Get all endpoint paths
     */
    public function getPaths(): array
    {
        return array_keys($this->paths);
    }

    /**
     * Get operations for a specific path
     */
    public function getOperationsForPath(string $path): array
    {
        return $this->paths[$path] ?? [];
    }

    /**
     * Get all schemas from components
     */
    public function getSchemas(): array
    {
        return $this->components['schemas'] ?? [];
    }

    /**
     * Get schema by reference
     */
    public function getSchemaByRef(string $ref): ?array
    {
        // Handle $ref format: #/components/schemas/SchemaName
        if (! str_starts_with($ref, '#/components/schemas/')) {
            return null;
        }

        $schemaName = substr($ref, strlen('#/components/schemas/'));
        $schemas = $this->getSchemas();

        return $schemas[$schemaName] ?? null;
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
     */
    public function getAllMethods(): array
    {
        $methods = [];

        foreach ($this->paths as $operations) {
            $methods = array_merge($methods, array_keys($operations));
        }

        return array_unique(array_map('strtoupper', $methods));
    }

    /**
     * Count total number of operations
     */
    public function getOperationCount(): int
    {
        $count = 0;

        foreach ($this->paths as $operations) {
            $count += count($operations);
        }

        return $count;
    }

    /**
     * Convert to array representation
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
}
