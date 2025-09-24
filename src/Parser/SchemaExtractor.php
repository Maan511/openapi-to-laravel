<?php

namespace Maan511\OpenapiToLaravel\Parser;

use InvalidArgumentException;
use Maan511\OpenapiToLaravel\Models\OpenApiSpecification;
use Maan511\OpenapiToLaravel\Models\SchemaObject;

/**
 * Extracts schemas from OpenAPI operations and components
 */
class SchemaExtractor
{
    public function __construct(
        private readonly ReferenceResolver $referenceResolver
    ) {}

    /**
     * Create a SchemaObject from raw schema data
     */
    /**
     * @param  array<string, mixed>  $schemaData
     */
    public function createSchemaObject(array $schemaData): SchemaObject
    {
        return SchemaObject::fromArray($schemaData);
    }

    /**
     * Extract validation constraints from schema data
     *
     * @param  array<string, mixed>  $schemaData
     */
    public function extractValidationConstraints(array $schemaData): \Maan511\OpenapiToLaravel\Models\ValidationConstraints
    {
        return \Maan511\OpenapiToLaravel\Models\ValidationConstraints::fromSchema($schemaData);
    }

    /**
     * Get the schema type from schema data
     */
    /**
     * @param  array<string, mixed>  $schemaData
     */
    public function getSchemaType(array $schemaData): ?string
    {
        // Direct type field
        if (isset($schemaData['type'])) {
            return $schemaData['type'];
        }

        // Infer type from structure
        if (isset($schemaData['properties'])) {
            return 'object';
        }

        if (isset($schemaData['items'])) {
            return 'array';
        }

        return null;
    }

    /**
     * Check if schema data represents an object
     */
    /**
     * @param  array<string, mixed>  $schemaData
     */
    public function isSchemaObject(array $schemaData): bool
    {
        return $this->getSchemaType($schemaData) === 'object';
    }

    /**
     * Merge two schema arrays
     *
     * @param  array<mixed>  $schema1
     * @param  array<mixed>  $schema2
     * @return array<mixed>
     */
    public function mergeSchemas(array $schema1, array $schema2): array
    {
        // If both are object schemas, merge properties
        if ($this->isSchemaObject($schema1) && $this->isSchemaObject($schema2)) {
            $merged = $schema2; // Start with second schema

            if (isset($schema1['properties']) && isset($schema2['properties'])) {
                $merged['properties'] = array_merge($schema1['properties'], $schema2['properties']);
            } elseif (isset($schema1['properties'])) {
                $merged['properties'] = $schema1['properties'];
            }

            if (isset($schema1['required']) && isset($schema2['required'])) {
                $merged['required'] = array_unique(array_merge($schema1['required'], $schema2['required']));
            } elseif (isset($schema1['required'])) {
                $merged['required'] = $schema1['required'];
            }

            return $merged;
        }

        // For non-object schemas, second takes precedence
        return $schema2;
    }

    /**
     * Validate schema data structure
     */
    /**
     * @param  array<string, mixed>  $schemaData
     * @return array{valid: bool, errors: array<string>, warnings: array<string>}
     */
    public function validateSchemaData(array $schemaData): array
    {
        $errors = [];

        // Schema must have either type, properties, or items
        if (! isset($schemaData['type']) && ! isset($schemaData['properties']) && ! isset($schemaData['items'])) {
            $errors[] = 'Schema must have either type, properties, or items';
        }

        // Validate properties structure
        if (isset($schemaData['properties']) && ! is_array($schemaData['properties'])) {
            $errors[] = 'Properties must be an array';
        }

        // Validate items structure
        if (isset($schemaData['items']) && ! is_array($schemaData['items'])) {
            $errors[] = 'Items must be an array';
        }

        // Validate required field
        if (isset($schemaData['required']) && ! is_array($schemaData['required'])) {
            $errors[] = 'Required field must be an array';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => [],
        ];
    }

