<?php

namespace Maan511\OpenapiToLaravel\Models;

/**
 * OpenAPI validation rules that map to Laravel validation
 */
class ValidationConstraints
{
    public function __construct(
        public readonly ?int $minLength = null,
        public readonly ?int $maxLength = null,
        public readonly int|float|null $minimum = null,
        public readonly int|float|null $maximum = null,
        public readonly ?string $pattern = null,
        public readonly ?array $enum = null,
        public readonly int|float|null $multipleOf = null,
        public readonly ?int $minItems = null,
        public readonly ?int $maxItems = null,
        public readonly ?bool $uniqueItems = null
    ) {
        $this->validateConstraints();
    }

    /**
     * Create instance from OpenAPI schema array
     */
    public static function fromSchema(array $schema): self
    {
        return new self(
            minLength: $schema['minLength'] ?? null,
            maxLength: $schema['maxLength'] ?? null,
            minimum: $schema['minimum'] ?? null,
            maximum: $schema['maximum'] ?? null,
            pattern: $schema['pattern'] ?? null,
            enum: isset($schema['enum']) ? $schema['enum'] : null,
            multipleOf: $schema['multipleOf'] ?? null,
            minItems: $schema['minItems'] ?? null,
            maxItems: $schema['maxItems'] ?? null,
            uniqueItems: $schema['uniqueItems'] ?? null
        );
    }

    /**
     * Check if any constraints are defined
     */
    public function hasConstraints(): bool
    {
        return $this->minLength !== null
            || $this->maxLength !== null
            || $this->minimum !== null
            || $this->maximum !== null
            || $this->pattern !== null
            || ($this->enum !== null && !empty($this->enum))
            || $this->multipleOf !== null
            || $this->minItems !== null
            || $this->maxItems !== null
            || $this->uniqueItems !== null;
    }

    /**
     * Get Laravel validation rules for string type
     */
    public function getStringValidationRules(): array
    {
        $rules = [];

        if ($this->minLength !== null) {
            $rules[] = "min:{$this->minLength}";
        }

        if ($this->maxLength !== null) {
            $rules[] = "max:{$this->maxLength}";
        }

        if ($this->pattern !== null) {
            $rules[] = "regex:/{$this->getEscapedPattern()}/";
        }

        if ($this->enum !== null && !empty($this->enum)) {
            $enumValues = implode(',', $this->enum);
            $rules[] = "in:{$enumValues}";
        }

        return $rules;
    }

    /**
     * Get Laravel validation rules for numeric type
     */
    public function getNumericValidationRules(): array
    {
        $rules = [];

        if ($this->minimum !== null) {
            $rules[] = "min:{$this->minimum}";
        }

        if ($this->maximum !== null) {
            $rules[] = "max:{$this->maximum}";
        }

        if ($this->multipleOf !== null) {
            // Laravel doesn't have built-in multipleOf rule
            // We can use a custom validation rule name that can be implemented by the user
            $rules[] = "multiple_of:{$this->multipleOf}";
        }

        if ($this->enum !== null && !empty($this->enum)) {
            $enumValues = implode(',', $this->enum);
            $rules[] = "in:{$enumValues}";
        }

        return $rules;
    }

    /**
     * Get Laravel validation rules for array type
     */
    public function getArrayValidationRules(): array
    {
        $rules = [];

        if ($this->minItems !== null) {
            $rules[] = "min:{$this->minItems}";
        }

        if ($this->maxItems !== null) {
            $rules[] = "max:{$this->maxItems}";
        }

        if ($this->uniqueItems === true) {
            $rules[] = "distinct";
        }

        return $rules;
    }

    /**
     * Get all validation rules based on type
     */
    public function getValidationRules(string $type): array
    {
        return match ($type) {
            'string' => $this->getStringValidationRules(),
            'integer', 'number' => $this->getNumericValidationRules(),
            'array' => $this->getArrayValidationRules(),
            default => [],
        };
    }

    /**
     * Check if has string constraints
     */
    public function hasStringConstraints(): bool
    {
        return $this->minLength !== null
            || $this->maxLength !== null
            || $this->pattern !== null
            || ($this->enum !== null && !empty($this->enum));
    }

