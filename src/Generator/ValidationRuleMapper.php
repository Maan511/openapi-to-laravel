<?php

namespace Maan511\OpenapiToLaravel\Generator;

use Maan511\OpenapiToLaravel\Models\SchemaObject;
use Maan511\OpenapiToLaravel\Models\ValidationRule;

/**
 * Maps OpenAPI constraints to Laravel validation rules
 */
class ValidationRuleMapper
{
    /**
     * Map schema to Laravel validation rules
     *
     * @return array<string, string>
     */
    public function mapSchema(SchemaObject $schema, string $fieldPrefix = ''): array
    {
        $rules = [];

        if ($schema->isObject()) {
            $rules = array_merge($rules, $this->mapObjectSchema($schema, $fieldPrefix));
        } elseif ($schema->isArray()) {
            $rules = array_merge($rules, $this->mapArraySchema($schema, $fieldPrefix));
        } else {
            $rules = array_merge($rules, $this->mapScalarSchema($schema, $fieldPrefix));
        }

        return $rules;
    }

    /**
     * Map object schema to validation rules
     *
     * @return array<string, string>
     */
    public function mapObjectSchema(SchemaObject $schema, string $fieldPrefix = ''): array
    {
        $rules = [];

        // Add object validation rule
        if ($fieldPrefix) {
            $rules[$fieldPrefix] = $this->buildRule($schema, $fieldPrefix, false);
        }

        // Map each property
        foreach ($schema->properties as $propertyName => $propertySchema) {
            $fieldPath = $fieldPrefix ? "{$fieldPrefix}.{$propertyName}" : $propertyName;
            $isRequired = $schema->isPropertyRequired($propertyName);

            $nestedRules = $this->mapSchema($propertySchema, $fieldPath);
            $rules = array_merge($rules, $nestedRules);

            // Update the rule to include required/nullable
            if (isset($rules[$fieldPath])) {
                $ruleString = $rules[$fieldPath];
                $existingRules = explode('|', $ruleString);
                $prefix = $isRequired ? 'required' : 'nullable';

                // Remove existing required/nullable rules
                $existingRules = array_values(array_filter($existingRules, fn (string $rule): bool => ! in_array($rule, ['required', 'nullable'], true)));

                // Add required/nullable at the beginning
                array_unshift($existingRules, $prefix);

                $rules[$fieldPath] = implode('|', $existingRules);
            } else {
                // Create a basic rule if none exists
                $typeRule = $propertySchema->getTypeValidationRule();
                $prefix = $isRequired ? 'required' : 'nullable';
                $rules[$fieldPath] = "{$prefix}|{$typeRule}";
            }
        }

        return $rules;
    }

    /**
     * Map array schema to validation rules
     *
     * @return array<string, string>
     */
    public function mapArraySchema(SchemaObject $schema, string $fieldPrefix = ''): array
    {
        $rules = [];

        // Add array validation rule
        $rules[$fieldPrefix] = $this->buildRule($schema, $fieldPrefix, false);

        // Map array items if defined
        if ($schema->items) {
            $itemsFieldPath = "{$fieldPrefix}.*";
            $itemRules = $this->mapSchema($schema->items, $itemsFieldPath);
            $rules = array_merge($rules, $itemRules);
        }

        return $rules;
    }

    /**
     * Map scalar schema to validation rules
     *
     * @return array<string, string>
     */
    public function mapScalarSchema(SchemaObject $schema, string $fieldPrefix = ''): array
    {
        $rules = [];

        if ($fieldPrefix) {
            $rules[$fieldPrefix] = $this->buildRule($schema, $fieldPrefix, false);
        }

        return $rules;
    }

    /**
     * Build complete validation rule string for a schema
     */
    public function buildRule(SchemaObject $schema, string $fieldPath, bool $includeRequired = true): string
    {
        $ruleParts = [];

        // Type rule
        $ruleParts[] = $schema->getTypeValidationRule();

        // Format rule
        $formatRule = $schema->getFormatValidationRule();
        if ($formatRule) {
            $ruleParts[] = $formatRule;
        }

        // Validation constraints
        if ($schema->validation !== null) {
            $constraintRules = $schema->validation->getValidationRules($schema->type);
            $ruleParts = array_merge($ruleParts, $constraintRules);
        }

        // Remove duplicates and filter out empty rules
        $ruleParts = array_filter(array_unique($ruleParts));

        return implode('|', $ruleParts);
    }