    /**
     * Extract schema from request body
     *
     * @param  array<string, mixed>  $requestBody
     */
    public function extractFromRequestBody(array $requestBody, OpenApiSpecification $specification): ?SchemaObject
    {
        if (! isset($requestBody['content'])) {
            throw new InvalidArgumentException('No content found in request body');
        }

        // Look for JSON content first, then specific types, then any other content type
        $content = $requestBody['content'];
        $schema = null;

        // Prefer application/json
        if (isset($content['application/json']['schema'])) {
            $schema = $content['application/json']['schema'];
        }
        // If no JSON, fall back to first available content type (for test compatibility)
        else {
            $firstContent = reset($content);
            if (isset($firstContent['schema'])) {
                $schema = $firstContent['schema'];
            }
        }

        if (! $schema) {
            throw new InvalidArgumentException('No content found in request body');
        }

        return $this->parseSchema($schema, $specification);
    }

    /**
     * Extract schema from parameters
     *
     * @param  array<mixed>  $parameters
     */
    public function extractFromParameters(array $parameters, OpenApiSpecification $specification): ?SchemaObject
    {
        if (empty($parameters)) {
            return null;
        }

        // Create a synthetic object schema from parameters
        $properties = [];
        $required = [];

        foreach ($parameters as $parameter) {
            // Resolve parameter reference if present
            if (isset($parameter['$ref'])) {
                $resolvedParam = $this->referenceResolver->resolve($parameter['$ref'], $specification);
                if ($resolvedParam) {
                    $parameter = $resolvedParam;
                }
            }

            $name = $parameter['name'] ?? null;
            if (! $name) {
                continue;
            }

            // Skip header and cookie parameters (only include query and path parameters)
            $parameterIn = $parameter['in'] ?? 'query';
            if (in_array($parameterIn, ['header', 'cookie'])) {
                continue;
            }

            $paramSchema = $parameter['schema'] ?? ['type' => 'string'];
            $properties[$name] = $this->parseSchema($paramSchema, $specification);

            if ($parameter['required'] ?? false) {
                $required[] = $name;
            }
        }

        if (empty($properties)) {
            return null;
        }

        return new SchemaObject(
            type: 'object',
            properties: $properties,
            required: $required,
            title: 'Parameters',
            description: 'Request parameters'
        );
    }

    /**
     * Extract schema from components by reference
     */
    public function extractFromComponents(string $ref, OpenApiSpecification $specification): ?SchemaObject
    {
        $schema = $specification->getSchemaByRef($ref);
        if (! $schema) {
            return null;
        }

        return $this->parseSchema($schema, $specification);
    }

    /**
     * Parse schema array into SchemaObject
     *
     * @param  array<string, mixed>  $schema
     */
    public function parseSchema(array $schema, OpenApiSpecification $specification): SchemaObject
    {
        // Resolve reference if present
        if (isset($schema['$ref'])) {
            $resolvedSchema = $this->referenceResolver->resolve($schema['$ref'], $specification);
            if ($resolvedSchema) {
                // Merge any additional properties with resolved schema
                $schema = array_merge($resolvedSchema, array_diff_key($schema, ['$ref' => '']));
            }
        }

        return SchemaObject::fromArray($schema);
    }

    /**
     * Extract all unique schemas from specification
     *
     * @return array<string, SchemaObject>
     */
    public function extractAllSchemas(OpenApiSpecification $specification): array
    {
        $schemas = [];

        // Extract from components/schemas
        foreach ($specification->getSchemas() as $name => $schemaArray) {
            $schemas[$name] = $this->parseSchema($schemaArray, $specification);
        }

        // Extract from operation request bodies
        foreach ($specification->paths as $path => $pathItem) {
            foreach ($pathItem as $method => $operation) {
                if (! is_array($operation) || ! isset($operation['requestBody'])) {
                    continue;
                }

                $schema = $this->extractFromRequestBody($operation['requestBody'], $specification);
                if ($schema) {
                    $operationId = $operation['operationId'] ?? "{$method}_{$path}";
                    $schemas[$operationId] = $schema;
                }
            }
        }

        return $schemas;
    }