    /**
     * Check if has numeric constraints
     */
    public function hasNumericConstraints(): bool
    {
        return $this->minimum !== null
            || $this->maximum !== null
            || $this->multipleOf !== null
            || ($this->enum !== null && !empty($this->enum));
    }

    /**
     * Check if has array constraints
     */
    public function hasArrayConstraints(): bool
    {
        return $this->minItems !== null
            || $this->maxItems !== null
            || $this->uniqueItems !== null;
    }

    /**
     * Check if has enum constraint
     */
    public function hasEnum(): bool
    {
        return $this->enum !== null && !empty($this->enum);
    }

    /**
     * Check if has pattern constraint
     */
    public function hasPattern(): bool
    {
        return $this->pattern !== null && $this->pattern !== '';
    }

    /**
     * Get enum values as string
     */
    public function getEnumValuesString(): string
    {
        if ($this->enum === null) {
            return '';
        }
        return implode(',', $this->enum);
    }

    /**
     * Get pattern with proper escaping for Laravel regex rule
     */
    public function getEscapedPattern(): string
    {
        if ($this->pattern === null) {
            return '';
        }

        // Escape forward slashes for regex delimiter
        return str_replace('/', '\/', $this->pattern);
    }

    /**
     * Validate pattern is a valid regex
     */
    public function validatePattern(): array
    {
        if ($this->pattern === null || $this->pattern === '') {
            return ['valid' => true, 'errors' => []];
        }

        // Clear any previous errors
        $previousErrorReporting = error_reporting(0);
        
        try {
            $result = preg_match("/{$this->getEscapedPattern()}/", '');
            
            if ($result === false) {
                $lastError = preg_last_error_msg();
                return [
                    'valid' => false,
                    'errors' => ["Invalid regex pattern: {$this->pattern}. Error: {$lastError}"]
                ];
            }
            
            return ['valid' => true, 'errors' => []];
        } finally {
            error_reporting($previousErrorReporting);
        }
    }

    /**
     * Get constraint summary for debugging
     */
    public function getSummary(): array
    {
        $summary = [];

        if ($this->minLength !== null) {
            $summary[] = "minLength: {$this->minLength}";
        }
        if ($this->maxLength !== null) {
            $summary[] = "maxLength: {$this->maxLength}";
        }
        if ($this->minimum !== null) {
            $summary[] = "minimum: {$this->minimum}";
        }
        if ($this->maximum !== null) {
            $summary[] = "maximum: {$this->maximum}";
        }
        if ($this->pattern !== null) {
            $summary[] = "pattern: {$this->pattern}";
        }
        if ($this->enum !== null && !empty($this->enum)) {
            $enumStr = implode(', ', $this->enum);
            $summary[] = "enum: [{$enumStr}]";
        }
        if ($this->multipleOf !== null) {
            $summary[] = "multipleOf: {$this->multipleOf}";
        }
        if ($this->minItems !== null) {
            $summary[] = "minItems: {$this->minItems}";
        }
        if ($this->maxItems !== null) {
            $summary[] = "maxItems: {$this->maxItems}";
        }
        if ($this->uniqueItems !== null) {
            $summary[] = "uniqueItems: " . ($this->uniqueItems ? 'true' : 'false');
        }

        return $summary;
    }

    /**
     * Validate constraint values are logical
     */
    private function validateConstraints(): void
    {
        // Length constraints
        if ($this->minLength !== null && $this->minLength < 0) {
            throw new \InvalidArgumentException('minLength must be >= 0');
        }

        if ($this->maxLength !== null && $this->maxLength < 0) {
            throw new \InvalidArgumentException('maxLength must be >= 0');
        }

        if ($this->minLength !== null && $this->maxLength !== null && $this->minLength > $this->maxLength) {
            throw new \InvalidArgumentException('minLength cannot be greater than maxLength');
        }

        // Numeric constraints
        if ($this->minimum !== null && $this->maximum !== null && $this->minimum > $this->maximum) {
            throw new \InvalidArgumentException('minimum cannot be greater than maximum');
        }

        if ($this->multipleOf !== null && $this->multipleOf <= 0) {
            throw new \InvalidArgumentException('multipleOf must be > 0');
        }

        // Array constraints
        if ($this->minItems !== null && $this->minItems < 0) {
            throw new \InvalidArgumentException('minItems must be >= 0');
        }

        if ($this->maxItems !== null && $this->maxItems < 0) {
            throw new \InvalidArgumentException('maxItems must be >= 0');
        }

        if ($this->minItems !== null && $this->maxItems !== null && $this->minItems > $this->maxItems) {
            throw new \InvalidArgumentException('minItems cannot be greater than maxItems');
        }

        // Pattern validation - don't throw exception, just store for later validation
        // The validatePattern() method will return the result
    }

