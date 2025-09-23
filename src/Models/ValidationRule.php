<?php

namespace Maan511\OpenapiToLaravel\Models;

use InvalidArgumentException;

/**
 * Individual Laravel validation rule derived from OpenAPI constraints
 */
class ValidationRule
{
    public readonly string $property;

    public readonly string $type;

    public array $rules;

    public readonly bool $isRequired;

    public readonly ?ValidationConstraints $constraints;

    public function __construct(
        string $property,
        string $type,
        array $rules = [],
        bool $isRequired = false,
        ?ValidationConstraints $constraints = null
    ) {
        $this->property = $property;
        $this->type = $type;
        $this->rules = $rules;
        $this->isRequired = $isRequired; // Only use explicit parameter, not derived from rules
        $this->constraints = $constraints;

        $this->validateProperty();
        $this->validateType();
    }

    /**
     * Magic getter to provide fieldPath as alias for property and rule for first rule
     */
    public function __get(string $name)
    {
        if ($name === 'fieldPath') {
            return $this->property;
        }

        if ($name === 'rule') {
            // Return the first rule since each ValidationRule now represents a single rule
            return ! empty($this->rules) ? $this->rules[0] : null;
        }

        throw new InvalidArgumentException("Property '{$name}' does not exist on ValidationRule");
    }

    /**
     * Check if property exists (including fieldPath alias)
     */
    public function __isset(string $name): bool
    {
        return in_array($name, ['fieldPath', 'rule']) || property_exists($this, $name);
    }

    /**
     * Create a required rule
     */
    public static function required(string $property, string $type): self
    {
        return new self(
            property: $property,
            type: $type,
            rules: ['required', $type],
            isRequired: true
        );
    }

    /**
     * Create an optional rule (nullable)
     */
    public static function optional(string $property, string $type): self
    {
        return new self(
            property: $property,
            type: $type,
            rules: [$type, 'nullable'],
            isRequired: false
        );
    }

    /**
     * Create an array rule
     */
    public static function array(string $property): self
    {
        return new self(
            property: $property,
            type: 'array',
            rules: ['array']
        );
    }

    /**
     * Create a string rule
     */
    public static function string(string $property): self
    {
        return new self(
            property: $property,
            type: 'string',
            rules: ['string']
        );
    }

    /**
     * Create an integer rule
     */
    public static function integer(string $property): self
    {
        return new self(
            property: $property,
            type: 'integer',
            rules: ['integer']
        );
    }

    /**
     * Create a number rule
     */
    public static function number(string $property): self
    {
        return new self(
            property: $property,
            type: 'number',
            rules: ['numeric']
        );
    }

    /**
     * Create a boolean rule
     */
    public static function boolean(string $property): self
    {
        return new self(
            property: $property,
            type: 'boolean',
            rules: ['boolean']
        );
    }

    /**
     * Check if this rule applies to nested fields
     */
    public function isNested(): bool
    {
        return str_contains($this->property, '.') && ! str_contains($this->property, '*');
    }

    /**
     * Check if rule has constraints
     */
    public function hasConstraints(): bool
    {
        return $this->constraints !== null && ! $this->constraints->isEmpty();
    }

    /**
     * Add a rule to existing rules array
     */
    public function addRule(string $rule): void
    {
        if (! in_array($rule, $this->rules)) {
            $this->rules[] = $rule;
        }
    }

    /**
     * Remove a rule from rules array
     */
    public function removeRule(string $ruleToRemove): void
    {
        $this->rules = array_values(array_filter($this->rules, fn ($rule) => $rule !== $ruleToRemove));
    }

    /**
     * Check if rule contains a specific rule
     */
    public function hasRule(string $rule): bool
    {
        return in_array($rule, $this->rules);
    }

    /**
     * Convert to Laravel validation array format
     */
    public function toValidationArray(): array
    {
        return [$this->property => $this->rules];
    }

    /**
     * Get property path (same as property)
     */
    public function getPropertyPath(): string
    {
        return $this->property;
    }

    /**
     * Get base property (first part of path)
     */
    public function getBaseProperty(): string
    {
        $firstDot = strpos($this->property, '.');
        if ($firstDot === false) {
            // Handle array notation like 'items.*'
            $firstStar = strpos($this->property, '*');
            if ($firstStar !== false) {
                return substr($this->property, 0, $firstStar - 1); // Remove '.*'
            }

            return $this->property;
        }

        return substr($this->property, 0, $firstDot);
    }

    /**
     * Check if this is an array type rule
     */
    public function isArray(): bool
    {
        return $this->type === 'array' || str_contains($this->property, '*');
    }

    /**
     * Check if this is an array element rule
     */
    public function isArrayElement(): bool
    {
        return str_contains($this->property, '*');
    }

    /**
     * Create a deep copy of this rule
     */
    public function clone(): self
    {
        return new self(
            property: $this->property,
            type: $this->type,
            rules: [...$this->rules], // Copy the array
            isRequired: $this->isRequired,
            constraints: $this->constraints ? clone $this->constraints : null
        );
    }

    /**
     * Convert to array representation
     */
    public function toArray(): array
    {
        return [
            'property' => $this->property,
            'type' => $this->type,
            'rules' => $this->rules,
            'isRequired' => $this->isRequired,
            'constraints' => $this->constraints?->toArray(),
            'isNested' => $this->isNested(),
            'isArray' => $this->isArray(),
            'isArrayElement' => $this->isArrayElement(),
        ];
    }

    /**
     * Validate property name
     */
    private function validateProperty(): void
    {
        if (empty($this->property)) {
            throw new InvalidArgumentException('Property cannot be empty');
        }

        if (! preg_match('/^[a-zA-Z0-9._*]+$/', $this->property)) {
            throw new InvalidArgumentException(
                "Invalid property name: {$this->property}. Must contain only letters, numbers, dots, underscores, and asterisks."
            );
        }
    }

    /**
     * Validate type
     */
    private function validateType(): void
    {
        if (empty($this->type)) {
            throw new InvalidArgumentException('Type cannot be empty');
        }

        $validTypes = ['string', 'integer', 'number', 'boolean', 'array', 'object'];
        if (! in_array($this->type, $validTypes)) {
            throw new InvalidArgumentException(
                "Invalid type: {$this->type}. Must be one of: " . implode(', ', $validTypes)
            );
        }
    }
}