    /**
     * Extract nested schemas recursively
     *
     * @return array<string, SchemaObject>
     */
    public function extractNestedSchemas(SchemaObject $schema, OpenApiSpecification $specification): array
    {
        $nestedSchemas = [];

        // Extract from properties
        foreach ($schema->properties as $propertyName => $propertySchema) {
            $nestedSchemas[$propertyName] = $propertySchema;

            // Recursively extract from nested properties
            $deepNested = $this->extractNestedSchemas($propertySchema, $specification);
            foreach ($deepNested as $nestedName => $nestedSchema) {
                $nestedSchemas["{$propertyName}.{$nestedName}"] = $nestedSchema;
            }
        }

        // Extract from array items
        if ($schema->items) {
            $nestedSchemas['*'] = $schema->items;

            // Recursively extract from array item schema
            $deepNested = $this->extractNestedSchemas($schema->items, $specification);
            foreach ($deepNested as $nestedName => $nestedSchema) {
                $nestedSchemas["*.{$nestedName}"] = $nestedSchema;
            }
        }

        return $nestedSchemas;
    }

    /**
     * Check if schema has validation constraints
     */
    public function hasValidationConstraints(SchemaObject $schema): bool
    {
        return $schema->hasValidation()
            || ! empty($schema->required)
            || $schema->format !== null;
    }

    /**
     * Extract schemas that need FormRequest generation
     *
     * @return array<string, array<string, mixed>>
     */
    public function extractFormRequestSchemas(OpenApiSpecification $specification): array
    {
        $schemas = [];

        foreach ($specification->paths as $path => $pathItem) {
            foreach ($pathItem as $method => $operation) {
                if (! is_array($operation)) {
                    continue;
                }

                // Only process operations with request bodies
                if (! isset($operation['requestBody'])) {
                    continue;
                }

                $schema = $this->extractFromRequestBody($operation['requestBody'], $specification);
                if (! $schema) {
                    continue;
                }

                $operationId = $operation['operationId'] ?? $this->generateOperationId($path, $method);
                $schemas[$operationId] = [
                    'schema' => $schema,
                    'path' => $path,
                    'method' => strtoupper($method),
                    'operation' => $operation,
                ];
            }
        }

        return $schemas;
    }

    /**
     * Validate schema structure
     *
     * @return array{valid: bool, errors: array<string>, warnings: array<string>}
     */
    public function validateSchema(SchemaObject $schema): array
    {
        $errors = [];
        $warnings = [];

        // Check for circular references
        if ($schema->hasCircularReference()) {
            $warnings[] = 'Schema contains circular references';
        }

        // Check maximum depth
        $maxDepth = $schema->getMaxDepth();
        if ($maxDepth > 10) {
            $warnings[] = "Schema has deep nesting (depth: {$maxDepth})";
        }

        // Check for unsupported types
        if ($schema->isReference() && ! $schema->ref) {
            $errors[] = 'Invalid reference object';
        }

        // Check array schemas have items
        if ($schema->isArray() && ! $schema->items) {
            $errors[] = 'Array schema missing items definition';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Get schema complexity score
     */
    public function getComplexityScore(SchemaObject $schema): int
    {
        $score = 0;

        // Base score for type
        $score += 1;

        // Add for each property
        $score += count($schema->properties);

        // Add for nesting
        $score += $schema->getMaxDepth() * 2;

        // Add for validation constraints
        if ($schema->hasValidation()) {
            $score += 2;
        }

        // Add for required fields
        $score += count($schema->required);

        // Add for array items
        if ($schema->items) {
            $score += $this->getComplexityScore($schema->items);
        }

        // Add for nested properties
        foreach ($schema->properties as $property) {
            $score += $this->getComplexityScore($property);
        }

        return $score;
    }

    /**
     * Extract content types from request body
     *
     * @param  array<string, mixed>  $requestBody
     * @return array<string>
     */
    public function extractContentTypes(array $requestBody): array
    {
        if (! isset($requestBody['content'])) {
            return [];
        }

        $keys = array_keys($requestBody['content']);

        return array_values(array_filter($keys, 'is_string'));
    }

    /**
     * Check if request body is required
     *
     * @param  array<string, mixed>  $requestBody
     */
    public function isRequestBodyRequired(array $requestBody): bool
    {
        return $requestBody['required'] ?? false;
    }

    /**
     * Generate operation ID from path and method
     */
    private function generateOperationId(string $path, string $method): string
    {
        $cleanPath = preg_replace('/\{[^}]+\}/', 'Id', $path) ?? $path;
        $parts = explode('/', trim($cleanPath, '/'));
        $parts = array_filter($parts);

        $operationId = strtolower($method);
        foreach ($parts as $part) {
            $operationId .= ucfirst(strtolower($part));
        }

        return $operationId;
    }
}