    /**
     * Map validation rules with proper field requirements
     *
     * @return array<string, string>
     */
    public function mapValidationRules(SchemaObject $schema, string $fieldPrefix = ''): array
    {
        $validationRules = [];

        if ($schema->isObject()) {
            foreach ($schema->properties as $propertyName => $propertySchema) {
                $fieldPath = $fieldPrefix ? "{$fieldPrefix}.{$propertyName}" : $propertyName;
                $isRequired = $schema->isPropertyRequired($propertyName);

                $rules = $this->mapValidationRules($propertySchema, $fieldPath);
                $validationRules = array_merge($validationRules, $rules);

                // Build the main rule for this field
                $ruleParts = [];

                // Required/nullable prefix
                $ruleParts[] = $isRequired ? 'required' : 'nullable';

                // Type and constraint rules
                $fieldRule = $this->buildRule($propertySchema, $fieldPath, false);
                if ($fieldRule) {
                    $ruleParts[] = $fieldRule;
                }

                $validationRules[$fieldPath] = implode('|', $ruleParts);
            }
        } elseif ($schema->isArray()) {
            if ($fieldPrefix) {
                // Array validation
                $ruleParts = ['array'];

                if ($schema->validation !== null) {
                    $arrayRules = $schema->validation->getArrayValidationRules();
                    $ruleParts = array_merge($ruleParts, $arrayRules);
                }

                $validationRules[$fieldPrefix] = implode('|', $ruleParts);

                // Array items validation
                if ($schema->items) {
                    $itemsFieldPath = "{$fieldPrefix}.*";

                    if ($schema->items->isObject()) {
                        // Add a rule for the array items themselves
                        $validationRules[$itemsFieldPath] = 'array';

                        // For object items, we need to recurse through properties
                        foreach ($schema->items->properties as $propertyName => $propertySchema) {
                            $propertyFieldPath = "{$itemsFieldPath}.{$propertyName}";
                            $isRequired = $schema->items->isPropertyRequired($propertyName);

                            $rules = $this->mapValidationRules($propertySchema, $propertyFieldPath);
                            $validationRules = array_merge($validationRules, $rules);

                            // Build the main rule for this property
                            $ruleParts = [];
                            $ruleParts[] = $isRequired ? 'required' : 'nullable';

                            $fieldRule = $this->buildRule($propertySchema, $propertyFieldPath, false);
                            if ($fieldRule) {
                                $ruleParts[] = $fieldRule;
                            }

                            $validationRules[$propertyFieldPath] = implode('|', $ruleParts);
                        }
                    } else {
                        // For scalar items
                        $itemRules = $this->mapValidationRules($schema->items, $itemsFieldPath);
                        $validationRules = array_merge($validationRules, $itemRules);
                    }
                }
            }
        } else {
            // Scalar field
            if ($fieldPrefix) {
                $validationRules[$fieldPrefix] = $this->buildRule($schema, $fieldPrefix, false);
            }
        }

        return $validationRules;
    }