    /**
     * Convert to array representation
     */
    public function toArray(): array
    {
        $array = [];

        if ($this->minLength !== null) {
            $array['minLength'] = $this->minLength;
        }
        if ($this->maxLength !== null) {
            $array['maxLength'] = $this->maxLength;
        }
        if ($this->minimum !== null) {
            $array['minimum'] = $this->minimum;
        }
        if ($this->maximum !== null) {
            $array['maximum'] = $this->maximum;
        }
        if ($this->pattern !== null) {
            $array['pattern'] = $this->pattern;
        }
        if ($this->enum !== null && !empty($this->enum)) {
            $array['enum'] = $this->enum;
        }
        if ($this->multipleOf !== null) {
            $array['multipleOf'] = $this->multipleOf;
        }
        if ($this->minItems !== null) {
            $array['minItems'] = $this->minItems;
        }
        if ($this->maxItems !== null) {
            $array['maxItems'] = $this->maxItems;
        }
        if ($this->uniqueItems !== null) {
            $array['uniqueItems'] = $this->uniqueItems;
        }

        return $array;
    }

    /**
     * Create empty constraints
     */
    public static function empty(): self
    {
        return new self();
    }

    /**
     * Create from array data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            minLength: $data['minLength'] ?? null,
            maxLength: $data['maxLength'] ?? null,
            minimum: $data['minimum'] ?? null,
            maximum: $data['maximum'] ?? null,
            pattern: $data['pattern'] ?? null,
            enum: isset($data['enum']) ? $data['enum'] : null,
            multipleOf: $data['multipleOf'] ?? null,
            minItems: $data['minItems'] ?? null,
            maxItems: $data['maxItems'] ?? null,
            uniqueItems: $data['uniqueItems'] ?? null
        );
    }

    /**
     * Check if has enum constraint
     */
    public function hasEnumConstraint(): bool
    {
        return $this->hasEnum();
    }

    /**
     * Check if has pattern constraint
     */
    public function hasPatternConstraint(): bool
    {
        return $this->hasPattern();
    }

    /**
     * Check if constraints are empty
     */
    public function isEmpty(): bool
    {
        return !$this->hasConstraints();
    }

    /**
     * Merge with another constraints object
     */
    public function merge(ValidationConstraints $other): self
    {
        return new self(
            minLength: $other->minLength ?? $this->minLength,
            maxLength: $other->maxLength ?? $this->maxLength,
            minimum: $other->minimum ?? $this->minimum,
            maximum: $other->maximum ?? $this->maximum,
            pattern: $other->pattern ?? $this->pattern,
            enum: ($other->enum !== null && !empty($other->enum)) ? $other->enum : $this->enum,
            multipleOf: $other->multipleOf ?? $this->multipleOf,
            minItems: $other->minItems ?? $this->minItems,
            maxItems: $other->maxItems ?? $this->maxItems,
            uniqueItems: $other->uniqueItems ?? $this->uniqueItems
        );
    }

    /**
     * Get complexity score for these constraints
     */
    public function getComplexityScore(): int
    {
        $score = 0;
        
        if ($this->minLength !== null) $score++;
        if ($this->maxLength !== null) $score++;
        if ($this->minimum !== null) $score++;
        if ($this->maximum !== null) $score++;
        if ($this->pattern !== null) $score += 2; // Patterns are more complex
        if ($this->enum !== null && !empty($this->enum)) $score++;
        if ($this->multipleOf !== null) $score += 2; // Custom rule needed
        if ($this->minItems !== null) $score++;
        if ($this->maxItems !== null) $score++;
        if ($this->uniqueItems !== null) $score++;

        return $score;
    }
}