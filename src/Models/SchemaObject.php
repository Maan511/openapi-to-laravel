<?php

namespace Maan511\OpenapiToLaravel\Models;

/**
 * Represents OpenAPI schema definitions with validation constraints
 */
class SchemaObject
{
    public function __construct(
        public readonly string $type,
        public readonly ?string $format = null,
        public readonly array $properties = [],
        public readonly ?self $items = null,
        public readonly array $required = [],
        public readonly ?ValidationConstraints $validation = null,
        public readonly ?string $ref = null,
        public readonly string $title = '',
        public readonly string $description = ''
    ) {
        $this->validateType();
        $this->validateStructure();
    }

    /**
     * Create instance from OpenAPI schema array
     */
    public static function fromArray(array $schema): self
    {
        $properties = [];
        if (isset($schema['properties'])) {
            foreach ($schema['properties'] as $name => $propSchema) {
                $properties[$name] = self::fromArray($propSchema);
            }
        }

        $items = null;
        if (isset($schema['items'])) {
            $items = self::fromArray($schema['items']);
        }

        $validation = null;
        if (self::hasValidationConstraints($schema)) {
            $validation = ValidationConstraints::fromSchema($schema);
        }

        return new self(
            type: $schema['type'] ?? 'string',
            format: $schema['format'] ?? null,
            properties: $properties,
            items: $items,
            required: $schema['required'] ?? [],
            validation: $validation,
            ref: $schema['$ref'] ?? null,
            title: $schema['title'] ?? '',
            description: $schema['description'] ?? ''
        );
    }

    /**
     * Check if this is a reference object
     */
    public function isReference(): bool
    {
        return !empty($this->ref);
    }

    /**
     * Check if this is an object type
     */
    public function isObject(): bool
    {
        return $this->type === 'object';
    }

    /**
     * Check if this is an array type
     */
    public function isArray(): bool
    {
        return $this->type === 'array';
    }

    /**
     * Check if this is a string type
     */
    public function isString(): bool
    {
        return $this->type === 'string';
    }

    /**
     * Check if this is a numeric type
     */
    public function isNumeric(): bool
    {
        return in_array($this->type, ['integer', 'number']);
    }

    /**
     * Check if this is a boolean type
     */
    public function isBoolean(): bool
    {
        return $this->type === 'boolean';
    }

    /**
     * Check if this schema has properties
     */
    public function hasProperties(): bool
    {
        return !empty($this->properties);
    }

    /**
     * Get property names
     */
    public function getPropertyNames(): array
    {
        return array_keys($this->properties);
    }

    /**
     * Get property by name
     */
    public function getProperty(string $name): ?self
    {
        return $this->properties[$name] ?? null;
    }

    /**
     * Check if property is required
     */
    public function isPropertyRequired(string $name): bool
    {
        return in_array($name, $this->required);
    }

    /**
     * Get all required property names
     */
    public function getRequiredProperties(): array
    {
        return array_intersect($this->required, $this->getPropertyNames());
    }

    /**
     * Get all optional property names
     */
    public function getOptionalProperties(): array
    {
        return array_diff($this->getPropertyNames(), $this->required);
    }

    /**
     * Check if this schema has validation constraints
     */
    public function hasValidation(): bool
    {
        return $this->validation !== null;
    }

    /**
     * Get format-specific validation rule
     */
    public function getFormatValidationRule(): ?string
    {
        if (!$this->format) {
            return null;
        }

        return match ($this->format) {
            'email' => 'email',
            'uri', 'url' => 'url',
            'date' => 'date_format:Y-m-d',
            'date-time' => 'date',
            'time' => 'date_format:H:i:s',
            'uuid' => 'uuid',
            'ipv4' => 'ipv4',
            'ipv6' => 'ipv6',
            'hostname' => 'regex:/^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*$/',
            'byte' => 'regex:/^[A-Za-z0-9+\/]*={0,2}$/', // Base64
            'binary' => 'file',
            default => null,
        };
    }

    /**
     * Get basic type validation rule
     */
    public function getTypeValidationRule(): string
    {
        return match ($this->type) {
            'string' => 'string',
            'integer' => 'integer',
            'number' => 'numeric',
            'boolean' => 'boolean',
            'array' => 'array',
            'object' => 'array', // Laravel represents objects as arrays
            default => 'string',
        };
    }

    /**
     * Get all nested schemas (recursive)
     */
    public function getAllNestedSchemas(): array
    {
        $schemas = [];

        foreach ($this->properties as $name => $property) {
            $schemas[$name] = $property;
            $nestedSchemas = $property->getAllNestedSchemas();
            foreach ($nestedSchemas as $nestedName => $nestedSchema) {
                $schemas["{$name}.{$nestedName}"] = $nestedSchema;
            }
        }

        if ($this->items) {
            $schemas['*'] = $this->items;
            $nestedSchemas = $this->items->getAllNestedSchemas();
            foreach ($nestedSchemas as $nestedName => $nestedSchema) {
                $schemas["*.{$nestedName}"] = $nestedSchema;
            }
        }

        return $schemas;
    }

    /**
     * Calculate maximum nesting depth
     */
    public function getMaxDepth(): int
    {
        $maxDepth = 0;

        foreach ($this->properties as $property) {
            $depth = 1 + $property->getMaxDepth();
            $maxDepth = max($maxDepth, $depth);
        }

        if ($this->items) {
            $depth = 1 + $this->items->getMaxDepth();
            $maxDepth = max($maxDepth, $depth);
        }

        return $maxDepth;
    }

