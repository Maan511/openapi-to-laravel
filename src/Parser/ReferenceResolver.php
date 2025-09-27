<?php

namespace Maan511\OpenapiToLaravel\Parser;

use Exception;
use InvalidArgumentException;
use Maan511\OpenapiToLaravel\Models\OpenApiSpecification;
use RuntimeException;

/**
 * Resolves OpenAPI reference objects ($ref)
 */
class ReferenceResolver
{
    /** @var array<string, array<string, mixed>|null> */
    private array $resolutionCache = [];

    /** @var array<string> */
    private array $resolutionStack = [];

    private int $maxCacheSize = 1000; // Prevent unbounded cache growth

    private int $cacheHits = 0;

    /**
     * Resolve a reference to its actual schema
     *
     * @return array<string, mixed>|null
     */
    public function resolve(string $ref, OpenApiSpecification $specification): ?array
    {
        // Check cache first
        if (isset($this->resolutionCache[$ref])) {
            $this->cacheHits++;

            return $this->resolutionCache[$ref];
        }

        // Check for circular reference
        if (in_array($ref, $this->resolutionStack)) {
            throw new RuntimeException('Circular reference detected: ' . implode(' -> ', [...$this->resolutionStack, $ref]));
        }

        $this->resolutionStack[] = $ref;

        try {
            $resolved = $this->resolveReference($ref, $specification);

            // If the resolved schema contains references, resolve them too
            if ($resolved) {
                $resolved = $this->resolveAllReferences($resolved, $specification);
            }

            // Manage cache size
            if (count($this->resolutionCache) >= $this->maxCacheSize) {
                // Remove 25% of oldest entries
                $entriesToRemove = (int) ($this->maxCacheSize * 0.25);
                $keys = array_keys($this->resolutionCache);
                for ($i = 0; $i < $entriesToRemove; $i++) {
                    unset($this->resolutionCache[$keys[$i]]);
                }
            }

            $this->resolutionCache[$ref] = $resolved;

            return $resolved;
        } finally {
            array_pop($this->resolutionStack);
        }
    }