    /**
     * Create ValidationRule objects from schema
     *
     * @return array<ValidationRule>
     */
    public function createValidationRules(SchemaObject $schema, string $fieldPrefix = ''): array
    {
        $rules = [];

        if ($schema->isObject()) {
            foreach ($schema->properties as $propertyName => $propertySchema) {
                $fieldPath = $fieldPrefix ? "{$fieldPrefix}.{$propertyName}" : $propertyName;
                $isRequired = $schema->isPropertyRequired($propertyName);

                // Collect all rules for this field
                $allRules = [];

                // Add required or nullable
                if ($isRequired) {
                    $allRules[] = 'required';
                } else {
                    $allRules[] = 'nullable';
                }

                // Add type rule
                $typeRule = $propertySchema->getTypeValidationRule();
                if ($typeRule) {
                    $allRules[] = $typeRule;
                }

                // Add format rule if present
                $formatRule = $propertySchema->getFormatValidationRule();
                if ($formatRule) {
                    $allRules[] = $formatRule;
                }

                // Add constraint rules
                if ($propertySchema->validation !== null) {
                    $constraintRules = $propertySchema->validation->getValidationRules($propertySchema->type);
                    $allRules = array_merge($allRules, $constraintRules);
                }

                // Filter out empty rules
                $allRules = array_filter($allRules);

                // Create individual ValidationRule objects for each rule
                foreach ($allRules as $singleRule) {
                    $rules[] = new ValidationRule(
                        $fieldPath,
                        $propertySchema->type,
                        [$singleRule], // Single rule array
                        $isRequired,
                        $propertySchema->hasValidation() ? $propertySchema->validation : null
                    );
                }

                // Recursively process nested objects and arrays
                if ($propertySchema->isObject() || $propertySchema->isArray()) {
                    $nestedRules = $this->createValidationRules($propertySchema, $fieldPath);
                    $rules = array_merge($rules, $nestedRules);
                }
            }
        } elseif ($schema->isArray() && $schema->items) {
            if ($fieldPrefix) {
                // Create rule for the array itself
                $arrayRules = ['array'];
                if ($schema->validation !== null) {
                    $arrayConstraints = $schema->validation->getArrayValidationRules();
                    $arrayRules = array_merge($arrayRules, $arrayConstraints);
                }

                // Create individual ValidationRule objects for each array rule
                foreach ($arrayRules as $singleRule) {
                    $rules[] = new ValidationRule(
                        $fieldPrefix,
                        'array',
                        [$singleRule],
                        false,
                        $schema->hasValidation() ? $schema->validation : null
                    );
                }

                // Create rules for array items
                $itemsFieldPath = "{$fieldPrefix}.*";
                $itemRules = $this->createValidationRules($schema->items, $itemsFieldPath);
                $rules = array_merge($rules, $itemRules);
            }
        } elseif ($fieldPrefix) {
            // Scalar field
            $scalarRules = [$schema->getTypeValidationRule()];

            // Add format rule if present
            $formatRule = $schema->getFormatValidationRule();
            if ($formatRule) {
                $scalarRules[] = $formatRule;
            }

            // Add constraint rules
            if ($schema->validation !== null) {
                $constraintRules = $schema->validation->getValidationRules($schema->type);
                $scalarRules = array_merge($scalarRules, $constraintRules);
            }

            // Create individual ValidationRule objects for each scalar rule
            $scalarRules = array_filter($scalarRules);
            foreach ($scalarRules as $singleRule) {
                $rules[] = new ValidationRule(
                    $fieldPrefix,
                    $schema->type,
                    [$singleRule],
                    false,
                    $schema->hasValidation() ? $schema->validation : null
                );
            }
        }

        return $rules;
    }

    /**
     * Sort validation rules by priority
     *
     * @param  array<string, string>  $rules
     * @return array<string, string>
     */
    public function sortValidationRules(array $rules): array
    {
        if (empty($rules)) {
            return $rules;
        }

        // Sort by keys (field names) for consistent ordering
        ksort($rules);

        return $rules;
    }

    /**
     * Combine multiple validation rules for the same field
     *
     * @param  array<string, string>  $rules
     * @return array<string, string>
     */
    public function combineRules(array $rules): array
    {
        $combined = [];

        foreach ($rules as $fieldPath => $ruleString) {
            if (isset($combined[$fieldPath])) {
                // Merge rules, avoiding duplicates
                $existingRules = explode('|', $combined[$fieldPath]);
                $newRules = explode('|', $ruleString);
                $allRules = array_unique(array_merge($existingRules, $newRules));
                $combined[$fieldPath] = implode('|', $allRules);
            } else {
                // Even for single rule strings, remove duplicates within the string
                $ruleParts = explode('|', $ruleString);
                $uniqueRules = array_unique($ruleParts);
                $combined[$fieldPath] = implode('|', $uniqueRules);
            }
        }

        return $combined;
    }

    /**
     * Validate that generated rules are valid Laravel validation syntax
     *
     * @param  array<string, mixed>  $rules
     * @return array<string>
     */
    public function validateLaravelRules(array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $ruleString) {
            if (! is_string($ruleString) || empty($ruleString)) {
                $errors[] = "Invalid rule for field '{$field}': must be non-empty string";

                continue;
            }

            $ruleParts = explode('|', $ruleString);
            foreach ($ruleParts as $rule) {
                if (empty($rule)) {
                    $errors[] = "Empty rule part in field '{$field}'";

                    continue;
                }

                // Basic validation of rule format
                if (str_contains($rule, ':')) {
                    [$ruleName, $parameters] = explode(':', $rule, 2);
                    if (empty($ruleName)) {
                        $errors[] = "Invalid rule format in field '{$field}': '{$rule}'";
                    }
                }
            }
        }

        return $errors;
    }
}