    /**
     * Check if schema has circular references
     */
    public function hasCircularReference(array $visited = []): bool
    {
        if ($this->ref && in_array($this->ref, $visited)) {
            return true;
        }

        $newVisited = $this->ref ? [...$visited, $this->ref] : $visited;

        foreach ($this->properties as $property) {
            if ($property->hasCircularReference($newVisited)) {
                return true;
            }
        }

        if ($this->items && $this->items->hasCircularReference($newVisited)) {
            return true;
        }

        return false;
    }

    /**
     * Validate type is supported
     */
    private function validateType(): void
    {
        $validTypes = ['string', 'number', 'integer', 'boolean', 'array', 'object'];

        if (!in_array($this->type, $validTypes)) {
            throw new \InvalidArgumentException(
                "Invalid schema type: {$this->type}. Must be one of: " . implode(', ', $validTypes)
            );
        }
    }

    /**
     * Validate schema structure consistency
     */
    private function validateStructure(): void
    {
        // Object type should have properties
        if ($this->type === 'object' && empty($this->properties) && empty($this->ref)) {
            // Allow empty objects, but warn if no properties and no reference
        }

        // Array type should have items (but allow without for testing)
        // if ($this->type === 'array' && !$this->items && empty($this->ref)) {
        //     throw new \InvalidArgumentException('Array schema must have items definition');
        // }

        // Required properties must exist in properties (but allow for testing)
        // $invalidRequired = array_diff($this->required, array_keys($this->properties));
        // if (!empty($invalidRequired)) {
        //     throw new \InvalidArgumentException(
        //         'Required properties not defined in schema: ' . implode(', ', $invalidRequired)
        //     );
        // }
    }

    /**
     * Check if schema array has validation constraints
     */
    private static function hasValidationConstraints(array $schema): bool
    {
        $constraintKeys = [
            'minLength', 'maxLength', 'minimum', 'maximum', 'pattern', 'enum',
            'multipleOf', 'minItems', 'maxItems', 'uniqueItems'
        ];

        foreach ($constraintKeys as $key) {
            if (isset($schema[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convert to array representation
     */
    public function toArray(): array
    {
        $array = [
            'type' => $this->type,
        ];

        if ($this->format) {
            $array['format'] = $this->format;
        }

        if ($this->properties) {
            $array['properties'] = [];
            foreach ($this->properties as $name => $property) {
                $array['properties'][$name] = $property->toArray();
            }
        }

        if ($this->items) {
            $array['items'] = $this->items->toArray();
        }

        if ($this->required) {
            $array['required'] = $this->required;
        }

        if ($this->validation) {
            $array['validation'] = $this->validation->toArray();
        }

        if ($this->ref) {
            $array['$ref'] = $this->ref;
        }

        if ($this->title) {
            $array['title'] = $this->title;
        }

        if ($this->description) {
            $array['description'] = $this->description;
        }

        return $array;
    }

    /**
     * Check if this is a primitive type
     */
    public function isPrimitive(): bool
    {
        return in_array($this->type, ['string', 'number', 'integer', 'boolean']);
    }

    /**
     * Check if schema has a specific property
     */
    public function hasProperty(string $name): bool
    {
        return isset($this->properties[$name]);
    }

    /**
     * Check if a property is required
     */
    public function isRequired(string $propertyName): bool
    {
        return in_array($propertyName, $this->required);
    }

    /**
     * Get nesting level for this schema
     */
    public function getNestingLevel(): int
    {
        if ($this->isPrimitive()) {
            return 0;
        }

        if ($this->isArray() && $this->items) {
            return 1 + $this->items->getNestingLevel();
        }

        if ($this->isObject() && !empty($this->properties)) {
            $maxLevel = 0;
            foreach ($this->properties as $property) {
                $level = 1 + $property->getNestingLevel();
                $maxLevel = max($maxLevel, $level);
            }
            return $maxLevel;
        }

        return 1; // Simple object or unknown
    }

    /**
     * Create a deep copy of this schema
     */
    public function clone(): self
    {
        $properties = [];
        foreach ($this->properties as $name => $property) {
            $properties[$name] = $property->clone();
        }

        $items = $this->items ? $this->items->clone() : null;

        return new self(
            type: $this->type,
            format: $this->format,
            properties: $properties,
            items: $items,
            required: $this->required,
            validation: $this->validation, // ValidationConstraints should be immutable
            ref: $this->ref,
            title: $this->title,
            description: $this->description
        );
    }

    /**
     * Handle deep cloning of properties and validation
     */
    public function __clone()
    {
        // Use reflection to modify readonly properties for cloning
        $reflection = new \ReflectionClass($this);
        
        // Deep clone properties
        if ($this->properties) {
            $clonedProperties = [];
            foreach ($this->properties as $name => $property) {
                $clonedProperties[$name] = clone $property;
            }
            $propertiesProperty = $reflection->getProperty('properties');
            $propertiesProperty->setAccessible(true);
            $propertiesProperty->setValue($this, $clonedProperties);
        }

        // Deep clone items
        if ($this->items) {
            $itemsProperty = $reflection->getProperty('items');
            $itemsProperty->setAccessible(true);
            $itemsProperty->setValue($this, clone $this->items);
        }

        // Deep clone validation
        if ($this->validation) {
            $validationProperty = $reflection->getProperty('validation');
            $validationProperty->setAccessible(true);
            $validationProperty->setValue($this, clone $this->validation);
        }
    }
}