    /**
     * Resolve all references in a schema recursively
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public function resolveAllReferences(array $schema, OpenApiSpecification $specification, int $maxDepth = 10, int $currentDepth = 0): array
    {
        // Prevent infinite recursion
        if ($currentDepth >= $maxDepth) {
            return $schema;
        }

        // Check for circular references by detecting if we've seen this reference before
        if (isset($schema['$ref']) && in_array($schema['$ref'], $this->resolutionStack)) {
            throw new InvalidArgumentException('Circular reference detected');
        }

        $resolved = $schema;

        // Resolve direct $ref
        if (isset($schema['$ref'])) {
            $refSchema = $this->resolve($schema['$ref'], $specification);
            if ($refSchema) {
                $resolved = array_merge($refSchema, array_diff_key($schema, ['$ref' => '']));
            }
        }

        // Recursively resolve references in properties
        if (isset($resolved['properties'])) {
            foreach ($resolved['properties'] as $propName => $propSchema) {
                if (is_array($propSchema)) {
                    $resolved['properties'][$propName] = $this->resolveAllReferences($propSchema, $specification, $maxDepth, $currentDepth + 1);
                }
            }
        }

        // Recursively resolve references in items
        if (isset($resolved['items']) && is_array($resolved['items'])) {
            $resolved['items'] = $this->resolveAllReferences($resolved['items'], $specification, $maxDepth, $currentDepth + 1);
        }

        // Resolve references in allOf, oneOf, anyOf
        foreach (['allOf', 'oneOf', 'anyOf'] as $compositionKey) {
            if (isset($resolved[$compositionKey]) && is_array($resolved[$compositionKey])) {
                foreach ($resolved[$compositionKey] as $index => $subSchema) {
                    if (is_array($subSchema)) {
                        $resolved[$compositionKey][$index] = $this->resolveAllReferences($subSchema, $specification, $maxDepth, $currentDepth + 1);
                    }
                }
            }
        }

        return $resolved;
    }

    /**
     * Check if reference exists in specification
     */
    public function referenceExists(string $ref, OpenApiSpecification $specification): bool
    {
        try {
            return $this->resolve($ref, $specification) !== null;
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Get all references used in a schema
     *
     * @param  array<string, mixed>  $schema
     * @return array<string>
     */
    public function getReferences(array $schema): array
    {
        $references = [];

        // Direct reference
        if (isset($schema['$ref'])) {
            $references[] = $schema['$ref'];
        }

        // References in properties
        if (isset($schema['properties'])) {
            foreach ($schema['properties'] as $propSchema) {
                $references = array_merge($references, $this->getReferences($propSchema));
            }
        }

        // References in items
        if (isset($schema['items'])) {
            $references = array_merge($references, $this->getReferences($schema['items']));
        }

        // References in composition schemas
        foreach (['allOf', 'oneOf', 'anyOf'] as $compositionKey) {
            if (isset($schema[$compositionKey])) {
                foreach ($schema[$compositionKey] as $subSchema) {
                    $references = array_merge($references, $this->getReferences($subSchema));
                }
            }
        }

        return array_unique($references);
    }

    /**
     * Validate all references in specification
     *
     * @return array<string, mixed>
     */
    public function validateReferences(OpenApiSpecification $specification): array
    {
        $errors = [];
        $warnings = [];

        // Check all schemas in components
        foreach ($specification->getSchemas() as $schemaName => $schemaArray) {
            $references = $this->getReferences($schemaArray);

            foreach ($references as $ref) {
                if (! $this->referenceExists($ref, $specification)) {
                    $errors[] = "Invalid reference in schema '{$schemaName}': {$ref}";
                }
            }
        }

        // Check references in paths
        foreach ($specification->paths as $path => $pathItem) {
            foreach ($pathItem as $method => $operation) {
                if (! is_array($operation)) {
                    continue;
                }
                if (! isset($operation['requestBody'])) {
                    continue;
                }
                $this->validateRequestBodyReferences($operation['requestBody'], $specification, $path, $method, $errors);
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Flatten nested references (resolve reference chains)
     *
     * @return array<string, mixed>
     */
    public function flattenReferences(string $ref, OpenApiSpecification $specification): array
    {
        $resolved = $this->resolve($ref, $specification);
        if (! $resolved) {
            return [];
        }

        // If the resolved schema has another reference, flatten it
        if (isset($resolved['$ref'])) {
            $nestedResolved = $this->flattenReferences($resolved['$ref'], $specification);

            return array_merge($nestedResolved, array_diff_key($resolved, ['$ref' => '']));
        }

        return $resolved;
    }

    /**
     * Get reference path parts
     *
     * @return array<string>
     */
    public function parseReference(string $ref): array
    {
        if (! str_starts_with($ref, '#/')) {
            throw new InvalidArgumentException("Invalid reference format: {$ref}. Must start with '#/'");
        }

        $path = substr($ref, 2); // Remove '#/'

        return explode('/', $path);
    }

    /**
     * Check for circular references in schema
     *
     * @param  array<string, mixed>  $schema
     * @param  array<string>  $visited
     */
    public function hasCircularReferences(array $schema, OpenApiSpecification $specification, array $visited = []): bool
    {
        if (isset($schema['$ref'])) {
            if (in_array($schema['$ref'], $visited)) {
                return true;
            }

            $resolved = $this->resolve($schema['$ref'], $specification);
            if ($resolved) {
                return $this->hasCircularReferences($resolved, $specification, [...$visited, $schema['$ref']]);
            }
        }

        // Check properties
        if (isset($schema['properties'])) {
            foreach ($schema['properties'] as $propSchema) {
                if ($this->hasCircularReferences($propSchema, $specification, $visited)) {
                    return true;
                }
            }
        }

        // Check items
        return isset($schema['items']) && $this->hasCircularReferences($schema['items'], $specification, $visited);
    }

    /**
     * Clear resolution cache
     */
    public function clearCache(): void
    {
        $this->resolutionCache = [];
        $this->resolutionStack = [];
    }

    /**
     * Get cache statistics
     *
     * @return array<string, int>
     */
    public function getCacheStats(): array
    {
        return [
            'cached_references' => count($this->resolutionCache),
            'resolution_stack_depth' => count($this->resolutionStack),
            'cache_hits' => $this->cacheHits,
            'max_cache_size' => $this->maxCacheSize,
        ];
    }

    /**
     * Check if a schema object is a reference
     *
     * @param  mixed  $data
     */
    public function isReference($data): bool
    {
        return is_array($data) && isset($data['$ref']);
    }

    /**
     * Get the type of reference (schema, parameter, response, etc.)
     */
    public function getReferenceType(string $ref): string
    {
        if (! str_starts_with($ref, '#/')) {
            return 'unknown';
        }

        $pathParts = $this->parseReference($ref);

        if (count($pathParts) >= 2 && $pathParts[0] === 'components') {
            return match ($pathParts[1]) {
                'schemas' => 'schema',
                'parameters' => 'parameter',
                'responses' => 'response',
                'requestBodies' => 'requestBody',
                'headers' => 'header',
                'securitySchemes' => 'securityScheme',
                'links' => 'link',
                'callbacks' => 'callback',
                default => 'unknown'
            };
        }

        return 'unknown';
    }

    /**
     * Extract the path components from a reference
     *
     * @return array<string>
     */
    public function extractReferencePath(string $ref): array
    {
        if (! str_starts_with($ref, '#/')) {
            throw new InvalidArgumentException("Invalid reference format: {$ref}");
        }

        $path = substr($ref, 2); // Remove '#/'
        $parts = explode('/', $path);

        // Decode JSON pointer escapes
        return array_map(fn ($part): string => str_replace(['~1', '~0'], ['/', '~'], $part), $parts);
    }

    /**
     * Validate reference format and structure
     *
     * @return array<string, mixed>
     */
    public function validateReference(string $ref): array
    {
        $errors = [];

        // Check if empty
        if ($ref === '' || $ref === '0') {
            $errors[] = 'Reference cannot be empty';

            return ['valid' => false, 'errors' => $errors];
        }

        // Check for external file references (contains filename before #)
        if (strpos($ref, '#') > 0) {
            $errors[] = 'External file references are not supported';

            return ['valid' => false, 'errors' => $errors];
        }

        // Check format - must start with #/
        if (! str_starts_with($ref, '#/')) {
            $errors[] = 'Reference must start with #/';

            return ['valid' => false, 'errors' => $errors];
        }

        // Try to parse the reference path
        try {
            $this->extractReferencePath($ref);
        } catch (InvalidArgumentException $e) {
            $errors[] = "Invalid reference path: {$e->getMessage()}";
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
        ];
    }

    /**
     * Resolve a single reference to its schema
     *
     * @return array<string, mixed>|null
     */
    private function resolveReference(string $ref, OpenApiSpecification $specification): ?array
    {
        // Check if it's a completely invalid reference format (no # at all)
        if (! str_contains($ref, '#')) {
            throw new InvalidArgumentException("Invalid reference format: {$ref}");
        }

        // Check for external file references (filename before #)
        if (strpos($ref, '#') > 0) {
            throw new InvalidArgumentException('External file references are not supported');
        }

        // Only support internal references (must start with #/)
        if (! str_starts_with($ref, '#/')) {
            throw new InvalidArgumentException("Invalid reference format: {$ref}");
        }

        // Validate reference format
        $validation = $this->validateReference($ref);
        if (! $validation['valid']) {
            throw new InvalidArgumentException("Invalid reference format: {$ref}");
        }

        $pathParts = $this->parseReference($ref);

        // Navigate through the specification structure
        $current = $specification->toArray();

        foreach ($pathParts as $part) {
            if (! is_array($current) || ! isset($current[$part])) {
                throw new InvalidArgumentException("Reference not found: {$ref}");
            }
            $current = $current[$part];
        }

        return is_array($current) ? $current : null;
    }

    /**
     * Validate references in request body
     *
     * @param  array<string, mixed>  $requestBody
     * @param  array<string>  $errors
     */
    private function validateRequestBodyReferences(
        array $requestBody,
        OpenApiSpecification $specification,
        string $path,
        string $method,
        array &$errors
    ): void {
        if (! isset($requestBody['content'])) {
            return;
        }

        foreach ($requestBody['content'] as $contentType => $content) {
            if (! isset($content['schema'])) {
                continue;
            }

            $references = $this->getReferences($content['schema']);
            foreach ($references as $ref) {
                if (! $this->referenceExists($ref, $specification)) {
                    $errors[] = "Invalid reference in {$method} {$path} ({$contentType}): {$ref}";
                }
            }
        }
    }
}
