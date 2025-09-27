<?php

namespace Maan511\OpenapiToLaravel\Models;

use InvalidArgumentException;

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
        public readonly int|float|null $exclusiveMinimum = null,
        public readonly int|float|null $exclusiveMaximum = null,
        public readonly ?string $pattern = null,
        /** @var array<mixed>|null */
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
     *
     * @param  array<string, mixed>  $schema
     */
    public static function fromSchema(array $schema): self
    {
        // Parse exclusiveMinimum/exclusiveMaximum with backward compatibility
        $exclusiveMinimum = self::parseExclusiveNumeric($schema, 'exclusiveMinimum', 'minimum');
        $exclusiveMaximum = self::parseExclusiveNumeric($schema, 'exclusiveMaximum', 'maximum');

        return new self(
            minLength: self::validateInt($schema['minLength'] ?? null),
            maxLength: self::validateInt($schema['maxLength'] ?? null),
            minimum: self::validateNumeric($schema['minimum'] ?? null),
            maximum: self::validateNumeric($schema['maximum'] ?? null),
            exclusiveMinimum: $exclusiveMinimum,
            exclusiveMaximum: $exclusiveMaximum,
            pattern: self::validateString($schema['pattern'] ?? null),
            enum: isset($schema['enum']) && is_array($schema['enum']) ? $schema['enum'] : null,
            multipleOf: self::validateNumeric($schema['multipleOf'] ?? null),
            minItems: self::validateInt($schema['minItems'] ?? null),
            maxItems: self::validateInt($schema['maxItems'] ?? null),
            uniqueItems: self::validateBool($schema['uniqueItems'] ?? null)
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
            || $this->exclusiveMinimum !== null
            || $this->exclusiveMaximum !== null
            || $this->pattern !== null
            || ($this->enum !== null && $this->enum !== [])
            || $this->multipleOf !== null
            || $this->minItems !== null
            || $this->maxItems !== null
            || $this->uniqueItems !== null;
    }

    /**
     * Get Laravel validation rules for string type
     *
     * @return array<string>
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

        if ($this->enum !== null && $this->enum !== []) {
            $enumValues = implode(',', $this->enum);
            $rules[] = "in:{$enumValues}";
        }

        return $rules;
    }

    /**
     * Get Laravel validation rules for numeric type
     *
     * @return array<string>
     */
    public function getNumericValidationRules(): array
    {
        $rules = [];

        // Handle exclusive bounds (take precedence over inclusive bounds)
        if ($this->exclusiveMinimum !== null) {
            $rules[] = "gt:{$this->exclusiveMinimum}";
        } elseif ($this->minimum !== null) {
            $rules[] = "min:{$this->minimum}";
        }

        if ($this->exclusiveMaximum !== null) {
            $rules[] = "lt:{$this->exclusiveMaximum}";
        } elseif ($this->maximum !== null) {
            $rules[] = "max:{$this->maximum}";
        }

        if ($this->multipleOf !== null) {
            // Laravel doesn't have built-in multipleOf rule
            // We can use a custom validation rule name that can be implemented by the user
            $rules[] = "multiple_of:{$this->multipleOf}";
        }

        if ($this->enum !== null && $this->enum !== []) {
            $enumValues = implode(',', $this->enum);
            $rules[] = "in:{$enumValues}";
        }

        return $rules;
    }

    /**
     * Get Laravel validation rules for array type
     *
     * @return array<string>
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
            $rules[] = 'distinct';
        }

        return $rules;
    }

    /**
     * Get all validation rules based on type
     *
     * @return array<string>
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
            || ($this->enum !== null && $this->enum !== []);
    }

    /**
     * Check if has numeric constraints
     */
    public function hasNumericConstraints(): bool
    {
        return $this->minimum !== null
            || $this->maximum !== null
            || $this->exclusiveMinimum !== null
            || $this->exclusiveMaximum !== null
            || $this->multipleOf !== null
            || ($this->enum !== null && $this->enum !== []);
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
        return $this->enum !== null && $this->enum !== [];
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
     *
     * @return array<string, mixed>
     */
    public function validatePattern(): array
    {
        if ($this->pattern === null || $this->pattern === '') {
            return ['valid' => true, 'errors' => []];
        }

        // Basic syntax checks before attempting preg_match
        $pattern = $this->pattern;
        $errors = [];

        // Check for basic bracket matching
        if (substr_count($pattern, '[') !== substr_count($pattern, ']')) {
            $errors[] = 'Unmatched square brackets in pattern';
        }

        if (substr_count($pattern, '(') !== substr_count($pattern, ')')) {
            $errors[] = 'Unmatched parentheses in pattern';
        }

        if (substr_count($pattern, '{') !== substr_count($pattern, '}')) {
            $errors[] = 'Unmatched curly braces in pattern';
        }

        // If basic checks failed, don't attempt preg_match
        if ($errors !== []) {
            return [
                'valid' => false,
                'errors' => array_map(fn ($error): string => "Invalid regex pattern: {$this->pattern}. Error: {$error}", $errors),
            ];
        }

        // Now safely test the pattern
        $testResult = @preg_match("/{$this->getEscapedPattern()}/", '');

        if ($testResult === false) {
            $lastError = preg_last_error_msg();

            return [
                'valid' => false,
                'errors' => ["Invalid regex pattern: {$this->pattern}. Error: {$lastError}"],
            ];
        }

        return ['valid' => true, 'errors' => []];
    }

    /**
     * Get constraint summary for debugging
     *
     * @return array<string>
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
        if ($this->exclusiveMinimum !== null) {
            $summary[] = "exclusiveMinimum: {$this->exclusiveMinimum}";
        }
        if ($this->exclusiveMaximum !== null) {
            $summary[] = "exclusiveMaximum: {$this->exclusiveMaximum}";
        }
        if ($this->pattern !== null) {
            $summary[] = "pattern: {$this->pattern}";
        }
        if ($this->enum !== null && $this->enum !== []) {
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
            $summary[] = 'uniqueItems: ' . ($this->uniqueItems ? 'true' : 'false');
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
            throw new InvalidArgumentException('minLength must be >= 0');
        }

        if ($this->maxLength !== null && $this->maxLength < 0) {
            throw new InvalidArgumentException('maxLength must be >= 0');
        }

        if ($this->minLength !== null && $this->maxLength !== null && $this->minLength > $this->maxLength) {
            throw new InvalidArgumentException('minLength cannot be greater than maxLength');
        }

        // Numeric constraints
        if ($this->minimum !== null && $this->maximum !== null && $this->minimum > $this->maximum) {
            throw new InvalidArgumentException('minimum cannot be greater than maximum');
        }

        if ($this->exclusiveMinimum !== null && $this->exclusiveMaximum !== null && $this->exclusiveMinimum >= $this->exclusiveMaximum) {
            throw new InvalidArgumentException('exclusiveMinimum must be less than exclusiveMaximum');
        }

        if ($this->minimum !== null && $this->exclusiveMaximum !== null && $this->minimum >= $this->exclusiveMaximum) {
            throw new InvalidArgumentException('minimum must be less than exclusiveMaximum');
        }

        if ($this->exclusiveMinimum !== null && $this->maximum !== null && $this->exclusiveMinimum >= $this->maximum) {
            throw new InvalidArgumentException('exclusiveMinimum must be less than maximum');
        }

        if ($this->multipleOf !== null && $this->multipleOf <= 0) {
            throw new InvalidArgumentException('multipleOf must be > 0');
        }

        // Array constraints
        if ($this->minItems !== null && $this->minItems < 0) {
            throw new InvalidArgumentException('minItems must be >= 0');
        }

        if ($this->maxItems !== null && $this->maxItems < 0) {
            throw new InvalidArgumentException('maxItems must be >= 0');
        }

        if ($this->minItems !== null && $this->maxItems !== null && $this->minItems > $this->maxItems) {
            throw new InvalidArgumentException('minItems cannot be greater than maxItems');
        }

        // Pattern validation - don't throw exception, just store for later validation
        // The validatePattern() method will return the result
    }

    /**
     * Convert to array representation
     *
     * @return array<string, mixed>
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
        if ($this->exclusiveMinimum !== null) {
            $array['exclusiveMinimum'] = $this->exclusiveMinimum;
        }
        if ($this->exclusiveMaximum !== null) {
            $array['exclusiveMaximum'] = $this->exclusiveMaximum;
        }
        if ($this->pattern !== null) {
            $array['pattern'] = $this->pattern;
        }
        if ($this->enum !== null && $this->enum !== []) {
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
        return new self;
    }

    /**
     * Create from array data
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            minLength: self::validateInt($data['minLength'] ?? null),
            maxLength: self::validateInt($data['maxLength'] ?? null),
            minimum: self::validateNumeric($data['minimum'] ?? null),
            maximum: self::validateNumeric($data['maximum'] ?? null),
            exclusiveMinimum: self::validateNumeric($data['exclusiveMinimum'] ?? null),
            exclusiveMaximum: self::validateNumeric($data['exclusiveMaximum'] ?? null),
            pattern: self::validateString($data['pattern'] ?? null),
            enum: isset($data['enum']) && is_array($data['enum']) ? $data['enum'] : null,
            multipleOf: self::validateNumeric($data['multipleOf'] ?? null),
            minItems: self::validateInt($data['minItems'] ?? null),
            maxItems: self::validateInt($data['maxItems'] ?? null),
            uniqueItems: self::validateBool($data['uniqueItems'] ?? null)
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
        return ! $this->hasConstraints();
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
            exclusiveMinimum: $other->exclusiveMinimum ?? $this->exclusiveMinimum,
            exclusiveMaximum: $other->exclusiveMaximum ?? $this->exclusiveMaximum,
            pattern: $other->pattern ?? $this->pattern,
            enum: ($other->enum !== null && $other->enum !== []) ? $other->enum : $this->enum,
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

        if ($this->minLength !== null) {
            $score++;
        }
        if ($this->maxLength !== null) {
            $score++;
        }
        if ($this->minimum !== null) {
            $score++;
        }
        if ($this->maximum !== null) {
            $score++;
        }
        if ($this->exclusiveMinimum !== null) {
            $score++;
        }
        if ($this->exclusiveMaximum !== null) {
            $score++;
        }
        if ($this->pattern !== null) {
            $score += 2;
        } // Patterns are more complex
        if ($this->enum !== null && $this->enum !== []) {
            $score++;
        }
        if ($this->multipleOf !== null) {
            $score += 2;
        } // Custom rule needed
        if ($this->minItems !== null) {
            $score++;
        }
        if ($this->maxItems !== null) {
            $score++;
        }
        if ($this->uniqueItems !== null) {
            $score++;
        }

        return $score;
    }

    /**
     * Validate and cast to int or null
     */
    private static function validateInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    /**
     * Validate and cast to numeric or null
     */
    private static function validateNumeric(mixed $value): int|float|null
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            $floatValue = (float) $value;
            $intValue = (int) $value;

            return $floatValue == $intValue ? $intValue : $floatValue;
        }

        return null;
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
     * Validate and cast to bool or null
     */
    private static function validateBool(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        return null;
    }

    /**
     * Parse exclusive numeric bounds with backward compatibility for OpenAPI 3.0
     *
     * @param  array<string, mixed>  $schema
     */
    private static function parseExclusiveNumeric(array $schema, string $exclusiveKey, string $inclusiveKey): int|float|null
    {
        // OpenAPI 3.1: exclusiveMinimum/exclusiveMaximum as numeric values
        if (isset($schema[$exclusiveKey])) {
            $value = $schema[$exclusiveKey];
            if (is_numeric($value)) {
                return self::validateNumeric($value);
            }

            // OpenAPI 3.0: exclusiveMinimum/exclusiveMaximum as boolean values
            if (is_bool($value) && $value) {
                // Use the corresponding inclusive bound value
                return self::validateNumeric($schema[$inclusiveKey] ?? null);
            }
        }

        return null;
    }
}
